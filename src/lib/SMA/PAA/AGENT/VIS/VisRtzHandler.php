<?php
namespace SMA\PAA\AGENT\VIS;

use SMA\PAA\AGENT\VIS\VisClient;
use SMA\PAA\AGENT\VIS\VisRtz;
use SMA\PAA\TOOL\IGeoTool;
use SMA\PAA\TOOL\GeoPlotTool;

use Exception;
use InvalidArgumentException;

class VisRtzHandler
{
    const UNDEFINED = 0;
    const INCOMING_RTZ_NOT_RELEVANT = 1;
    const INCOMING_RTZ_CALCULATED_SCHEDULE_NOT_FOUND = 2;
    const INCOMING_RTZ_SYNC_WITH_ETA_FOUND = 3;
    const INCOMING_RTZ_SYNC_WITHOUT_ETA_FOUND = 4;
    const INCOMING_RTZ_SYNC_NOT_FOUND_CAN_BE_ADDED = 5;
    const INCOMING_RTZ_SYNC_NOT_FOUND_CAN_NOT_BE_ADDED = 6;
    const INCOMING_RTZ_PORT_TO_PORT = 7;

    private $visClient;
    private $geoTool;
    private $geoPlotTool;
    private $portCoordinates;
    private $portRadius;
    private $syncPointCoordinates;
    private $syncPointRadius;
    private $rtzRouteAuthor;
    private $rtzScheduleName;

    public function __construct(
        VisClient $visClient,
        IGeoTool $geoTool
    ) {
        $this->visClient = $visClient;
        $this->geoTool = $geoTool;
        $this->geoPlotTool = new GeoPlotTool();

        $this->portCoordinates = $this->geoTool->createLatLon(
            $this->visClient->getPortCoordinates()["lat"],
            $this->visClient->getPortCoordinates()["lon"]
        );

        $this->portRadius = $this->visClient->getPortRadius();

        $this->syncPointCoordinates = $this->geoTool->createLatLon(
            $this->visClient->getSyncPointCoordinates()["lat"],
            $this->visClient->getSyncPointCoordinates()["lon"]
        );

        $this->syncPointRadius = $this->visClient->getSyncPointRadius();

        $this->rtzRouteAuthor = $this->visClient->getRtzRouteAuthor();
        $this->rtzScheduleName = $this->visClient->getRtzScheduleName();
    }

    private function commonRtzChanges(VisRtz $visRtz)
    {
        $visRtz->setRouteStatusEnum(3);
        $visRtz->deleteCalculatedSchedule();
        $visRtz->setScheduleName($this->rtzScheduleName);
        $visRtz->setRouteAuthor($this->rtzRouteAuthor);
        $visRtz->setRouteStatus("Optimized");
    }


    public function incomingRtz(string $rtz): array
    {
        $routeName = "UNKNOWN";
        preg_match('/routeName=\"(.*?)\"/', $rtz, $routeNames);
        if (isset($routeNames[1])) {
            $routeName = $routeNames[1];
        }
        error_log("Incoming RTZ: " . $routeName);

        $res = [];

        $res["status"] = VisRtzHandler::UNDEFINED;
        $res["route_name"] = $routeName;
        $visRtz = new VisRtz($rtz);

        $routeStatusEnum = $visRtz->getRouteStatusEnum();
        $waypoints = $visRtz->getWaypoints();

        if ($this->isVoyagePlanRelevant($routeStatusEnum, $waypoints)) {
            error_log("Voyage plan is relevant");
            $res["vessel_imo"] = $visRtz->getVesselImo();
            $res["vessel_name"] = $visRtz->getVesselName();
            error_log("Vessel: " . $res["vessel_name"] . "," . $res["vessel_imo"]);

            $calculatedSchedules = $visRtz->getCalculatedSchedule();

            if ($calculatedSchedules !== null) {
                error_log("Calculated schedule(s) found");
                $waypointId = $this->findSyncPoint($waypoints);

                if ($waypointId !== "") {
                    error_log("Sync point found at waypoint ID: " . $waypointId);
                    $syncPointEta = $visRtz->getEta($waypointId);

                    if ($syncPointEta !== "") {
                        error_log("ETA found for sync point: " . $syncPointEta);
                        $res["status"] = VisRtzHandler::INCOMING_RTZ_SYNC_WITH_ETA_FOUND;
                        $res["waypointId"] = $waypointId;
                        $res["eta"] = $syncPointEta;
                    } else {
                        // This can happen if we have waypoint close to syncro point
                        // but it does not have calculated schedule attached to it
                        error_log("No ETA found for sync point");
                        $res["status"] = VisRtzHandler::INCOMING_RTZ_SYNC_WITHOUT_ETA_FOUND;
                        $res["waypointId"] = $waypointId;
                    }
                } else {
                    error_log("No sync point found. Trying to add.");
                    $matchResults = $this->matchSyncPoint($waypoints);

                    if (!empty($matchResults)) {
                        error_log("Sync point can be added without changing route geometry");
                        error_log("Add before waypoint ID: " . $matchResults["legEndWaypointId"]);
                        error_log("At coordinates: " . $matchResults["lat"] . "," . $matchResults["lon"]);
                        $res["status"] = VisRtzHandler::INCOMING_RTZ_SYNC_NOT_FOUND_CAN_BE_ADDED;
                        $res["legEndWaypointId"] = $matchResults["legEndWaypointId"];
                        $res["lat"] = $matchResults["lat"];
                        $res["lon"] = $matchResults["lon"];
                    } else {
                        error_log("No sync point can be added without changing route geometry");
                        $res["status"] = VisRtzHandler::INCOMING_RTZ_SYNC_NOT_FOUND_CAN_NOT_BE_ADDED;
                    }
                }
            } else {
                error_log("No calculated schedule found");
                $res["status"] = VisRtzHandler::INCOMING_RTZ_CALCULATED_SCHEDULE_NOT_FOUND;
            }
        } elseif ($this->isVoyagePlanPortToPort($visRtz->getVesselVoyage(), $routeStatusEnum, $waypoints)) {
            error_log("Voyage plan is port to port");
            $res["status"] = VisRtzHandler::INCOMING_RTZ_PORT_TO_PORT;
            $res["vessel_imo"] = $visRtz->getVesselImo();
            $res["vessel_name"] = $visRtz->getVesselName();
            $res["time"] = $visRtz->getManualEtd(1);
        } else {
            error_log("Voyage plan is not relevant");
            $res["status"] = VisRtzHandler::INCOMING_RTZ_NOT_RELEVANT;
        }

        return $res;
    }

