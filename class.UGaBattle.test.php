<?php
/*
  _    _                                      _                   _                 
 | |  | |                                    | |                 | |                
 | |  | |   __ _    __ _   _ __ ___     ___  | |   __ _   _ __   | |   __ _   _   _ 
 | |  | |  / _` |  / _` | | '_ ` _ \   / _ \ | |  / _` | | '_ \  | |  / _` | | | | |
 | |__| | | (_| | | (_| | | | | | | | |  __/ | | | (_| | | |_) | | | | (_| | | |_| |
  \____/   \__, |  \__,_| |_| |_| |_|  \___| |_|  \__,_| | .__/  |_|  \__,_|  \__, |
            __/ |                                        | |                   __/ |
           |___/                                         |_|                  |___/ 


    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Lesser General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Lesser General Public License for more details.

    You should have received a copy of the GNU Lesser General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

 

 *
 * @author shoghicp@gmail.com
 *

*/

class UGaBattle{
	private $Attackers, $Defenders, $MaxRounds, $DefenseReg, $EmpMissileBlock, $Battles, $Formulas;
	function __construct($Attackers, $Defenders, $MaxRounds = 6, $DefenseReg = 70, $EmpMissileBlock = false, $Formulas = array('rapidfire' => 'return (( $RF - 1 ) / $RF) * 100;')){
		//ignore_user_abort(1);
		ini_set("max_execution_time", 10);
		if(intval(str_replace('M', '', ini_get("memory_limit"))) < 16 ){
			ini_set("memory_limit","16M");
		}
		$this->MaxRounds = intval($MaxRounds);
		if(intval($DefenseReg) > 100){
			$DefenseReg == 100;
		}
		$this->Formulas = $Formulas;
		$this->Battles = array();
		$this->DefenseReg = intval($DefenseReg);
		settype($EmpMissileBlock, "boolean");
		$this->EmpMissileBlock = $EmpMissileBlock;
		$this->Attackers = $this->GenerateCombatArray($Attackers, 'a', $this->EmpMissileBlock);
		$this->Defenders = $this->GenerateCombatArray($Defenders, 'd', $this->EmpMissileBlock);
		
	}
	
	function Battle(){
		$TotalMetal = 0;
		$TotalCrystal = 0;
		$TotalAttackersLost = 0;
		$TotalDefendersLost = 0;	
		for($Round = 1; $Round <= $this->MaxRounds; ++$Round){
			$this->Battles[$Round - 1] = array();
			$this->Battles[$Round - 1]['attackers'] = array();
			$this->Battles[$Round - 1]['defenders'] = array();
			$AttAny = false;
			$DefAny = false;
			foreach($this->Attackers as $Order => $Player){
				$this->Battles[$Round - 1]['attackers'][$Order] = array();
				$Count = $this->CleanShips($Player);
				if($Count['total'] == 0){
					$this->Battles[$Round - 1]['attackers'][$Order]['Ships'] = $Count['all'];
				}else{
					$this->Battles[$Round - 1]['attackers'][$Order]['Ships'] = $Count['all'];
					$AttAny = true;
				}		
			}
			foreach($this->Defenders as $Order => $Player){
				$this->Battles[$Round - 1]['defenders'][$Order] = array();
				$Count = $this->CleanShips($Player);
				if($Count['total'] == 0){
					$this->Battles[$Round - 1]['defenders'][$Order]['Ships'] = $Count['all'];
				}else{
					$this->Battles[$Round - 1]['defenders'][$Order]['Ships'] = $Count['all'];
					$DefAny = true;
				}		
			}
			$CurrentRound = $this->CombatRound();
			$this->Battles[$Round - 1]['Materials'] = $this->RoundClean();
			$this->Battles[$Round - 1]['Materials']['attack_a'] = $CurrentRound['attack_a'];
			$this->Battles[$Round - 1]['Materials']['shield_a'] = $CurrentRound['shield_a'];
			$this->Battles[$Round - 1]['Materials']['attack_b'] = $CurrentRound['attack_b'];
			$this->Battles[$Round - 1]['Materials']['shield_b'] = $CurrentRound['shield_b'];
			$this->Battles[$Round - 1]['Materials']['destroyed_a'] = $CurrentRound['destroyed_a'];
			$this->Battles[$Round - 1]['Materials']['destroyed_b'] = $CurrentRound['destroyed_b'];
			$TotalMetal += $this->Battles[$Round - 1]['Materials']['debris']['metal'];
			$TotalCrystal += $this->Battles[$Round - 1]['Materials']['debris']['crystal'];
			$TotalAttackersLost += $this->Battles[$Round - 1]['Materials']['lostunits'][0];
			$TotalDefendersLost += $this->Battles[$Round - 1]['Materials']['lostunits'][1];	
			if($AttAny == false or $DefAny == false){
				break;
			}			
		}
		$Result = array();
		foreach($this->Attackers as $Order => $Player){
			$Result['attackers'][$Order] = array();
			$Count = $this->CleanShips($Player);
			if($Count['total'] == 0){
				$Result['attackers'][$Order]['Ships'] = $Count['all'];
			}else{
				$Result['attackers'][$Order]['Ships'] = $Count['all'];
				$AttAny = true;
			}		
		}
		foreach($this->Defenders as $Order =>  $Player){
			$Result['defenders'][$Order] = array();
			$Count = $this->CleanShips($Player);
			if($Count['total'] == 0){
				$Result['defenders'][$Order]['Ships'] = $Count['all'];
			}else{
				$Result['defenders'][$Order]['Ships'] = $Count['all'];
				$DefAny = true;
			}		
		}
		$Won = $this->WhoWonBattle();
		$Repair = $this->RepairDefenses();
		return array('rounds' => $this->Battles, 'debris' => array('metal' => $TotalMetal, 'crystal' => $TotalCrystal), 'lostunits' => array('attackers' => $TotalAttackersLost, 'defenders' => $TotalDefendersLost), 'repair' => $Repair, 'attackers' => $this->Attackers, 'defenders'=> $this->Defenders, 'won' => $Won, 'last_round' => $Result);
	}
	
