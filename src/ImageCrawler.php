<?php
namespace Crawler;

set_time_limit(0);
require(__DIR__."/../include/url_to_absolute.php");

class ImageCrawler {

	public function scanForImageLinks($url, $containerId = null, $cookie = null) {
		$links = array();
		$nodes = array();
		$user_agent =
		'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.106 Safari/537.36';
		$header = array(
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
			'Accept-Encoding: gzip,deflate,sdch',
			'Accept-Language: en-US,en;q=0.8,en-AU;q=0.6',
			'Cache-Control:max-age=0',
			'Connection:keep-alive',
		);

		$curlOptions = array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 20,
			CURLOPT_AUTOREFERER => true,
			CURLOPT_USERAGENT => $user_agent,
			CURLOPT_COOKIE => $cookie,
			CURLOPT_HTTPGET => true,
			CURLOPT_HEADER => 0
			);

		$dom = new DOMDocument;

		$html = $this->getURLContent($curlOptions);
		libxml_use_internal_errors(true); //ignore HTML5 tags error
		$dom->loadHTML($html);
		libxml_use_internal_errors(false);

		$title = 'cannotgettitle';

		foreach ($dom->getElementsByTagName("title") as $dom_node) {
			$title = $dom_node->nodeValue;
		}

		if ($containerId) {
			$html = $dom->getElementById($containerId);
			if ($html) {
				libxml_use_internal_errors(true);
				$dom->loadHTML($html->C14N());
				libxml_use_internal_errors(false);
			}
		}

		// Search for <a>...</a> tags containing <img> and return the href attributes
		foreach ($dom->getElementsByTagName('a') as $node) {
			if ($node->getElementsByTagName('img')->length > 0) {
				$link = url_to_absolute($url, $node->getAttribute("href"));
				if (!in_array($link, $links) && $this->isValidImageLink($link)) {
					$links[] = $link;
					$node->setAttribute("href", $link);
					$nodes[] = $node->C14N();
				}
			}
		}

