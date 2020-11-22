<?php
namespace SMA\PAA\TOOL;

use PHPUnit\Framework\TestCase;

final class DateTimeToolsTest extends TestCase
{
    /**
     * @dataProvider \TESTS\DATA\DataProviders::createDateTimeValidDateTimeStringInputProvider
     */
    public function testCreateDateTimeValidInput($dateTimeInputString, $expectedFormattedDateTimeString): void
    {
        $dateTimeTools = new DateTimeTools();
        $dateTime = $dateTimeTools->createDateTime($dateTimeInputString);
        $dateTimeString = $dateTime->format("Y-m-d\TH:i:sP");
        $this->assertEquals($expectedFormattedDateTimeString, $dateTimeString);
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::createDateTimeInvalidDateTimeStringInputProvider
     * @expectedException Exception
     * @expectedExceptionMessage Failed to parse time string
     */
    public function testCreateDateTimeInvalidInput($dateTimeInputString): void
    {
        $dateTimeTools = new DateTimeTools();
        $dateTime = $dateTimeTools->createDateTime($dateTimeInputString);
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::dateTimeDifferenceInXsdDurationValidDateTimeStringsInputProvider
     */
    public function testDateTimeDifferenceInXsdDurationValidInput(
        $dateTimeInputString1,
        $dateTimeInputString2,
        $expectedXsdDurationString
    ): void {
        $dateTimeTools = new DateTimeTools();
        $dateTime1 = $dateTimeTools->createDateTime($dateTimeInputString1);
        $dateTime2 = $dateTimeTools->createDateTime($dateTimeInputString2);
        $xsdDurationString = $dateTimeTools->dateTimeDifferenceInXsdDuration($dateTime1, $dateTime2);
        $this->assertEquals($expectedXsdDurationString, $xsdDurationString);
    }
}
