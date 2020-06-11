<?php


namespace App\Minter;


use Minter\MinterAPI;

class Block
{

    /**
     * @var \GuzzleHttp\Client|string
     */
    private $httpClient;
    /**
     * @var MinterAPI
     */
    private $api;

    /**
     * Transaction constructor.
     * @param \GuzzleHttp\Client|string $httpClient
     */
    public function __construct($httpClient)
    {
        $this->httpClient = $httpClient;
        $this->api = new MinterAPI($this->httpClient);

    }

    /**
     * @param int $height
     * @return \stdClass|null
     */
    function getResult($height, &$error = null)
    {
        try {
            $json = $this->api->getBlock($height);
            return $this->validate($json, $error);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
        } catch (\Exception $e) {
        }
        return null;
    }

    /**
     * @param \stdClass $json
     * @return \stdClass|null
     */
    public function validate($json, &$error = null)
    {
        if (isset($json->result)) {
            if (isset($json->result->code)) {
                if ($json->result->code != 0) {
                    $error = $json->result->code;
                    return null;
                } else {
                    return $json->result;
                }
            } else {
                return $json->result;
            }
        }
        return null;
    }
}