<?php
namespace SMA\PAA\AGENT\VIS;

use PHPUnit\Framework\TestCase;

final class VisRtzTest extends TestCase
{
    /**
     * @dataProvider \TESTS\DATA\DataProviders::getVesselVoyageProvider
     */
    public function testGetVesselVoyage($rtz, $expectedVesselVoyage): void
    {
        $visRtz = new VisRtz($rtz);
        $vesselVoyage = $visRtz->getVesselVoyage();
        $this->assertEquals($expectedVesselVoyage, json_encode($vesselVoyage));
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::getVesselVoyageProvider
     */
    public function testSetVesselVoyage($rtz): void
    {
        $visRtz = new VisRtz($rtz);
        $visRtz->setVesselVoyage("urn:mrn:stm:voyage:id:testing:1234567890");
        $vesselVoyage = $visRtz->getVesselVoyage();
        $this->assertEquals("urn:mrn:stm:voyage:id:testing:1234567890", $vesselVoyage["complete"]);
        $this->assertEquals("1234567890", $vesselVoyage["postfix"]);
        $this->assertEquals("urn:mrn:stm:voyage:id:testing:", $vesselVoyage["prefix"]);
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::getRouteStatusEnumProvider
     */
    public function testGetRouteStatusEnum($rtz, $expectedRouteStatusEnum): void
    {
        $visRtz = new VisRtz($rtz);
        $routeStatusEnum = $visRtz->getRouteStatusEnum();
        $this->assertEquals($expectedRouteStatusEnum, json_encode($routeStatusEnum));
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::getRouteStatusEnumProvider
     */
    public function testSetRouteStatusEnum($rtz): void
    {
        $visRtz = new VisRtz($rtz);
        $visRtz->setRouteStatusEnum("5");
        $routeStatusEnum = $visRtz->getRouteStatusEnum();
        $this->assertEquals("5", $routeStatusEnum);
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::getWaypointsProvider
     */
    public function testGetWaypoints($rtz, $expectedWaypoints): void
    {
        $visRtz = new VisRtz($rtz);
        $waypoints = $visRtz->getWaypoints();
        $this->assertEquals($expectedWaypoints, json_encode($waypoints));
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::getWaypointIdsProvider
     */
    public function testGetWaypointIds($rtz, $expectedWaypointIds): void
    {
        $visRtz = new VisRtz($rtz);
        $waypointIds = $visRtz->getWaypointIds();
        $this->assertEquals($expectedWaypointIds, json_encode($waypointIds));
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::newWaypointReturnProvider
     */
    public function testNewWaypointReturn($rtz, $expectedNewWaypointReturn): void
    {
        $visRtz = new VisRtz($rtz);
        $newWaypointReturn = $visRtz->newWaypoint("Test Waypoint", 20, ["lat" => 12.345, "lon" => 67.890]);
        $this->assertEquals($expectedNewWaypointReturn, json_encode($newWaypointReturn));
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::newWaypointRtzProvider
     */
    public function testNewWaypointRtz($rtz, $expectedNewWaypointRtz): void
    {
        $visRtz = new VisRtz($rtz);
        $visRtz->newWaypoint("Test Waypoint", 20, ["lat" => 12.345, "lon" => 67.890]);
        $newWaypointRtz = $visRtz->toXmlString();
        $this->assertEquals($expectedNewWaypointRtz, json_encode($newWaypointRtz));
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::getCalculatedScheduleProvider
     */
    public function testGetCalculatedSchedule($rtz, $expectedCalculatedSchedule): void
    {
        $visRtz = new VisRtz($rtz);
        $calculatedSchedule = $visRtz->getCalculatedSchedule();
        $this->assertEquals($expectedCalculatedSchedule, json_encode($calculatedSchedule));
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::deleteCalculatedScheduleRtzProvider
     */
    public function testDeleteCalculatedScheduleRtz($rtz, $expectedDeleteCalculatedScheduleRtz): void
    {
        $visRtz = new VisRtz($rtz);
        $visRtz->deleteCalculatedSchedule();
        $deleteCalculatedScheduleRtz = $visRtz->toXmlString();
        $this->assertEquals($expectedDeleteCalculatedScheduleRtz, json_encode($deleteCalculatedScheduleRtz));
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::getEtaProvider
     */
    public function testGetEta($rtz, $expectedEta): void
    {
        $visRtz = new VisRtz($rtz);
        $eta = $visRtz->getEta(60);
        $this->assertEquals($expectedEta, json_encode($eta));
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::setEtaRtzProvider
     */
    public function testSetEtaRtz($rtz, $expectedSetEtaRtz): void
    {
        $visRtz = new VisRtz($rtz);
        $visRtz->setEta(60, "2019-12-24T21:20:19+00:00", "PT10M", "PT15M");
        $setEtaRtz = $visRtz->toXmlString();
        $this->assertEquals($expectedSetEtaRtz, json_encode($setEtaRtz));
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::getScheduleNameProvider
     */
    public function testGetScheduleName($rtz, $expectedScheduleName): void
    {
        $visRtz = new VisRtz($rtz);
        $scheduleName = $visRtz->getScheduleName();
        $this->assertEquals($expectedScheduleName, json_encode($scheduleName));
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::setScheduleNameRtzProvider
     */
    public function testSetScheduleNameRtz($rtz, $expectedSetScheduleNameRtz): void
    {
        $visRtz = new VisRtz($rtz);
        $visRtz->setScheduleName("Test Schedule");
        $setScheduleNameRtz = $visRtz->toXmlString();
        $this->assertEquals($expectedSetScheduleNameRtz, json_encode($setScheduleNameRtz));
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::getRouteAuthorProvider
     */
    public function testGetRouteAuthor($rtz, $expectedRouteAuthor): void
    {
        $visRtz = new VisRtz($rtz);
        $routeAuthor = $visRtz->getRouteAuthor();
        $this->assertEquals($expectedRouteAuthor, json_encode($routeAuthor));
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::setRouteAuthorRtzProvider
     */
    public function testSetRouteAuthorRtz($rtz, $expectedSetRouteAuthorRtz): void
    {
        $visRtz = new VisRtz($rtz);
        $visRtz->setRouteAuthor("Test Route Author");
        $setRouteAuthorRtz = $visRtz->toXmlString();
        $this->assertEquals($expectedSetRouteAuthorRtz, json_encode($setRouteAuthorRtz));
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::setRouteStatusRtzProvider
     */
    public function testSetRouteStatusRtz($rtz, $expectedSetRouteStatusRtz): void
    {
        $visRtz = new VisRtz($rtz);
        $visRtz->setRouteStatus("Test Route Status");
        $setRouteStatusRtz = $visRtz->toXmlString();
        $this->assertEquals($expectedSetRouteStatusRtz, json_encode($setRouteStatusRtz));
    }
}
