<?php
namespace SMA\PAA\AGENT\VIS;

use PHPUnit\Framework\TestCase;

use TESTS\DATA\DataProviders;

final class VisRtzHandlerTest extends TestCase
{
    /**
     * @dataProvider \TESTS\DATA\DataProviders::setEtaToWaypointRtzProvider
    */
    public function testSetEtaToWaypoint($rtz, $expectedSetEtaToWaypointRtz): void
    {
        $rtzHandler = new VisRtzHandler(DataProviders::getVisClient(), DataProviders::getGeoTool());
        $setEtaToWaypointRtz = $rtzHandler->setEtaToWaypoint("2019-12-24T21:20:19+00:00", "PT10M", "PT15M", 60, $rtz);
        $this->assertEquals($expectedSetEtaToWaypointRtz, json_encode($setEtaToWaypointRtz));
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::setEtaToNewWaypointRtzProvider
    */
    public function testSetEtaToNewWaypoint($rtz, $expectedSetEtaToNewWaypointRtz): void
    {
        $rtzHandler = new VisRtzHandler(DataProviders::getVisClient(), DataProviders::getGeoTool());
        $setEtaToNewWaypointRtz = $rtzHandler->setEtaToNewWaypoint(
            "2019-12-24T21:20:19+00:00",
            "PT20M",
            "PT30M",
            "Test Waypoint",
            60,
            ["lat" => 12.345, "lon" => 67.890],
            $rtz
        );

        $this->assertEquals($expectedSetEtaToNewWaypointRtz, json_encode($setEtaToNewWaypointRtz));
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::isVoyagePlanRelevantProvider
    */
    public function testIsVoyagePlanRelevant($routeStatusEnum, $waypoints, $expectedResult): void
    {
        $rtzHandler = new VisRtzHandler(DataProviders::getVisClient(), DataProviders::getGeoTool());
        $result = $rtzHandler->isVoyagePlanRelevant($routeStatusEnum, $waypoints);

        $this->assertEquals($result, $expectedResult);
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::findSyncPointProvider
    */
    public function testFindSyncPoint($waypoints, $expectedResult): void
    {
        $rtzHandler = new VisRtzHandler(DataProviders::getVisClient(), DataProviders::getGeoTool());
        $result = $rtzHandler->findSyncPoint($waypoints);

        $this->assertEquals($expectedResult, json_encode($result));
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::matchSyncPointProvider
    */
    public function testMatchSyncPoint($waypoints, $expectedResult): void
    {
        $rtzHandler = new VisRtzHandler(DataProviders::getVisClient(), DataProviders::getGeoTool());
        $result = $rtzHandler->matchSyncPoint($waypoints);

        $this->assertEquals($expectedResult, json_encode($result));
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::incomingRtzProvider
    */
    public function testIncomingRtz($rtz, $expectedResult): void
    {
        $rtzHandler = new VisRtzHandler(DataProviders::getVisClient(), DataProviders::getGeoTool());
        $result = $rtzHandler->incomingRtz($rtz);

        $this->assertEquals($expectedResult, json_encode($result));
    }
}
