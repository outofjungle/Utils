#!/usr/bin/php -q
<?php

/**
 * Translates human readable location info and sends lon/lat info to android AVD
 *
 * @author      Venkat Venkataraju
 * @version     $Id$
 */

	function find($element, $name) {
		$found = NULL;
		$child = $element->firstChild;
		do {
			if ($child->nodeName == $name) {
				$found = $child;
				break;
			}
		} while ($child = $child->nextSibling);
		return $found;
	}

	$opts = getopt("l:p:");
	$location = (isset($opts['l'])) ? $opts['l'] : "sunnyvale, ca";
	$port = (isset($opts['p'])) ? $opts['p'] : "5554";

	$YQL = rawurlencode("SELECT * FROM geo.placemaker WHERE documentContent = '{$location}' AND documentType='text/plain'");
	$url = "http://query.yahooapis.com/v1/public/yql?q={$YQL}&format=xml&diagnostics=false";

	$ch = curl_init();
	curl_setopt ($ch, CURLOPT_URL, $url);
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 5);
	$xml = curl_exec($ch);
	curl_close($ch);

	$doc = new DOMDocument();
	$doc->loadXML($xml);
	$doc->normalizeDocument();

	$query = find($doc, 'query');
	$results = find($query, 'results');
	$matches = find($results, 'matches');
	$match = find($matches, 'match');
	$place = find($match, 'place');
	$centroid = find($place, 'centroid');
	$longitude = find($centroid, 'longitude');
	$latitude = find($centroid, 'latitude');

	if (!is_null($longitude) AND !is_null($latitude)) {
		$command = "geo fix {$longitude->nodeValue} {$latitude->nodeValue}\r\n";
		$fp = fsockopen("tcp://127.0.0.1", $port, $errno, $errstr);
		if ($fp) {
			stream_set_timeout($fp, 2);
			do { $rcvd = fgets($fp); } while ($rcvd);
			echo "SEND: {$command}";
			fwrite($fp, $command);
			echo fgets($fp);
			fclose($fp);
		}
	} else {
		throw new Exception("Unknown Location");
	}
?>