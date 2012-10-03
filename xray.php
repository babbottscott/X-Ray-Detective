<?php require_once('inc/core_xdetector.php'); ?>
<?php include_once('inc/auth_xray.php'); ?>
<?php

//echo "Global Init...<BR>";
Global_Init();
//echo "Global Init Complete...<BR>";
$auth = Do_Auth();

if(array_key_exists('Submit', $_POST)){ $_GET = $_POST; }
if(array_key_exists('command', $_POST)){ $_GET = $_POST; }
$command = array_key_exists('command', $_GET) ? $_GET['command'] : "";
$command_error = ""; $command_success = "";

//echo "Begin script...<br>";
if($_SESSION["auth_is_valid"] && !$_SESSION['first_setup'])
{
	//echo "Continuing...<br>";
	@mysql_connect($db['x_host'], $db['x_user'], $db['x_pass']) or die($_SERVER["REQUEST_URI"] . "Could not connect to XRAY DB host [".$db['x_host']."].");
	@mysql_selectdb($db['x_base']) or die($_SERVER["REQUEST_URI"] . "Could not select XRAY DB [".$db['x_base']."]");

	$block_type = array_key_exists('block_type', $_GET) ? $_GET['block_type'] : 56;
	$stone_threshold = array_key_exists('stone_threshold', $_GET) ? $_GET['stone_threshold'] : 500;
	$limit_results = array_key_exists('limit_results', $_GET) ? $_GET['limit_results'] : 100;
	$player_name = array_key_exists('player', $_GET) ? $_GET['player'] : NULL;
	$player_id = Get_Player_IDByName($player_name);
	$show_process = false;
	$require_confirmation = false;
	$_GET['confirm'] = array_key_exists('confirm', $_GET) ? $_GET['confirm'] : NULL;
	
	switch($block_type){
		case 56: $sortby_column_name = "diamond_ratio"; break;
		case 25: $sortby_column_name = "lapis_ratio"; break;
		case 14: $sortby_column_name = "gold_ratio"; break;
		case 48: $sortby_column_name = "mossy_ratio"; break;
		case 15: $sortby_column_name = "iron_ratio"; break;
		default: $sortby_column_name = "invalid"; break;
	}
	//echo "LIMIT BLOCK: $sortby_column_name<BR>";
	//echo "WORLD ID: $world_id<BR>";
	//echo "WORLD NAME: $world_name<BR>";
	//echo "WORLD ALIAS: $world_alias<BR>";
	
	/*
	echo "ARGUMENTS [GET] : ----<br>";
	print_r($_GET); echo "<br>";
	echo "---------------<br>";
	echo "ARGUMENTS [POST] : ----<br>";
	print_r($_POST); echo "<br>";
	echo "---------------<br>";
	*/


	$colorbins["diamond_ratio"] = array_fill(0, 10, 0); $colorbins["lapis_ratio"] = array_fill(0, 10, 0); $colorbins["gold_ratio"] = array_fill(0, 10, 0); $colorbins["mossy_ratio"] = array_fill(0, 10, 0); $colorbins["iron_ratio"] = array_fill(0, 10, 0);
	
	// Here are the sensitivity colorbins for each block type.
	// 3 is the LOW value (GREEN)
	// 6 is the MID value (YELLOW)
	// 9 is the HIGH value (RED)
	//
	// All other color values will be created for you automatically.
	//
	if($command == "xsingle" || $command == "xtoplist")
	{
		/////////////////////////////////////////[   ]///////////[    ]//////////[   ]/////
		$colorbins["diamond_ratio"] = array(0 => 0,	3 => "0.5", 	6 => "1.25",	9 => "2");
		$colorbins["lapis_ratio"] =   array(0 => 0,	3 => "1",		6 => "2",   	9 => "3");
		$colorbins["gold_ratio"] =    array(0 => 0, 3 => "2.5",		6 => "4", 		9 => "6");
		$colorbins["mossy_ratio"] =   array(0 => 0,	3 => "5",   	6 => "10",		9 => "15");
		$colorbins["iron_ratio"] =    array(0 => 0,	3 => "15",  	6 => "20",		9 => "30");
		/////////////////////////////////////////[   ]///////////[    ]//////////[   ]/////	
	}
	if($command == "xsingle")
	{
		$colorbins["slope_before|-"] =array(0 => 0,	3 => "-0.21", 	6 => "-0.35",	9 => "-0.45");
		$colorbins["slope_before|+"] =array(0 => 0,	3 => "0.1", 	6 => "0.20",	9 => "0.30");
		$colorbins["spread_before"] =	array(0 => 0, 	3 => "1",		6 => "2.1", 		9 => "4");
	}
	/////////////////////////////////////////[   ]///////////[    ]//////////[   ]/////
	
	//echo "LIMITS::<br>"; print_r($colorbins); echo "<br><br>";
	
	foreach($colorbins as $column_name => $bins)
	{
		//echo "BLOCK TYPE: $sortby_column_name <br>";
		$colorbins[$column_name][1] = $colorbins[$column_name][3] * 0.33;
		$colorbins[$column_name][2] = $colorbins[$column_name][3] * 0.66;
		$colorbins[$column_name][4] = $colorbins[$column_name][3] + ($colorbins[$column_name][6] - $colorbins[$column_name][3]) * 0.33;
		$colorbins[$column_name][5] = $colorbins[$column_name][3] + ($colorbins[$column_name][6] - $colorbins[$column_name][3]) * 0.66;
		$colorbins[$column_name][7] = $colorbins[$column_name][6] + ($colorbins[$column_name][9] - $colorbins[$column_name][6]) * 0.33;
		$colorbins[$column_name][8] = $colorbins[$column_name][6] + ($colorbins[$column_name][9] - $colorbins[$column_name][6]) * 0.66;
		$colorbins[$column_name][10] = $colorbins[$column_name][9] + ($colorbins[$column_name][9] - $colorbins[$column_name][6]) * 1.33;
		asort($colorbins[$column_name]);
		//echo "[" . $column_name . "]<br>"; print_r($colorbins[$column_name]); echo "<br>";
	}

	if ($command == 'xsingle')
	{
	
		
		$_GET['xr_submit'] = array_key_exists('xr_submit', $_GET) ? $_GET['xr_submit'] : NULL;
//		echo "XCHECK";
		if($_GET['xr_submit']=="Check" || $_GET['xr_submit']=="")
		{
			// Check user's totals from stats table
			$player_world_stats = Get_Player_WorldRatios($player_id);
			$player_mines_all = Get_Player_Mines_InWorld($player_id, $GLOBALS['worlds'][0]['worldid']);
			$player_info = Get_Playerinfo($player_id);
			$color_template_list = array("max_ratio_diamond" => "diamond_ratio", "max_ratio_gold" => "gold_ratio", "max_ratio_lapis" => "lapis_ratio", "max_ratio_mossy" => "mossy_ratio", "max_ratio_iron" => "iron_ratio",
				 "avg_slope_before_pos" => "slope_before_pos", "avg_slope_before_neg" => "slope_before_neg", "avg_slope_after_pos" => "slope_after_pos", "avg_slope_after_neg" => "slope_after_neg", "ratio_first_block_ore"=>"first_block_ore");
			$color_important_columns = array("max_ratio_diamond", "max_ratio_gold", "avg_slope_before_neg", "avg_slope_after_neg");	
			Array_Apply_ColorMap($player_info, $color_template_list, $color_important_columns);
			$player_info = Calc_Playerinfo_SuspicionLevel($player_info);
			
			foreach($GLOBALS['worlds'] as $world_index => $world_item)
			{
				$player_clusters_world[$world_index] = Get_Player_Clusters_InWorld($player_id, $world_item['worldid']);
			}


			foreach($player_world_stats as $dataset_rownum => &$dataset_row)
			{
				//echo "INDEX: $dataset_rownum <br>";
				foreach($colorbins as $color_column_name => $bins)
				{
					//echo "COLOR_SEARCH: $color_column_name <br>";					
					foreach($dataset_row as $row_column_name => &$row_column_value)
					{
						if(array_key_exists($color_column_name, $dataset_row) && $color_column_name == $row_column_name)
						{
							//echo "MATCHING_COLUMN: $row_column_name == $color_column_name <br>";
							$tempcolor = 10;
							$dataset_row["color_" . $row_column_name] = -3;
							while($row_column_value < $colorbins[$color_column_name][$tempcolor] && $tempcolor > 0)
							{
								//echo "$color_column_name >> " . $colorbins[$color_column_name][$tempcolor] . " [" . ($tempcolor) . "]<br>";
								$tempcolor--;	
							}
							$dataset_row["color_" . $row_column_name] = $tempcolor;
						}
					}
				}
				$dataset_row["color_max"] = 
					max(	$dataset_row["color_diamond_ratio"],
							$dataset_row["color_lapis_ratio"],
							$dataset_row["color_gold_ratio"],
							$dataset_row["color_mossy_ratio"],
							$dataset_row["color_iron_ratio"]);
			}
			
			foreach($GLOBALS['worlds'] as $world_index => $world_item)
			{
				foreach($player_clusters_world[$world_index] as $dataset_rownum => &$dataset_row)
				{
					//echo "INDEX: $dataset_rownum <br>";
					foreach($colorbins as $color_column_name => $bins)
					{
						//echo "COLOR_SEARCH: $color_column_name <br>";
						$column_name_suffix = "";
						foreach($dataset_row as $row_column_name => &$row_column_value)
						{
							if($row_column_name == "slope_before" && $row_column_value >= 0){$column_name_suffix = "|+";}
							elseif($row_column_name == "slope_before" && $row_column_value < 0){$column_name_suffix = "|-";}
							else {$column_name_suffix = "";}
							$truncated_column_name = str_replace(mysql_real_escape_string($column_name_suffix), '', $color_column_name);
							
							//echo "Match? [". $truncated_column_name . "]<BR>";
							if(array_key_exists($truncated_column_name, $dataset_row) && $truncated_column_name == $row_column_name)
							{
								//echo "MATCHING_COLUMN: $row_column_name == $color_column_name <br>";
								$dataset_row["color_" . $row_column_name] = -3;
								$compare_value = ($colorbins[$color_column_name][9] < 0) ? abs($row_column_value) : $row_column_value;
								if($colorbins[$color_column_name][9] > 0)
								{
									$tempcolor = 10;									
									while($row_column_value < $colorbins[$color_column_name][$tempcolor] && $tempcolor > 0)
									{
										//echo "$color_column_name >> " . $colorbins[$color_column_name][$tempcolor] . " [" . ($tempcolor) . "]<br>";
										$tempcolor--;	
									}
								}
								else
								{
									$tempcolor = 0;
									while($row_column_value < $colorbins[$color_column_name][$tempcolor] && $tempcolor < 10)
									{
										//echo "$color_column_name >> " . $colorbins[$color_column_name][$tempcolor] . " [" . ($tempcolor) . "]<br>";
										$tempcolor++;	
									}	
								}
								
								$dataset_row["color_" . $row_column_name] = $tempcolor;
							}
						}
						//echo "<BR>";
					}
					$dataset_row["color_max"] = 
						max(	$dataset_row["color_slope_before"],
								$dataset_row["color_spread_before"]);
				}
			}
		}
		if($_GET['xr_submit']=="Analyze")
		{
			$command = "xanalyze"; $show_process = true;
		}
	}
	elseif ($command == 'xglobal')
	{
		// Check average ratios from stats table

		// Calculate a ratio based on totals
		if ($dias > 0) { $findrate["diamond"] = number_format($dias * 100 / $stones,2); } else { $findrate["diamond"] = number_format(0,4); }
		if ($mossy > 0) { $findrate["mossy"] = number_format($mossy * 100 / $stones,2); } else { $findrate["mossy"] = number_format(0,4); }
		if ($lapis > 0) { $findrate["lapis"] = number_format($lapis * 100 / $stones,2); } else { $findrate["lapis"] = number_format(0,4); }
		if ($gold > 0) { $findrate["gold"] = number_format($gold * 100 / $stones,2); } else { $findrate["gold"] = number_format(0,4); }
		if ($iron > 0) { $findrate["iron"] = number_format($iron * 100 / $stones,2); } else { $findrate["iron"] = number_format(0,4); }

		foreach($colorbins as $column_name => $bins)
		{
			//echo "BLOCK: "; print_r($column_name); echo "<br>";
			//echo "ARRAY: "; print_r($limt_array); echo "<br>";
			$tempcolor = 10;
			$color[$column_name] = -3;
			while($findrate[$column_name] < $colorbins[$column_name][$tempcolor] && $tempcolor > 0)
			{
				//echo "$column_name >> " . $colorbins[$column_name][$tempcolor] . " [" . ($tempcolor) . "]<br>";
				$tempcolor--;	
			}
			$color[$column_name] = $tempcolor;
		}
	}
	elseif ($command == 'xtoplist')
	{
		$world_id = array_key_exists('worldid', $_GET) ? $_GET["worldid"] : $GLOBALS['worlds'][0]["worldid"];
		
		foreach($GLOBALS['worlds'] as $world_key => $world_item )
		{
			if($world_id==$world_item["worldid"]){ $world_name = $world_item["worldname"]; $world_alias = $world_item["worldalias"];}
		}
		
		if($world_id==""){$world_id=1;}
		if($block_type==""){$block_type=56;}
		if($limit_results==""){$limit_results=50;}
		if($stone_threshold==""){$stone_threshold=500;}
		
		$TopArray = Get_Ratios_ByWorldID($world_id, $limit_results, $block_type, $stone_threshold);

	}
	elseif ($command == 'xscan')
	{
		$show_process = true;
	}
	elseif ($command == 'xupdate')
	{
		$show_process = true;
	}
	elseif ($command == 'xanalyze')
	{
		$show_process = true;
	}
	elseif ($command == 'xclear')
	{
		$show_process = true;
		$require_confirmation = true;
		$msg_confirmation = "You are about to delete all collected x-ray statistics (block counts) for all users!";
	}
	elseif ($command == 'xworlds')
	{
		
	}
	elseif ($command == '')
	{
		
	}
	else
	{
		echo "ERROR: Unrecognized command: [$command]";
		
	}
}