	private function RoundClean(){
		global $lang, $resource, $reslist, $pricelist, $CombatCaps, $game_config;
		$Debris = array('metal' => 0, 'crystal' => 0);
		$LostUnits = array(0,0);
		foreach($this->Attackers as $Array){
			foreach($Array as $Pass){
					$Pass['shield'] = $Pass['shield2'];
					if(($Pass['count'] <= $Pass['count2']) or ($Pass['integrity'] <= 0 and $Pass['destroyed'] == 0)){
						if(in_array($Pass['ship'], $reslist['fleet'])){
							if(UNI_TYPE == 4){
								$Debris['metal'] += $pricelist[$Pass['ship']]['metal'] * 0.5 * ($Pass['count2'] - $Pass['count']);
								$Debris['crystal'] += $pricelist[$Pass['ship']]['crystal'] * 0.5 * ($Pass['count2'] - $Pass['count']);							
							}else{
								$Debris['metal'] += $pricelist[$Pass['ship']]['metal'] * 0.3 * ($Pass['count2'] - $Pass['count']);
								$Debris['crystal'] += $pricelist[$Pass['ship']]['crystal'] * 0.3 * ($Pass['count2'] - $Pass['count']);
							}
						}
						$LostUnits[0] += ($pricelist[$Pass['ship']]['metal'] * ($Pass['count2'] - $Pass['count']) + $pricelist[$Pass['ship']]['crystal'] * ($Pass['count2'] - $Pass['count']) + $pricelist[$Pass['ship']]['deuterium'] * ($Pass['count2'] - $Pass['count']) + $pricelist[$Pass['ship']]['hidrogeno'] * ($Pass['count2'] - $Pass['count']));
						if($Pass['count'] > 0){
							$Pass['count2'] = $Pass['count'];
						}else{
							$Pass['destroyed'] = 1;
							$Pass['count'] = 0;
							$Pass['count2'] = 0;
						}
					}
					
							
			}	
		}
		foreach($this->Defenders as $Array){
			foreach($Array as $Pass){
					$Pass['shield'] = $Pass['shield2'];
					if(($Pass['count'] <= $Pass['count2']) or ($Pass['integrity'] <= 0 and $Pass['destroyed'] == 0)){
						if(in_array($Pass['ship'], $reslist['fleet']) or (defined('DEFENSE_TO_DEBRIS') and DEFENSE_TO_DEBRIS == 1)){
							if(UNI_TYPE == 4){
								$Debris['metal'] += $pricelist[$Pass['ship']]['metal'] * 0.5 * ($Pass['count2'] - $Pass['count']);
								$Debris['crystal'] += $pricelist[$Pass['ship']]['crystal'] * 0.5 * ($Pass['count2'] - $Pass['count']);							
							}else{
								$Debris['metal'] += $pricelist[$Pass['ship']]['metal'] * 0.3 * ($Pass['count2'] - $Pass['count']);
								$Debris['crystal'] += $pricelist[$Pass['ship']]['crystal'] * 0.3 * ($Pass['count2'] - $Pass['count']);
							}
						}
						$LostUnits[1] += ($pricelist[$Pass['ship']]['metal'] * ($Pass['count2'] - $Pass['count']) + $pricelist[$Pass['ship']]['crystal'] * ($Pass['count2'] - $Pass['count']) + $pricelist[$Pass['ship']]['deuterium'] * ($Pass['count2'] - $Pass['count']) + $pricelist[$Pass['ship']]['hidrogeno'] * ($Pass['count2'] - $Pass['count']));
						if($Pass['count'] > 0){
							$Pass['count2'] = $Pass['count'];
						}else{
							$Pass['destroyed'] = 1;
							$Pass['count'] = 0;
							$Pass['count2'] = 0;
						}
					}
							
			}	
		}
		return array('debris' => $Debris,'lostunits' => $LostUnits);
	}
	private function CleanShips($Player){
		global $lang, $resource, $reslist, $pricelist, $CombatCaps;
		$Ships = array();
		$TotalShips = 0;
		foreach($Player as $Ship){
			if(!isset($Ships[$Ship['ship']])){
				$Ships[$Ship['ship']] = array();
				$Ships[$Ship['ship']]['count'] = 0;
				$Ships[$Ship['ship']]['attack'] = 0;
				$Ships[$Ship['ship']]['integrity'] = 0;
				$Ships[$Ship['ship']]['shield'] = 0;	
			}
			if($Ship['integrity'] > 0 and $Ship['count'] > 0){
				$Ships[$Ship['ship']]['count'] = $Ship['count'];
				$Ships[$Ship['ship']]['attack'] = $Ship['attack2'] * $Ship['count'];
				$Ships[$Ship['ship']]['integrity'] = $Ship['integrity'];
				$Ships[$Ship['ship']]['shield'] = $Ship['shield2'] * $Ship['count'];	
				$TotalShips += $Ship['count'];
			}
		}
		return array('total' => $TotalShips, 'all' => $Ships);
	}
	
