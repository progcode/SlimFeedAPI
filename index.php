<?php
/**
 * Slim Feed API
 *
 * Simple RSS parser to json or jsonp like Google Feed API
 *
 * @category  WebPositive
 * @package   Feed_API
 * @copyright 2016. WebPositive (https://progweb.hu)
 * @license   https://progweb.hu/license
 * @link      https://progweb.hu
 * @version   1.0.2
 */

require 'vendor/autoload.php';
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
$config = [
    'settings' => [
        'displayErrorDetails' => true,
    ],
];
$app = new \Slim\App($config);

$api_key = base64_encode('**********************');
define('API_KEY', $api_key);

/**
 * @param $str
 * @return mixed
 *
 * Remove html tags from string
 */
function removeTags($str) {
    $str = preg_replace("#<(.*)/(.*)>#iUs", "", $str);
    return $str;
}

/**
 * @param $string
 * @param int $length
 * @param string $append
 * @return array|string
 *
 * Truncate description to contentSnippet
 */
function truncate($string, $length=150, $append="...") {
    $string = trim($string);
    if(strlen($string) > $length) {
        $string = wordwrap($string, $length);
        $string = explode("\n", $string, 2);
        $string = $string[0] . $append;
    }
    return $string;
}

/**
 * @param $rss
 * @return bool
 *
 * Validate RSS url
 */
function validateRssUrl($rss) {
    $check = simplexml_load_string(file_get_contents($rss));
    if($check) {
        return true;
    }
    return false;
}

$app->get('/', function ($request, $response) {
    $response->write("Slim Feed API v1.0.2");
    return $response;
});

/**
 * Parse RSS xml to JSON
 */
$app->get('/v1/feed/load', function(Request $request,  Response $response, $args = []) use($app) {
    /** @var $route \Slim\Route */
    $feed_url           = isset($request->getQueryParams()['q']) ? $request->getQueryParams()['q'] : null;
    $jsonp_callback     = isset($request->getQueryParams()['callback']) ? $request->getQueryParams()['callback'] : null;
    $is_encoded         = isset($request->getQueryParams()['is_encoded']) ? $request->getQueryParams()['is_encoded'] : null;
    $request_api_key    = isset($request->getQueryParams()['apiKey']) ? $request->getQueryParams()['apiKey'] : null;

    try
    {
        /**
         * Validate RSS url
         */
        if(API_KEY != $request_api_key || !$feed_url || !validateRssUrl($feed_url)) {
            $message = array(
                "responseData" => null,
                "responseDetails" => "Malformed API request - Feed could not be loaded / Invalid API_KEY!",
                "responseStatus" => 400
            );
            $response->getBody()->write($jsonp_callback. '(' . json_encode($message) . ');');
            $apiResponse = $response->withHeader(
                'Content-type',
                'application/json; charset=utf-8'
            );
            return $apiResponse;
        }

        $feed = new DOMDocument();
        $feed->load($feed_url);
        $items = $feed->getElementsByTagName('channel')->item(0)->getElementsByTagName('item');
        $feed = array();
        $json['entries'] = array();

        foreach($items as $key => $item) {
            $title          = $item->getElementsByTagName('title')->item(0)->firstChild->nodeValue;
            $link           = $item->getElementsByTagName('link')->item(0)->firstChild->nodeValue;
            $publishedDate  = $item->getElementsByTagName('pubDate')->item(0)->firstChild->nodeValue;
            // $contentSnippet = truncate(removeTags($item->getElementsByTagName('description')->item(0)->firstChild->nodeValue));

            switch ($is_encoded)
            {
                case 0:
                    $xmlElem = 'description';
                    break;
                case 1:
                    $xmlElem = 'encoded';
                    break;
                default:
                    $xmlElem = 'description';
            }

            $contentSnippet = truncate(removeTags($item->getElementsByTagName($xmlElem)->item(0)->firstChild->nodeValue));
            $content        = strip_tags($item->getElementsByTagName($xmlElem)->item(0)->firstChild->nodeValue, '<p>, <br/>, <br />, <br>, <b>, <strong>, <ul>, <li>');
            $categories     = $item->getElementsByTagName('category')->item(0)->firstChild->nodeValue;
            $guid           = $item->getElementsByTagName('guid')->item(0)->firstChild->nodeValue;

            preg_match('/< *img[^>]*src *= *["\']?([^"\']*)/i', $item->getElementsByTagName($xmlElem)->item(0)->firstChild->nodeValue, $contentImg);

            $json['entries'][$key]['title']             = $title;
            $json['entries'][$key]['link']              = $link;
            $json['entries'][$key]['author']            = "Author";
            $json['entries'][$key]['publishedDate']     = $publishedDate;
            $json['entries'][$key]['contentSnippet']    = $contentSnippet;
            $json['entries'][$key]['content']           = $content;
            $json['entries'][$key]['contentimg']        = $contentImg[1];
            $json['entries'][$key]['categories']        = $categories;
            $json['entries'][$key]['guid']              = $guid;
        }

        /**
         * Build json or jsonp response with callback
         */
        $feed{'responseData'}{'feed'}['entries'] = $json['entries'];
        $feed_jsonp = $jsonp_callback. '(' . json_encode($feed) . ');';

        /**
         * If 'callback' parameter exists
         */
        if($jsonp_callback) {
            $response->getBody()->write($feed_jsonp);
        }
        else {
            $response->getBody()->write(json_encode($feed));
        }

        $apiResponse = $response->withHeader(
            'Content-type',
            'application/json; charset=utf-8'
        );

        return $apiResponse;

    } catch(PDOException $e) {
        $message = array(
            "responseData" => null,
            "responseDetails" => $e->getMessage(),
            "responseStatus" => 400
        );
        $response->getBody()->write($jsonp_callback. '(' . json_encode($message) . ');');
        $apiResponse = $response->withHeader(
            'Content-type',
            'application/json; charset=utf-8'
        );

        return $apiResponse;
    }
});

$app->run();