$datetime_now = new DateTime;
$datetime_week_ago = new DateTime;
$datetime_week_ago->modify( '-14 day' );

//echo $datetime_week_ago->format( 'Y-m-d H:i:s' );

?>

<style type="text/css">
a:link {
	color: #FFF;
}
a:visited {
	color: #FFF;
}
a:hover {
	color: #CCC;
}
a:active {
	color: #CCC;
}
body {
	background-image: url(img/bg/xrd_bg.jpg);
	background-repeat: repeat-y;
	margin-left: 100px;
	margin-top: 25px;
	margin-right: 50px;
	margin-bottom: 50px;
	background-color: #000;
}
body,td,th { font-family: Tahoma, Geneva, sans-serif; }
</style>
<link type="text/css" href="styles/css/xray-default/jquery-ui-1.8.18.custom.css" rel="stylesheet">
<link type="text/css" href="styles/css/xray-dark/jquery-ui-1.8.19.custom.css" rel="stylesheet">	
<link type="text/css" href="styles/css/xray-light/jquery-ui-1.8.19.custom.css" rel="stylesheet">	
<link type="text/css" href="styles/css/xray-whiteborder/jquery-ui-1.8.19.custom.css" rel="stylesheet">
<script type="text/javascript" src="styles/jquery-1.7.1.js"></script>
<script type="text/javascript" src="styles/external/jquery.bgiframe-2.1.2.js"></script>
<script type="text/javascript" src="styles/ui/jquery.ui.core.js"></script>
<script type="text/javascript" src="styles/ui/jquery.ui.widget.js"></script>
<script type="text/javascript" src="styles/ui/jquery.ui.accordion.js"></script>
<script type="text/javascript" src="styles/ui/jquery.ui.tabs.js"></script>
<script type="text/javascript" src="styles/ui/jquery.ui.mouse.js"></script>
<script type="text/javascript" src="styles/ui/jquery.ui.slider.js"></script>
<script type="text/javascript" src="styles/ui/jquery.ui.button.js"></script>
<script type="text/javascript" src="styles/ui/jquery.ui.draggable.js"></script>
<script type="text/javascript" src="styles/ui/jquery.ui.position.js"></script>
<script type="text/javascript" src="styles/ui/jquery.ui.resizable.js"></script>
<script type="text/javascript" src="styles/ui/jquery.ui.dialog.js"></script>
<script type="text/javascript" src="styles/ui/jquery.ui.autocomplete.js"></script>
<script type="text/javascript" src="styles/ui/jquery.ui.progressbar.js"></script>
<script type="text/javascript" src="styles/ui/jquery.effects.core.js"></script>
<script type="text/javascript" src="styles/ui/jquery.effects.blind.js"></script>
<script type="text/javascript" src="inc/jquery.form.js"></script>
<script type="text/javascript" src="http://www.google.com/jsapi"></script>
<script type="text/javascript">
$(function(){
	$('.ui-state-default').hover(
		function(){ $(this).addClass('ui-state-hover'); }, 
		function(){ $(this).removeClass('ui-state-hover'); }
	);
	$('.ui-state-default').click(function(){ $(this).toggleClass('ui-state-active'); });
	$('.icons').append(' <a href="#">Toggle text</a>').find('a').click(function(){ $('.icon-collection li span.text').toggle(); return false; }).trigger('click');
	$( "#tabs" ).tabs();

<?php $stone_threshold_options = array(0, 100, 250, 500, 750, 1000); $stone_threshold_default = 500; ?>
	$(function() {
		var valMap = [<?php echo implode(", ", $stone_threshold_options); ?>];
		$("#stone_threshold_slider").slider({
			min: 0,
			max: valMap.length - 1,
			value: <?php 
				if(isset($_POST['stone_threshold']))
				{
					$stone_threshold_default = $_POST['stone_threshold'];
				}
				foreach($stone_threshold_options as $index => $item)
				{
					if($item == $stone_threshold_default){echo $index;}
				}
			?>,
			slide: function(event, ui) {                        
					$("#amount").val( valMap[ui.value] + " Stones");
					$("#stone_threshold").val( valMap[ui.value] );
			},
			change: function(event, ui) { $( "#Get_Ratios_ByWorldID_form" ).submit(); },
		});
		$("#amount").val( valMap[$("#stone_threshold_slider").slider("value")] + " Stones");
	});

	$( "#sort_by_radio" ).buttonset();
	$( "#worldid_radio" ).buttonset();
	$( "#limit_results_radio" ).buttonset();
	
	$( "#sort_by_radio" ).change(function(){ $( "#Get_Ratios_ByWorldID_form" ).submit(); });
	$( "#worldid_radio" ).change(function(){ $( "#Get_Ratios_ByWorldID_form" ).submit(); });
	$( "#limit_results_radio" ).change(function(){ $( "#Get_Ratios_ByWorldID_form" ).submit(); });

	$( '#refresh_stats_button' ).button();

	$( '#refresh_stats_button' ).click(function()
	{
		var clicked_obj = $(this);
		
		clicked_obj.switchClass( "ui-state-default", "ui-state-error", 1000 );
		clicked_obj.switchClass( "ui-state-highlight", "ui-state-error", 1000 );
		clicked_obj.button();
		document.getElementById("refresh_stats_text").innerHTML = "Refreshing...";

		$( "#refresh_stats_progressbar" ).progressbar({
			value: 0,
			option: enabled
		});

		// Get range of dates


		// Divide range of dates into separate AJAX calls

		
		
		$.ajax(
		{ url: 'inc/live/update_newbreaks.php',
				dataType: 'json',
				success: function(response, data)
						 {
								//alert(clicked_obj.attr('id'));
								//alert(response);
								
								clicked_obj.switchClass( "ui-state-default", "ui-state-highlight", 1000 );
								clicked_obj.switchClass( "ui-state-error", "ui-state-highlight", 1000 );
								if(response > 0)
								{
									document.getElementById("refresh_stats_text").innerHTML = response + " Users Updated";
									$( "#refresh_stats_records" ).val(response);
									$( "#Get_Ratios_ByWorldID_form" ).submit();
								}
								else
								{
									document.getElementById("refresh_stats_text").innerHTML = "No Changes Detected";
									//$( "#Get_Ratios_ByWorldID_form" ).submit();
								}

								
								if(response.message == "HOST OK")
								{

								} else {
/*
							
									document.getElementById("source_db_error_main").innerHTML = "An error occurred while validating MySQL Server.<BR>Please check the information and try again.";
									document.getElementById("source_db_error_specific").innerHTML = response.message;
									$( "#db_setup_error_dialog" ).dialog({
										autoOpen: true,
										width: 500,
										modal: false,
										buttons: {
											Ok: function() {
												$( this ).dialog( "close" );
											}
										}
									});
									
									clicked_obj.switchClass( "ui-state-default", "ui-state-error", 1000 );
									clicked_obj.switchClass( "ui-state-highlight", "ui-state-error", 1000 );
									document.getElementById("check_source_db_text").innerHTML = "Check Connection";
									clicked_obj.closest('ul').find('input').val('0');
									clicked_obj.button();
									
									if( ( $('input:radio[name=copy_stx]:checked').val() == 1 ) && ( clicked_obj.attr("id") == "check_source_db"  ) )
									{
										$( "#db_xray_ok" ).val('0');
										$( "#check_xray_db" ).switchClass( "ui-state-default", "ui-state-error", 1000 );
										$( "#check_xray_db" ).switchClass( "ui-state-highlight", "ui-state-error", 1000 );
										document.getElementById("check_xray_db_text").innerHTML = document.getElementById("check_source_db_text").innerHTML;
									}
*/
								}
						 }
		}); // AJAX
		
	});

	$( "#refresh_stats_progressbar" ).progressbar({
		value: 0,
		option: disabled
	});
});



