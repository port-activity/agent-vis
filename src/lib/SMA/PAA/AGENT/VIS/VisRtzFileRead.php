<?php
namespace SMA\PAA\AGENT\VIS;

use SMA\PAA\AGENT\VIS\VisClient;
use SMA\PAA\AGENT\VIS\VisRtzHandler;
use SMA\PAA\AGENT\VIS\VisRtz;
use SMA\PAA\TOOL\IGeoTool;
use SMA\PAA\TOOL\GeoPlotTool;

use Exception;
use InvalidArgumentException;

class VisRtzFileRead
{
    private $visClient;
    private $outputDirectory;
    private $rtzTestOutDirectory;
    private $rtzFile;
    private $rtzHandler;
    private $geoPlotTool;
    private $portCoordinates;
    private $portRadius;
    private $syncpointName;
    private $syncPointCoordinates;
    private $syncPointRadius;

    public function __construct(
        VisClient $visClient,
        IGeoTool $geoTool,
        string $outputDirectory,
        string $rtzFile
    ) {
        $this->visClient = $visClient;
        $this->geoTool = $geoTool;
        $this->rtzFile = $rtzFile;
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

        $this->syncpointName = $this->visClient->getSyncPointName();
        $this->syncPointRadius = $this->visClient->getSyncPointRadius();

        if (!is_dir($outputDirectory)) {
            throw new InvalidArgumentException("Given output directory does not exist: " . $outputDirectory);
        }

        $clientDir = str_replace(":", "-", $this->visClient->getServiceInstanceUrn());
        $this->outputDirectory = $outputDirectory . "/" . $clientDir;
        if (!is_dir($this->outputDirectory)) {
            mkdir($this->outputDirectory);
        }

        $this->rtzTestOutDirectory = $this->outputDirectory . "/rtztestout";
        if (!is_dir($this->rtzTestOutDirectory)) {
            mkdir($this->rtzTestOutDirectory);
        }

        $this->rtzHandler = new VisRtzHandler($this->visClient, $this->geoTool);
    }

    private function plotWaypoints(string $rtz)
    {
        $visRtz = new VisRtz($rtz);

        $waypoints = $visRtz->getWaypoints();

        foreach ($waypoints as $waypoint) {
            $latLon = $this->geoTool->createLatLon(
                $waypoint["lat"],
                $waypoint["lon"]
            );
            $this->geoPlotTool->addPoint($latLon, "green");
        }
    }

    public function execute()
    {
        $this->readRtzFile();
    }

    public function readRtzFile()
    {
        $rtz = file_get_contents($this->rtzFile);

        preg_match('/routeName=\".*?\"/', $rtz, $routeName);
        preg_match('/routeStatusEnum=\".*?\"/', $rtz, $routeStatusEnum);
        $debugStr = "," . $routeName[0];
        $debugStr .= "," . $routeStatusEnum[0];

        $handlerResult = $this->rtzHandler->incomingRtz($rtz);

        $newRtz = "";
        switch ($handlerResult["status"]) {
            case VisRtzHandler::UNDEFINED:
                throw new Exception("VisRtzHandler status is UNDEFINED");
                break;
            case VisRtzHandler::INCOMING_RTZ_NOT_RELEVANT:
                $debugStr = "VisRtzHandler::INCOMING_RTZ_NOT_RELEVANT" . $debugStr;
                break;
            case VisRtzHandler::INCOMING_RTZ_CALCULATED_SCHEDULE_NOT_FOUND:
                $debugStr = "VisRtzHandler::INCOMING_RTZ_CALCULATED_SCHEDULE_NOT_FOUND" . $debugStr;
                break;
            case VisRtzHandler::INCOMING_RTZ_SYNC_WITH_ETA_FOUND:
                $debugStr .= ",waypointId=" . $handlerResult["waypointId"];
                $debugStr .= ",eta=" . $handlerResult["eta"];
                $debugStr = "VisRtzHandler::INCOMING_RTZ_SYNC_WITH_ETA_FOUND" . $debugStr;
                $eta = "2019-12-24T21:20:19Z";
                $windowBefore = "PT30M";
                $windowAfter = "PT20M";
                $waypointId = $handlerResult["waypointId"];
                $newRtz = $this->rtzHandler->setEtaToWaypoint($eta, $windowBefore, $windowAfter, $waypointId, $rtz);
                break;
            case VisRtzHandler::INCOMING_RTZ_SYNC_WITHOUT_ETA_FOUND:
                $debugStr = "VisRtzHandler::INCOMING_RTZ_SYNC_WITHOUT_ETA_FOUND" . $debugStr;
                $eta = "2019-12-24T21:20:19Z";
                $windowBefore = "PT30M";
                $windowAfter = "PT20M";
                $waypointId = $handlerResult["waypointId"];
                $newRtz = $this->rtzHandler->setEtaToWaypoint($eta, $windowBefore, $windowAfter, $waypointId, $rtz);
                break;
            case VisRtzHandler::INCOMING_RTZ_SYNC_NOT_FOUND_CAN_BE_ADDED:
                $debugStr = "VisRtzHandler::INCOMING_RTZ_SYNC_NOT_FOUND_CAN_BE_ADDED" . $debugStr;
                $eta = "2019-12-24T21:20:19Z";
                $windowBefore = "PT30M";
                $windowAfter = "PT20M";
                $waypointId = $handlerResult["legEndWaypointId"];
                $latLon["lat"] = $handlerResult["lat"];
                $latLon["lon"] = $handlerResult["lon"];
                $newRtz = $this->rtzHandler->setEtaToNewWaypoint(
                    $eta,
                    $windowBefore,
                    $windowAfter,
                    $this->syncpointName,
                    $waypointId,
                    $latLon,
                    $rtz
                );
                break;
            case VisRtzHandler::INCOMING_RTZ_SYNC_NOT_FOUND_CAN_NOT_BE_ADDED:
                $debugStr = "VisRtzHandler::INCOMING_RTZ_SYNC_NOT_FOUND_CAN_NOT_BE_ADDED" . $debugStr;
                break;
            default:
                throw new Exception("VisRtzHandler status is unknown");
                break;
        }

        $debugStr .= "," . str_replace(" ", "\\ ", $this->rtzFile) . "\n";
        #print($newRtz);
        print($debugStr);
    }
}
