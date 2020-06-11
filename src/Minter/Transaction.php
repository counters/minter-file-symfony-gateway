<?php

namespace App\Minter;

use Minter\MinterAPI;


final class Transaction
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
     * @param string $transaction
     * @return \stdClass|null
     */
    function getResult($transaction, &$error = null)
    {
        try {
            $jsonTrs = $this->api->getTransaction($transaction);
            return $this->validate($jsonTrs, $error);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
        } catch (\Exception $e) {
        }
        return null;
    }

    /**
     * @param \stdClass $transaction
     * @return \stdClass|null
     */
    public function validate($transaction, &$error = null)
    {
        if (isset($transaction->result)) {
            if (isset($transaction->result->code)) {
                if ($transaction->result->code != 0) {
                    $error = $transaction->result->code;
                    return null;
                } else {
                    return $transaction->result;
                }
            } else {
                return $transaction->result;
            }
        }
        return null;
    }
}