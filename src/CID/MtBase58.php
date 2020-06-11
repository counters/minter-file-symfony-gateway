<?php

namespace App\CID;


use StephenHill\Base58;

class MtBase58
{

    /**
     * @var Base58
     */
    private $base58;

    /**
     * MtBase58 constructor.
     */
    public function __construct()
    {
        $this->base58 = new Base58();
    }

    public function getBase58($transaction)
    {
        return $this->base58->encode(hex2bin(substr($transaction, 2)));
    }

    public function getTransaction($base58)
    {
        return "Mt" . bin2hex($this->base58->decode($base58));
    }

}