	private function GenerateCombatArray($Arrayd, $Current, $EMPmissileBlock = false){
		global $lang, $resource, $reslist, $pricelist, $CombatCaps;
		
		
		if($Current == 'a'){
			$this->Attackers = array();
			foreach($Arrayd as $Order => $Array){
				$this->Attackers[$Order] = array();
				foreach($Array[3] as $Ship => $Count){
						$Count2 = count($this->Attackers[$Order]);
						$this->Attackers[$Order][$Count2] = array(
						'ship' => $Ship,
						'attack' => ($CombatCaps[$Ship]['attack'] * (1 + (0.1 * ($Array[1]['military_tech']) + (0.05 * $Array[2]['rpg_amiral'])))) * $Count,
						'attack2' => ($CombatCaps[$Ship]['attack'] * (1 + (0.1 * ($Array[1]['military_tech']) + (0.05 * $Array[2]['rpg_amiral'])))),
						'shield' => ($CombatCaps[$Ship]['shield'] * (1 + (0.1 * ($Array[1]['defence_tech']) + (0.05 * $Array[2]['rpg_amiral'])))) * $Count,
						'shield2' => ($CombatCaps[$Ship]['shield'] * (1 + (0.1 * ($Array[1]['defence_tech']) + (0.05 * $Array[2]['rpg_amiral'])))),
						'integrity' => ((($pricelist[$Ship]['metal'] + $pricelist[$Ship]['crystal']) / 10) * (1 + (0.1 * ($Array[1]['shield_tech']) + (0.05 * $Array[2]['rpg_amiral'])))) * $Count,
						'integrity2' => ((($pricelist[$Ship]['metal'] + $pricelist[$Ship]['crystal']) / 10) * (1 + (0.1 * ($Array[1]['shield_tech']) + (0.05 * $Array[2]['rpg_amiral'])))),
						'destroyed' => 0,
						'count' => $Count,
						'count2' => $Count,
						);
				}
			}
			return $this->Attackers;
		}elseif($Current == 'd'){
			$this->Defenders = array();
			foreach($Arrayd as $Order => $Array){
				$this->Defenders[$Order] = array();
				foreach($Array[3] as $Ship => $Count){
						$Count2 = count($this->Defenders[$Order]);
						if(in_array($Ship, $reslist['fleet'])){
							$this->Defenders[$Order][$Count2] = array(
							'ship' => $Ship,
							'attack' => ($CombatCaps[$Ship]['attack'] * (1 + (0.1 * ($Array[1]['military_tech']) + (0.05 * $Array[2]['rpg_amiral'])))) * $Count,
							'attack2' => ($CombatCaps[$Ship]['attack'] * (1 + (0.1 * ($Array[1]['military_tech']) + (0.05 * $Array[2]['rpg_amiral'])))),
							'shield' => ($CombatCaps[$Ship]['shield'] * (1 + (0.1 * ($Array[1]['defence_tech']) + (0.05 * $Array[2]['rpg_amiral'])))) * $Count,
							'shield2' => ($CombatCaps[$Ship]['shield'] * (1 + (0.1 * ($Array[1]['defence_tech']) + (0.05 * $Array[2]['rpg_amiral'])))),
							'integrity' => ((($pricelist[$Ship]['metal'] + $pricelist[$Ship]['crystal']) / 10) * (1 + (0.1 * ($Array[1]['shield_tech']) + (0.05 * $Array[2]['rpg_amiral'])))) * $Count,
							'integrity2' => ((($pricelist[$Ship]['metal'] + $pricelist[$Ship]['crystal']) / 10) * (1 + (0.1 * ($Array[1]['shield_tech']) + (0.05 * $Array[2]['rpg_amiral'])))),
							'destroyed' => 0,
							'count' => $Count,
							'count2' => $Count,
							);
						}elseif(in_array($Ship, $reslist['defense']) and $EMPmissileBlock == false){
							$this->Defenders[$Order][$Count2] = array(
							'ship' => $Ship,
							'attack' => ($CombatCaps[$Ship]['attack'] * (1 + (0.1 * ($Array[1]['military_tech']) + (0.05 * $Array[2]['rpg_amiral'])))) * $Count,
							'attack2' => ($CombatCaps[$Ship]['attack'] * (1 + (0.1 * ($Array[1]['military_tech']) + (0.05 * $Array[2]['rpg_amiral'])))),
							'shield' => ($CombatCaps[$Ship]['shield'] * (1 + (0.1 * ($Array[1]['defence_tech']) + (0.05 * $Array[2]['rpg_amiral'])))) * $Count,
							'shield2' => ($CombatCaps[$Ship]['shield'] * (1 + (0.1 * ($Array[1]['defence_tech']) + (0.05 * $Array[2]['rpg_amiral'])))),
							'integrity' => ((($pricelist[$Ship]['metal'] + $pricelist[$Ship]['crystal']) / 10) * (1 + (0.1 * ($Array[1]['shield_tech']) + (0.05 * $Array[2]['rpg_amiral'])))) * $Count,
							'integrity2' => ((($pricelist[$Ship]['metal'] + $pricelist[$Ship]['crystal']) / 10) * (1 + (0.1 * ($Array[1]['shield_tech']) + (0.05 * $Array[2]['rpg_amiral'])))),
							'destroyed' => 0,
							'count' => $Count,
							'count2' => $Count,
							);
						}else{
							$this->Defenders[$Order][$Count2] = array(
							'ship' => $Ship,
							'attack' => 0,
							'attack2' => 0,
							'shield' => ($CombatCaps[$Ship]['shield'] * (1 + (0.1 * ($Array[1]['defence_tech']) + (0.05 * $Array[2]['rpg_amiral'])))) * $Count,
							'shield2' => ($CombatCaps[$Ship]['shield'] * (1 + (0.1 * ($Array[1]['defence_tech']) + (0.05 * $Array[2]['rpg_amiral'])))),
							'integrity' => ((($pricelist[$Ship]['metal'] + $pricelist[$Ship]['crystal']) / 10) * (1 + (0.1 * ($Array[1]['shield_tech']) + (0.05 * $Array[2]['rpg_amiral'])))) * $Count,
							'integrity2' => ((($pricelist[$Ship]['metal'] + $pricelist[$Ship]['crystal']) / 10) * (1 + (0.1 * ($Array[1]['shield_tech']) + (0.05 * $Array[2]['rpg_amiral'])))),
							'destroyed' => 0,
							'count' => $Count,
							'count2' => $Count,
							);	
						}
				}
			}
			return $this->Defenders;
		}
	}
	private function WhoWonBattle(){

			$Attackers = 0;
			$Defenders = 0;
			foreach($this->Attackers as $Order => $Player){
				$Count = $this->CleanShips($Player);
				if($Count['total'] == 0){
					
				}else{
					++$Attackers;
				}		
			}
			foreach($this->Defenders as $Order =>  $Player){
				$Count = $this->CleanShips($Player);
				if($Count['total'] == 0){
					
				}else{
					++$Defenders;
				}		
			}
		if($Attackers > 0 and $Defenders > 0){
			return 'drawn';
		}elseif($Attackers == 0){	
			return 'defender';
		}elseif($Defenders == 0){
			return 'attacker';
		}else{
			return 'drawn';
		}
	}
	private function RepairDefenses(){
		global $lang, $resource, $reslist, $pricelist, $CombatCaps, $game_config;
		$return = array();
		foreach($this->Defenders as $Array){
			foreach($Array as $Pass){
					if(in_array($Pass['ship'], $reslist['defense'])){
						$rand2 = mt_rand(-10, 10);
						
							$Ships = floor($Pass['count2'] * (($this->DefenseReg + $rand2) / 100 ));
							if($Ships > 0){
								$Pass['shield'] == 1;
								$Pass['integrity'] == 1;
								$Pass['destroyed'] == 0;
								if(!isset($return[$Pass['ship']])){
									$return[$Pass['ship']] = 0;
								}
								$return[$Pass['ship']] = $Ships;
							}
					}
							
			}	
		}	
		return $return;
	}
	private function CombatRound(){
		global $lang, $resource, $reslist, $pricelist, $CombatCaps, $game_config;
		$TotalAttack = 0;
		$TotalShield = 0;
		$TotalAttack2 = 0;
		$TotalShield2 = 0;
		$Destroyed = 0;
		$Destroyed2 = 0;
		foreach($this->Attackers as $Array){
			foreach($Array as $Pass){
					if($Pass['destroyed'] == 1){
						continue;
					}
					$now = true;
					$RestAttack = $Pass['attack'];
					while($now == true){
								$Rand1 = mt_rand(0, (count($this->Defenders) - 1));
								$Rand2 = mt_rand(0, (count($this->Defenders[$Rand1]) - 1));
								$AttackTo =& $this->Defenders[$Rand1][$Rand2];
								if($AttackTo['destroyed'] == 1 or $AttackTo['count'] <= 0){
									$Continue = false;
									foreach($this->Defenders as $Count => $Player){
										$Rest = $this->CleanShips($Player);
										if($Rest['total'] > 0){
											$Continue = true;
										}
									}
									if($Continue === true){
										continue;
									}else{
										break;
									}
								}
								$TheAttack = $RestAttack;
								$InitIntegrity = $AttackTo['integrity'];
								if($TheAttack >= $AttackTo['shield']){
									$TheAttack -= $AttackTo['shield'];
									$TotalShield2 += $AttackTo['shield'];
									$TotalAttack += $AttackTo['shield'];									
									if($TheAttack > $AttackTo['integrity']){
										$TheAttack -= $AttackTo['integrity'];
										$TotalAttack += $AttackTo['integrity'];
										$AttackTo['integrity'] = 0;
									}else{
										$TotalAttack += $TheAttack;
										$AttackTo['integrity'] -= $TheAttack;	
										$TheAttack = 0;
									}
									$AttackTo['shield'] = 0;
								}else{
									$TotalAttack += $TheAttack;
									$TotalShield2 += $TheAttack;
									$AttackTo['shield'] -= $TheAttack;	
									$TheAttack = 0;
								}
								$RestAttack = $TheAttack;
								$Destroyed = floor(abs($AttackTo['integrity2'] * $AttackTo['count'] - $AttackTo['integrity']) / $AttackTo['integrity2']);
								if($Destroyed > 0){
									$Destroyed1+= $Destroyed;
									$AttackTo['count'] -= $Destroyed;
									$AttackTo['attack'] = $AttackTo['attack2'] * $AttackTo['count'];	
								}
								$now = false;
								if($CombatCaps[$Pass['ship']]['sd'][$AttackTo['ship']] > 1 and $Count2 > 0){
									$RF = $CombatCaps[$Pass['ship']]['sd'][$AttackTo['ship']];
									$RfPercent = eval($this->Formulas['rapidfire']);
									$rand = mt_rand(0, 100);
									if($rand <= $RfPercent){
										$now = true;
									}
								}
								if($RestAttack > 0){
									$now = true;
								}
								/*$TheAttack = $RestAttack / $Pass['count'];
								$Shield = $AttackTo['shield'] / $AttackTo['count'];
								$Integrity = $AttackTo['integrity'] / $AttackTo['count'];
								
								if($TheAttack >= $Shield){
									$TheAttack -= $Shield;
									$TotalShield2 += $Shield;
									$TotalAttack += $Shield;									
									if($TheAttack > $Integrity){
										$TheAttack -= $Integrity;
										$TotalAttack += $Integrity;
										$Integrity = 0;
									}else{
										$TotalAttack += $TheAttack;
										$Integrity -= $TheAttack;	
										$TheAttack = 0;
									}
									$Shield = 0;
								}else{
									$TotalAttack += $TheAttack;
									$TotalShield2 += $TheAttack;
									$Shield -= $TheAttack;	
									$TheAttack = 0;
								}
								
								$ShieldRest = $TheAttack - $AttackTo['shield'];
								if($ShieldRest > 0){							
									$AttackTo['integrity'] -= abs($ShieldRest);									
									if($AttackTo['integrity'] < 0){
										$RestAttack -= abs($AttackTo['integrity'] - abs($AttackTo['shield']));
									}else{
										$RestAttack = 0;
									}
									$AttackTo['shield'] = 0;
								}else{
									$TotalShield2 += $AttackTo['shield'] - abs($ShieldRest);
									$TotalAttack += $TheAttack;
									$AttackTo['shield'] -= $TheAttack;
									$RestAttack = 0;

								}
								*/
								/*if($AttackIntegrityPercent < $Pass['attack']){
									$rand = mt_rand(0, 100);
									if($rand <= 30){
										//BOOOOMM!!!!
										$AttackTo['shield'] = 0;
									}
								}*/
								/*
								$Destroyed = floor((($AttackTo['shield2'] * $AttackTo['count']) - $AttackTo['shield']) / max(1, abs($ShieldRest)));
								if($AttackTo['shield'] <= 0 and $AttackTo['integrity'] > 0){
									$Destroyed1+= $Destroyed;
									$AttackTo['count'] -= $Destroyed;
									$AttackTo['shield'] = $AttackTo['shield2'] * $AttackTo['count'];
									$AttackTo['attack'] = $AttackTo['attack2'] * $AttackTo['count'];	
								}elseif($AttackTo['shield'] <= 0 and $AttackTo['integrity'] <= 0){
									$Destroyed1+= $Destroyed;
									$AttackTo['count'] 	= 0;
									$AttackTo['shield'] = 0;
									$AttackTo['attack'] = 0;
								}
								$now = false;
								if($CombatCaps[$Pass['ship']]['sd'][$AttackTo['ship']] > 1 and $Count2 > 0){
									$RF = $CombatCaps[$Pass['ship']]['sd'][$AttackTo['ship']];
									$RfPercent = eval($this->Formulas['rapidfire']);
									$rand = mt_rand(0, 100);
									if($rand <= $RfPercent){
										$now = true;
									}
								}
								if($RestAttack > 0){
									$now = true;
								}
								*/
					}
							
			}	
		}
		foreach($this->Defenders as $Array){
			foreach($Array as $Pass){
					if($Pass['destroyed'] == 1){
						continue;
					}
					$now = true;
					$RestAttack = $Pass['attack'];
					while($now == true){
								$Rand1 = mt_rand(0, (count($this->Attackers) - 1));
								$Rand2 = mt_rand(0, (count($this->Attackers[$Rand1]) - 1));
								$AttackTo =& $this->Attackers[$Rand1][$Rand2];
								if($AttackTo['destroyed'] == 1 or $AttackTo['count'] <= 0){
									$Continue = false;
									foreach($this->Attackers as $Count => $Player){
										$Rest = $this->CleanShips($Player);
										if($Rest['total'] > 0){
											$Continue = true;
										}
									}
									if($Continue === true){
										continue;
									}else{
										break;
									}
								}
								
								$TheAttack = $RestAttack;
								$InitIntegrity = $AttackTo['integrity'];
								if($TheAttack >= $AttackTo['shield']){
									$TheAttack -= $AttackTo['shield'];
									$TotalShield += $AttackTo['shield'];
									$TotalAttack2 += $AttackTo['shield'];									
									if($TheAttack > $AttackTo['integrity']){
										$TheAttack -= $AttackTo['integrity'];
										$TotalAttack2 += $AttackTo['integrity'];
										$AttackTo['integrity'] = 0;
									}else{
										$TotalAttack2 += $TheAttack;
										$AttackTo['integrity'] -= $TheAttack;	
										$TheAttack = 0;
									}
									$AttackTo['shield'] = 0;
								}else{
									$TotalAttack2 += $TheAttack;
									$TotalShield += $TheAttack;
									$AttackTo['shield'] -= $TheAttack;	
									$TheAttack = 0;
								}
								$RestAttack = $TheAttack;
								$Destroyed = floor(abs($AttackTo['integrity2'] * $AttackTo['count'] - $AttackTo['integrity']) / $AttackTo['integrity2']);
								if($Destroyed > 0){
									$Destroyed2 += $Destroyed;
									$AttackTo['count'] -= $Destroyed;
									$AttackTo['attack'] = $AttackTo['attack2'] * $AttackTo['count'];	
								}
								$now = false;
								if($CombatCaps[$Pass['ship']]['sd'][$AttackTo['ship']] > 1 and $Count2 > 0){
									$RF = $CombatCaps[$Pass['ship']]['sd'][$AttackTo['ship']];
									$RfPercent = eval($this->Formulas['rapidfire']);
									$rand = mt_rand(0, 100);
									if($rand <= $RfPercent){
										$now = true;
									}
								}
								if($RestAttack > 0){
									$now = true;
								}
					}
							
			}	
		}
		return array('attack_a' => $TotalAttack,'shield_a' => $TotalShield, 'attack_b' => $TotalAttack2, 'shield_b' => $TotalShield2, 'destroyed_a' => $Destroyed1, 'destroyed_b' => $Destroyed2);
	}

}