</script>
<script type="text/javascript">
  google.load('visualization', '1.1', {packages: ['gauge']});
</script>
<script type="text/javascript">

function Draw_Gauges()
{
	var Accuracy_data = google.visualization.arrayToDataTable([
	  ['Label', 'Value'],
	  ['Accuracy', <?php echo $player_info["accuracy"]; ?>],
	]);
	
	var Suspicion_data = google.visualization.arrayToDataTable([
	  ['Label', 'Value'],
	  ['Suspicion', <?php echo $player_info["suspicion"]; ?>],
	]);
	
	// Create and populate the data table.
	var Accuracy_options = {
	  width: 180, height: 180,
	  greenFrom: 2.5, greenTo: 3,
	  yellowFrom:1.5, yellowTo: 2.5,
	  redFrom: 0, redTo: 1.5,
	  majorTicks: ['','','','','','','','','',''],
	  minorTicks: 0,
	  max: 3
	};
	
	var Suspicion_options = {
	  width: 180, height: 180,
	  greenFrom: 0, greenTo: 5,
	  yellowFrom:5, yellowTo: 8,
	  redFrom: 8, redTo: 10,
	  majorTicks: ['','','','','','','','','','',''],
	  minorTicks: 0,
	  max: 10
	};
	
	// Create and draw the visualization.

	vis_accuracy_g = new google.visualization.Gauge(document.getElementById('accuracy_gauge'));
	vis_suspicion_g = new google.visualization.Gauge(document.getElementById('suspicion_gauge'));

	vis_accuracy_g.draw(Accuracy_data, Accuracy_options);
	vis_suspicion_g.draw(Suspicion_data, Suspicion_options);

}

google.setOnLoadCallback(Draw_Gauges);



/*
function Draw_Gauges
{
	var Accuracy_data = google.visualization.arrayToDataTable([
	  ['Label', 'Value'],
	  ['Accuracy', 0],
	]);
	
	var Suspicion_data = google.visualization.arrayToDataTable([
	  ['Label', 'Value'],
	  ['Suspicion', 3],
	]);
	
	// Create and populate the data table.
	var Accuracy_options = {
	  width: 180, height: 180,
	  greenFrom: 2.5, greenTo: 3,
	  yellowFrom:1.5, yellowTo: 2.5,
	  redFrom: 0, redTo: 1.5,
	  majorTicks: ['','','','','','','','','',''],
	  minorTicks: 0,
	  max: 3
	};
	
	var Suspicion_options = {
	  width: 180, height: 180,
	  greenFrom: 0, greenTo: 5,
	  yellowFrom:5, yellowTo: 8,
	  redFrom: 8, redTo: 10,
	  majorTicks: ['','','','','','','','','','',''],
	  minorTicks: 0,
	  max: 10
	};
	
	new google.visualization.Gauge(document.getElementById('accuracy_gauge')).draw(Accuracy_data, Accuracy_options);
	//new google.visualization.Gauge(document.getElementById('suspicion_gauge')).draw(Suspicion_data, Suspicion_options);


}*/

//google.setOnLoadCallback(Draw_Gauges);
/*
function Draw_Gauges(){
  // Create and populate the data table.
  var Accuracy_data = google.visualization.arrayToDataTable([
	['Label', 'Value'],
	['Accuracy', 2],
  ]);
  
  var Accuracy_options = {
	  width: 180, height: 180,
	  greenFrom: 2.5, greenTo: 3,
	  yellowFrom:1.5, yellowTo: 2.5,
	  redFrom: 0, redTo: 1.5,
	  majorTicks: ['','','','','','','','','',''],
	  minorTicks: 0,
	  max: 3
  };
  
  var Suspicion_data = google.visualization.arrayToDataTable([
	['Label', 'Value'],
	['Suspicion', 6],
  ]);
  
  var Suspicion_options = {
	  width: 180, height: 180,
	  greenFrom: 0, greenTo: 5,
	  yellowFrom:5, yellowTo: 8,
	  redFrom: 8, redTo: 10,
	  majorTicks: ['','','','','','','','','','',''],
	  minorTicks: 0,
	  max: 10
  };


  // Create and draw the visualization.
  new google.visualization.Gauge(document.getElementById('accuracy_gauge')).
	  draw(Accuracy_data, Accuracy_options);
  new google.visualization.Gauge(document.getElementById('suspicion_gauge')).
	  draw(Suspicion_data, Suspicion_options);
}

google.setOnLoadCallback(Draw_Gauges);

*/
</script>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>X-Ray Detective</title>
<link href="styles/style_weblinks_global.css" rel="stylesheet" type="text/css" />
<link href="styles/style_borders.css" rel="stylesheet" type="text/css" />
<link href="styles/style_backgrounds.css" rel="stylesheet" type="text/css" />
<link href="styles/style_xray.css" rel="stylesheet" type="text/css" />
</head>

