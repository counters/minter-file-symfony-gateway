<?php

namespace App\CID;

use StephenHill\Base58;

final class ObjCIDv0
{
    /**
     * @var integer
     */
    private $fnCode;

    /**
     * @var integer
     */
    private $digSize = null;

    /**
     * @var string
     */
    private $hashDigest = null;
    /**
     * @var Base58
     */
    private $base58;

    /**
     * ObjCIDv0 constructor.
     * @param string|null $CIDv0
     */
    public function __construct(string $CIDv0 = null)
    {
        $this->base58 = new Base58();
        if ($CIDv0) {
            $raw = $this->base58->decode($CIDv0);
            $this->parseCIDv0($raw);
        }
    }

    private function parseCIDv0($binaryCIDv0)
    {
        if (strlen($binaryCIDv0) > 1
            and $fnCode = ord($binaryCIDv0[0])
            and $digSize = ord($binaryCIDv0[1])
            and $digSize == strlen($binaryCIDv0) - 2
            and HashAlgorithm::get($fnCode)
        ) {
            $this->fnCode = $fnCode;
            $this->digSize = $digSize;
            $this->hashDigest = bin2hex(substr($binaryCIDv0, 2));
        } else {
            return null;
        }
    }

    /**
     * @return int
     */
    public function getFnCode(): int
    {
        return $this->fnCode;
    }

    /**
     * @param int $fnCode
     */
    public function setFnCode(int $fnCode): void
    {
        $this->fnCode = $fnCode;
    }

    /**
     * @return int
     */
    public function getDigSize(): int
    {
        return $this->digSize;
    }

    /**
     * @param int $digSize
     */
    public function setDigSize(int $digSize): void
    {
        $this->digSize = $digSize;
        if ($this->hashDigest) {
            $this->hashDigest = $this->newHashDigest($this->hashDigest);
        }
    }

    private function newHashDigest($hashDigest)
    {
        return bin2hex(substr(hex2bin($hashDigest), 0, $this->digSize));
    }

    /**
     * @return string
     */
    public function getHashDigest(): string
    {
        return $this->hashDigest;
    }

    /**
     * @param string $hashDigest
     */
    public function setHashDigest(string $hashDigest): void
    {
        if ($this->digSize)
            $this->hashDigest = $this->newHashDigest($hashDigest);
        else
            $this->hashDigest = $hashDigest;
    }

    /**
     * @return string
     */
    public function toString()
    {
        return $this->__toString();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $hex = dechex($this->fnCode) . dechex($this->digSize) . $this->hashDigest;
        return $this->base58->encode(hex2bin($hex));
    }

}