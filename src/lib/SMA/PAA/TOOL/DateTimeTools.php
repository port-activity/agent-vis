<?php
namespace SMA\PAA\TOOL;

use DateTime;
use DateTimeZone;
use DateInterval;
use Exception;

class DateTimeTools
{
    public function nowUtcString(): string
    {
        $dateTime = new DateTime();
        $dateTime->setTimeZone(new DateTimeZone("UTC"));

        return $dateTime->format("Y-m-d\TH:i:s\Z");
    }

    public function createDateTime(string $dateTimeString): DateTime
    {
        $dateTime = null;

        try {
            $dateTime = new DateTime($dateTimeString);
        } catch (Exception $e) {
            throw new Exception(
                "Cannot create DateTime from given string: "
                . $dateTimeString
                . " " . $e->getMessage()
            );
        }

        return $dateTime;
    }

    public function dateTimeDifferenceInXsdDuration(DateTime $dateTime1, DateTime $dateTime2): string
    {
        $diff = $dateTime1->diff($dateTime2);

        if (!$diff) {
            throw new Exception(
                "Cannot calculate difference between given DateTimes"
                . "dateTime1: " . $dateTime1->format("Y-m-d\TH:i:sP")
                . "dateTime2: " . $dateTime2->format("Y-m-d\TH:i:sP")
            );
        }

        return $diff->format("P%yY%mM%dDT%hH%iM%sS");
    }
}