//--------------------PARA TESTEO-----------------------------
/*$Attackers = array(
0 => array(id, technos, officiers, ships) [PRINCIPAL],
1 => ... [OPCIONAL],
...
);

$Defenders = array(
0 => array(id, technos, officiers, ships) [PRINCIPAL],
1 => ... [OPCIONAL],
...
);
*/
function fd(){
global $game_config, $lang, $reslist, $resource, $CombatCaps;
$ARR = array();
foreach($reslist['fleet'] as $Element){
$ARR[$Element] = mt_rand(4000, 10000);
}
foreach($reslist['defense'] as $Element){
$ARR2[$Element] = mt_rand(4000, 1000);
}
$Attack = array(
array(23, array('military_tech' => 4, 'defence_tech' => 7, 'shield_tech' => 3), array('rpg_amiral' => 2), $ARR),
array(57, array('military_tech' => 6, 'defence_tech' => 4, 'shield_tech' => 5), array('rpg_amiral' => 3), array(
201 => 2,
202 => 665,
203 => 400,
217 => 13,
)));

$Defend = array(
array(23, array('military_tech' => 4, 'defence_tech' => 7, 'shield_tech' => 3), array('rpg_amiral' => 2), array(
201 => 2,
202 => 665,
203 => 400,
404 => 13,
)),
array(21, array('military_tech' => 4, 'defence_tech' => 7, 'shield_tech' => 3), array('rpg_amiral' => 2), array(
201 => 2,
202 => 665,
203 => 400,
404 => 13,
)),
array(24, array('military_tech' => 4, 'defence_tech' => 7, 'shield_tech' => 3), array('rpg_amiral' => 2), $ARR2),
array(57, array('military_tech' => 6, 'defence_tech' => 4, 'shield_tech' => 5), array('rpg_amiral' => 3), array(
201 => 2,
213 => 665,
215 => 400,
407 => 13,
)),
);


$time_start = microtime(true);

$BattleObject = new UGaBattle($Attack, $Defend, 6, 70, true);
$Battle = $BattleObject->Battle();
$time_end = microtime(true);
$totaltime = $time_end - $time_start;
			$FleetResult  = $Battle['won'];
			$dane_do_rw   = $Battle["rounds"];
			$FleetArray2   = array();
			$FleetAmount  = array();
			$FleetStorage = array();
			$FleetStorage2 = array();
			$StoragePerFleet = array('metal' => 0, 'crystal' => 0, 'deuterium' => 0, 'hidrogeno' => 0);
			foreach($Battle['attackers'] as $Order => $Array){
				$FleetStorage[$Order] = 0;
				$FleetStorage2[$Order] = 0;
				$FleetArray2[$Order] = array();
				$FleetAmount[$Order] = 0;
				foreach($Array as $Pass){
					if($Pass['ship'] == 201 or $Pass['ship'] == 220){
						$FleetStorage[$Order] += $pricelist[$Pass['ship']]["capacity"] * $Pass['count'];
						$FleetStorage2[$Order] += $pricelist[$Pass['ship']]["capacity"] * $Pass['count'];
					}else{
						$FleetStorage[$Order] += $pricelist[$Pass['ship']]["capacity"] * $Pass['count'];					
					}
					$FleetStorage[$Order] -= $AttackersArray[$Order]['fleet']["fleet_resource_metal"];
					$FleetStorage[$Order] -= $AttackersArray[$Order]['fleet']["fleet_resource_crystal"];
					$FleetStorage[$Order] -= $AttackersArray[$Order]['fleet']["fleet_resource_deuterium"];
					$FleetStorage2[$Order] -= $AttackersArray[$Order]['fleet']["fleet_resource_hidrogeno"];
					$FleetAmount[$Order] + $Pass['count'];
					$FleetArray2[$Order][$Pass['ship']] + $Pass['count'];
				}	
			}

			

					$TargetPlanetUpd = "";
						foreach($Battle['defenders'][0] as $Ship => $Details){
								$TargetPlanetUpd .= "`". $resource[$Details['ship']] ."` = '". $Details['count'] ."', ";
						}

			// Determination des ressources pillées
			$Mining['metal']   = 0;
			$Mining['crystal'] = 0;
			$Mining['deuter']  = 0;
			$Mining['hidrogeno']  = 0;
			$AttackersResources = array();
			if ($FleetResult == "a") {
				$TotalAtt = count($Battle['attackers']);
				foreach($Battle['attackers'] as $Order => $Array){
					$AttackersResources[$Order] = array('metal' => 0, 'crystal' => 0, 'deuterium' => 0, 'hidrogeno' => 0);
					if ($FleetStorage[$Order] > 0) {
						$resources_divider = 0.5;
						if($AttackersArray[$Order]['user']['link_center'] == 1){
							$resources_divider = 0.65;
						}
						$metal   = ($TargetPlanet['metal'] * $resources_divider) / $TotalAtt;
						$crystal = ($TargetPlanet['crystal'] * $resources_divider) / $TotalAtt ;
						$deuter  = ($TargetPlanet["deuterium"] * $resources_divider) / $TotalAtt ;
						$hidrogeno  = ($TargetPlanet["hidrogeno"] * $resources_divider) / $TotalAtt;
						if (($hidrogeno) > $FleetStorage2[$Order] / 4) {
							$Mining['hidrogeno']  += $FleetStorage2[$Order] / 4;
							$AttackersResources[$Order]['hidrogeno'] = $FleetStorage2[$Order] / 4;
							$FleetStorage2[$Order]      = $FleetStorage2[$Order] - $FleetStorage2[$Order] / 4;
						} else {
							$Mining['hidrogeno']  += $hidrogeno;
							$AttackersResources[$Order]['hidrogeno'] = $hidrogeno;
							$FleetStorage2[$Order]      = $FleetStorage2[$Order] - $hidrogeno;
						}						
						if (($metal) > $FleetStorage[$Order] / 3) {
							$Mining['metal']   += $FleetStorage[$Order] / 3;
							$AttackersResources[$Order]['metal'] = $FleetStorage[$Order] / 3;
							$FleetStorage[$Order]      = $FleetStorage[$Order] - $FleetStorage[$Order] / 3;
						} else {
							$Mining['metal']   += $metal;
							$AttackersResources[$Order]['metal'] = $metal;
							$FleetStorage[$Order]      = $FleetStorage[$Order] - $metal;
						}

						if (($crystal) > $FleetStorage[$Order] / 2) {
							$Mining['crystal'] += $FleetStorage[$Order] / 2;
							$AttackersResources[$Order]['crystal'] = $FleetStorage[$Order] / 2;
							$FleetStorage[$Order]      = $FleetStorage[$Order] - $FleetStorage[$Order] / 2;
						} else {
							$Mining['crystal'] += $crystal;
							$AttackersResources[$Order]['crystal'] = $crystal;
							$FleetStorage[$Order]      = $FleetStorage[$Order] - $crystal;
						}

						if (($deuter) > $FleetStorage[$Order]) {
							$Mining['deuter']  += $FleetStorage[$Order];
							$AttackersResources[$Order]['deuterium'] = $FleetStorage[$Order];
							$FleetStorage[$Order]      = $FleetStorage[$Order] - $FleetStorage[$Order];
						} else {
							$Mining['deuter']  += $deuter;
							$AttackersResources[$Order]['deuterium'] = $FleetStorage[$Order] / 4;
							$FleetStorage[$Order]      = $FleetStorage[$Order] - $deuter;
						}
					}
				}
			}
			$Mining['metal']   = pretty_number($Mining['metal']);
			$Mining['crystal'] = pretty_number($Mining['crystal']);
			$Mining['deuter']  = pretty_number($Mining['deuter']);
			$Mining['hidrogeno']  = pretty_number($Mining['hidrogeno']);

			// Mise a jour de l'enregistrement de la planete attaquée
			$QryUpdateTarget  = "UPDATE {{table}} SET ";
			$QryUpdateTarget .= $TargetPlanetUpd;
			$QryUpdateTarget .= "`metal` = `metal` - '". abs($Mining['metal']) ."', ";
			$QryUpdateTarget .= "`crystal` = `crystal` - '". abs($Mining['crystal']) ."', ";
			$QryUpdateTarget .= "`hidrogeno` = `hidrogeno` - '". abs($Mining['hidrogeno']) ."', ";
			$QryUpdateTarget .= "`deuterium` = `deuterium` - '". abs($Mining['deuter']) ."' ";
			$QryUpdateTarget .= "WHERE ";
			$QryUpdateTarget .= "`galaxy` = '". $FleetRow['fleet_end_galaxy'] ."' AND ";
			$QryUpdateTarget .= "`system` = '". $FleetRow['fleet_end_system'] ."' AND ";
			$QryUpdateTarget .= "`planet` = '". $FleetRow['fleet_end_planet'] ."' AND ";
			$QryUpdateTarget .= "`planet_type` = '". $FleetRow['fleet_end_type'] ."' ";
			$QryUpdateTarget .= "LIMIT 1;";
			//doquery( $QryUpdateTarget , 'planets');

			// Mise a jour du champ de ruine devant la planete attaquée
			$QryUpdateGalaxy  = "UPDATE {{table}} SET ";
			$QryUpdateGalaxy .= "`metal` = `metal` + '". abs($Battle['debris']['metal']) ."', ";
			$QryUpdateGalaxy .= "`crystal` = `crystal` + '". abs($Battle['debris']['crystal']) ."' ";
			$QryUpdateGalaxy .= "WHERE ";
			$QryUpdateGalaxy .= "`galaxy` = '". $FleetRow['fleet_end_galaxy'] ."' AND ";
			$QryUpdateGalaxy .= "`system` = '". $FleetRow['fleet_end_system'] ."' AND ";
			$QryUpdateGalaxy .= "`planet` = '". $FleetRow['fleet_end_planet'] ."' ";
			$QryUpdateGalaxy .= "LIMIT 1;";
			//doquery( $QryUpdateGalaxy , 'galaxy');

			// Là on va discuter le bout de gras pour voir s'il y a moyen d'avoir une Lune !

			$AttackDate        = date(DATE_FORMAT, $FleetRow["fleet_start_time"]);
			$title             = sprintf ($lang['sys_attack_title'], $AttackDate);
			$raport            = "<center><table><tr><td class='c'>". $title ."<br></td></tr><tr height='50'><td>&nbsp;</td></tr>";
			$DefendTechon['A'] = $TargetTechno["military_tech"] * 10;
			$DefendTechon['B'] = $TargetTechno["defence_tech"] * 10;
			$DefendTechon['C'] = $TargetTechno["shield_tech"] * 10;
			$DefenderData      = sprintf ($lang['sys_attack_defender_pos'], $TargetUser["username"], $FleetRow['fleet_end_galaxy'], $FleetRow['fleet_end_system'], $FleetRow['fleet_end_planet'] );
			$DefenderTech      = sprintf ($lang['sys_attack_techologies'], $DefendTechon['A'], $DefendTechon['B'], $DefendTechon['C']);
			// mod TOP KB
			$angreifer     = $CurrentUser["username"];
			$defender      = $TargetUser["username"];
			// mod TOP KB
			$roundas = 0;
			$TotalAttack = array('attackers' => 0, 'defenders' => 0);
			foreach ($dane_do_rw as $a => $b) {
				++$roundas;
				$raport .= "<tr><th><center><table border=1 width=80%><div style=\"border-top: 1px dashed rgb(102, 102, 102); border-right: 1px dashed rgb(102, 102, 102); width: 100%; height: 22px; text-align: right; font-size: 21px; color: rgb(153, 153, 153);\"><span style='border-left: 1px dashed rgb(102, 102, 102);border-bottom: 1px dashed rgb(102, 102, 102);'>Ronda $roundas</span></div>";
				foreach($b['attackers'] as $Attacker => $Array){
				$Ships = $Array['Ships'];
				$AttackTechon['A'] = $AttackersArray[$Attacker]['user']["military_tech"] * 10;
				$AttackTechon['B'] = $AttackersArray[$Attacker]['user']["defence_tech"] * 10;
				$AttackTechon['C'] = $AttackersArray[$Attacker]['user']["shield_tech"] * 10;
				$AttackerData      = sprintf ($lang['sys_attack_attacker_pos'], $AttackersArray[$Attacker]['user']["username"], $AttackersArray[$Attacker]['fleet']['fleet_start_galaxy'], $AttackersArray[$Attacker]['fleet']['fleet_start_system'], $AttackersArray[$Attacker]['fleet']['fleet_start_planet'] );
				$AttackerTech      = sprintf ($lang['sys_attack_techologies'], $AttackTechon['A'], $AttackTechon['B'], $AttackTechon['C']);

				$raport .= "<tr><td class='c'><center>".$AttackerData."<br />".$AttackerTech."</center></td></tr><tr><th><center><table border=1>";
					
					if (is_array($Ships)) {
						$raport1 = "<tr><th>".$lang['sys_ship_type']."</th>";
						$raport2 = "<tr><th>".$lang['sys_ship_count']."</th>";
						$raport3 = "<tr><th>".$lang['sys_ship_weapon']."</th>";
						$raport4 = "<tr><th>".$lang['sys_ship_shield']."</th>";
						$raport5 = "<tr><th>".$lang['sys_ship_armour']."</th>";
						foreach ($Ships as $Ship => $Data) {
							if (is_numeric($Ship)) {
								if ($Data['count'] > 0) {
									$raport1 .= "<th>". $lang["tech"][$Ship] ."</th>";
									$raport2 .= "<td class=k><span style=color:lime; >". pretty_number($Data['count']) ."</span></td>";
									$raport3 .= "<td class=k>". pretty_number(round($Data["attack"])) ."</td>";
									$raport4 .= "<td class=k>". pretty_number(round($Data["shield"])) ."</td>";
									$raport5 .= "<td class=k>". pretty_number(round($Data["integrity"])) ."</td>";
								}
							}
						}
						$raport1 .= "</tr>";
						$raport2 .= "</tr>";
						$raport3 .= "</tr>";
						$raport4 .= "</tr>";
						$raport5 .= "</tr>";
						$raport .= $raport1 . $raport2 . $raport3 . $raport4 . $raport5;
					} else {
						$raport .= "<br />". $lang['sys_destroyed'];
					}
					$raport .= "</table></center></th></tr>";
				}
				$raport .= "<tr><th style=background:yellow;color:black; ><center>VS</center></th></tr>";
				foreach($b['defenders'] as $Defender => $Array){
				$Ships = $Array['Ships'];
				$DefendTechon['A'] = $DefendersArray[$Defender]['user']["military_tech"] * 10;
				$DefendTechon['B'] = $DefendersArray[$Defender]['user']["defence_tech"] * 10;
				$DefendTechon['C'] = $DefendersArray[$Defender]['user']["shield_tech"] * 10;
				$DefenderData      = sprintf ($lang['sys_attack_defender_pos'], $DefendersArray[$Defender]['user']["username"], $DefendersArray[$Defender]['fleet']['fleet_end_galaxy'], $DefendersArray[$Defender]['fleet']['fleet_end_system'], $DefendersArray[$Defender]['fleet']['fleet_end_planet'] );
				$DefenderTech      = sprintf ($lang['sys_attack_techologies'], $DefendTechon['A'], $DefendTechon['B'], $DefendTechon['C']);
					
				$raport .= "<tr><td class='c'><center>".$DefenderData."<br />".$DefenderTech."</center></td></tr><tr><th><br /><center><table border=1>";

					if (is_array($Ships)) {
						$raport1 = "<tr><th>".$lang['sys_ship_type']."</th>";
						$raport2 = "<tr><th>".$lang['sys_ship_count']."</th>";
						$raport3 = "<tr><th>".$lang['sys_ship_weapon']."</th>";
						$raport4 = "<tr><th>".$lang['sys_ship_shield']."</th>";
						$raport5 = "<tr><th>".$lang['sys_ship_armour']."</th>";
						foreach ($Ships as $Ship => $Data) {
							if (is_numeric($Ship)) {
								if ($Data['count'] > 0) {
									$raport1 .= "<th>". $lang["tech"][$Ship] ."</th>";
									$raport2 .= "<td class=k><span style=color:lime; >". pretty_number($Data['count']) ."</span></td>";
									$raport3 .= "<td class=k>". pretty_number(round($Data["attack"])) ."</td>";
									$raport4 .= "<td class=k>". pretty_number(round($Data["shield"])) ."</td>";
									$raport5 .= "<td class=k>". pretty_number(round($Data["integrity"])) ."</td>";
								}
							}
						}
						$raport1 .= "</tr>";
						$raport2 .= "</tr>";
						$raport3 .= "</tr>";
						$raport4 .= "</tr>";
						$raport5 .= "</tr>";
						$raport .= $raport1 . $raport2 . $raport3 . $raport4 . $raport5;
					} else {
						$raport .= "<br />". $lang['sys_destroyed'];
					}
					$raport .= "</table></center>";
				}
				$raport .= "</th></tr></table>";
					$TotalAttack['defenders'] += floor($b["Materials"]["attack_a"] - floor($b["Materials"]["shield_b"]));
					$TotalAttack['attackers'] += floor($b["Materials"]["attack_b"] - floor($b["Materials"]["shield_a"]));
					$AttackWaveStat    = sprintf ($lang['sys_attack_attack_wave'], pretty_number(floor($b["Materials"]["attack_a"])), pretty_number(floor($b["Materials"]["shield_b"])));
					$DefendWavaStat    = sprintf ($lang['sys_attack_defend_wave'], pretty_number(floor($b["Materials"]["attack_b"])), pretty_number(floor($b["Materials"]["shield_a"])));
					$raport           .= "<br />".$AttackWaveStat."<br />".$DefendWavaStat."</center></th></tr><tr height='10'><td>&nbsp;</td></tr>";
			}
			$raport .= "<tr height='10'><td>&nbsp;</td></tr><tr><td class='c'>Resultado de la batalla:</td></tr><tr><th><center>";
				$raport .= "<table border=1 width=80%><div style=\"border-top: 1px dashed rgb(102, 102, 102); border-right: 1px dashed rgb(102, 102, 102); width: 100%; height: 22px; text-align: right; font-size: 21px; color: rgb(153, 153, 153);\"><span style='border-left: 1px dashed rgb(102, 102, 102);border-bottom: 1px dashed rgb(102, 102, 102);'>Resultado</span></div><center>";
				foreach($Battle['last_round']['attackers'] as $Attacker => $Array){
				$Ships = $Array['Ships'];
				$AttackTechon['A'] = $AttackersArray[$Attacker]['user']["military_tech"] * 10;
				$AttackTechon['B'] = $AttackersArray[$Attacker]['user']["defence_tech"] * 10;
				$AttackTechon['C'] = $AttackersArray[$Attacker]['user']["shield_tech"] * 10;
				$AttackerData      = sprintf ($lang['sys_attack_attacker_pos'], $AttackersArray[$Attacker]['user']["username"], $AttackersArray[$Attacker]['fleet']['fleet_start_galaxy'], $AttackersArray[$Attacker]['fleet']['fleet_start_system'], $AttackersArray[$Attacker]['fleet']['fleet_start_planet'] );
				$AttackerTech      = sprintf ($lang['sys_attack_techologies'], $AttackTechon['A'], $AttackTechon['B'], $AttackTechon['C']);

				$raport .= "<tr><td class='c'><center>".$AttackerData."<br />".$AttackerTech."</center></td></tr><tr><th><center><table border=1>";
					
					if (is_array($Ships)) {
						$raport1 = "<tr><th>".$lang['sys_ship_type']."</th>";
						$raport2 = "<tr><th>".$lang['sys_ship_count']."</th>";
						$raport3 = "<tr><th>".$lang['sys_ship_weapon']."</th>";
						$raport4 = "<tr><th>".$lang['sys_ship_shield']."</th>";
						$raport5 = "<tr><th>".$lang['sys_ship_armour']."</th>";
						foreach ($Ships as $Ship => $Data) {
							if (is_numeric($Ship)) {
								if ($Data['count'] > 0) {
									$raport1 .= "<th>". $lang["tech"][$Ship] ."</th>";
									$raport2 .= "<th>". pretty_number($Data['count']) ."</th>";
									$raport3 .= "<th>". pretty_number(round($Data["attack"])) ."</th>";
									$raport4 .= "<th>". pretty_number(round($Data["shield"])) ."</th>";
									$raport5 .= "<th>". pretty_number(round($Data["integrity"])) ."</th>";
								}
							}
						}
						$raport1 .= "</tr>";
						$raport2 .= "</tr>";
						$raport3 .= "</tr>";
						$raport4 .= "</tr>";
						$raport5 .= "</tr>";
						$raport .= $raport1 . $raport2 . $raport3 . $raport4 . $raport5;
					} else {
						$raport .= "<br />". $lang['sys_destroyed'];
					}
					$raport .= "</table></center></th></tr>";
				}
				$raport .= "<tr><th style=background:yellow;color:black; ><center>VS</center></th></tr>";
				foreach($Battle['last_round']['defenders'] as $Defender => $Array){
				$Ships = $Array['Ships'];
				$DefendTechon['A'] = $DefendersArray[$Defender]['user']["military_tech"] * 10;
				$DefendTechon['B'] = $DefendersArray[$Defender]['user']["defence_tech"] * 10;
				$DefendTechon['C'] = $DefendersArray[$Defender]['user']["shield_tech"] * 10;
				$DefenderData      = sprintf ($lang['sys_attack_defender_pos'], $DefendersArray[$Defender]['user']["username"], $DefendersArray[$Defender]['fleet']['fleet_end_galaxy'], $DefendersArray[$Defender]['fleet']['fleet_end_system'], $DefendersArray[$Defender]['fleet']['fleet_end_planet'] );
				$DefenderTech      = sprintf ($lang['sys_attack_techologies'], $DefendTechon['A'], $DefendTechon['B'], $DefendTechon['C']);
					
				$raport .= "<center><tr><td class='c'><center>".$DefenderData."<br />".$DefenderTech."</center></td></tr><tr><th><br /><center><table border=1>";

					if (is_array($Ships)) {
						$raport1 = "<tr><th>".$lang['sys_ship_type']."</th>";
						$raport2 = "<tr><th>".$lang['sys_ship_count']."</th>";
						$raport3 = "<tr><th>".$lang['sys_ship_weapon']."</th>";
						$raport4 = "<tr><th>".$lang['sys_ship_shield']."</th>";
						$raport5 = "<tr><th>".$lang['sys_ship_armour']."</th>";
						foreach ($Ships as $Ship => $Data) {
							if (is_numeric($Ship)) {
								if ($Data['count'] > 0) {
									$raport1 .= "<th>". $lang["tech"][$Ship] ."</th>";
									$raport2 .= "<th>". pretty_number($Data['count']) ."</th>";
									$raport3 .= "<th>". pretty_number(round($Data["attack"])) ."</th>";
									$raport4 .= "<th>". pretty_number(round($Data["shield"])) ."</th>";
									$raport5 .= "<th>". pretty_number(round($Data["integrity"])) ."</th>";
								}
							}
						}
						$raport1 .= "</tr>";
						$raport2 .= "</tr>";
						$raport3 .= "</tr>";
						$raport4 .= "</tr>";
						$raport5 .= "</tr>";
						$raport .= $raport1 . $raport2 . $raport3 . $raport4 . $raport5;
					} else {
						$raport .= "<br />". $lang['sys_destroyed'];
					}
					$raport .= "</table></center>";
				}
				$raport .= "</th></tr></center></table></center>";			
			$FleetDebris      = abs($Battle['debris']['metal']) + abs($Battle['debris']['crystal']);
			$StrAttackerUnits = sprintf ($lang['sys_attacker_lostunits'], pretty_number(abs($TotalAttack['attackers'])));
			$StrDefenderUnits = sprintf ($lang['sys_defender_lostunits'], pretty_number(abs($TotalAttack['defenders'])));
			$StrRuins         = sprintf ($lang['sys_gcdrunits'], pretty_number(abs($Battle['debris']['metal'])), $lang['Metal'], pretty_number(abs($Battle['debris']['crystal'])), $lang['Crystal']);
			// mod TOP KB
			$strunitsgesamt      = $Battle['lostunits']['attackers'] + $Battle['lostunits']['defenders'];
			$user1lostunits      = $Battle['lostunits']['attackers'];
			$user1shotunits      = $Battle['lostunits']['defenders'];
			$user2lostunits      = $Battle['lostunits']['defenders'];
			$user2shotunits      = $Battle['lostunits']['attackers'];
			$strtruemmerfeld     = $Battle['debris']['metal'] + $Battle['debris']['crystal'];
			$strtruemmermetal    = $Battle['debris']['metal'];
			$strtruemmercrystal  = $Battle['debris']['crystal'];
			// mod TOP KB
			
			//raiders
			$unidades  = floor($Mining['metal']+$Mining['crystal']+$Mining['deuter']+$Mining['hidrogeno']);
			$unidades =  $unidades/1500000;

			
			$DebrisField      = $StrAttackerUnits ."<br />". $StrDefenderUnits ."<br />". $StrRuins;
			$MoonChance       = $FleetDebris / 1000000;
			$lunita = 0;
			if ($FleetDebris > 25000000) {
				$MoonChance = 25;
			}
			if ($FleetDebris < 1000000) {
				$UserChance = 0;
				$ChanceMoon = "";
			} elseif ($FleetDebris >= 1000000) {
				$UserChance = rand(1, 100);
				$ChanceMoon = sprintf ($lang['sys_moonproba'], $MoonChance);
			}

			if (($UserChance > 0) and ($UserChance <= $MoonChance) and $TargetMoon == 0){
				//$TargetPlanetName = CreateOneMoonRecord ( $FleetRow['fleet_end_galaxy'], $FleetRow['fleet_end_system'], $FleetRow['fleet_end_planet'], $TargetUserID, time(), 'Luna', $MoonChance );
				$GottenMoon       = sprintf ($lang['sys_moonbuilt'], $TargetPlanetName, $FleetRow['fleet_end_galaxy'], $FleetRow['fleet_end_system'], $FleetRow['fleet_end_planet']);
				$lunita = 1;
			} elseif ($UserChance = 0 or $UserChance > $MoonChance) {
				$GottenMoon = "";
				$lunita = 0;
			}			
			
			switch ($FleetResult) {
				case "attacker":
					$Pillage           = sprintf ($lang['sys_stealed_ressources'], $Mining['metal'], $lang['metal'], $Mining['crystal'], $lang['crystal'], $Mining['deuter'], $lang['Deuterium'], $Mining['hidrogeno'], $lang['Hidrogeno']);
					$raport           .= $lang['sys_attacker_won'] ."<br />";
					$raport			  .= $Pillage ."<br />";
					$raport           .= $DebrisField ."<br />";
					$raport           .= $ChanceMoon ."<br />";
					$raport           .= $GottenMoon ."<br />";
					break;
				case "drawn":
					$raport           .= $lang['sys_both_won'] ."<br />";
					$raport           .= $DebrisField ."<br />";
					$raport           .= $ChanceMoon ."<br />";
					$raport           .= $GottenMoon ."<br />";
					break;
				case "defender":
					$raport           .= $lang['sys_defender_won'] ."<br />";
					$raport           .= $DebrisField ."<br />";
					$raport           .= $ChanceMoon ."<br />";
					$raport           .= $GottenMoon ."<br />";
					break;
				default:
					break;
			}
			$image = "<a href=\"rw_img.php?win=".$FleetResult."&per_def=".$Battle['lostunits']['defenders']."&per_atc=".$Battle['lostunits']['attackers']."&dbr_met=".$Battle['debris']['metal']."&dbr_cri=".$Battle['debris']['crystal']."&luna=".$MoonChance."&luna_si=".$lunita."\" title='Imagen de la batalla'><img src=\"rw_img.php?win=".$FleetResult."&per_def=".$zlom['wrog']."&per_atc=".$zlom['atakujacy']."&dbr_met=".$Battle['debris']['metal']."&dbr_cri=".$Battle['debris']['crystal']."&luna=".$MoonChance."&luna_si=".$lunita."\" width='200' height='100'></a>";
			$raport .= "Las siguientes defensas han sido reparadas: ";
			foreach($Battle['repair'] as $Ship => $Count){
				if($Count > 0){
					$raport .= $lang['tech'][$Ship] .": ".pretty_number($Count).",";
				}
			}
			$raport .= "<br/><br/>";
			$report = $raport.$image ."</center></th></tr></table>";
			$Resources = array('metal' => $Battle['debris']['metal'], 'crystal' => $Battle['debris']['crystal'], 'steal_metal' => $Mining['metal'], 'steal_crystal' => $Mining['crystal'], 'steal_deuterium' => $Mining['deuterium']);
			//$raport = ParseCombatReport($CurrentUser, $TargetUser, $StartFleet, $EndFleet, $Resources, $raport, $roundas, $FleetResult);
			$rid   = md5($report);
			
			// mod TOP KB     
			$user1stat = $FleetRow['fleet_owner'];
			$user2stat = $TargetUserID;
			// mod TOP KB

			// Colorisation du résumé de rapport pour l'attaquant
            $raport  = "<a href='#' OnClick=\"f( 'rw.php?unid=". UNID ."&raport=". $rid ."', '');\" >";
			$raport .= "<center>";
            if       ($FleetResult == "a") {
				$raport .= "<font color=\"green\">";
            } elseif ($FleetResult == "r") {
				$raport .= "<font color=\"orange\">";
            } elseif ($FleetResult == "w") {
				$raport .= "<font color=\"red\">";
			}
			$raport .= $lang['sys_mess_attack_report'] ." [". $FleetRow['fleet_end_galaxy'] .":". $FleetRow['fleet_end_system'] .":". $FleetRow['fleet_end_planet'] ."] </font></a><br /><br />";
			$raport .= "<font color=\"red\">". $lang['sys_perte_attaquant'] .": ". pretty_number($TotalAttack['attackers']) ."</font>";
			$raport .= "<font color=\"green\">   ". $lang['sys_perte_defenseur'] .":". pretty_number($TotalAttack['defenders']) ."</font><br />" ;
            $raport .= $lang['sys_gain'] ." ". $lang['Metal'] .":<font color=\"#adaead\">". $Mining['metal'] ."</font>   ". $lang['Crystal'] .":<font color=\"#ef51ef\">". $Mining['crystal'] ."</font>   ". $lang['Deuterium'] .":<font color=\"#f77542\">". $Mining['deuter'] ."</font>";
			$raport .= "   ". $lang['Hidrogeno'] .":<font color=\"skyblue\">". $Mining['hidrogeno'] ."</font>";
			
            $raport .= "<br/>".$lang['sys_debris'] ." ". $lang['Metal'] .":<br><font color=\"#adaead\">". $Battle['debris']['metal'] ."</font>   ". $lang['Crystal'] .":<font color=\"#ef51ef\">". $Battle['debris']['crystal'] ."</font><br></center>";

		$Page  = "<html>";
		$Page .= "<head>";
		$Page .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"". DEFAULT_SKINPATH ."/formate.css\">";
		$Page .= "<meta http-equiv=\"content-type\" content=\"text/html; charset=iso-8859-2\" />";
		$Page .= "</head>";
		$Page .= "<body>";
		$Page .= "<center>";
		$Page .= $totaltime.$report;
		$Page .= "</center>";
		$Page .= "</body>";
		$Page .= "</html>";	
		print_r($Battle);
		die($Page);

//--------------------[FIN] PARA TESTEO-----------------------------
}
?>