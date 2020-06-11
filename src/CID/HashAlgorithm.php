<?php

namespace App\CID;


final class HashAlgorithm
{
    private const LIST = [
        0x11 => 'sha1',
        0x12 => 'sha256',
        0x13 => 'sha512',
    ];

    static public function get($algorithm)
    {
        if (isset(self::LIST[$algorithm]))
            return self::LIST[$algorithm];
        else
            return null;
    }
}