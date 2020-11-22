<?php

namespace SMA\PAA\TESTING;

use SMA\PAA\CURL\CurlRequest;
use SMA\PAA\AGENT\VIS\VisClient;
use SMA\PAA\AGENT\VIS\VisRtzHandler;
use SMA\PAA\TOOL\GeoTool;

class VisRtzTest
{
    private $rtz;
    private $rta;
    private $windowBefore;
    private $windowAfter;
    private $visClient;
    private $rtzHandler;

    public function __construct($rtz, $rta, $windowBefore, $windowAfter)
    {
        $this->rtz = $rtz;
        $this->rta = $rta;
        $this->windowBefore = $windowBefore;
        $this->windowAfter = $windowAfter;
        $this->visClient = new VisClient(
            new CurlRequest(),
            "stm",
            "sma",
            "https://smavistest.stmvalidation.eu/UNIKIE01",
            444,
            "urn:mrn:stm:service:instance:sma:vis:portofrauma",
            "Port of Rauma operator",
            "Port of Rauma schedule",
            61.1297155,
            21.4491551,
            3704,
            "Rauma pilot boarding ground",
            61.11806,
            21.16778,
            1852,
            false,
            "",
            ""
        );
        $this->rtzHandler = new VisRtzHandler($this->visClient, new GeoTool());
    }

    public function execute()
    {
        $this->processRtz();
    }

    public function processRtz()
    {
        preg_match('/routeName=\".*?\"/', $this->rtz, $routeName);
        preg_match('/routeStatusEnum=\".*?\"/', $this->rtz, $routeStatusEnum);
        $debugStr = "," . $routeName[0];
        $debugStr .= "," . $routeStatusEnum[0];

        $handlerResult = $this->rtzHandler->incomingRtz($this->rtz);

        $newRtz = "";
        switch ($handlerResult["status"]) {
            case VisRtzHandler::UNDEFINED:
                $debugStr = "VisRtzHandler::UNDEFINED" . $debugStr;
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
                $waypointId = $handlerResult["waypointId"];
                $newRtz = $this->rtzHandler->setEtaToWaypoint(
                    $this->rta,
                    $this->windowBefore,
                    $this->windowAfter,
                    $waypointId,
                    $this->rtz
                );
                break;
            case VisRtzHandler::INCOMING_RTZ_SYNC_WITHOUT_ETA_FOUND:
                $debugStr = "VisRtzHandler::INCOMING_RTZ_SYNC_WITHOUT_ETA_FOUND" . $debugStr;
                $waypointId = $handlerResult["waypointId"];
                $newRtz = $this->rtzHandler->setEtaToWaypoint(
                    $this->rta,
                    $this->windowBefore,
                    $this->windowAfter,
                    $waypointId,
                    $this->rtz
                );
                break;
            case VisRtzHandler::INCOMING_RTZ_SYNC_NOT_FOUND_CAN_BE_ADDED:
                $debugStr = "VisRtzHandler::INCOMING_RTZ_SYNC_NOT_FOUND_CAN_BE_ADDED" . $debugStr;
                $waypointId = $handlerResult["legEndWaypointId"];
                $latLon["lat"] = $handlerResult["lat"];
                $latLon["lon"] = $handlerResult["lon"];
                $newRtz = $this->rtzHandler->setEtaToNewWaypoint(
                    $this->rta,
                    $this->windowBefore,
                    $this->windowAfter,
                    $this->visClient->getSyncPointName(),
                    $waypointId,
                    $latLon,
                    $this->rtz
                );
                break;
            case VisRtzHandler::INCOMING_RTZ_SYNC_NOT_FOUND_CAN_NOT_BE_ADDED:
                $debugStr = "VisRtzHandler::INCOMING_RTZ_SYNC_NOT_FOUND_CAN_NOT_BE_ADDED" . $debugStr;
                break;
            default:
                $debugStr = "Unknown VisRtzHandler state" . $debugStr;
                break;
        }

        if ($newRtz !== "") {
            header('Content-Type: application/xml');
            header('Content-Disposition: attachment; filename="modified.rtz"');
            echo($newRtz);
            exit();
        } else {
            print("\n<br><pre>\n");
            print($debugStr);
            print("</pre>\n");
        }
    }
}
