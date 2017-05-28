<?php
namespace Crawler;

/**
 * Crawler class
 * Defines web crawler using cURL library to fetch content from URL
 *
 * PHP 5.5
 *
 * @author  Nathan Quach <nathanquach8x@gmail.com>
 * @version 0.0.1
 * @license The MIT License
 */
class Crawler {

    public $userAgent;

    public function __construct($userAgent = null) {
        $this->userAgent = $userAgent? $userAgent :
        'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.1916.153 Safari/537.36';
    }

    /**
     * Executes a cURL transfer and returns an array which contains
     * response content, error code, error message and details
     * of the transfer
     * For details about curl functions
     * check http://php.net/manual/en/ref.curl.php
     *
     * @param  [array] $options [options for cURL resource]
     * @return [array]
     *
     */
    private function execute($options) {
        $ch = curl_init();
        curl_setopt_array($ch, $options);

        $content = curl_exec($ch);
        $err     = curl_errno($ch);
        $errmsg  = curl_error($ch);
        $info    = curl_getinfo($ch);

        curl_close($ch);

        return array('content' => $content,
                     'errcode' => $err,
                     'errmsg' => $errmsg,
                     'info' => $info
                     );
    }

    /**
     * Returns an array of cURL Options, which will be used
     * as input for execute() function
     * See http://au2.php.net/manual/en/function.curl-setopt.php
     *
     * @param [string] $url  [URL to be crawled]
     * @param [array] $args [options for cURL resource]
     */
    private function setOptions($url, $args = []) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Please enter a valid URL');
        }

        $options = array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT => $this->userAgent,
                CURLOPT_HEADER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 2,
                CURLOPT_AUTOREFERER => true,
                CURLOPT_NOBODY => false,
                CURLOPT_PROGRESSFUNCTION => '\Crawler\Crawler::progressCallback',
                CURLOPT_NOPROGRESS => false,
                CURLOPT_COOKIE => ''
                );

        // Override the $options (default) value with $args
        if (is_array($args) && array_diff_key($args, array_keys(array_keys($args)))) {
            return $args + $options;
        }
        return $options;
    }

    /**
     * Callback function for CURLOPT_PROGRESSFUNCTION
     *
     * @param  [resources] $ch    [the cURL resource]
     * @param  [integer] $dlTotal [total number of bytes expected
     *                             to be downloaded in this transfer]
     * @param  [integer] $dlNow   [number of bytes already
     *                             downloaded]
     * @param  [integer] $ulTotal [total number of bytes expected
     *                             to be uploaded in this transfer]
     * @param  [integer] $ulNow   [number of bytes already uploaded]
     * @return [integer]          [return non-zero value to abort transfer]
     */
    public static function progressCallback($ch, $dlTotal, $dlNow, $ulTotal, $ulNow) {
        $percent = $dlTotal>0 ? $dlNow/$dlTotal : 0;
        return 0;
    }

    /**
     * Send a HTTP GET request
     *
     * @param  [string] $url  [the request URL]
     * @param  [array] $args [options for cURL resource]
     * @return [array]       [output of execute()]
     */
    public function sendHttpGet($url, $args = []) {
        $options = $this->setOptions($url, $args);
        $response = $this->execute($options);
        return $response;
    }

    /**
     * Send a Http POST request
     *
     * @param  [string] $url    [the request URL]
     * @param  [array] $params [Post parameters]
     * @param  [array] $args   [options for cURL resource]
     * @return [array]         [output of execute()]
     */
    public function sendHttpPost($url, $params, $args = []) {
        $baseOpt = $this->setOptions($url, $args);

        $postData = '';
        if (is_array($params) && array_diff_key($params, array_keys(array_keys($params)))) {
            foreach ($params as $key => $value) {
                $postData .= $key.'='.$value.'&';
            }
            rtrim($postData, '&');
        }

        $postOpt = array(
            CURLOPT_POST => count($postData),
            CURLOPT_POSTFIELDS => $postData,
            );

        $options = $postOpt + $baseOpt;
        return $this->execute($options);
    }

    /**
     * Send HTTP request to server and return the cookie
     *
     * @param  [string] $url    [request URL]
     * @param  [string] $method ['GET' or 'POST']
     * @param  [array] $params  [options for cURL resource]
     * @return [string]         [cookie received from server:
     *                           "value_a=key_a; value_b=key_b; ..."]
     */
    public function getCookie($url, $method, $params = []) {
        $cookie = '';
        $args = array(
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => false,
            CURLOPT_NOPROGRESS => true,
            );

        switch ($method) {
            case 'GET':
                $response = $this->sendHttpGet($url, $args);
                break;
            case 'POST':
                $response = $this->sendHttpPost($url, $params, $args);
                break;
            default:
                throw new \InvalidArgumentException('Method argument must be either "GET" or "POST"');
        }

        preg_match_all('/^Set-Cookie: (.*?);/m', $response['content'], $m);
        foreach ($m[1] as $value) {
            $cookie .= $value.';';
        }
        return $cookie;
    }
}
