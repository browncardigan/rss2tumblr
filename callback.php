<?php

session_start();

include('_conf.php');

require_once('tumblroauth/tumblroauth.php');

// Define the needed keys
$consumer_key = API_KEY;
$consumer_secret = API_SECRET;

$tum_oauth = new TumblrOAuth($consumer_key, $consumer_secret, $_SESSION['request_token'], $_SESSION['request_token_secret']);

$access_token = $tum_oauth->getAccessToken($_REQUEST['oauth_verifier']);

unset($_SESSION['request_token']);
unset($_SESSION['request_token_secret']);

if (200 == $tum_oauth->http_code) {
  // good to go
} else {
	echo '<a href="connect.php">RE-connect</a>';
	exit;
}

$tum_oauth = new TumblrOAuth($consumer_key, $consumer_secret, $access_token['oauth_token'], $access_token['oauth_token_secret']);

$rss = curlContents(RSS_URL);
$rss = simplexml_load_string($rss);
$rss = objectToArray($rss);

$stored = file_get_contents(STORED_FILE);
$stored = json_decode($stored, true);

$success = array();

echo '<pre>';

if (isset($rss['channel']['item'])) {
	//open already 'sent' items
	
	foreach ($rss['channel']['item'] as $item) {
		
		if (!in_array($item['guid'], $stored)) {
			// get the url
			$post = curlContents($item['guid']);
			$doc = new DOMDocument();
			@$doc->loadHTML($post);
		
			$metatags = $doc->getElementsByTagName('meta');
			$metatagsArray = array();
		
			for ($i = 0; $i < $metatags->length; $i++) {
			    $meta = $metatags->item($i);
				$attribute = false;
				if ($meta->getAttribute('name')) {
					$attribute = $meta->getAttribute('name');
				}
				else if ($meta->getAttribute('property')) {
					$attribute = $meta->getAttribute('property');
				}
				if ($attribute) {
					$metatagsArray[$attribute] = $meta->getAttribute('content');
				}
			}
		
			if (isset($metatagsArray['og:image'])) {
				$image = $metatagsArray['og:image'];
				$caption = '<a href="' . $item['guid'] . '" target="_blank">' . $item['title'] . '</a>';
				if ($item['description'] != '') { $caption .= '<br />' . $item['description']; }
				$data = array(
					'type' 		=> 'photo',
					'link'		=> $item['guid'],
					'source' 	=> $image,
					'caption'	=> $caption
				);
			}
		
			else {
				$data = array(
					'type'			=> $item['title'],
					'url'			=> $item['guid'],
					'description' 	=> $item['description']
				);
			}
			$url = 'http://api.tumblr.com/v2/blog/' . TUMBLR_ID . '.tumblr.com/post';
			$post = $tum_oauth->post($url, $data);
		
			// success?
			if (isset($post->response->id)) {
				echo 'done for: ' . $post->response->id . "\n";
				print_r($data);
				$success[] = $item['guid'];
			}
			else {
				echo 'FAILED to post to tumblr .. ' . "\n";
				print_r($data);
			}
		
			echo "\n\n";
		}

		flush();
	}
}

echo 'new items added: ' . count($success);

// re-save $stored - urls of posted items
$stored = array_merge($stored, $success);
$stored_json = json_encode($stored);
file_put_contents(STORED_FILE, $stored_json);




/* basic functions */

function objectToArray($d) {
	if (is_object($d)) {
		$d = get_object_vars($d);
	}
	if (is_array($d)) {
		return array_map(__FUNCTION__, $d);
	}
	else {
		return $d;
	}
}

function curlContents($url=false, $data=array()) {
	$contents = '';
	if ($url) {
		$ch = curl_init();
		$timeout = 0; // set to zero for no timeout
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_COOKIESESSION, true);
		if (count($data) > 0) {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}
		$contents = curl_exec($ch);
		curl_close($ch);
	}
	return $contents;
}

?>