		// echo '<h2>'.$url.'</h2>';
		return array($nodes, $links, $title); //$node: html texts, $links: urls only
	}

	public function scanForImages($url, $containerId = null, $cookie = null, $containerClass = null) {
		$links = array();
		$nodes = array();
		$user_agent =
		'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.1916.153 Safari/537.36';

		$curlOptions = array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_USERAGENT => $user_agent,
			CURLOPT_COOKIE => $cookie
			);
		$dom = new DOMDocument;

		$html = $this->getURLContent($curlOptions);

		libxml_use_internal_errors(true); //ignore HTML5 tags error
		$dom->loadHTML($html);
		libxml_use_internal_errors(false);

		if ($containerId) {
			$html = $dom->getElementById($containerId);
			if ($html && $html->hasAttribute("src")) {
				$link = url_to_absolute($url, $html->getAttribute("src"));
				if (!in_array($link, $links)) {
					$links[] = $link;
				}

				return $links;
			}

			if ($html) {
				libxml_use_internal_errors(true);
				$dom->loadHTML($html->C14N());
				libxml_use_internal_errors(false);
			}
		}

		foreach ($dom->getElementsByTagName('img') as $node) {
			if ($node->hasAttribute("src")) {
				$link = url_to_absolute($url, $node->getAttribute("src"));
				if (!in_array($link, $links)) {
					$links[] = $link;
					$node->setAttribute("src", $link);
					$nodes[] = $node->C14N();
				}
			}
		}

		return $links;
	}

	public function downloadImage($url, $name_prefix = null, $cookie = null, $progress = null) {
		$pattern = '#[^\/]+\.(jpg|png)#i';
		$header = array(
			'Accept: image/webp,*/*;q=0.8',
			'Accept-Encoding: gzip,deflate,sdch',
			'Accept-Language: en-US,en;q=0.8,en-AU;q=0.6',
			);
		$user_agent =
		'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.1916.153 Safari/537.36';
		if (preg_match($pattern, $url, $matches)) {
			$foldername = "/media/nathan/D/".preg_replace('#[:;\.<>\|\?\\\[]#i', '', $name_prefix);
			$foldername = preg_replace('#\]#', ',', $foldername);
			$foldername = preg_replace('#- E-Hentai Galleries#i', '', $foldername);
			$foldername = preg_replace('#- ExHentaiorg#i', '', $foldername);
			$foldername = preg_replace('#Hentairulesnet#i', '', $foldername);
			if (!file_exists($foldername)) {
				$oldmask = umask(0);
				mkdir($foldername, 0777, true);
				umask($oldmask);
			}

			$filename = $this->cleanName(substr($name_prefix, 0, 10) . "_" . $matches[0]);
			$fp = fopen($foldername."/".$filename, "w");

			$curlOptions = array(
				CURLOPT_URL => $url,
				CURLOPT_FILE => $fp,
				CURLOPT_HEADER => false,
				CURLOPT_HTTPGET => true,
				CURLOPT_USERAGENT => $user_agent,
				CURLOPT_HTTPHEADER => $header,
				CURLOPT_COOKIE => $cookie
				);

			if ($progress) {
				$curlOptions[CURLOPT_NOPROGRESS] = false;
				$curlOptions[CURLOPT_PROGRESSFUNCTION] = $progress;
			}

			$ch = curl_init();
			curl_setopt_array($ch, $curlOptions);
			curl_exec($ch);
			curl_close($ch);
			fclose($fp);
			chmod($foldername."/".$filename, 0766);

			// echo "Successfully download ", $url, "<br>";
			return 1;
		}
	}

	public function getURLContent($setopt_content) {
		$ch = curl_init();
		curl_setopt_array($ch, $setopt_content);
		$result_data = curl_exec($ch);
		$info = curl_getinfo($ch);
		// echo '<h1> CRUL_GETINFO: </h1>'; var_dump($info);
		curl_close($ch);
		return $result_data;
	}

	public function getDomainName($url) {
		$pattern = '#^(?:http://){1}([^/]+)#i';
		if (preg_match($pattern, $url, $matches)) {
			return $matches;
		}

		return null;
	}

	private function isValidImageLink($url) {
		if (strstr($url, "newreply.php") || strstr($url, "forums.e-hentai.org/index.php?showuser=")) {
			return false;
		}

		return true;
	}

	private function cleanName($string) {
		$clean_name = strtr($string, array(
			'Š' => 'S','Ž' => 'Z','š' => 's',
			'ž' => 'z','Ÿ' => 'Y','À' => 'A',
			'Á' => 'A','Â' => 'A','Ã' => 'A',
			'Ä' => 'A','Å' => 'A','Ç' => 'C',
			'È' => 'E','É' => 'E','Ê' => 'E',
			'Ë' => 'E','Ì' => 'I','Í' => 'I',
			'Î' => 'I','Ï' => 'I','Ñ' => 'N',
			'Ò' => 'O','Ó' => 'O','Ô' => 'O',
			'Õ' => 'O','Ö' => 'O','Ø' => 'O',
			'Ù' => 'U','Ú' => 'U','Û' => 'U',
			'Ü' => 'U','Ý' => 'Y','à' => 'a',
			'á' => 'a','â' => 'a','ã' => 'a',
			'ä' => 'a','å' => 'a','ç' => 'c',
			'è' => 'e','é' => 'e','ê' => 'e',
			'ë' => 'e','ì' => 'i','í' => 'i',
			'î' => 'i','ï' => 'i','ñ' => 'n',
			'ò' => 'o','ó' => 'o','ô' => 'o',
			'õ' => 'o','ö' => 'o','ø' => 'o',
			'ù' => 'u','ú' => 'u','û' => 'u',
			'ü' => 'u','ý' => 'y','ÿ' => 'y'));
		$clean_name = strtr($clean_name, array(
			'Þ' => 'TH', 'þ' => 'th', 'Ð' => 'DH',
			'ð' => 'dh', 'ß' => 'ss', 'Œ' => 'OE',
			'œ' => 'oe', 'Æ' => 'AE', 'æ' => 'ae',
			'µ' => 'u'));
		$clean_name = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array(' ', '.', ''), $clean_name);
		return $clean_name;
	}

	public function curlGetFileSize($url, $cookie = null) {
		// Assume failure.
		$result = -1;
		$header = array(
			'Accept: image/webp,*/*;q=0.8',
			'Accept-Encoding: gzip,deflate,sdch',
			'Accept-Language: en-US,en;q=0.8,en-AU;q=0.6',
			);
		$user_agent =
		'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.1916.153 Safari/537.36';

		$curlOptions = array(
			CURLOPT_URL => $url,
			CURLOPT_HEADER => true,
			CURLOPT_HTTPGET => true,
			CURLOPT_USERAGENT => $user_agent,
			CURLOPT_HTTPHEADER => $header,
			CURLOPT_NOBODY => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_COOKIE => $cookie
			);
		$ch = curl_init();
		curl_setopt_array($ch, $curlOptions);
		$data = curl_exec($ch);
		curl_close($ch);

		if ($data) {
			$content_length = "unknown";
			$status = "unknown";

			if (preg_match("/^HTTP\/1\.[01] (\d\d\d)/", $data, $matches)) {
				$status = (int)$matches[1];
			}

			if ( preg_match("/Content-Length: (\d+)/", $data, $matches)) {
				$content_length = (int)$matches[1];
			}

			// http://en.wikipedia.org/wiki/List_of_HTTP_status_codes
			if ($status == 200 || ($status > 300 && $status <= 308)) {
				$result = $content_length;
			}
		}

		return $result;
	}
}

