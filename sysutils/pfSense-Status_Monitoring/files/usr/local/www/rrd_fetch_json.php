<?php
/*
	rrd_fetch_json.php

	part of pfSense (https://www.pfsense.org)
	Copyright (c) 2005 Bill Marquette
	Copyright (c) 2006 Peter Allgeyer
	Copyright (c) 2008-2016 Electric Sheep Fencing, LLC. All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in
	   the documentation and/or other materials provided with the
	   distribution.

	3. All advertising materials mentioning features or use of this software
	   must display the following acknowledgment:
	   "This product includes software developed by the pfSense Project
	   for use in the pfSense® software distribution. (http://www.pfsense.org/).

	4. The names "pfSense" and "pfSense Project" must not be used to
	   endorse or promote products derived from this software without
	   prior written permission. For written permission, please contact
	   coreteam@pfsense.org.

	5. Products derived from this software may not be called "pfSense"
	   nor may "pfSense" appear in their names without prior written
	   permission of the Electric Sheep Fencing, LLC.

	6. Redistributions of any form whatsoever must retain the following
	   acknowledgment:

	"This product includes software developed by the pfSense Project
	for use in the pfSense software distribution (http://www.pfsense.org/).

	THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
	EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
	PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
	ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
	SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
	OF THE POSSIBILITY OF SUCH DAMAGE.
*/

header('Content-Type: application/json');

$rrd_location = "/var/db/rrd/";

//TODO security checks
$left = $_POST['left'];
$right = $_POST['right'];
$start = $_POST['start'];
$end = $_POST['end'];
$timePeriod = $_POST['timePeriod'];
$invert_graph = ($_POST['invert'] === 'true');

//Figure out the type of information stored in RRD database
$left_pieces = explode("-", $left);
$right_pieces = explode("-", $right);

//Build RRD options bases on settings
$rrd_options = array( 'AVERAGE', '-r', '900' );

if ($start > 0) {
	array_push($rrd_options, '-s', '1445816765047', '-e', '1456184765047');
} else {
	array_push($rrd_options, '-s', $timePeriod);
}

//Initialze
$left_unit_acronym = $right_unit_acronym = "";

//Set units based on RRD database name
$graph_unit_lookup = array(
	"traffic"   => "b/s",
	"packets"   => "pps",
	"states"    => "cps",
	"quality"   => "ms",
	"processor" => "%",
	"memory"    => "%"
);

$left_unit_acronym = $graph_unit_lookup[$left_pieces[1]];
$right_unit_acronym = $graph_unit_lookup[$right_pieces[1]];

//Overrides units based on line name
$line_unit_lookup = array(
	"Packet Loss" => "%",
	"Processes"   => ""
);

//lookup table for acronym to full description
$unit_desc_lookup = array(
	"b/s" => "Bits Per Second",
	"pps" => "Packets Per Second",
	"cps" => "Changes Per Second",
	"ms"  => "Milliseconds",
	"%"   => "Percent",
	""    => ""
);

