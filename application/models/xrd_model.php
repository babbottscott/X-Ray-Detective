<?php

class Xrd_model extends CI_Model {
	
	public function __construct()
	{
		parent::__construct();
	}

	function Add_Player_Mines($playerid)
	{
		// Check to make sure player exists
		if(!Check_Player_Exists($playerid))
		{
			echo "ERROR: The player you specified does not exist. (Player ID: '$playerid')<BR>";
		}
		else
		{
			//die("VARIABLES NOT IMPLEMENTED");
			$datetime_now = new DateTime;
			$datetime_hour_ago = new DateTime;
			$datetime_hour_ago->modify( '-1 hour' );
		
			foreach($GLOBALS['worlds'] as $world_index => $world_item)
			{
				
				$latest_mine_date = Get_Mine_Latest($playerid, $world_item['worldid']);
			
			
				// Get all breaks after date
				// ------------------------------
			
				$sql_getPlayerBreaks  = "SELECT `replaced` AS beforeblock, `x`, `y`, `z`, `date` FROM `lb-".strtolower($world_item['worldname'])."`";
				$sql_getPlayerBreaks .= " WHERE playerid = '$playerid' ";
				$sql_getPlayerBreaks .= "		AND ( replaced = 1 ";
				$sql_getPlayerBreaks .= "	    OR replaced = 15";
				$sql_getPlayerBreaks .= "	    OR replaced = 14";
				$sql_getPlayerBreaks .= "	    OR replaced = 56";
				$sql_getPlayerBreaks .= "	    OR replaced = 25";
				$sql_getPlayerBreaks .= "	    OR replaced = 48 ) ";
				$sql_getPlayerBreaks .= "	    AND type = 0 ";
				$sql_getPlayerBreaks .= "	    AND y <= 50 ";
				$sql_getPlayerBreaks .= "	    AND date > '$latest_mine_date' ";
				$sql_getPlayerBreaks .= " ORDER BY date DESC";
			
				//echo "SQL QUERY: <BR>" . $sql_getPlayerBreaks . "<BR>";
				$res_getPlayerBreaks = mysql_query($sql_getPlayerBreaks) or die("getPlayerBreaks: " . mysql_error());
				while(($FullBreaksArray[] = mysql_fetch_assoc($res_getPlayerBreaks)) || array_pop($FullBreaksArray));
			
				$FullBreaksArray = array_reverse($FullBreaksArray); //echo "FULL BREAKS ARRAY: "; print_r($FullBreaksArray); echo "<BR>";
				//array_push($FullBreaksArray,$init_prev_break);
			
				// Process all breaks into chunks
				// ------------------------------
				echo "<BR><BR>[========================[WORLD ".$world_item["worldalias"]."]========================]<BR>";
				echo "Analyzing mining behavior of player [".Get_Player_NameByID($playerid)."] in World [".$world_item["worldalias"]."]...<br>";
				echo "<BR><BR>[------------------------PROCESS------------------------]<BR>";
				// Initiate statistic arrays and variables
				$init_prev_break = array("replaced"=>"0", "beforeblock"=>"0", "x"=>"0","y"=>"64","z"=>"0");
				$init_current_mine = array("breaks"=>array(),"ores"=>array(),"stats"=>array("first_block_ore"=>false,"total_volume"=>0,"adjusted_volume"=>0,"total_ores"=>0,"total_notores"=>0,"postbreak_possible"=>0,"postbreak_total"=>0), "clusters"=>array() );
				$init_cluster = array("nearby_before"=>array(), "nearby_after"=>array(), "ore_begin"=>NULL, "ore_length"=>NULL);
			
				$Mine_Array = array();
	
				$current_mine = $init_current_mine; $prev_break=$init_prev_break;
				$current_cluster = $init_cluster; $prev_cluster=$init_cluster;
	
				$fullbreaks_item = array();	$fullbreaks_done = false; 
				$recent_depth = array(); $inside_cluster = false;
				$blocks_since_ore = 0;
		
				// Process each break, moving it from the original array into smaller chunks (mines)
				while( !$fullbreaks_done && $fullbreaks_item = array_pop($FullBreaksArray) )
				{
					if(count($current_mine["breaks"]) == 0){ echo "Parsing Mine [".count($Mine_Array)."]...<BR>"; }
					//echo "<".$fullbreaks_item["beforeblock"]."{";
				
					// Add current break to a list of recent breaks history,(Used for calculating slope near clusters)
					if(	$fullbreaks_item["beforeblock"]=="1" || $fullbreaks_item["beforeblock"]=="3" ) // Only keep track of non-ores
					{
						array_push($recent_depth, $fullbreaks_item["y"]);
						if(count($recent_depth)>10){array_shift($recent_depth);}
					}
				
					// First block is an ore
					if(count($current_mine["breaks"]) == 0     && ($fullbreaks_item["beforeblock"]=="56" || 
							 $fullbreaks_item["beforeblock"]=="48" ||  $fullbreaks_item["beforeblock"]=="25" ||
							 $fullbreaks_item["beforeblock"]=="15" ||  $fullbreaks_item["beforeblock"]=="14") )
					{
						//echo "First Block: [".$current_mine["breaks"][0]["beforeblock"]."]";
						$current_mine["stats"]["first_block_ore"]=true;
						$current_mine["stats"]["total_ores"]++;
						//echo "FIRST BLOCK ORE<BR>";
					
						echo "--New cluster detected [".count($current_mine["clusters"])."]";
						array_push($current_mine["ores"],$fullbreaks_item);
					
						$current_cluster["ore_begin"] = count($current_mine["breaks"]);
						$current_cluster["ore_length"] = 1;
						$current_cluster["ore_type"] = $fullbreaks_item["beforeblock"];
						$postbreak_checking = true; $postbreak_check_count = 0;
						$blocks_since_ore = 0;
						$inside_cluster = true;
					}
				
					// 
					if( $blocks_since_ore == 10 && $current_mine["stats"]["total_ores"]>0 )
					{
						//echo "Adding recent breaks to previous cluster history.<BR>";
						$prev_cluster['nearby_after'] = $recent_depth;
						
						//echo "CLUSTER INFO [".count($current_mine["clusters"])."]: "; print_r($prev_cluster); echo "<BR>";
					}
			
					// Current mine is not brand new, has 1 or more breaks
					if(count($current_mine["breaks"]) > 0)
					{	
						$adjacent = false; $distance = 0;
						// Check current blocks distance from all previous breaks in current mine
						foreach($current_mine["breaks"] as $block_index => $block_compare)
						{
							$distance = max( pow($fullbreaks_item["x"] - $block_compare["x"] , 2),
											 pow($fullbreaks_item["y"] - $block_compare["y"] , 2),
											 pow($fullbreaks_item["z"] - $block_compare["z"] , 2) );
							//echo "Dist: [ ". (float) $distance . " ] <br>";
							//echo " | [".$fullbreaks_item["x"]."]>[".pow($fullbreaks_item["x"] - $block_compare["x"] , 2)."]<[".$block_compare["x"]."] ,";
							//echo   " [".$fullbreaks_item["y"]."]>[".pow($fullbreaks_item["y"] - $block_compare["y"] , 2)."]<[".$block_compare["y"]."] ,";
							//echo   " [".$fullbreaks_item["z"]."]>[".pow($fullbreaks_item["z"] - $block_compare["z"] , 2)."]<[".$block_compare["z"]."]<br>";
		
		
							if($distance <= $GLOBALS['config']['settings']['mine_max_distance']) // New break is part of current mine
							{
								$adjacent = true;
								break;
							}
						}
					}
					else // Current mine has 0 breaks, empty set
					{
						//echo "FIRST BREAK IN SET...<BR>";
					
						$current_mine["stats"]["total_volume"]++;
						$current_mine["stats"]["adjusted_volume"]++;
									
					
						// Check current blocks distance from previous block to determine if they are far enough to form new mine
						$adjacent = false; $distance = 0;
						$distance = max( pow($fullbreaks_item["x"] - $prev_break["x"] , 2),
										 pow($fullbreaks_item["y"] - $prev_break["y"] , 2),
										 pow($fullbreaks_item["z"] - $prev_break["z"] , 2) );
						//echo "Dist: [ ". (float) $distance . " ] <br>";
						//echo " | [".$fullbreaks_item["x"]."]>[".sqrt(pow($fullbreaks_item["x"] - $prev_break["x"] , 2))."]<[".$prev_break["x"]."] ,";
						//echo   " [".$fullbreaks_item["y"]."]>[".sqrt(pow($fullbreaks_item["y"] - $prev_break["y"] , 2))."]<[".$prev_break["y"]."] ,";
						//echo   " [".$fullbreaks_item["z"]."]>[".sqrt(pow($fullbreaks_item["z"] - $prev_break["z"] , 2))."]<[".$prev_break["z"]."]<br>";
					
						$inside_cluster = false;
						$blocks_since_ore = 0;
	
						if($distance <= $GLOBALS['config']['settings']['mine_max_distance'])
						{
							$adjacent = true;
						}	
					}
				
					// New break is part of existing mine
					if(count($current_mine["breaks"]) > 0 && $adjacent)
					{
						$current_mine["stats"]["total_volume"]++;
						//if($current_mine["stats"]["first_block_ore"]){echo "@";}
					
						// Adjusted volume ignores initial blocks if first block was ore
						if(!$current_mine["stats"]["first_block_ore"] || count($current_mine["breaks"])-1>$GLOBALS['config']['settings']['ignorefirstore_before'])
							{$current_mine["stats"]["adjusted_volume"]++;}
					
						// New break is an ore
						if($fullbreaks_item["beforeblock"]=="56" || $fullbreaks_item["beforeblock"]=="48" || 
						   $fullbreaks_item["beforeblock"]=="15" || $fullbreaks_item["beforeblock"]=="14" || $fullbreaks_item["beforeblock"]=="25")
						{
							$current_mine["stats"]["total_ores"]++;
							if(!$current_mine["stats"]["first_block_ore"] || count($current_mine["breaks"])-1>$GLOBALS['config']['settings']['ignorefirstore_before'])
								{$current_mine["stats"]["adjusted_ores"]++;}
						
							array_push($current_mine["ores"],$fullbreaks_item);
							$postbreak_checking = true; $postbreak_check_count = 0;
						
							// New cluster
							if(!$inside_cluster)
							{
								echo "--New cluster detected [".count($current_mine["clusters"])."]";
								$current_cluster["ore_begin"] = count($current_mine["breaks"]);
								$current_cluster["ore_length"] = 1;
								$current_cluster["ore_type"] = $fullbreaks_item["beforeblock"];
							
								$current_cluster["nearby_before"]=$recent_depth;
							}
							else // Existing cluster
							{
								//echo "(Ore Length + ".($blocks_since_ore + 1).")";
								$current_cluster["ore_length"] += $blocks_since_ore + 1; // Add current ore to count, plus additional recent non-ores
								if($current_cluster["ore_length"] > 1)
								{
									$current_mine["stats"]["postbreak_possible"] -= $blocks_since_ore-1;
									$current_mine["stats"]["postbreak_total"] -= $blocks_since_ore-1;
									//echo "(PB: [".$current_mine["stats"]["postbreak_possible"]."][".$current_mine["stats"]["postbreak_total"]."][".$blocks_since_ore."])";
								}
							}
	
							$inside_cluster = true;
							$blocks_since_ore = 0;
						} 
						else // New break is not an ore
						{ 
							$current_mine["stats"]["total_notores"]++;
							if(!$current_mine["stats"]["first_block_ore"] || count($current_mine["breaks"])-1>$GLOBALS['config']['settings']['ignorefirstore_before'])
								{$current_mine["stats"]["adjusted_notores"]++;}
						
							// Check to see if user continued to mine after finding the last ore
							if($postbreak_checking)
							{
								$current_mine["stats"]["postbreak_possible"]++; $postbreak_check_count++;
								$ore_distance_ok = false;
								foreach(array_slice($current_mine["ores"],-2 ) as $block_index => $block_compare)
								{
									$distance = sqrt( max(	pow($fullbreaks_item["x"] - $block_compare["x"] , 2),
															pow($fullbreaks_item["y"] - $block_compare["y"] , 2),
															pow($fullbreaks_item["z"] - $block_compare["z"] , 2) ) );
									//echo "Dist[$block_index]: [ ". (float) $distance . " ] <br>";
									//echo " | [".$fullbreaks_item["x"]."]>[".sqrt(pow($fullbreaks_item["x"] - $block_compare["x"] , 2))."]<[".$block_compare["x"]."] ,";
									//echo   " [".$fullbreaks_item["y"]."]>[".sqrt(pow($fullbreaks_item["y"] - $block_compare["y"] , 2))."]<[".$block_compare["y"]."] ,";
									//echo   " [".$fullbreaks_item["z"]."]>[".sqrt(pow($fullbreaks_item["z"] - $block_compare["z"] , 2))."]<[".$block_compare["z"]."]<br>";
				
				
									if($distance <= $GLOBALS['config']['settings']['postbreak_check']) // New break is near recent ore
									{
										$current_mine["stats"]["postbreak_total"]++;
										//echo "Postbreak OK([".$current_mine["stats"]["postbreak_possible"]."][".$current_mine["stats"]["postbreak_total"]."][".$blocks_since_ore."]) @ $distance ";
										$ore_distance_ok = true;
										break;
									}
									//else{ echo "x"; }
								}
								if(!$ore_distance_ok)
								{
									//echo "Postbreak MISSED ([".$current_mine["stats"]["postbreak_possible"]."][".$current_mine["stats"]["postbreak_total"]."][".$blocks_since_ore."]) @ $distance ";
								}
							}
							if($postbreak_check_count >= $GLOBALS['config']['settings']['postbreak_check']){ $postbreak_checking = false; $postbreak_check_count = 0;}
						
							// Check for end of cluster
							if($inside_cluster)
							{
								if($blocks_since_ore >= 5) // Current cluster has ended (earlier)
								{
									$inside_cluster = false;
								
									//echo "Cluster core end detected... ";
									//echo "CLUSTER INFO [".count($current_mine["clusters"])."]: "; print_r($current_cluster); echo "<BR>";
	
									array_push($current_mine["clusters"], $current_cluster);
									$prev_cluster = &$current_mine["clusters"][count($current_mine["clusters"])-1];
									$current_cluster = $init_cluster;
								}
	
							}
							$blocks_since_ore++;
						}
	
					}
				
				
					$current_mine["stats"]["depth_total"] += $fullbreaks_item["y"];
				
			
					if($adjacent || count($current_mine["breaks"]) == 0) // New break is part of current mine
					{
						//echo "=";
						array_unshift($current_mine["breaks"], $fullbreaks_item);
					
					}
					else // New break is part of NEW mine
					{
						if($postbreak_checking && !$current_mine["stats"]["first_block_ore"])
						{
							while($postbreak_check_count < $GLOBALS['config']['settings']['postbreak_check']){ $current_mine["stats"]["postbreak_possible"]++;	$postbreak_check_count++; }
						}
						$postbreak_checking = false; $postbreak_check_count = 0;
					
						echo "--End Of Mine Detected [".count($Mine_Array)."] ... (Volume: ".$current_mine["stats"]["total_volume"]." , Adjusted: ".$current_mine["stats"]["adjusted_volume"].")<BR>";
						//echo "(PB Possible: ".$current_mine["stats"]["postbreak_possible"].", PB Actual: ".$current_mine["stats"]["postbreak_total"].")";
						if($current_mine["stats"]["first_block_ore"]==true)
						{
							//echo "*";
						
						}
														
						if(count($current_mine["clusters"])>0)
						{
							echo " -- Mine OK (".count($current_mine["clusters"])." clusters), including in results.<BR>";
							$current_mine["breaks"]=array_reverse($current_mine["breaks"]);
							array_push($Mine_Array, $current_mine);
							
							$current_mine = $init_current_mine; $prev_break = $init_prev_break; $current_cluster = $init_cluster; $recent_depth = array();
							array_push($FullBreaksArray,$fullbreaks_item);
						}
						else
						{
							if($current_mine["stats"]["first_block_ore"])
							{
								echo " -- Mine OK (firstblock ore), including in results.<BR>";
								$current_mine["breaks"]=array_reverse($current_mine["breaks"]);
								array_push($Mine_Array, $current_mine);
							
								$current_mine = $init_current_mine; $prev_break = $init_prev_break; $current_cluster = $init_cluster; $recent_depth = array();
								array_push($FullBreaksArray,$fullbreaks_item);
							}
							else
							{
								echo " -- Mine BAD (0 clusters). Omitting from results.<BR>";
								$current_mine = $init_current_mine; $prev_break = $init_prev_break; $current_cluster = $init_cluster; $recent_depth = array();
								array_push($FullBreaksArray,$fullbreaks_item);
							}
						}
	
						// If mine is large enough, keep it.  If it's too small, omit from final results
						/*
						if(count($current_mine["breaks"])>10)
						{
							if($current_mine["stats"]["adjusted_volume"]>10)
							{
								echo " -- Mine volume OK, including in results.";
								$current_mine["breaks"]=array_reverse($current_mine["breaks"]);
								array_push($Mine_Array, $current_mine);
							
								$current_mine = $init_current_mine; $prev_break = $init_prev_break; $current_cluster = $init_cluster; $recent_depth = array();
								array_push($FullBreaksArray,$fullbreaks_item);
							}
							else
							{
								echo " -- Adjusted volume too small. Omitting from results.";
								$current_mine = $init_current_mine; $prev_break = $init_prev_break; $current_cluster = $init_cluster; $recent_depth = array();
								array_push($FullBreaksArray,$fullbreaks_item);
							}
						}
						else
						{
							if($current_mine["stats"]["first_block_ore"])
							{
								echo " -- Mine OK (firstblock ore), including in results.";
								$current_mine["breaks"]=array_reverse($current_mine["breaks"]);
								array_push($Mine_Array, $current_mine);
								
								$current_mine = $init_current_mine; $prev_break = $init_prev_break; $current_cluster = $init_cluster; $recent_depth = array();
								array_push($FullBreaksArray,$fullbreaks_item);
							}
							else
							{
								echo " -- Mine volume too small. Omitting from results.";
								$current_mine = $init_current_mine; $prev_break = $init_prev_break; $current_cluster = $init_cluster; $recent_depth = array();
								array_push($FullBreaksArray,$fullbreaks_item);
							}
						}*/
				
						$prev_break = $fullbreaks_item;
					}
				
					// When last block is reached, finalize last mine
					if(count($FullBreaksArray) == 0)
					{
						$fullbreaks_done = true;
						echo "LAST BLOCK!<BR>";
					
						// Check to see if player is still mining (last break is within past 10 minutes)
						// TODO
		
						//if($adjacent){ array_unshift($current_mine["breaks"], $fullbreaks_item); }
						
						/*
						if($current_mine["breaks"][0]["date"] < $datetime_hour_ago)
						{
							echo "--New Mine Detected ... (Volume: ".$current_mine["stats"]["total_volume"]." , Adjusted: ".$current_mine["stats"]["adjusted_volume"].") <BR><BR>";
							$current_mine["breaks"]=array_reverse($current_mine["breaks"]);
							array_push($Mine_Array, $current_mine);
						}
						else
						{
							echo "--New Mine Detected ... <BR>";
						}*/
					}
				
					//echo "}".$fullbreaks_item["beforeblock"]."> <BR>";
					if(count($current_mine["breaks"]) != 0)
					{
						//echo "FULL BREAK SIZE: [" . count($FullBreaksArray)  . "] | MINE BREAK SIZE: [". count($current_mine["breaks"]) ."][".$current_mine["stats"]["total_volume"]."]";
						//echo "_ores[".$current_mine["stats"]["total_ores"]."]";
						//echo "<BR>";
					}
					//else { echo "<BR><BR>==========================<BR>"; }
				}
				
				echo "<BR><BR>[--------------------ADD TO DATABASE--------------------]<BR>";
			
				// Insert new mines/clusters into database
				// ------------------------------
				
				//echo "FULL MINE_ARRAY: "; print_r($Mine_Array); echo "<BR>";
				
				//$sql_newmine  = "START TRANSACTION; ";
				echo "New Mines Found: ". count($Mine_Array). " <BR>";
				if(count($Mine_Array) > 0)
					{ echo "Adding mines and clusters to player's records...<br>"; }
				else
					{ echo "WARNING: User does not have enough new mining data. No changes will be made.<br>"; }
			
				foreach($Mine_Array as $mine_index => $mine_item)
				{
					//echo "<BR><BR>==========================<BR>";
					foreach($mine_item["clusters"] as $cluster_index => &$cluster_item)
					{
						//echo "CLUSTER INFO [$cluster_index]: "; print_r($cluster_item); echo "<BR><BR>";
									
						if(count($cluster_item["nearby_before"])>0)
						{
							$cluster_item["slope_before"] = number_format( ( ($cluster_item["nearby_before"][0] - $cluster_item["nearby_before"][ count($cluster_item["nearby_before"])-1 ] ) / (0 - count($cluster_item["nearby_before"])-1 ) ), 2);
							$cluster_item["spread_before"] = max($cluster_item["nearby_before"]) - min($cluster_item["nearby_before"]);
						} else { $cluster_item["slope_before"] = NULL; $cluster_item["spread_before"] = NULL; }
					
						if(count($cluster_item["nearby_after"])>0)
						{
							$cluster_item["slope_after"] = number_format( ( ($cluster_item["nearby_after"][0] - $cluster_item["nearby_after"][ count($cluster_item["nearby_after"])-1 ] ) / (0 - count($cluster_item["nearby_after"])-1 ) ), 2);
							$cluster_item["spread_after"] = max($cluster_item["nearby_after"]) - min($cluster_item["nearby_after"]);
						} else { $cluster_item["slope_after"] = NULL; $cluster_item["spread_after"] = NULL; }
					
						//echo "RAW CLUSTER INFO [$mine_index]>>[$cluster_index]: "; print_r($cluster_item); echo "<BR><BR>";
	
						$cluster_item["nearby_before"] = count($cluster_item["nearby_before"]);
						$cluster_item["nearby_after"] = count($cluster_item["nearby_after"]);
					
						//echo "SQL CLUSTER INFO [$mine_index]>>[$cluster_index]: "; print_r($cluster_item); echo "<BR><BR>";
					
					
					}
					
					$mine_item["stats"]["last_break_date"] = $mine_item["breaks"][0]["date"];
					$mine_item["stats"]["depth_avg"] = $mine_item["stats"]["depth_total"] / $mine_item["stats"]["total_volume"];
				
					//echo "STATS[$mine_index]: "; print_r($mine_item["stats"]); echo "<BR><BR>";
	
					/*
					echo "...Adding Mine ($mine_index of ".count($Mine_Array).")...";
					$sql_newmine = "INSERT INTO `x-mines` ";
					$sql_newmine .= " 	( `playerid`, `worldid`, `volume`, `first_block_ore`, `last_break_date`, `diamond_ratio`, `lapis_ratio`, `iron_ratio`, `gold_ratio`, `mossy_ratio`) ";
					$sql_newmine .= " VALUES ";
					$sql_newmine .= " 	( $playerid, 1, ".$mine_item["stats"]["total_volume"].", ".$mine_item["stats"]["first_block_ore"].", '".$mine_item["stats"]["last_break_date"]."', 2.5, 1.2, 5.7, 4.3, 0 ); ";
					$sql_newmine .= "  ";
					if( count($mine_item["clusters"]) > 0)
					{
						$sql_newmine .= "INSERT INTO `minecraft`.`x-clusters` ";
						$sql_newmine .= " 	( `mineid`, `playerid`, `worldid`, `ore_begin`, `ore_length`, `slope_before`, `slope_after`, `spread_before`, `spread_after`) ";
						$sql_newmine .= " VALUES ";
						foreach($mine_item["clusters"] as $cluster_index => $cluster_item)
						{
							$sql_newmine .= " ( last_insert_id(), $playerid, 1, ".$cluster_item["ore_begin"].", ".$cluster_item["ore_length"].", ";
							$sql_newmine .= $cluster_item["slope_before"].", ".$cluster_item["slope_after"].", ".$cluster_item["spread_before"].", ".$cluster_item["spread_after"]." ) ";
		
							if($cluster_index < count($mine_item["clusters"])-1 ){ $sql_newmine .= " , "; } // Join clusters together with a single query
						}
					}
					$sql_newmine .= ";";
					*/
					
					//echo "...Adding Mine ($mine_index of ".count($Mine_Array).")...";
					$sql_newmine = "INSERT INTO `x-mines` ";
					$sql_newmine .= " 	( `playerid`, `worldid`, `volume`, `first_block_ore`, `last_break_date`, `diamond_ratio`, `lapis_ratio`, `iron_ratio`, `gold_ratio`, `mossy_ratio`) ";
					$sql_newmine .= " VALUES ";
					$sql_newmine .= sprintf(" 	( %s, %s, %s, %s, %s, %s, %s, %s, %s, %s); ",
											GetSQLValueString($playerid,"int"),
											GetSQLValueString(1,"int"),
											GetSQLValueString($mine_item["stats"]["total_volume"],"int"),
											GetSQLValueString($mine_item["stats"]["first_block_ore"],"defined",1,0),
											GetSQLValueString($mine_item["stats"]["last_break_date"],"date"),
											GetSQLValueString(NULL,"int"),
											GetSQLValueString(NULL,"int"),
											GetSQLValueString(NULL,"int"),
											GetSQLValueString(NULL,"int"),
											GetSQLValueString(NULL,"int") );
					$sql_newmine .= "  ";
					//echo "SQL NEWMINE[$mine_index]: <BR> $sql_newmine <BR><BR>";
					$res_newbreaks = mysql_query($sql_newmine) or die("SQL_QUERY[newmine - $mine_index]: " . $sql_newmine . "<BR> " . mysql_error() . "<BR>");
					//if(!mysql_errno()){ echo "DONE!<BR>"; }
				
					if( count($mine_item["clusters"]) > 0)
					{
						$sql_newmine = "INSERT INTO `x-clusters` ";
						$sql_newmine .= " 	( `mineid`, `playerid`, `worldid`, `ore_begin`, `ore_length`, `slope_before`, `slope_after`, `spread_before`, `spread_after`) ";
						$sql_newmine .= " VALUES ";
						foreach($mine_item["clusters"] as $cluster_index => $cluster_item)
						{
							$sql_newmine .= sprintf(" 	( %s, %s, %s, %s, %s, %s, %s, %s, %s) ",
								"last_insert_id()",
								GetSQLValueString($playerid,"int"),
								GetSQLValueString(1,"int"),
								GetSQLValueString($cluster_item["ore_begin"],"int"),
								GetSQLValueString($cluster_item["ore_length"],"int"),
								GetSQLValueString($cluster_item["slope_before"],"double"),
								GetSQLValueString($cluster_item["slope_after"],"double"),
								GetSQLValueString($cluster_item["spread_before"],"double"),
								GetSQLValueString($cluster_item["spread_after"],"double") );
							if($cluster_index < count($mine_item["clusters"])-1 ){ $sql_newmine .= " , "; } // Join clusters together with a single query
						}
					}
		
					//echo "SQL NEWCLUSTERS[$mine_index]: <BR> $sql_newmine <BR><BR>";
					$res_newbreaks = mysql_query($sql_newmine) or die("SQL_QUERY[newmine - $mine_index]: " . $sql_newmine . "<BR> " . mysql_error() . "<BR>");
					//if(!mysql_errno()){ echo "DONE!<BR>"; }
				
					// Overall stats
					$final_postbreak_total += $mine_item["stats"]["postbreak_total"];
					$final_postbreak_possible += $mine_item["stats"]["postbreak_possible"];
					
					if(count($Mine_Array) > 100 && ($mine_index % (count($Mine_Array) / 10) ) == 0 )
					{
						//echo "[=====]";
						echo "Processing... " . round( ($mine_index / (count($Mine_Array) / 10)) * 10 ) . "%...<BR>";
						//echo "[$mine_index]";
					}
				}
			
				echo "DONE! Database update complete! <BR>";
			
				/*
				$sql_newmine .= " COMMIT; ";
				echo "SQL NEWMINE: <BR> $sql_newmine <BR><BR>";
				$res_newbreaks = mysql_query($sql_newmine);
				if(mysql_errno())
				{
					die("SQL_QUERY[newmine]: " . $sql_newmine . "<BR> " . mysql_error() . "<BR>");
				}else { echo "DONE!<BR>"; }
				*/
			
				echo "<BR><BR>[------------------------STATS------------------------]<BR>";
				if($final_postbreak_possible != 0)
					{	$final_postbreak_ratio = $final_postbreak_total / $final_postbreak_possible; } 
					else { $final_postbreak_ratio = "0"; }
				echo "POSTBREAK RATIO: " . number_format($final_postbreak_ratio * 100, 2) . "%<BR>";
			}
		}
	}
}