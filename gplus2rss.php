<?php
//mb_internal_encoding('UTF-8');
$path = __DIR__.'/src/';
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
require_once 'Google/Client.php';
require_once 'Google/Service/Plus.php';
date_default_timezone_set('Australia/Adelaide');

// Set API key. Get your key at https://code.google.com/apis/console.
$api_key = 'YOURAPIKEY';
// Set ID of Plus user. That's the long number in their profile URL.
$gplus_uid = 'YOURGOOGLEUSERID';

$client = new Google_Client();
$client->setApplicationName("gplus2rss-leogaggl");
$client->setDeveloperKey($api_key);
$plus = new Google_Service_Plus($client);
$person = $plus->people->get($gplus_uid);
$collection = 'public';
$optParams = array('maxResults' => '20');
$activities = $plus->activities->listActivities($gplus_uid, $collection, $optParams);

//Write output
$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" ?><rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" />'); 
$xml->addChild('channel');
$atomlink=$xml->channel->addChild('link','','http://www.w3.org/2005/Atom');
$atomlink->addAttribute('href','https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
$atomlink->addAttribute('rel',"self");
$atomlink->addAttribute('type',"application/rss+xml");
$xml->channel->addChild('title', $person->displayName.' - Public Google Plus Feed');
$xml->channel->addChild('link', $person->url);
$xml->channel->addChild('language','en-us');
$xml->channel->addChild('description', $person->aboutMe);
$xml->channel->addChild('pubDate', date(DATE_RSS));

foreach ($activities as $activity) {
	// add item element for each article
	$item = $xml->channel->addChild('item');
	$item->addChild('guid');
	$item->guid = $activity['url'];
	$item->addChild('title');
	$item->title = htmlspecialchars_decode(mb_convert_encoding(str_replace("\xEF\xBB\xBF", '', $activity['title']), 'HTML-ENTITIES', 'utf-8'), ENT_QUOTES);
	$item->addChild('link');
	$item->link = $activity['url'];
	$item->addChild('description');
	$item->description = strip_tags(mb_convert_encoding(str_replace("\xEF\xBB\xBF", '', $activity['object']['content']), 'HTML-ENTITIES', 'utf-8'));
	$item->addChild('pubDate');
	$item->pubDate = date(DATE_RSS, strtotime($activity['published']));
}

header('Content-Type: application/rss+xml');
$dom = new DOMDocument('1.0');
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;
$dom->loadXML($xml->asXML());
echo $dom->saveXML();
