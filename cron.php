<?php
require 'vendor/autoload.php';
require __DIR__ . '/env.php';

$ntfy_endpoint = 'https://ntfy.sh/' . NTFY_TOPIC;
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

// load state.json with defaults if it doesn't exist
$state = [
	'updatedAt' => 0,
	'lastAlert' => 0,
	'status' => 'green',
	'lastRun' => 0
];
if (file_exists(__DIR__ . '/state.json')) {
	$state = json_decode(file_get_contents(__DIR__ . '/state.json'), true);
}

// get the XML, using file_get_contents or curl
$xml = null;
if (str_starts_with(API_URL, 'http')) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, API_URL);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'User-Agent: AuroraWatch ntfy bridge'
	]);
	$xml = curl_exec($ch);
	curl_close($ch);
} else {
	$xml = file_get_contents(API_URL);
}

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
	$state = array_merge(
		array_merge($state, $params),
		[ 'lastRun' => date('c') ]
	);
	file_put_contents(__DIR__ . '/state.json', json_encode($state));
	exit();
}

function alert($status) {
	global $ntfy_endpoint, $alert_content;
	echo "ALERT: $status\n";

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $ntfy_endpoint);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $alert_content[$status]['body']);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		"Content-Type: text/plain",
		"X-Title: " . $alert_content[$status]['title'],
		"X-Tags: " . $alert_content[$status]['tags'],
		"X-Click: https://aurorawatch.lancs.ac.uk",
	]);

	curl_exec($ch);
	curl_close($ch);
}