    public function outgoingRtz(string $rtz): int
    {
        $res = VisRtzHandler::UNDEFINED;

        // todo

        return $res;
    }

    public function setEtaToWaypoint(
        string $eta,
        string $windowBefore,
        string $windowAfter,
        int $waypointId,
        string $rtz
    ): string {
        $visRtz = new VisRtz($rtz);

        $this->commonRtzChanges($visRtz);
        $visRtz->setEta($waypointId, $eta, $windowBefore, $windowAfter);

        return $visRtz->toXmlString();
    }

    public function setEtaToNewWaypoint(
        string $eta,
        string $windowBefore,
        string $windowAfter,
        string $waypointName,
        int $insertBeforeWaypointId,
        array $latLon,
        string $rtz
    ): string {
        $visRtz = new VisRtz($rtz);

        $this->commonRtzChanges($visRtz);
        $newWaypointId = $visRtz->newWaypoint($waypointName, $insertBeforeWaypointId, $latLon, $eta);
        $this->commonRtzChanges($visRtz);
        $visRtz->setEta($newWaypointId, $eta, $windowBefore, $windowAfter);

        return $visRtz->toXmlString();
    }

    public function isVoyagePlanRelevant(string $routeStatusEnum, array $waypoints): bool
    {
        error_log("Relevancy check");
        error_log("Port: " . $this->portCoordinates["lat"] . "," . $this->portCoordinates["lon"]
        . " Search radius: " . $this->portRadius);

        $res = false;

        $waypointWithinPortRadius = false;

        // Check only last 3 waypoints, since in theory only the last waypoint should be relevant
        $lastWaypoints = array_slice($waypoints, -3, 3, true);

        foreach ($lastWaypoints as $waypoint) {
            $waypointCoordinates = $this->geoTool->createLatLon(
                $waypoint["lat"],
                $waypoint["lon"]
            );

            $dist = $this->geoTool->pointDistanceVincenty(
                $this->portCoordinates,
                $waypointCoordinates
            );

            error_log("Near port check: " . $waypointCoordinates["lat"] . "," . $waypointCoordinates["lon"]
            . " Distance: " . $dist);

            if ($dist <= $this->portRadius) {
                $waypointWithinPortRadius = true;
            }
        }

        error_log("Route status enum: " . $routeStatusEnum);

        // If route is monitored and within port radius, it is relevant
        if ($routeStatusEnum === "7" && $waypointWithinPortRadius) {
            $res = true;
        }

        return $res;
    }

