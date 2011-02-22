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
		ignore_user_abort(1);
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
								if($AttackTo['count'] > 0 and $AttackTo['integrity2'] > 0){
									$Destroyed = floor(abs($AttackTo['integrity2'] * $AttackTo['count'] - $AttackTo['integrity']) / $AttackTo['integrity2']);
									if($Destroyed > 0){
										$Destroyed1+= $Destroyed;
										$AttackTo['count'] -= $Destroyed;
										$AttackTo['attack'] = $AttackTo['attack2'] * $AttackTo['count'];	
									}
								}else{
									$Destroyed = 0;
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
								if($AttackTo['count'] > 0 and $AttackTo['integrity2'] > 0){
									$Destroyed = floor(abs($AttackTo['integrity2'] * $AttackTo['count'] - $AttackTo['integrity']) / $AttackTo['integrity2']);
									if($Destroyed > 0){
										$Destroyed2+= $Destroyed;
										$AttackTo['count'] -= $Destroyed;
										$AttackTo['attack'] = $AttackTo['attack2'] * $AttackTo['count'];	
									}
								}else{
									$Destroyed = 0;
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
?>