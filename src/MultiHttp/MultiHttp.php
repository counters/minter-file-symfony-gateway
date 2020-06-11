<?php

namespace App\MultiHttp;


class MultiHttp
{

    private $curl_options = [
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_USERAGENT => 'Minter content loader',
        CURLOPT_HEADER => false,
        CURLOPT_RETURNTRANSFER => true
    ];

    /**
     * MultiHttp constructor.
     * @param array|null $headers
     */
    public function __construct($headers)
    {
        if ($headers) {
            if (isset($this->curl_options[CURLOPT_HTTPHEADER]))
                $this->curl_options[CURLOPT_HTTPHEADER] = array_merge($this->curl_options[CURLOPT_HTTPHEADER], $this->getHeadersCurl($headers));
            else $this->curl_options[CURLOPT_HTTPHEADER] = $this->getHeadersCurl($headers);
        }
    }

    private function getHeadersCurl(array $headers)
    {
        $new_headers = [];
        foreach ($headers as $key => $header) {
            $new_headers[] = $key . ": " . $header;
        }
        return $new_headers;
    }

    public function run($urls, $callback = null)
    {

        $mh = curl_multi_init();
        $chs = array();
        foreach ($urls as $url) {
            $chs[] = ($ch = curl_init());

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt_array($ch, $this->curl_options);
            curl_multi_add_handle($mh, $ch);
        }
        if ($callback === null) {
            $results = array();
        }

        $prev_running = $running = null;

        do {
            curl_multi_exec($mh, $running);

            if ($running != $prev_running) {
                $info = curl_multi_info_read($mh);

                if (is_array($info) && ($ch = $info['handle'])) {
                    $content = curl_multi_getcontent($ch);
                    $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

                    if ($callback) {
                        $callback($url, $content, $info['result'], $ch);
                    } else {
                        $results[$url] = array('content' => $content, 'status' => $info['result'], 'status_text' => curl_error($ch));
                    }
                }
                $prev_running = $running;
            }

        } while ($running > 0);

        foreach ($chs as $ch) {
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
        return ($callback !== null) ? true : $results;

    }
}