/*
Example of how to use ImageCrawler
*/
function downloadImages($pages, $thumbnailContainerId = null, $imageContainerId = null, $cookie = null, $sizelimit = 30720, $preprocess = null) {
	if (empty($pages)) {
		return 0;
	}

	$crawler = new ImageCrawler;
	foreach ($pages as $page) {
		$array = $crawler->scanForImageLinks($page, $thumbnailContainerId, $cookie);
		$urls = $array[1];
		$title = substr($array[2], 0, 300); //cut part of title longer than 300 characters
		foreach ($urls as $i=>$u) {
			if (substr($u, -4) === '.jpg' || substr($u, -4) === '.png') {
				downloadImage($preprocess, $u, $crawler, $sizelimit, $title);
			} else {
				foreach ($crawler->scanForImages($u, $imageContainerId, $cookie) as $image) {
					downloadImage($preprocess, $image, $crawler, $sizelimit, $title);
				}
			}
		}
	}

	return 1;
}

function downloadImage($preprocess, $image, $crawler, $sizelimit, $title) {
	if ($preprocess) {
		$image = call_user_func($preprocess, $image);
	}

	// download images have size > size limit
	if ($crawler->curlGetFileSize($image) > $sizelimit) {
		$crawler->downloadImage($image, $title);
	}
}

// Send POST request
function httpPost($url, $params) {
	$postData = '';
	//create name value pairs seperated by &
	foreach($params as $k => $v) {
		$postData .= $k . '='.$v.'&';
	}

	rtrim($postData, '&');

	$user_agent =
		'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.71 Safari/537.36';

	$ch = curl_init();

	curl_setopt($ch,CURLOPT_URL,$url);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
	curl_setopt($ch,CURLOPT_HEADER, 1);
	curl_setopt($ch,CURLOPT_USERAGENT, $user_agent);
	curl_setopt($ch, CURLOPT_POST, count($postData));
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

	$output=curl_exec($ch);

	curl_close($ch);
	return $output;
}

// Get url of full size image of hentairules.net
function getOriginSizeImageUrl($url) {
	$url = str_replace('/_data/i/', '/', $url);
	$url = str_replace('-xx.', '.', $url);
	return $url;
}

// Get cookie for g.e-hentai.org
function getCookie() {
	$params = array(
	"UserName" => "meaning",
	"PassWord" => "iamtheone",
	"bt" => '',
	"b" => "d",
	"CookieDate" => 1,
	"ipb_login_submit" => "Login!"
	);

	$result = httpPost("https://forums.e-hentai.org/index.php?act=Login&CODE=01",$params);
	// get cookie from e-hentai.org
	preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $m);
	$cookie = '';
	foreach($m[1] as $value) {
		$cookie .= $value . ';';
	}
	echo $cookie . "\r\n";
	return $cookie;
}