<body>
<?php //echo "FIRST SETUP: [".$GLOBALS['config_settings']['settings']['first_setup']."][".FixOutput_Bool($_SESSION['first_setup'],"YES","NO","EMPTY")."]";?>
<?php //echo "AUTH_IS_VALID: [".FixOutput_Bool($_SESSION['auth_is_valid'],"YES","NO","UNDEFINED")."]"; ?>
<?php if(!$_SESSION["auth_is_valid"] || $_SESSION["first_setup"]){ ?>
<table width="800" border="0" class="borderblack_greybg_light_thick ui-corner-all">
  <tr>
    <td><form id="loginform" name="loginform" method="post" action="">
      <table width="100%" border="0">
        <tr>
          <td><table width="100%" height="90" border="0" class="xray_header">
            <tr>
              <td><a href="xray.php" target="_self"><img src="img/null15.gif" width="500" height="80" hspace="0" vspace="0" border="0" /></a></td>
            </tr>
          </table></td>
        </tr>
        <tr>
          <td><table width="100%" border="0" class="borderblack_greybg_dark_thick ui-corner-all">
            <tr>
              <td align="right">&nbsp;</td>
            </tr>
          </table></td>
        </tr>
        <tr>
          <td><table width="100%" border="0" class="borderblack_greybg_dark_thick ui-corner-all">
            <tr>
              <td>&nbsp;</td>
              </tr>
            <tr>
              <td align="right"><?php if($auth['logout_success']!=""){ ?>
                <table width="100%" border="0" cellpadding="20" class="ui-widget ui-state-highlight ui-corner-all border_black_thick">
                  <tr>
                    <td align="center" valign="middle">&nbsp;</td>
                  </tr>
                  <tr>
                    <td align="center" valign="middle"><strong><?php echo $auth['logout_success']; ?>
                      </h1>
                    </strong></td>
                  </tr>
                  <tr>
                    <td align="center" valign="middle">[ <a href="xray.php" target="_self">Login</a> ]</td>
                  </tr>
              </table>
<br />
                <?php } if($auth['login_error']!=""){ ?>
                <table width="100%" border="0" cellpadding="20" class="ui-widget ui-state-error ui-corner-all border_black_thick">
                  <tr>
                    <td align="center" valign="middle">&nbsp;</td>
                  </tr>
                  <tr>
                    <td align="center" valign="middle"><strong><?php echo $auth['login_error']; ?>
                    </strong></td>
                  </tr>
                  <tr>
                    <td align="center" valign="middle">&nbsp;</td>
                  </tr>
                  </table>
<br />
                <?php } ?>
				<?php if($_SESSION['first_setup']){ ?>
                <table width="100%" border="0" cellpadding="20" class="ui-widget ui-state-error ui-corner-all border_black_thick">
                  <tr>
                    <td align="center" valign="middle">&nbsp;</td>
                  </tr>
                  <tr>
                    <td align="center" valign="middle"><strong>
						Thank you for choosing X-Ray Detective!<br /><br />
						It looks like you are running this for the first time.<BR /><BR />
						You cannot use X-Ray Detective until you have fully completed the <a href="setup.php">Setup</a>.
                    </strong></td>
                  </tr>
                  <tr>
                    <td align="center" valign="middle">&nbsp;</td>
                  </tr>
                </table>
                <?php } elseif($GLOBALS['config_settings']['auth']['mode'] == "username"){ ?>
                <?php if(isset($GLOBALS['auth']['IP_Users_list']) && count($GLOBALS['auth']['IP_Users_list']) > 0) { // Show if recordset not empty ?>
                <table width="100%" border="0">
                  <tr>
                    <td align="center" valign="middle"><h1>Please Login...</h1></td>
                    <td><table width="100%" border="0" class="borderblack_greybg_light_thick ui-corner-all">
                      <tr>
                        <td class="borderblack_greybg_norm_thin"><strong>Select Your Username</strong></td>
                        </tr>
                      <tr>
                        <td><table width="100%" border="0">
                          <tr>
                            <td width="200" valign="top" nowrap="nowrap"><strong>Your Username:</strong></td>
                            <td valign="top"><select name="my_username" id="my_username">
                              <?php foreach($GLOBALS['auth']['IP_Users_list'] as $ip_index => $ip_item) { ?>
                              <option value="<?php echo $ip_item['playername']; ?>"><?php echo $ip_item['playername']; ?></option>
                              <?php } ?>
                              </select></td>
                            </tr>
                          <tr>
                            <td>&nbsp;</td>
                            <td><input name="Submit" type="submit" id="Submit" value="Login" />
                              <input name="form" type="hidden" id="form" value="loginform" /></td>
                            </tr>
                          </table></td>
                        </tr>
                      </table></td>
                    </tr>
                  </table>
                <?php } else { ?>
                <table width="100%" border="0" cellpadding="20" class="ui-widget ui-state-error ui-corner-all border_black_thick">
                  <tr>
                    <td align="center" valign="middle">&nbsp;</td>
                  </tr>
                  <tr>
                    <td align="center" valign="middle"><strong>You are not authorized to view this page:<BR /><BR />Could not find any users matching your IP.
                    </strong></td>
                  </tr>
                  <tr>
                    <td align="center" valign="middle">&nbsp;</td>
                  </tr>
                </table>
                <?php } } if ($GLOBALS['config_settings']['auth']['mode'] == "password"){ ?>
                <table width="100%" border="0">
                  <tr>
                    <td align="center" valign="middle"><h1>Please Login...</h1></td>
                    <td align="center" valign="middle"><table width="100%" border="0" class="borderblack_greybg_light_thick ui-corner-all">
                      <tr>
                        <td class="borderblack_greybg_norm_thin"><strong>Enter Your Password</strong></td>
                        </tr>
                      <tr>
                        <td><table  border="0" cellspacing="0" cellpadding="0">
                          <tr>
                            <td nowrap="nowrap"><strong>Password</strong></td>
                            <td><input name="login_password" type="password" id="login_password" size="30" maxlength="30" /></td>
                            </tr>
                          <tr>
                            <td nowrap="nowrap">&nbsp;</td>
                            <td align="right"><input name="Submit" type="submit" id="Submit" value="Login" />
                              <input name="form" type="hidden" id="form" value="loginform" /></td>
                            </tr>
                          </table></td>
                        </tr>
                      </table></td>
                    </tr>
              </table>
                <?php } ?>
                <br /></td>
              </tr>
            <tr>
              <td align="right">&nbsp;</td>
              </tr>
            </table></td>
        </tr>
        <tr>
          <td><table width="100%" border="0" class="borderblack_greybg_dark_thick ui-corner-all">
            <tr>
              <td align="right">&nbsp;</td>
              </tr>
            </table></td>
        </tr>
      </table>
    </form></td>
  </tr>
</table>
<br />
<?php } if($_SESSION["auth_is_valid"] && !$_SESSION["first_setup"] && $show_process==true){ ?>
<table width="800" border="0" class="borderblack_greybg_light_thick ui-corner-all">
  <tr>
    <td><table width="100%" border="0">
      <tr>
        <td><table width="100%" height="90" border="0" cellpadding="0" cellspacing="0" class="xray_header">
          <tr>
            <td><a href="xray.php" target="_self"><img src="img/null15.gif" alt="" width="500" height="80" hspace="0" vspace="0" border="0" /></a></td>
            <td align="right"><table width="100%" border="0">
              <tr>
                <td align="right"><strong>Logged in as: <?php echo $_SESSION["auth_level"]; if($_SESSION["account"]["playername"]!=""){ echo "<BR>(".$_SESSION["account"]["playername"].")";}elseif($_SESSION["auth_type"]=="ip"){echo "<BR>IP Failsafe Login";} ?><br />
                  </strong>
                  <form id="logoutform" name="logoutform" method="post" action="xray.php">
                    <strong>
                      <input type="submit" name="Submit" id="Submit" value="Logout" />
                      <input name="form" type="hidden" id="form" value="logoutform" />
                      </strong>
                  </form></td>
              </tr>
            </table></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><table width="100%" border="0" class="borderblack_greybg_dark_thick ui-corner-all">
          <tr>
            <td>&nbsp;</td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><?php if($require_confirmation && $_GET['confirm']!="1"){ ?>
          <table width="100%" border="0" class="borderblack_greybg_dark_thick ui-corner-all">
          <tr>
            <td><table width="100%" border="0" cellpadding="25">
              <tr>
                <td><table width="100%" border="0" cellpadding="15" class="borderblack_greybg_dark_thick ui-corner-all">
                  <tr>
                    <td colspan="2" class="bg_I_10"><h2>WARNING:</h2></td>
                  </tr>
                  <tr>
                    <td colspan="2" class="bg_I_-3"><?php echo $msg_confirmation; ?></td>
                  </tr>
                  <tr>
                    <td align="center" class="borderblack_greybg_norm_thick ui-corner-all"><strong><a href="xray.php">ABORT</a></strong></td>
                    <td align="center" class="borderblack_greybg_norm_thick ui-corner-all"><strong><a href="<?php echo $_SERVER['REQUEST_URI'] . "&confirm=1"; ?>">PROCEED</a></strong></td>
                  </tr>
                </table></td>
              </tr>
            </table></td>
          </tr>
        </table>
          <?php } else { ?>
          <table width="100%" border="0" class="borderblack_greybg_dark_thick ui-corner-all">
          <tr>
            <td><h2>
              Processing...              
              
</h2></td>
          </tr>
          <tr>
            <td><?php 
					if($command == "xupdate")
					{
						if($_SESSION["auth_admin"] || $_SESSION["auth_mod"]) { Add_NewBreaks(); /* AutoFlagWatching(); TakeSnapshots();*/ }
						else { $command_error .= "You do not have permission to do that.<BR>"; }
					}
					if($command == "xanalyze")
					{						
						if($_SESSION["auth_admin"] || $_SESSION["auth_mod"])
						{
							Add_Player_Mines($player_id);
							Update_Player_MinesStats($player_id);
						}
						else { $command_error .= "You do not have permission to do that.<BR>"; }
					}
					if($command == "xclear")
					{
						if($_SESSION["auth_admin"]) { Clear_XStats(); }
						else { $command_error .= "You do not have permission to do that.<BR>"; }
					}
			 ?></td>
          </tr>
          <tr>
            <td><?php if($command_error!=""){ ?>
              <table width="100%" border="0" class="ui-widget ui-state-error ui-corner-all border_black_thick">
              <tr>
                <td align="center" valign="middle"><h1 class="error"><?php echo $command_error; ?></h1>
                  </h1></td>
              </tr>
              <tr>
                <td align="center" valign="middle">[ <a href="xray.php">Home</a> ]</td>
              </tr>
            </table>
              <?php } else { $command_success .= "Execution complete.<BR>"; } ?></td>
          </tr>
          <tr>
            <td><?php if($command_success!=""){ ?>
              <table width="100%" border="0" class="ui-widget ui-state-highlight ui-corner-all border_black_thick">
              <tr>
                <td align="center" valign="middle"><h1 class="success"><?php echo $command_success; ?></h1>
                  </h1></td>
              </tr>
              <tr>
                <td align="center" valign="middle">
					<?php if($player_name!=""){ ?>[ <a href="xray.php?command=xsingle&player=<?php echo $player_name; ?>">Player's Stats</a> ] <?php } ?>
                    <?php if($command=="xupdate"){ ?>[ <a href="xray.php?command=xtoplist">Top List</a> ] <?php } ?>
                    [ <a href="xray.php">Home</a> ]</td>
              </tr>
            </table>
              <?php } ?></td>
          </tr>
        </table>
          <?php } ?></td>
      </tr>
      <tr>
        <td><table width="100%" border="0" class="borderblack_greybg_dark_thick ui-corner-all">
          <tr>
            <td>&nbsp;</td>
          </tr>
        </table></td>
      </tr>
    </table></td>
  </tr>
</table>
<br />
<?php } elseif($_SESSION["auth_is_valid"] && !$_SESSION["first_setup"] && array_search($command, array("", "xtoplist", "xsingle", "xglobal", "xworlds"))!==false ) { ?>
<table width="800" border="0" class="borderblack_greybg_light_thick ui-corner-all">
  <tr>
    <td><table width="100%" border="0">
      <tr>
        <td><table width="100%" height="90" border="0" cellpadding="0" cellspacing="0" class="xray_header">
          <tr>
            <td><a href="xray.php" target="_self"><img src="img/null15.gif" alt="" width="500" height="80" hspace="0" vspace="0" border="0" /></a></td>
            <td align="right"><table width="100%" border="0">
              <tr>
                <td align="right"><strong>Logged in as: <?php echo $_SESSION["auth_level"]; if($_SESSION["account"]["playername"]!=""){ echo "<BR>(".$_SESSION["account"]["playername"].")";}elseif($_SESSION["auth_type"]=="ip"){echo "<BR>IP Failsafe Login";} ?><br />
                </strong>
                  <form id="logoutform" name="logoutform" method="post" action="xray.php">
                    <strong>
                      <input type="submit" name="Submit" id="Submit" value="Logout" />
                      <input name="form" type="hidden" id="form" value="logoutform" />
                      </strong>
                  </form>
                  </td>
              </tr>
            </table></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><table width="100%" border="0" class="borderblack_greybg_norm_thick ui-corner-all">
          <tr>
            <td><table width="100%" border="0" class="borderblack_greybg_dark_thick ui-corner-all">
              <tr>
                <td><h1>Tasks</h1></td>
              </tr>
            </table></td>
          </tr>
          <tr>
            <td><table width="100%" border="0" class="bg_black">
              <tr class="borderblack_greybg_light_thin">
                <td><h3><strong>Users</strong></h3></td>
                <td><h3><strong>Moderators</strong></h3></td>
                <td><h3><strong>Administrators</strong></h3></td>
              </tr>
              <tr class="bg_white">
                <td><strong><a href="xray.php?command=xtoplist" style="color:#000000">Top User Statistics</a><a href="xray.php?command=xclear" style="color:#000000"></a></strong></td>
                <td><a href="xray.php?command=xupdate" style="color:#000000"><strong>Update  X-Ray Stats</strong></a></td>
                <td><a href="setup.php" style="color:#000000"><strong>Change X-Ray Settings</strong></a></td>
              </tr>
              <tr class="bg_white">
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
              </tr>
              <tr class="bg_white">
                <td><strong><a href="xray.php?command=xglobal&amp;player=GlobalRates" style="color:#000000"><s>Check Global Averages</s></a></strong></td>
                <td>&nbsp;</td>
                <td><a href="xray.php?command=xclear" style="color:#000000"><strong>Clear X-Ray Stats</strong></a></td>
              </tr>
             </table></td>
          </tr>
          <tr>
            <td><form action="xray.php" method="post" name="XR_form" target="_self" id="XR_form">
              <table width="100%" border="0" class="borderblack_greybg_light_thin">
                <tr>
                  <td width="14%" nowrap="nowrap"><strong><s>Check Player By Name</s>
                    <input name="command" type="hidden" id="command" value="xsingle" />
                    <input name="form" type="hidden" id="form" value="XR_form" />
                  </strong></td>
                  <td width="86%" nowrap="nowrap"><input name="player" type="text" id="player" maxlength="20" />
                    <input type="submit" name="xr_submit" id="xr_submit" value="Check" />
                    <input type="submit" name="xr_submit" id="xr_submit" value="Analyze" /></td>
                </tr>
              </table>
            </form></td>
          </tr>
        </table></td>
      </tr>
      <tr>
<?php
	/* echo "player_info: "; print_r($player_info); */
	echo "POST: "; print_r($_POST);
?>
        <td><?php if($command=="xtoplist"){ ?>
          <form id="Get_Ratios_ByWorldID_form" name="Get_Ratios_ByWorldID_form" method="post" action="xray.php">
            <table width="100%" border="0" class="borderblack_greybg_norm_thick ui-corner-all">
              <tr>
                <td><table width="100%" border="0" class="borderblack_greybg_dark_thick ui-corner-all">
                  <tr>
                    <td><h1>Top Ratios</h1></td>
                  </tr>
                </table></td>
              </tr>
              <tr>
                <td><!--<table width="100%" border="0">
                  <tr>
                      <td><strong>Block Type
                        <input name="form" type="hidden" id="form" value="form_Get_Ratios_ByWorldID" />
                        <input name="command" type="hidden" id="command" value="xtoplist" />
                      </strong></td>
                      <td><select name="block_type" id="block_type">
                        <option value="56"<?php if($block_type=="56"){echo " selected";}?>>Diamonds</option>
                        <option value="25"<?php if($block_type=="25"){echo " selected";}?>>Lapis</option>
                        <option value="14"<?php if($block_type=="14"){echo " selected";}?>>Gold</option>
                        <option value="48"<?php if($block_type=="48"){echo " selected";}?>>Mossy</option>
                        <option value="15"<?php if($block_type=="15"){echo " selected";}?>>Iron</option>
                      </select>
                        <input type="submit" name="top_go" id="top_go" value="Go" /></td>
                    </tr>
                    <tr>
                      <td><strong>World</strong></td>
                      <td><select name="worldid" id="worldid">
<?php foreach($GLOBALS['worlds'] as $world_key => $world_item ){ ?>
                        <option value="<?php echo $world_item["worldid"]; ?>"<?php if($world_id==$world_item["worldid"]){ echo " selected";}?>><?php echo $world_item["worldalias"]; ?></option>
<?php } ?>
                      </select>
                        <input type="submit" name="top_go" id="top_go" value="Go" /></td>
                    </tr>
                    <tr>
                      <td><strong>Stone Threshold</strong></td>
                      <td><strong><em>
                        <select name="stone_threshold" id="stone_threshold">
                            <option value="1000"<?php if($stone_threshold=="1000"){ echo " selected";}?>>1000+ Stone Broken (Most Accurate)</option>
                            <option value="750"<?php if($stone_threshold=="750"){ echo " selected";}?>>750+ Stone Broken</option>
                            <option value="500"<?php if($stone_threshold=="500"||$stone_threshold==""){ echo " selected";}?>>500+ Stone Broken (Recommended)</option>
                            <option value="200"<?php if($stone_threshold=="200"){ echo " selected";}?>>200+ Stone Broken</option>
                            <option value="100"<?php if($stone_threshold=="100"){ echo " selected";}?>>100+ Stone Broken (Least Accurate)</option>
                            <option value="0"<?php if($stone_threshold=="0"&&$stone_threshold!=""){ echo " selected";}?>>Show All</option>
                        </select>
                        <input type="submit" name="top_go" id="top_go" value="Go" />
                      </em></strong></td>
                    </tr>
                    <tr>
                      <td><strong>Number Of Results</strong></td>
                      <td><select name="limit_results" id="limit_results">
                        <option value="10"<?php if($limit_results=="10"){ echo " selected";}?>>10 Users</option>
                        <option value="25"<?php if($limit_results=="25"||$limit_results==""){ echo " selected";}?>>25 Users</option>
                        <option value="50"<?php if($limit_results=="50"){ echo " selected";}?>>50 Users</option>
                        <option value="75"<?php if($limit_results=="75"){ echo " selected";}?>>75 Users</option>
                        <option value="100"<?php if($limit_results=="100"){ echo " selected";}?>>100 Users</option>
                        <option value="250"<?php if($limit_results=="250"){ echo " selected";}?>>250 Users</option>
                        <option value="500"<?php if($limit_results=="500"){ echo " selected";}?>>500 Users</option>
                        <option value="-1"<?php if($limit_results=="-1"){ echo " selected";}?>>All Users</option>
                      </select>
                        <input type="submit" name="top_go" id="top_go" value="Go" /></td>
                    </tr>
                    <?php 
					// Feature currently hidden until future version
					/*
                    <tr>
                      <td><s><strong>Hide Banned Users</strong></s></td>
                      <td><input name="hide_banned" type="checkbox" id="hide_banned" value="1" />
                        <input type="submit" name="top_go" id="top_go" value="Go" /></td>
                    </tr>*/
					?>
                </table>-->
                  <table width="100%" border="0">
                    <tr>
                      <td valign="middle"><strong>Update Stats
                        <input name="refresh_stats_records" type="hidden" id="refresh_stats_records" value="NULL" />
                      </strong></td>
                      <td>
	                      	<table width="100%">
	                      		<tr>
	                      			<td width="30%">
										<div id="refresh_stats_button" class="<?php if(isset($_POST['refresh_stats_records'])){ ?>ui-state-highlight<?php } else { ?>ui-state-default<?php } ?>">
				                      		<span class="text" id="refresh_stats_text"><?php if(isset($_POST['refresh_stats_records'])){ echo $_POST['refresh_stats_records']; ?> Users Updated<?php } else { ?>Refresh Stats<?php } ?></span>
				            	        </div>
	                      			</td>
	                      			<td>
	                      				<div id="refresh_stats_progressbar" width="100%"></div>
	                      			</td>
	                      		</tr>
	                      	</table>
                      	</td>
                    </tr>
                    <tr>
                      <td valign="middle"><strong>Block Type
                        <input name="form" type="hidden" id="form" value="form_Get_Ratios_ByWorldID" />
                        <input name="command" type="hidden" id="command" value="xtoplist" />
                      </strong></td>
                      <td>
                        <div id="sort_by_radio"> <br />
                          <input name="block_type" type="radio" id="block_type_radio1" value="56" <?php if($block_type=="56"){?> checked="checked"<?php }?> />
                          <label for="block_type_radio1">Diamonds</label>
                          <input name="block_type" type="radio" id="block_type_radio2" value="25" <?php if($block_type=="25"){?> checked="checked"<?php }?> />
                          <label for="block_type_radio2">Lapis</label>
                          <input name="block_type" type="radio" id="block_type_radio3" value="14" <?php if($block_type=="14"){?> checked="checked"<?php }?> />
                          <label for="block_type_radio3">Gold</label>
                          <input name="block_type" type="radio" id="block_type_radio4" value="48" <?php if($block_type=="48"){?> checked="checked"<?php }?> />
                          <label for="block_type_radio4">Mossy</label>
                          <input name="block_type" type="radio" id="block_type_radio5" value="15" <?php if($block_type=="15"){?> checked="checked"<?php }?> />
                          <label for="block_type_radio5">Iron</label>
                      </div>
                      </td>
                    </tr>
                    <tr>
                      <td valign="middle"><strong>World</strong></td>
                      <td>
                      <div id="worldid_radio">
                        <?php 
						$radio_index = 1;
						foreach($GLOBALS['worlds'] as $world_key => $world_item ){ ?>
                          <input name="worldid" type="radio" id="worldid_radio<?php echo $radio_index;?>" value="<?php echo $world_item["worldid"]; ?>" <?php if($world_id==$world_item["worldid"]){?> checked="checked"<?php }?> />
                          <label for="worldid_radio<?php echo $radio_index;?>"><?php echo $world_item["worldalias"]; ?></label>
                        <?php $radio_index++;
						} ?>
                      </div></td>
                    </tr>
                    <tr>
                      <td valign="middle"><strong>Stone Threshold</strong></td>
                      <td>
                        <label for="amount" style="font-size: small; font-weight:bold; ">Stone Broken:</label>
                        <input type="text" id="amount" style="border:0; color:#FFFFFF; background-color: transparent; font-weight:bold;" />
                        <input type="hidden" name="stone_threshold" id="stone_threshold" value="1000"/>
                        <div id="stone_threshold_slider"></div>
                        <table width="100%" border="0">
                          <tr>
                            <td align="left"><span style="font-size: small; font-weight:bold; ">Less Accurate</span></td>
                            <td align="right"><span style="font-size: small; font-weight:bold; ">More Accurate</span></td>
                          </tr>
                        </table>

                      </td>
                    </tr>
                    <tr>
                      <td valign="middle"><strong>Number Of Results</strong></td>
                      <td>
	                    <div id="limit_results_radio"> <br />
                          <input name="limit_results" type="radio" id="limit_results_radio1" value="10" <?php if($limit_results=="10"){?> checked="checked"<?php }?> />
                          <label for="limit_results_radio1">10</label>
                          <input name="limit_results" type="radio" id="limit_results_radio2" value="25" <?php if($limit_results=="25"){?> checked="checked"<?php }?> />
                          <label for="limit_results_radio2">25</label>
                          <input name="limit_results" type="radio" id="limit_results_radio3" value="50" <?php if($limit_results=="50"){?> checked="checked"<?php }?> />
                          <label for="limit_results_radio3">50</label>
                          <input name="limit_results" type="radio" id="limit_results_radio4" value="75" <?php if($limit_results=="75"){?> checked="checked"<?php }?> />
                          <label for="limit_results_radio4">75</label>
                          <input name="limit_results" type="radio" id="limit_results_radio5" value="100" <?php if($limit_results=="100"){?> checked="checked"<?php }?> />
                          <label for="limit_results_radio5">100</label>
                          <input name="limit_results" type="radio" id="limit_results_radio6" value="250" <?php if($limit_results=="250"){?> checked="checked"<?php }?> />
                          <label for="limit_results_radio6">250</label>
                          <input name="limit_results" type="radio" id="limit_results_radio7" value="500" <?php if($limit_results=="500"){?> checked="checked"<?php }?> />
                          <label for="limit_results_radio7">500</label>
                          <input name="limit_results" type="radio" id="limit_results_radio8" value="-1" <?php if($limit_results=="-1"){?> checked="checked"<?php }?> />
                          <label for="limit_results_radio8">All Users</label>
                      </div></td>
                    </tr>
                    <?php 
					// Feature currently hidden until future version
					/*
                    <tr>
                      <td><s><strong>Hide Banned Users</strong></s></td>
                      <td><input name="hide_banned" type="checkbox" id="hide_banned" value="1" />
                        <input type="submit" name="top_go" id="top_go" value="Go" /></td>
                    </tr>*/
					?>
                </table></td>
              </tr>
              <tr>
                <td>
                <?php 
				//echo "TOP_ARRAY: "; print_r($TopArray); echo "<br>";
				if(count($TopArray)>0){ 
				?>
                  <table width="100%" border="0" class="bg_black">
                  <tr class="bg_white">
                    <td class="bg_AAA_x"><strong>Username</strong></td>
                    <td class="bg_AAA_x"><strong>Stones</strong></td>
                    <td class="bg_AAA_x"><strong>Info</strong></td>
                    <td colspan="2" align="center" class="bg_<?php if($sortby_column_name=="diamond_ratio"){echo"I";}else{echo"AAA";}?>_x"><strong>Diamonds</strong></td>
                    <td colspan="2" align="center" class="bg_<?php if($sortby_column_name=="lapis_ratio"){echo"I";}else{echo"AAA";}?>_x"><strong>Lapis</strong></td>
                    <td colspan="2" align="center" class="bg_<?php if($sortby_column_name=="gold_ratio"){echo"I";}else{echo"AAA";}?>_x"><strong>Gold</strong></td>
                    <td colspan="2" align="center" class="bg_<?php if($sortby_column_name=="mossy_ratio"){echo"I";}else{echo"AAA";}?>_x"><strong>Mossy</strong></td>
                    <td colspan="2" align="center" class="bg_<?php if($sortby_column_name=="iron_ratio"){echo"I";}else{echo"AAA";}?>_x"><strong>Iron</strong></td>
                    </tr>
                  <?php foreach($TopArray as $key => $top)
				  		{
							foreach($colorbins as $column_name => $bins)
							{
								$tempcolor = 10;
								$color[$column_name] = -3;
								while($top[$column_name] < $colorbins[$column_name][$tempcolor] && $tempcolor > 0)
								{
									//echo "<br>$sortby_column_name >> " . $colorbins[$sortby_column_name][$tempcolor] . " [" . ($tempcolor) . "]";
									$tempcolor--;	
								}
								//echo "<< <BR>";
								$color[$column_name] = $tempcolor;
							}
							$top["firstlogin"] = date_create_from_format("Y-m-d H:i:s", $top["firstlogin"]);
?>
                  <tr class="bg_I_<?php echo $color[$sortby_column_name];?>">
<!--                    <td nowrap="nowrap" class="bg_I_<?php echo $color[$sortby_column_name];?>"><strong><?php echo $top["playername"]; ?></strong></td> -->
                <td nowrap="nowrap" class="bg_I_<?php echo $color[$sortby_column_name];?>"><a href="xray.php?command=xsingle&amp;player=<?php echo $top["playername"]; ?>"><strong><?php echo $top["playername"]; ?></strong></a></td>
                    <td nowrap="nowrap" class="bg_I_<?php echo $color[$sortby_column_name];?>"><strong><?php echo $top["stone_count"]; ?></strong></td>
                    <td nowrap="nowrap"><span class="bg_I_<?php echo $color[$sortby_column_name];?>&gt;&lt;strong&gt;&lt;a href=">
                      <?php if($top["firstlogin"] > $datetime_week_ago){ ?>
                      <img src="img/green.png" width="15" height="15" alt="New User" />
                      <?php } else { /*echo $top["firstlogin"];*/ } ?>
                    </span></td>
                    <td nowrap="nowrap" class="bg_<?php if($sortby_column_name=="diamond_ratio"){echo"E";}else{echo"I";}?>_<?php echo $color["diamond_ratio"];?>"><?php if($sortby_column_name=="diamond"){echo"<strong>";}?><?php echo $top["diamond_count"]; ?><?php if($sortby_column_name=="diamond"){echo"</strong>";}?></td>
                    <td nowrap="nowrap" class="bg_<?php if($sortby_column_name=="diamond_ratio"){echo"E";}else{echo"I";}?>_<?php echo $color["diamond_ratio"];?>"><?php if($sortby_column_name=="diamond"){echo"<strong>";}?><?php echo number_format($top["diamond_ratio"], 2); ?> %<?php if($sortby_column_name=="diamond"){echo"</strong>";}?></td>
                    <td nowrap="nowrap" class="bg_<?php if($sortby_column_name=="lapis_ratio"){echo"E";}else{echo"I";}?>_<?php echo $color["lapis_ratio"];?>"><?php if($sortby_column_name=="lapis"){echo"<strong>";}?><?php echo $top["lapis_count"]; ?><?php if($sortby_column_name=="lapis"){echo"</strong>";}?></td>
                    <td nowrap="nowrap" class="bg_<?php if($sortby_column_name=="lapis_ratio"){echo"E";}else{echo"I";}?>_<?php echo $color["lapis_ratio"];?>"><?php if($sortby_column_name=="lapis"){echo"<strong>";}?><?php echo number_format($top["lapis_ratio"], 2); ?> %<?php if($sortby_column_name=="lapis"){echo"</strong>";}?></td>
                    <td nowrap="nowrap" class="bg_<?php if($sortby_column_name=="gold_ratio"){echo"E";}else{echo"I";}?>_<?php echo $color["gold_ratio"];?>"><?php if($sortby_column_name=="gold"){echo"<strong>";}?><?php echo $top["gold_count"]; ?><?php if($sortby_column_name=="gold"){echo"</strong>";}?></td>
                    <td nowrap="nowrap" class="bg_<?php if($sortby_column_name=="gold_ratio"){echo"E";}else{echo"I";}?>_<?php echo $color["gold_ratio"];?>"><?php if($sortby_column_name=="gold"){echo"<strong>";}?><?php echo number_format($top["gold_ratio"], 2); ?> %<?php if($sortby_column_name=="gold"){echo"</strong>";}?></td>
                    <td nowrap="nowrap" class="bg_<?php if($sortby_column_name=="mossy_ratio"){echo"E";}else{echo"I";}?>_<?php echo $color["mossy_ratio"];?>"><?php if($sortby_column_name=="mossy"){echo"<strong>";}?><?php echo $top["mossy_count"]; ?><?php if($sortby_column_name=="mossy"){echo"</strong>";}?></td>
                    <td nowrap="nowrap" class="bg_<?php if($sortby_column_name=="mossy_ratio"){echo"E";}else{echo"I";}?>_<?php echo $color["mossy_ratio"];?>"><?php if($sortby_column_name=="mossy"){echo"<strong>";}?><?php echo number_format($top["mossy_ratio"], 2); ?> %<?php if($sortby_column_name=="mossy"){echo"</strong>";}?></td>
                    <td nowrap="nowrap" class="bg_<?php if($sortby_column_name=="iron_ratio"){echo"E";}else{echo"I";}?>_<?php echo $color["iron_ratio"];?>"><?php if($sortby_column_name=="iron"){echo"<strong>";}?><?php echo $top["iron_count"]; ?><?php if($sortby_column_name=="iron"){echo"</strong>";}?></td>
                    <td nowrap="nowrap" class="bg_<?php if($sortby_column_name=="iron_ratio"){echo"E";}else{echo"I";}?>_<?php echo $color["iron_ratio"];?>"><?php if($sortby_column_name=="iron"){echo"<strong>";}?><?php echo number_format($top["iron_ratio"], 2); ?> %<?php if($sortby_column_name=="iron"){echo"</strong>";}?></td>
                    </tr>
                  <?php if(!(($key+1) % 25) ){ ?>
                  <tr class="bg_white">
                    <td class="bg_AAA_x"><strong>Username</strong></td>
                    <td class="bg_AAA_x"><strong>Stones</strong></td>
                    <td class="bg_AAA_x"><strong>Info</strong></td>
                    <td colspan="2" align="center" class="bg_<?php if($sortby_column_name=="diamond"){echo"I";}else{echo"AAA";}?>_x"><strong>Diamonds</strong></td>
                    <td colspan="2" align="center" class="bg_<?php if($sortby_column_name=="lapis"){echo"I";}else{echo"AAA";}?>_x"><strong>Lapis</strong></td>
                    <td colspan="2" align="center" class="bg_<?php if($sortby_column_name=="gold"){echo"I";}else{echo"AAA";}?>_x"><strong>Gold</strong></td>
                    <td colspan="2" align="center" class="bg_<?php if($sortby_column_name=="mossy"){echo"I";}else{echo"AAA";}?>_x"><strong>Mossy</strong></td>
                    <td colspan="2" align="center" class="bg_<?php if($sortby_column_name=="iron"){echo"I";}else{echo"AAA";}?>_x"><strong>Iron</strong></td>
                    </tr>
                  <?php } }
				  if( (($key+1) % 25) ){ ?>
                  <tr class="bg_white">
                    <td class="bg_AAA_x"><strong>Username</strong></td>
                    <td class="bg_AAA_x"><strong>Stones</strong></td>
                    <td class="bg_AAA_x"><strong>Info</strong></td>
                    <td colspan="2" align="center" class="bg_<?php if($sortby_column_name=="diamond"){echo"I";}else{echo"AAA";}?>_x"><strong>Diamonds</strong></td>
                    <td colspan="2" align="center" class="bg_<?php if($sortby_column_name=="lapis"){echo"I";}else{echo"AAA";}?>_x"><strong>Lapis</strong></td>
                    <td colspan="2" align="center" class="bg_<?php if($sortby_column_name=="gold"){echo"I";}else{echo"AAA";}?>_x"><strong>Gold</strong></td>
                    <td colspan="2" align="center" class="bg_<?php if($sortby_column_name=="mossy"){echo"I";}else{echo"AAA";}?>_x"><strong>Mossy</strong></td>
                    <td colspan="2" align="center" class="bg_<?php if($sortby_column_name=="iron"){echo"I";}else{echo"AAA";}?>_x"><strong>Iron</strong></td>
                    </tr>
                  <?php } ?>
                </table>
				<?php } // TopArray is not empty ?></td>
              </tr>
              <tr>
                <td>&nbsp;</td>
              </tr>
            </table>
          </form>
          <?php } ?></td>
      </tr>
      <tr>
        <td><?php if($command=="xsingle" || $command=="xglobal"){ ?>
          <table width="100%" border="0" class="borderblack_greybg_norm_thick ui-corner-all">
            <tr>
            <td><table width="100%" border="0" class="borderblack_greybg_dark_thick ui-corner-all">
              <tr>
                <td><h1>Basic Player Stats: <font color="#FF0000"><?php echo $player_name; ?></font></h1></td>
              </tr>
            </table></td>
          </tr>
          <tr>
            <td><form action="xray.php" method="post" name="useraction_form" target="_self" id="useraction_form">
              <table width="100%" border="0">
                <tr>
                  <td valign="top"><table width="100%" border="0" class="borderblack_greybg_light_thick ui-corner-all">
                    <tr>
                      <td><table width="100%" border="0" class="borderblack_greybg_dark_thick ui-corner-all">
                        <tr>
                          <td><strong>User Status </strong></td>
                          </tr>
                        </table></td>
                      </tr>
                    <tr>
                      <td><table width="100%" border="0" class="borderblack_greybg_norm_thick ui-corner-all">
                        <tr>
                          <td><strong>Punishment Status</strong></td>
                          <td><select name="playerstatus" id="playerstatus">
                            <option value="0" selected="selected">Normal</option>
                            <option value="1">Warned</option>
                            <option value="2">Jailed</option>
                            <option value="3">Suspended</option>
                            <option value="4">Banned</option>
                            </select></td>
                          </tr>
                        <tr>
                          <td><strong>Watching</strong></td>
                          <td><label for="watchingplayer"></label>
                            <select name="watchingplayer" id="watchingplayer">
                              <option value="0">Hide User</option>
                              <option value="1" selected="selected">Normal</option>
                              <option value="2">Watching</option>
                            </select></td>
                          </tr>
                        <tr>
                          <td>&nbsp;</td>
                          <td><input type="submit" name="button" id="button" value="Modify" />
                            <input name="form" type="hidden" id="form" value="form_useraction" />
                            <input name="command" type="hidden" id="command" value="xmodifyuser" /></td>
                          </tr>
                        </table></td>
                      </tr>
                  </table></td>
                </tr>
              </table>
            </form></td>
          </tr>
          <tr>
            <td><table width="100%" border="0">
              <tr>
                <td><table width="100%" border="0" class="borderblack_greybg_light_thick ui-corner-all">
                  <tr>
                    <td><table width="100%" border="0" class="borderblack_greybg_dark_thick ui-corner-all">
                      <tr>
                        <td><strong>Summary</strong></td>
                        </tr>
                      </table></td>
                    </tr>
                  <tr>
                    <td><table width="100%" border="0" class="borderblack_greybg_norm_thick ui-corner-all">
                      <tr>
                        <td><table width="100%" border="0">
                          <tr>
                            <td><table width="100%" border="0" class="borderblack_greybg_dark_thin ui-corner-all">
                              <tr>
                                <td><div id="accuracy_gauge" style="width: 180px; height: 180px;"></div></td>
                                <td><table width="100%" border="0">
                                  <?php if(isset($player_info["accuracy"]) )
								  { ?>
                                  <tr>
                                    <td><?php
                                	switch($player_info["accuracy"])
									{
										case "3": ?>
										<div class="ui-widget-content ui-corner-all" style="padding: 10">
											<div style="float:left" class="ui-icon ui-icon-info"></div>
                                            <img src="img/null15.gif" width="15" height="15" />
                                          The information about this player is almost certainly accurate.
										</div>
                                      <?php break;
										case "2": ?>
										<div class="ui-widget-content ui-corner-all" style="padding: 10; float:left">
											<div style="float:left" class="ui-icon ui-icon-info"></div>
                                            <img src="img/null15.gif" width="15" height="15" />
                                            The information about this player is probably accurate.
										</div>
                                      <?php break;
										case "1": ?>
										<div class="ui-state-error ui-corner-all" style="padding: 10">
											<div style="float:left" class="ui-icon ui-icon-alert"></div>
                                            <img src="img/null15.gif" width="15" height="15" />
                                          This user does not have enough mining data to come to any accurate conclusions. Any incriminating evidence may be inaccurate.
										</div>
                                      <?php break;
										case "0": ?>
										<div class="ui-state-error ui-corner-all" style="padding: 10">
											<div style="float:left" class="ui-icon ui-icon-alert"></div>
                                            <img src="img/null15.gif" width="15" height="15" />
                                          This user does not have enough mining data to come to any conclusions.
										</div>
                                      <?php break;
										default: ?>
										<div class="ui-state-error ui-corner-all" style="padding: 10">
											<div style="float:left" class="ui-icon ui-icon-alert"></div>
                                            <img src="img/null15.gif" width="15" height="15" />
                                          ERROR: Accuracy value undefined
										</div>
                                      <?php break;
									}?></td>
                                    </tr>
                                  <?php } ?>
                                  </table></td>
                                </tr>
                              </table></td>
                          </tr>
                          <tr>
                            <td><table width="100%" border="0" class="borderblack_greybg_dark_thin ui-corner-all">
                              <tr>
                                <td>
                                  <div id="suspicion_gauge" style="width: 180px; height: 180px;"></div>
                                  </td>
                                <td><table width="100%" border="0">
                                  <tr>
                                    <td>
                                      <?php if(isset($player_info["traits"]) && count($player_info["traits"])>0){ foreach($player_info["traits"] as $trait_index => $trait_item)
						 			 { ?>
                                      <table width="100%" border="0">
                                        <tr>
                                          <td><?php
                                	switch($trait_item["type"])
									{
										case "disclaimer": ?>
                                            <div class="ui-widget-content ui-corner-all xray-dark" style="padding: 10">
                                              <div style="float:left" class="ui-icon ui-icon-info"></div>
                                              <img src="img/null15.gif" width="15" height="15" />
                                              <strong><?php echo $trait_item["message"];?></strong>
                                              </div>
                                            <?php break;
										case "bad": ?>
                                            <div class="ui-state-error ui-corner-all" style="padding: 10">
                                              <div style="float:left" class="ui-icon ui-icon-info"></div>
                                              <img src="img/null15.gif" width="15" height="15" />
                                              <strong><?php echo $trait_item["message"];?></strong>
                                              </div>
                                            <?php break;
										case "neutral": ?>
                                            <div class="ui-widget-content ui-corner-all" style="padding: 10">
                                              <div style="float:left" class="ui-icon ui-icon-info"></div>
                                              <img src="img/null15.gif" width="15" height="15" />
                                              <strong><?php echo $trait_item["message"];?></strong>
                                              </div>
                                            <?php break;
										case "good": ?>
                                            <div class="ui-state-highlight ui-corner-all" style="padding: 10">
                                              <div style="float:left" class="ui-icon ui-icon-plus"></div>
                                              <img src="img/null15.gif" width="15" height="15" />
                                              <strong><?php echo $trait_item["message"];?></strong>
                                              </div>
                                            <?php break;
										default: ?>
                                            <div class="ui-widget-content ui-corner-all" style="padding: 10">
                                              <div style="float:left" class="ui-icon ui-icon-info"></div>
                                              <img src="img/null15.gif" width="15" height="15" />
                                              <strong><?php echo $trait_item["message"];?></strong>
                                              </div>
                                            <?php break;
									}?></td>
                                          <td></td>
                                          </tr>
                                        <?php } ?>
                                        </table>
                                      <?php } ?>
                                      </td>
                                    </tr>
                                  </table></td>
                                </tr>
                              </table></td>
                          </tr>
                          </table></td>
                      </tr>
                    </table></td>
                    </tr>
                  </table></td>
              </tr>
              </table></td>
          </tr>
          <tr>
            <td><table width="100%" border="0">
              <tr>
                <td><table width="100%" border="0" class="borderblack_greybg_light_thick ui-corner-all">
                  <tr>
                    <td><table width="100%" border="0" class="borderblack_greybg_dark_thick ui-corner-all">
                      <tr>
                        <td><strong>General Info</strong></td>
                      </tr>
                    </table></td>
                  </tr>
                  <tr>
                    <td>
                      <table width="100%" border="0" class="borderblack_greybg_norm_thick ui-corner-all">
                        <tr>
                          <th width="22%" align="right" scope="row">Join Date</th>
                          <td width="78%">&nbsp;</td>
                        </tr>
                        <tr>
                          <th align="right" scope="row">Online Time</th>
                          <td>&nbsp;</td>
                        </tr>
                        <tr>
                          <th align="right" scope="row">&nbsp;</th>
                          <td>&nbsp;</td>
                        </tr>
                      </table></td>
                  </tr>
                </table></td>
              </tr>
            </table></td>
          </tr>
          <tr>
            <td><table width="100%" border="0">
              <tr>
                <td><table width="100%" border="0" class="borderblack_greybg_light_thick ui-corner-all">
                  <tr>
                    <td><table width="100%" border="0" class="borderblack_greybg_dark_thick ui-corner-all">
                      <tr>
                        <td><strong><?php echo $player_name; ?>'s Basic Stats</strong></td>
                        </tr>
                      </table></td>
                    </tr>
                  <tr>
                    <td><table width="100%" border="0" class="bg_black">
                      <tr class="bg_white">
                        <td class="bg_I_x"><strong>World</strong></td>
                        <td class="bg_I_x"><strong>Stones</strong></td>
                        <td colspan="2" align="center" class="bg_AAA_x"><strong>Diamonds</strong></td>
                        <td colspan="2" align="center" class="bg_AAA_x"><strong>Lapis</strong></td>
                        <td colspan="2" align="center" class="bg_AAA_x"><strong>Gold</strong></td>
                        <td colspan="2" align="center" class="bg_AAA_x"><strong>Mossy</strong></td>
                        <td colspan="2" align="center" class="bg_AAA_x"><strong>Iron</strong></td>
                        </tr>
                      <?php foreach($player_world_stats as $pw_index => $pw_item) {?>
                      <tr class="bg_I_<?php echo $color[$sortby_column_name];?>">
                        <td nowrap="nowrap" class="bg_H_<?php echo $pw_item["color_max"];?>"><?php echo $pw_item["worldalias"]; ?></td>
                        <td nowrap="nowrap" class="bg_H_<?php echo $pw_item["color_max"];?>"><?php echo $pw_item["stone_count"];?></td>
                        <td nowrap="nowrap" class="bg_H_<?php echo $pw_item["color_diamond_ratio"];?>"><?php echo $pw_item["diamond_count"];?></td>
                        <td nowrap="nowrap" class="bg_H_<?php echo $pw_item["color_diamond_ratio"];?>"><?php echo $pw_item["diamond_ratio"];?></td>
                        <td nowrap="nowrap" class="bg_H_<?php echo $pw_item["color_lapis_ratio"];?>"><?php echo $pw_item["lapis_count"];?></td>
                        <td nowrap="nowrap" class="bg_H_<?php echo $pw_item["color_lapis_ratio"];?>"><?php echo $pw_item["lapis_ratio"];?></td>
                        <td nowrap="nowrap" class="bg_H_<?php echo $pw_item["color_gold_ratio"];?>"><?php echo $pw_item["gold_count"];?></td>
                        <td nowrap="nowrap" class="bg_H_<?php echo $pw_item["color_gold_ratio"];?>"><?php echo $pw_item["gold_ratio"];?></td>
                        <td nowrap="nowrap" class="bg_H_<?php echo $pw_item["color_mossy_ratio"];?>"><?php echo $pw_item["mossy_count"];?></td>
                        <td nowrap="nowrap" class="bg_H_<?php echo $pw_item["color_mossy_ratio"];?>"><?php echo $pw_item["mossy_ratio"];?></td>
                        <td nowrap="nowrap" class="bg_H_<?php echo $pw_item["color_iron_ratio"];?>"><?php echo $pw_item["iron_count"];?></td>
                        <td nowrap="nowrap" class="bg_H_<?php echo $pw_item["color_iron_ratio"];?>"><?php echo $pw_item["iron_ratio"];?></td>
                        </tr>
                      <?php } ?>
                      <tr class="bg_white">
                        <td class="bg_I_x"><strong>World</strong></td>
                        <td class="bg_I_x"><strong>Stones</strong></td>
                        <td colspan="2" align="center" class="bg_AAA_x"><strong>Diamonds</strong></td>
                        <td colspan="2" align="center" class="bg_AAA_x"><strong>Lapis</strong></td>
                        <td colspan="2" align="center" class="bg_AAA_x"><strong>Gold</strong></td>
                        <td colspan="2" align="center" class="bg_AAA_x"><strong>Mossy</strong></td>
                        <td colspan="2" align="center" class="bg_AAA_x"><strong>Iron</strong></td>
                        </tr>
                      </table></td>
                    </tr>
                  </table></td>
              </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td><table width="100%" border="0" class="borderblack_greybg_light_thick ui-corner-all">
              <tr>
                <td><table width="100%" border="0" class="borderblack_greybg_dark_thick ui-corner-all">
                  <tr>
                    <td><strong><?php echo $player_name; ?>'s Advanced Stats</strong></td>
                  </tr>
                </table></td>
              </tr>
              <tr>
                <td><table width="100%" border="0" class="borderblack_greybg_norm_thick ui-corner-all">
                  <tr>
                    <td><table width="100%" border="0">
                      <tr>
                        <td align="center">&nbsp;</td>
                      </tr>
                      <tr>
                        <td align="center" class="bg_H_-3">
                        <?php if(count($player_clusters_world) == 0){ ?>
                          <p>You have not yet analyzed this players mining behavior. Would you like to do that now?</p>
                          <p>&nbsp;</p>
                          <form action="xray.php" method="post" name="form_startanalysis" target="_self" id="form_startanalysis">
                            <input name="form" type="hidden" id="form" value="form_analyze_mines_now" />
                            <input type="submit" name="Submit" id="Submit" value="Analyze Mining Behavior" />
                            <input name="command" type="hidden" id="command" value="xanalyze" />
                            <input name="player" type="hidden" id="player" value="<?php echo $player_name;?>" />
                          </form>
                          </p>
						  <?php } ?></td>
                      </tr>
                      <tr>
                        <td align="center">&nbsp;</td>
                      </tr>
                    </table>
                      <?php foreach($GLOBALS['worlds'] as $world_index => $world_item)
					  { 
					  	if(count( $player_clusters_world[$world_index]) > 0)
						{ ?>
                      <table width="100%" border="0">
                      <tr>
                        <td>&nbsp;</td>
                      </tr>
                      <tr>
                        <td><table width="100%" border="0" class="bg_black">
                          <tr class="bg_white">
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>Slope Before</strong></td>
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>Spread Before</strong></td>
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>Ores</strong></td>
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>Slope After</strong></td>
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>Spread After</strong></td>
                          </tr>
                          <?php 
				  		foreach($player_clusters_world[$world_index] as $cluster_index => $cluster_item)
				  		{
						?>
                          <tr class="bg_I_0">
                            <td nowrap="nowrap" class="bg_H_<?php echo (!isset($cluster_item["slope_before"]) ) ? "-3" : $cluster_item["color_slope_before"];?>"><strong><?php echo $cluster_item["slope_before"]; ?></strong></td>
                            <td nowrap="nowrap" class="bg_H_<?php echo (!isset($cluster_item["spread_before"]) ) ? "-3" : $cluster_item["color_spread_before"];?>"><strong><?php echo $cluster_item["spread_before"]; ?></strong></td>
                            <td nowrap="nowrap"><strong><?php echo $cluster_item["ore_length"]; ?></strong></td>
                            <td nowrap="nowrap" class="bg_H_<?php echo (!isset($cluster_item["slope_after"]) ) ? "-3" : "0"; ?>"><strong><?php echo $cluster_item["slope_after"]; ?></strong></td>
                            <td nowrap="nowrap" class="bg_H_<?php echo (!isset($cluster_item["spread_after"]) ) ? "-3" : "0"; ?>"><strong><?php echo $cluster_item["spread_after"]; ?></strong></td>
                          </tr>
                          <?php if(!(($cluster_index+1) % 25) ){ ?>
                          <tr class="bg_white">
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>Slope Before</strong></td>
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>Spread Before</strong></td>
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>Ores</strong></td>
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>Slope After</strong></td>
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>Spread After</strong></td>
                          </tr>
                          <?php } }
				  if( (($cluster_index+1) % 25) ){ ?>
                          <tr class="bg_white">
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>Slope Before</strong></td>
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>Spread Before</strong></td>
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>Ores</strong></td>
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>Slope After</strong></td>
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>Spread After</strong></td>
                          </tr>
                          <?php } ?>
                        </table></td>
                      </tr>
                      <?php /*<!--<tr>
                        <td><table width="100%" border="0" class="bg_black">
                          <tr class="bg_white">
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>Date</strong></td>
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>Volume</strong></td>
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>First Block Ore?</strong></td>
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>PostBreaks</strong></td>
                            </tr>
	                  <?php 
				  		foreach($player_mines_all as $mine_index => $mine_item)
				  		{
							foreach($colorbins as $column_name => $bins)
							{
								$tempcolor = 10;
								$color[$column_name] = -3;
								while($mine_item[$column_name . "_ratio"] < $colorbins[$column_name][$tempcolor] && $tempcolor > 0)
								{
									//echo "<br>$sortby_column_name >> " . $colorbins[$sortby_column_name][$tempcolor] . " [" . ($tempcolor) . "]";
									$tempcolor--;	
								}
								//echo "<< <BR>";
								$color[$column_name] = $tempcolor;
							}
						?>
                          <tr class="bg_I_<?php echo $color[$sortby_column_name];?>">
                            <td nowrap="nowrap"><strong><?php echo $mine_item["volume"]; ?></strong></td>
                            <td nowrap="nowrap"><strong><?php echo $mine_item["volume"]; ?></strong></td>
                            <td nowrap="nowrap"><strong><?php echo FixOutput_Bool($mine_item["first_block_ore"],"Yes","No","?"); ?></strong></td>
                            <td nowrap="nowrap"><strong><?php echo $mine_item["volume"]; ?></strong></td>
                            </tr>
                          <?php if(!(($mine_index+1) % 25) ){ ?>
                          <tr class="bg_white">
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>Date</strong></td>
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>Volume</strong></td>
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>First Block Ore?</strong></td>
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>PostBreaks</strong></td>
                          </tr>
                          <?php } }
				  if( (($mine_index+1) % 25) ){ ?>
                          <tr class="bg_white">
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>Date</strong></td>
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>Volume</strong></td>
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>First Block Ore?</strong></td>
                            <td nowrap="nowrap" class="bg_AAA_x"><strong>PostBreaks</strong></td>
                          </tr>
                          <?php } ?>
                        </table></td>
                      </tr>-->*/ ?>
                    </table>
                    <?php } } ?></td>
                  </tr>
                </table></td>
              </tr>
            </table></td>
          </tr>
          </table>
          <?php } ?></td>
      </tr>
      <tr>
        <td>&nbsp;</td>
      </tr>
<tr>
  <td><?php if($command=="xsingle" || $command=="xglobal"){ ?>
    <table width="100%" border="0" class="borderblack_greybg_norm_thick ui-corner-all">
      <tr>
        <td><table width="100%" border="0" class="borderblack_greybg_dark_thick ui-corner-all">
          <tr>
            <td><h1>Global Averages</h1></td>
            </tr>
          </table></td>
        </tr>
      <tr>
        <td><table width="100%" border="0">
          <tr>
            <td class="bg_black">&nbsp;</td>
            <td width="80" align="center" class="bg_I_0"><strong>0</strong></td>
            <td width="80" align="center" class="bg_I_1"><strong>1</strong></td>
            <td width="80" align="center" class="bg_I_2"><strong>2</strong></td>
            <td width="80" align="center" class="bg_I_3"><strong>3</strong></td>
            <td width="80" align="center" class="bg_I_4"><strong>4</strong></td>
            <td width="80" align="center" class="bg_I_5"><strong>5</strong></td>
            <td width="80" align="center" class="bg_I_6"><strong>6</strong></td>
            <td width="80" align="center" class="bg_I_7"><strong>7</strong></td>
            <td width="80" align="center" class="bg_I_8"><strong>8</strong></td>
            <td width="80" align="center" class="bg_I_9"><strong>9</strong></td>
            <td width="80" align="center" class="bg_I_10"><strong>10</strong></td>
            </tr>
          <tr>
            <td class="bg_black"><strong>Diamonds</strong></td>
            <?php
$sortby_column_name = "diamond_ratio";
for ($col = 0; $col <= 10 ; $col++)
{ ?>
            <td class="bg_G_<?php echo $col;?><?php if($col == $color[$sortby_column_name]){ echo " border_black_thick"; }?>"><?php echo  number_format($colorbins[$sortby_column_name][$col], 2); ?></td>
            <?php
} ?>
            </tr>
          <tr>
            <td class="bg_black"><strong>Lapis</strong></td>
            <?php
$sortby_column_name = "lapis_ratio";
for ($col = 0; $col <= 10 ; $col++)
{ ?>
            <td class="bg_G_<?php echo $col;?><?php if($col == $color[$sortby_column_name]){ echo " border_black_thick"; }?>"><?php echo  number_format($colorbins[$sortby_column_name][$col], 2); ?></td>
            <?php
} ?>
            </tr>
          <tr>
            <td class="bg_black"><strong>Gold</strong></td>
            <?php
$sortby_column_name = "gold_ratio";
for ($col = 0; $col <= 10 ; $col++)
{ ?>
            <td class="bg_G_<?php echo $col;?><?php if($col == $color[$sortby_column_name]){ echo " border_black_thick"; }?>"><?php echo  number_format($colorbins[$sortby_column_name][$col], 2); ?></td>
            <?php
} ?>
            </tr>
          <tr>
            <td class="bg_black"><strong>Mossy</strong></td>
            <?php
$sortby_column_name = "mossy_ratio";
for ($col = 0; $col <= 10 ; $col++)
{ ?>
            <td class="bg_G_<?php echo $col;?><?php if($col == $color[$sortby_column_name]){ echo " border_black_thick"; }?>"><?php echo  number_format($colorbins[$sortby_column_name][$col], 2); ?></td>
            <?php
} ?>
            </tr>
          <tr>
            <td class="bg_black"><strong>Iron</strong></td>
            <?php
$sortby_column_name = "iron_ratio";
for ($col = 0; $col <= 10 ; $col++)
{ ?>
            <td class="bg_G_<?php echo $col;?><?php if($col == $color[$sortby_column_name]){ echo " border_black_thick"; }?>"><?php echo  number_format($colorbins[$sortby_column_name][$col], 2); ?></td>
            <?php
} ?>
            </tr>
          </table></td>
        </tr>
      </table>
    <?php } ?></td>
</tr>
</table>
<br />
  <?php } ?>
</body>
