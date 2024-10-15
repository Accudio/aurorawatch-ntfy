<?php
require 'vendor/autoload.php';

$test_mode = false;

$api_url = 'https://aurorawatch-api.lancs.ac.uk/0.2/status/alerting-site-activity.xml';
$ntfy_endpoint = $test_mode
	? 'https://ntfy.sh/aurorawatch-test'
	: 'https://ntfy.sh/aurorawatch';
$cool_off = 60*60*6;
$alert_levels = [
	'green' => 0,
	'yellow' => 1,
	'amber' => 2,
	'red' => 3
];
$alert_content = [
	'yellow' => [
		'title' => 'Minor geomagnetic activity',
		'tags' => 'milky_way,yellow_circle',
		'body' => 'Aurora may be visible by eye from Scotland and may be visible by camera from Scotland, northern England and Northern Ireland.'
	],
	'amber' => [
		'title' => 'Amber alert: possible aurora',
		'tags' => 'milky_way,orange_circle',
		'body' => 'Aurora is likely to be visible by eye from Scotland, northern England and Northern Ireland; possibly visible from elsewhere in the UK. Photographs of aurora are likely from anywhere in the UK.'
	],
	'red' => [
		'title' => 'Red alert: aurora likely',
		'tags' => 'milky_way,red_circle',
		'body' => 'It is likely that aurora will be visible by eye and camera from anywhere in the UK.'
	]
	];

// load state.json
$state = json_decode(file_get_contents(__DIR__ . '/state.json'), true);

// get the XML from the API or dummy data
$url = $test_mode ? __DIR__ . '/dummy.xml' : $api_url;
$xml = file_get_contents(
	$url, false,
	stream_context_create(['http' => ['header'  => 'User-Agent: AuroraWatch ntfy bridge']])
);

// parse XML
$service = new Sabre\Xml\Service();
$service->elementMap = [
	'{}activity' => function(Sabre\Xml\Reader $reader) {
		return Sabre\Xml\Deserializer\keyValue($reader, '');
	},
];
$result = $service->parse($xml);

// handle latest update
$latest = $result[array_key_last($result)];

// filter out existing updates
if ($latest['value']['datetime'] === $state['updatedAt']) {
	echo "existing update\n";
	update_exit([
		'updatedAt'=> $latest['value']['datetime']
	]);
}

// filter out green updates
if ($latest['attributes']['status_id'] === 'green') {
	echo "green update\n";
	update_exit([
		'updatedAt'=> $latest['value']['datetime'],
		'status' => 'green'
	]);
}

// if we're not within cool-off period, send an alert
$current_time = strtotime($latest['value']['datetime']);
$last_alert = strtotime($state['lastAlert']);
if ($current_time - $last_alert > $cool_off) {
	echo "fresh alert\n";
	alert($latest['attributes']['status_id']);
	update_exit([
		'updatedAt'=> $latest['value']['datetime'],
		'lastAlert'=> $latest['value']['datetime'],
		'status' => $latest['attributes']['status_id']
	]);
}

// we're in a cool-off period, only alert for escalations
$current_status_num = $alert_levels[$state['status']];
$new_status_num = $alert_levels[$latest['attributes']['status_id']];
if ($new_status_num > $current_status_num) {
	echo "escalated alert\n";
	alert($latest['attributes']['status_id']);
	update_exit([
		'updatedAt'=> $latest['value']['datetime'],
		'lastAlert'=> $latest['value']['datetime'],
		'status' => $latest['attributes']['status_id']
	]);
}

echo "no change in status level\n";

function update_exit($params = []) {
	global $state;
	$state = array_merge($state, $params);
	file_put_contents(__DIR__ . '/state.json', json_encode($state));
	exit();
}

function alert($status) {
	global $ntfy_endpoint, $alert_content;
	echo "ALERT: $status\n";
	file_get_contents(
		$ntfy_endpoint, false,
		stream_context_create(['http' => [
			'method'  => 'POST',
			'header'  => implode("\r\n", [
				"Content-Type: text/plain",
				"X-Title: " . $alert_content[$status]['title'],
				"X-Tags: " . $alert_content[$status]['tags'],
				"X-Click: https://aurorawatch.lancs.ac.uk",
			]),
			'content' => $alert_content[$status]['body']
		]])
	);
}