//TODO make this a function for left and right
if ($left != "null") {
	//$rrd_info_array = rrd_info($rrd_location . $left . ".rrd");
	//$left_step = $rrd_info_array['step'];
	//$left_last_updated = $rrd_info_array['last_update'];

	$rrd_array = rrd_fetch($rrd_location . $left . ".rrd", $rrd_options);

	if (!($rrd_array)) {
		die ('{ "error" : "There was an error loading the Left Y Axis." }');
	}

	$ds_list = array_keys ($rrd_array['data']);
	$ignored_left = 0;

	foreach ($ds_list as $ds_key_left => $ds) {

		$ds_key = $ds_key_left - $ignored_left;
		$data_list = $rrd_array['data'][$ds];
		$ignore = $invert = $ninetyfifth = false;
		$graph_type = "line";

		//Overrides based on line name
		switch($ds) {
		case "user":
			$ds = "user util.";
			break;
		case "nice":
			$ds = "nice util.";
			break;
		case "system":
			$ds = "system util.";
			break;
		case "Packet Loss":
			$left_unit_acronym = "%";
			break;
		case "processes":
			$left_unit_acronym = "";
			break;
		case "pfstates":
			$left_unit_acronym = "";
			$ds = "Filter States";
			break;
		case "srcip":
			$left_unit_acronym = "";
			$ds = "Source Addr.";
			break;
		case "dstip":
			$left_unit_acronym = "";
			$ds = "Dest. Addr.";
			break;
		case "pfrate":
			$ds = "State Changes";
			break;
		case "pfnat":
			$ignored_left++;
			$ignore = true;
			break;
		case "inpass":
			//$graph_type = "area"; //TODO figure out why it breaks NVD3 legend/colors
			$ninetyfifth = true;
			break;
		case "inpass6":
			//$graph_type = "area"; //TODO figure out why it breaks NVD3 legend/colors
			$ninetyfifth = true;
			break;
		case "outpass":
			//$graph_type = "area"; //TODO figure out why it breaks NVD3 legend/colors
			$invert = $invert_graph;
			$ninetyfifth = true;
			break;
		case "outpass6":
			//$graph_type = "area"; //TODO figure out why it breaks NVD3 legend/colors
			$invert = $invert_graph;
			$ninetyfifth = true;
			break;
		}

		if (!$ignore) {
			$obj[$ds_key_left]['key'] = $ds;
			$obj[$ds_key_left]['type'] = $graph_type;
			$obj[$ds_key_left]['format'] = "s";
			$obj[$ds_key_left]['yAxis'] = 1;
			$obj[$ds_key_left]['unit_acronym'] = $left_unit_acronym;
			$obj[$ds_key_left]['unit_desc'] = $unit_desc_lookup[$left_unit_acronym];
			$obj[$ds_key_left]['invert'] = $invert;
			$obj[$ds_key_left]['ninetyfifth'] = $ninetyfifth;

			$data = array();

			foreach ($data_list as $time => $value) {
				$data[] = array($time*1000, $value);
			}

			$obj[$ds_key_left]['values'] = $data;
		}
	}

	if ( ($left_pieces[1] === "traffic") || ($left_pieces[1] === "packets") ) {
		//TODO add inpass and outpass and make two new "total" lines

		//loop through array
		foreach ($obj as $key => $value) {
			//grab inpass and outpass attributes and values
			if ($value['key'] === "inpass") {
				$inpass_array = [];

				//loop through values and use time
				foreach ($value['values'] as $datapoint) {
					$y_point = $datapoint[1];
					if (is_nan($y_point)) { $y_point = 0; }
					$inpass_array[$datapoint[0]] = $y_point;
				}
			}

			if ($value['key'] === "inpass6") {
				$inpass6_array = [];

				//loop through values and use time
				foreach ($value['values'] as $datapoint6) {
					$y_point = $datapoint6[1];
					if (is_nan($y_point)) { $y_point = 0; }
					$inpass6_array[$datapoint6[0]] = $y_point;
				}
			}

			if ($value['key'] === "outpass") {
				$outpass_array = [];

				//loop through values and use time
				foreach ($value['values'] as $datapoint) {
					$y_point = $datapoint[1];
					if (is_nan($y_point)) { $y_point = 0; }
					$outpass_array[$datapoint[0]] = $y_point;
				}
			}

			if ($value['key'] === "outpass6") {
				$outpass6_array = [];

				//loop through values and use time
				foreach ($value['values'] as $datapoint6) {
					$y_point = $datapoint6[1];
					if (is_nan($y_point)) { $y_point = 0; }
					$outpass6_array[$datapoint6[0]] = $y_point;
				}
			}

		}

		//totals
		$inpass_total = [];
		foreach ($inpass_array as $key => $value) {
			$inpass_total[] = array($key, $value + $inpass6_array[$key]);
		}

		$outpass_total = [];
		foreach ($outpass_array as $key => $value) {
			$outpass_total[] = array($key, $value + $outpass6_array[$key]);
		}

		//add to array as total
		$obj[$ds_key_left]['key'] = "inpass total";
		$obj[$ds_key_left]['type'] = "line";
		$obj[$ds_key_left]['format'] = "s";
		$obj[$ds_key_left]['yAxis'] = 1;
		$obj[$ds_key_left]['unit_acronym'] = $left_unit_acronym;
		$obj[$ds_key_left]['unit_desc'] = $unit_desc_lookup[$left_unit_acronym];
		$obj[$ds_key_left]['invert'] = false;
		$obj[$ds_key_left]['ninetyfifth'] = true;
		$obj[$ds_key_left]['values'] = $inpass_total;

		$obj[$ds_key_left+1]['key'] = "outpass total";
		$obj[$ds_key_left+1]['type'] = "line";
		$obj[$ds_key_left+1]['format'] = "s";
		$obj[$ds_key_left+1]['yAxis'] = 1;
		$obj[$ds_key_left+1]['unit_acronym'] = $left_unit_acronym;
		$obj[$ds_key_left+1]['unit_desc'] = $unit_desc_lookup[$left_unit_acronym];
		$obj[$ds_key_left+1]['invert'] = $invert_graph;
		$obj[$ds_key_left+1]['ninetyfifth'] = true;
		$obj[$ds_key_left+1]['values'] = $outpass_total;
	}
}