    public function isVoyagePlanPortToPort(array $vesselVoyage, string $routeStatusEnum, array $waypoints): bool
    {
        error_log("Port to port check");

        // First check if vesselVoyage field contains unikieporttoport
        error_log("vesselVoyage: " . $vesselVoyage["complete"]);
        if (strpos($vesselVoyage["complete"], ":unikieporttoport:") === false) {
            error_log("vesselVoyage does not indicate port to port communications");
            return false;
        }

        error_log("Port: " . $this->portCoordinates["lat"] . "," . $this->portCoordinates["lon"]
        . " Search radius: " . $this->portRadius);

        $res = false;

        $waypointWithinPortRadius = false;

        // Check last waypoint since it contains the destination port
        $waypoint = end($waypoints);

        $waypointCoordinates = $this->geoTool->createLatLon(
            $waypoint["lat"],
            $waypoint["lon"]
        );

        $dist = $this->geoTool->pointDistanceVincenty(
            $this->portCoordinates,
            $waypointCoordinates
        );

        error_log("Near port check: " . $waypointCoordinates["lat"] . "," . $waypointCoordinates["lon"]
        . " Distance: " . $dist);

        if ($dist <= $this->portRadius) {
            $waypointWithinPortRadius = true;
        }

        error_log("Route status enum: " . $routeStatusEnum);

        // If route is planned and within port radius, it is port to port
        if ($routeStatusEnum === "2" && $waypointWithinPortRadius) {
            $res = true;
        }

        return $res;
    }

    public function findSyncPoint(array $waypoints): string
    {
        $res = "";

        $waypointsWithinRadius = [];
        foreach ($waypoints as $waypoint) {
            $waypointCoordinates = $this->geoTool->createLatLon(
                $waypoint["lat"],
                $waypoint["lon"]
            );

            $dist = $this->geoTool->pointDistanceVincenty(
                $this->syncPointCoordinates,
                $waypointCoordinates
            );

            if ($dist <= $this->syncPointRadius) {
                $waypointsWithinRadius[$waypoint["id"]] = $dist;
            }
        }

        if (!empty($waypointsWithinRadius)) {
            asort($waypointsWithinRadius);
            reset($waypointsWithinRadius);
            $res = key($waypointsWithinRadius);
        }

        return $res;
    }

    public function matchSyncPoint(array $waypoints): array
    {
        $res = [];

        $ctdResults = [];
        $startWaypoint = reset($waypoints);

        while ($startWaypoint !== false) {
            $startWaypointCoordinates = $this->geoTool->createLatLon(
                $startWaypoint["lat"],
                $startWaypoint["lon"]
            );

            $startKey = key($waypoints);
            $endWaypoint = next($waypoints);

            if ($endWaypoint !== false) {
                $endKey = key($waypoints);
                $endWaypointCoordinates = $this->geoTool->createLatLon(
                    $endWaypoint["lat"],
                    $endWaypoint["lon"]
                );

                $dist = $this->geoTool->crossTrackDistanceToArc(
                    $startWaypointCoordinates,
                    $endWaypointCoordinates,
                    $this->syncPointCoordinates
                );

                if ($dist !== null) {
                    if ($dist <= $this->syncPointRadius) {
                        $ctdResults["start"] = $startKey;
                        $ctdResults["end"] = $endKey;
                        $ctdResults["dist"] = $dist;
                    }
                }
            }

            $startWaypoint = $endWaypoint;
        }

        if (!empty($ctdResults)) {
            $startWaypointCoordinates = $this->geoTool->createLatLon(
                $waypoints[$ctdResults["start"]]["lat"],
                $waypoints[$ctdResults["start"]]["lon"]
            );

            $endWaypointCoordinates = $this->geoTool->createLatLon(
                $waypoints[$ctdResults["end"]]["lat"],
                $waypoints[$ctdResults["end"]]["lon"]
            );

            if ($waypoints[$ctdResults["end"]]["geometryType"] === "Loxodrome") {
                $res = $this->geoTool->crossTrackToRhumbLine(
                    $startWaypointCoordinates,
                    $endWaypointCoordinates,
                    $this->syncPointCoordinates
                );
                $res["legEndWaypointId"] = $waypoints[$ctdResults["end"]]["id"];
            } elseif ($waypoints[$ctdResults["end"]]["geometryType"] === "Orthodrome") {
                $dist = $this->geoTool->alongTrackDistance(
                    $startWaypointCoordinates,
                    $endWaypointCoordinates,
                    $this->syncPointCoordinates,
                    $ctdResults["dist"]
                );
                $res = $this->geoTool->destination(
                    $startWaypointCoordinates,
                    $endWaypointCoordinates,
                    $dist
                );
                $res["legEndWaypointId"] = $waypoints[$ctdResults["end"]]["id"];
            } else {
                throw new Exception("Unknown geometry type: " . $waypoints[$ctdResults["end"]]["geometryType"]);
            };
        }

        return $res;
    }
}
