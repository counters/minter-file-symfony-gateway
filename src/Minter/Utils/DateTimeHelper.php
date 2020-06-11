<?php


namespace App\Minter\Utils;


class DateTimeHelper
{
    private $dateTimeZone;
    private $timezone;

    public function __construct()
    {
        $this->dateTimeZone = new \DateTimeZone("GMT");
        $this->timezone = new \DateTimeZone(date_default_timezone_get());
    }

    public function parse(string $stringDateTime, \DateTimeZone $inTimezone = null): ?\DateTime
    {
        if (is_null($inTimezone)) $inTimezone = $this->dateTimeZone;
        $count = 0;
        $newStrTime = preg_replace('/(.+)\.(\d{6})(\d*)Z/', '${1}.${2}Z', $stringDateTime, 1, $count);
        if ($count == 1) {
            $datetime = new \DateTime($newStrTime, $inTimezone);
            $datetime->setTimezone($this->timezone);
            return $datetime;
        }

        $count = 0;
        $newStrTime = preg_replace('/(.+):(\d{2}):(\d{2})Z/', '${1}:${2}:${3}Z', $stringDateTime, 1, $count);
        if ($count == 1) {
            $datetime = new \DateTime($newStrTime, $inTimezone);
            $datetime->setTimezone($this->timezone);
            return $datetime;
        }
        return null;
    }
}