if ($right != "null") {

	//$rrd_info_array = rrd_info($rrd_location . $right . ".rrd");
	//$right_step = $rrd_info_array['step'];
	//$right_last_updated = $rrd_info_array['last_update'];

	$rrd_array = rrd_fetch($rrd_location . $right . ".rrd", array('AVERAGE', '-r', '900', '-s', $timePeriod ));

	if (!($rrd_array)) {
		die ('{ "error" : "There was an error loading the Right Y Axis." }');
	}

	$ds_list = array_keys ($rrd_array['data']);
	$ignored_right = 0;

	foreach ($ds_list as $ds_key_right => $ds) {
		$last_left_key = 0;

		if ($left != "null") {
			//TODO make sure subtracting ignored_left is correct
			$last_left_key = 1 + $ds_key_left - $ignored_left;
		}

		$ds_key = $last_left_key + $ds_key_right - $ignored_right;
		$data_list = $rrd_array['data'][$ds];
		$ignore = $invert = $ninetyfifth = false;
		$graph_type = "line";

		//Override acronym based on line name
		switch($ds) {
		case "user":
			$ds = "user util.";
			break;
		case "nice":
			$ds = "nice util.";
			break;
		case "system":
			$ds = "system util.";
			break;
		case "Packet Loss":
			$right_unit_acronym = "%";
			break;
		case "processes":
			$right_unit_acronym = "";
			break;
		case "pfstates":
			$right_unit_acronym = "";
			$ds = "Filter States";
			break;
		case "srcip":
			$right_unit_acronym = "";
			$ds = "Source Addr.";
			break;
		case "dstip":
			$right_unit_acronym = "";
			$ds = "Dest. Addr.";
			break;
		case "pfrate":
			$ds = "State Changes";
			break;
		case "pfnat":
			$ignored_right++;
			$ignore = true;
			break;
		case "inpass":
			//$graph_type = "area"; //TODO figure out why it breaks NVD3 legend/colors
			$ninetyfifth = true;
			break;
		case "inpass6":
			//$graph_type = "area"; //TODO figure out why it breaks NVD3 legend/colors
			$ninetyfifth = true;
			break;
		case "outpass":
			//$graph_type = "area"; //TODO figure out why it breaks NVD3 legend/colors
			$invert = $invert_graph;
			$ninetyfifth = true;
			break;
		case "outpass6":
			//$graph_type = "area"; //TODO figure out why it breaks NVD3 legend/colors
			$invert = $invert_graph;
			$ninetyfifth = true;
			break;
		}

		if (!$ignore) {
			$obj[$ds_key]['key'] = $ds;
			$obj[$ds_key]['type'] = $graph_type;
			$obj[$ds_key]['format'] = "s";
			$obj[$ds_key]['yAxis'] = 2;
			$obj[$ds_key]['unit_acronym'] = $right_unit_acronym;
			$obj[$ds_key]['unit_desc'] = $unit_desc_lookup[$right_unit_acronym];
			$obj[$ds_key]['invert'] = $invert;
			$obj[$ds_key]['ninetyfifth'] = $ninetyfifth;

			$data = array();

			foreach ($data_list as $time => $value) {
				$data[] = array($time*1000, $value);
			}

			$obj[$ds_key]['values'] = $data;

		}

	}

	if ( ($right_pieces[1] === "traffic") || ($right_pieces[1] === "packets") ) {
		//TODO add inpass and outpass and make two new "total" lines

		//loop through array
		foreach ($obj as $key => $value) {

			//grab inpass and outpass attributes and values
			if ($value['key'] === "inpass") {
				$inpass_array = [];

				//loop through values and use time
				foreach ($value['values'] as $datapoint) {
					$y_point = $datapoint[1];
					if (is_nan($y_point)) { $y_point = 0; }
					$inpass_array[$datapoint[0]] = $y_point;
				}

			}

			if ($value['key'] === "inpass6") {
				$inpass6_array = [];

				//loop through values and use time
				foreach ($value['values'] as $datapoint6) {
					$y_point = $datapoint6[1];
					if (is_nan($y_point)) { $y_point = 0; }
					$inpass6_array[$datapoint6[0]] = $y_point;
				}
			}

			if ($value['key'] === "outpass") {
				$outpass_array = [];

				//loop through values and use time
				foreach ($value['values'] as $datapoint) {
					$y_point = $datapoint[1];
					if (is_nan($y_point)) { $y_point = 0; }
					$outpass_array[$datapoint[0]] = $y_point;
				}
			}

			if ($value['key'] === "outpass6") {
				$outpass6_array = [];

				//loop through values and use time
				foreach ($value['values'] as $datapoint6) {
					$y_point = $datapoint6[1];
					if (is_nan($y_point)) { $y_point = 0; }
					$outpass6_array[$datapoint6[0]] = $y_point;
				}
			}
		}

		//totals
		$inpass_total = [];
		foreach ($inpass_array as $key => $value) {
			$inpass_total[] = array($key, $value + $inpass6_array[$key]);
		}

		$outpass_total = [];
		foreach ($outpass_array as $key => $value) {
			$outpass_total[] = array($key, $value + $outpass6_array[$key]);
		}

		//add to array as total
		$obj[$ds_key]['key'] = "inpass total";
		$obj[$ds_key]['type'] = "line";
		$obj[$ds_key]['format'] = "s";
		$obj[$ds_key]['yAxis'] = 2;
		$obj[$ds_key]['unit_acronym'] = $right_unit_acronym;
		$obj[$ds_key]['unit_desc'] = $unit_desc_lookup[$right_unit_acronym];
		$obj[$ds_key]['invert'] = false;
		$obj[$ds_key]['ninetyfifth'] = true;
		$obj[$ds_key]['values'] = $inpass_total;

		$obj[$ds_key+1]['key'] = "outpass total";
		$obj[$ds_key+1]['type'] = "line";
		$obj[$ds_key+1]['format'] = "s";
		$obj[$ds_key+1]['yAxis'] = 2;
		$obj[$ds_key+1]['unit_acronym'] = $right_unit_acronym;
		$obj[$ds_key+1]['unit_desc'] = $unit_desc_lookup[$right_unit_acronym];
		$obj[$ds_key+1]['invert'] = $invert_graph;
		$obj[$ds_key+1]['ninetyfifth'] = true;
		$obj[$ds_key+1]['values'] = $outpass_total;
	}
}

echo json_encode($obj,JSON_PRETTY_PRINT|JSON_PARTIAL_OUTPUT_ON_ERROR|JSON_NUMERIC_CHECK);

?>
