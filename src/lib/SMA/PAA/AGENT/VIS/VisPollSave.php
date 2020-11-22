<?php
namespace SMA\PAA\AGENT\VIS;

use SMA\PAA\AGENT\VIS\VisClient;
use SMA\PAA\AGENT\VIS\VisRtzHandler;
use SMA\PAA\TOOL\IGeoTool;
use SMA\PAA\AGENT\ApiConfig;
use SMA\PAA\RESULTPOSTER\IResultPoster;
use SMA\PAA\AINO\AinoClient;

use Exception;
use InvalidArgumentException;

class VisPollSave
{
    private $resultPoster;
    private $visClient;
    private $outputDirectory;
    private $notificationDirectory;
    private $messageDirectory;
    private $rtzHandler;
    private $ainoToVis;
    private $ainoFromVis;
    private $ainoToApi;
    private $ainoFromApi;

    public function __construct(
        IResultPoster $resultPoster,
        VisClient $visClient,
        IGeoTool $geoTool,
        string $outputDirectory,
        AinoClient $ainoToVis = null,
        AinoClient $ainoFromVis = null,
        AinoClient $ainoToApi = null,
        AinoClient $ainoFromApi = null
    ) {
        $this->resultPoster = $resultPoster;
        $this->visClient = $visClient;
        $this->geoTool = $geoTool;
        $this->ainoToVis = $ainoToVis;
        $this->ainoFromVis = $ainoFromVis;
        $this->ainoToApi = $ainoToApi;
        $this->ainoFromApi = $ainoFromApi;

        if (!is_dir($outputDirectory)) {
            throw new InvalidArgumentException("Given output directory does not exist: " . $outputDirectory);
        }

        $clientDir = str_replace(":", "-", $this->visClient->getServiceInstanceUrn());
        $this->outputDirectory = $outputDirectory . "/" . $clientDir;
        if (!is_dir($this->outputDirectory)) {
            if (!mkdir($this->outputDirectory)) {
                throw new Exception("Cannot create directory: " . $this->outputDirectory);
            }
        }

        $this->notificationDirectory = $this->outputDirectory . "/notifications";
        if (!is_dir($this->notificationDirectory)) {
            if (!mkdir($this->notificationDirectory)) {
                throw new Exception("Cannot create directory: " . $this->notificationDirectory);
            }
        }

        $this->messageDirectory = $this->outputDirectory . "/messages";
        if (!is_dir($this->messageDirectory)) {
            if (!mkdir($this->messageDirectory)) {
                throw new Exception("Cannot create directory: " . $this->messageDirectory);
            }
        }

        $this->rtzHandler = new VisRtzHandler($this->visClient, $this->geoTool);
    }

    private function ainoToApiFail(
        string $payloadType,
        array $ids = [],
        array $meta = [],
        string $flowId = null
    ) {
        if (isset($this->ainoToApi)) {
            $this->ainoToApi->failure(
                gmdate("Y-m-d\TH:i:s\Z"),
                "VIS agent failed",
                "Post",
                $payloadType,
                $ids,
                $meta,
                $flowId
            );
        }
    }

    private function ainoFromVisFail(
        string $payloadType,
        array $ids = [],
        array $meta = [],
        string $flowId = null
    ) {
        if (isset($this->ainoToApi)) {
            $this->ainoToApi->failure(
                gmdate("Y-m-d\TH:i:s\Z"),
                "VIS agent failed",
                "VIS service call",
                $payloadType,
                $ids,
                $meta,
                $flowId
            );
        }
    }

    public function execute(
        ApiConfig $apiVisNotificationConfig,
        ApiConfig $apiVisMessageConfig,
        ApiConfig $apiVisVoyagePlanConfig,
        ApiConfig $apiTimestampConfig,
        ApiConfig $apiInboundVesselsConfig
    ) {
        $this->processNotifications($apiVisNotificationConfig);
        $this->processMessages(
            $apiVisMessageConfig,
            $apiVisVoyagePlanConfig,
            $apiTimestampConfig,
            $apiInboundVesselsConfig
        );
    }

    private function createLocalFilename(array $visItem, string $time, string $parentDirectory): string
    {
        $filename = "";

        $prefix = str_replace(":", "-", $time);
        $prefix = str_replace(".", "-", $time);

        if (isset($visItem["id"])) {
            $postfix = $visItem["id"];
            $postfix = str_replace(":", "-", $postfix);
        } else {
            $json = json_encode($visItem);
            $postfix = md5($json);
        }

        $filename = $parentDirectory . "/" . $prefix . $postfix . ".json";

        return $filename;
    }

    private function saveVisNotificationLocally(array $visNotification): string
    {
        $filename = "";

        $time = "0000-00-00T00:00:00.000Z";

        if (isset($visNotification["NotificationCreatedAt"])) {
            $time = $visNotification["NotificationCreatedAt"];
        } elseif (isset($visNotification["ReceivedAt"])) {
            $time = $visNotification["ReceivedAt"];
        }

        $filename = $this->createLocalFilename($visNotification, $time, $this->notificationDirectory);
        $json = json_encode($visNotification);
        if (file_put_contents($filename, $json) === false) {
            throw new Exception("Cannot save: " . $filename);
        }

        return $filename;
    }

    private function saveVisMessageLocally(array $visMessage): string
    {
        $filename = "";

        $time = "0000-00-00T00:00:00.000Z";

        if (isset($visMessage["receivedAt"])) {
            $time = $visMessage["receivedAt"];
        }

        $filename = $this->createLocalFilename($visMessage, $time, $this->messageDirectory);
        $json = json_encode($visMessage);
        if (file_put_contents($filename, $json) === false) {
            throw new Exception("Cannot save: " . $filename);
        }

        return $filename;
    }

    private function getCommonVisMessageResults(array $visMessage): array
    {
        $result = [];

        if (!isset($visMessage["receivedAt"])) {
            throw new Exception("Cannot find receivedAt from visMessage");
        }

        if (!isset($visMessage["FromServiceId"])) {
            throw new Exception("Cannot find FromServiceId from visMessage");
        }

        if (!isset($visMessage["id"])) {
            throw new Exception("Cannot find id from visMessage");
        }

        if (!isset($visMessage["messageType"])) {
            throw new Exception("Cannot find messageType from visMessage");
        }

        $result["time"] = $visMessage["receivedAt"];
        $result["from_service_id"] = $visMessage["FromServiceId"];
        $result["to_service_id"] = $this->visClient->getServiceInstanceUrn();
        $result["message_id"] = $visMessage["id"];
        $result["message_type"] = $visMessage["messageType"];
        $result["payload"] = json_encode($visMessage);

        return $result;
    }

    private function postResultsAndDeleteLocalCopy(ApiConfig $apiConfig, array $result, string $filename)
    {
        try {
            $this->resultPoster->postResult($apiConfig, $result);
        } catch (\Exception $e) {
            error_log("Failed to post results to API: " . $e->getMessage());
            $this->ainoToApiFail("VIS message");
            throw $e;
        }

        unlink($filename);
    }

    private function postEtaToApi(ApiConfig $apiConfig, array $visMessage, array $rtzHandlerResult)
    {
        $timestamp = [];
        $timestamp["time"] = $rtzHandlerResult["eta"];
        $timestamp["time_type"] = "Planned";
        $timestamp["state"] = "Arrival_Vessel_PortArea";
        $timestamp["payload"] = ["source" => "vis"];

        // Fallback to services search in case RTZ does not contain vessel imo or name
        if ($rtzHandlerResult["vessel_imo"] === 0 || $rtzHandlerResult["vessel_name"] === "") {
            $filters["serviceInstanceId"] = $visMessage["FromServiceId"];
            $services = $this->visClient->visFindServices($filters);

            if (!empty($services)) {
                if ($rtzHandlerResult["vessel_imo"] === 0) {
                    $rtzHandlerResult["vessel_imo"] = $services[0]["imo"];
                }

                if ($rtzHandlerResult["vessel_name"] === "") {
                    $rtzHandlerResult["vessel_name"] = $services[0]["name"];
                }
            }
        }

        $timestamp["imo"] = $rtzHandlerResult["vessel_imo"];
        $timestamp["vessel_name"] = $rtzHandlerResult["vessel_name"];

        if ($timestamp["imo"] === 0 && $timestamp["vessel_name"] === "") {
            throw new Exception("IMO and vessel name unknown. Cannot post timestamp.");
        } else {
            try {
                $this->resultPoster->postResult($apiConfig, $timestamp);
            } catch (\Exception $e) {
                error_log("Failed to post ETA to API: " . $e->getMessage());
                $this->ainoToApiFail("timestamp", ["imo" => $timestamp["imo"]]);
                throw $e;
            }
        }
    }

    private function postPortToPortAndDeleteLocalCopy(
        ApiConfig $apiConfig,
        array $commonResult,
        array $rtzHandlerResult,
        string $filename
    ) {
        $inboundVessel = [];
        $inboundVessel["imo"] = $rtzHandlerResult["vessel_imo"];
        $inboundVessel["vessel_name"] = $rtzHandlerResult["vessel_name"];
        $inboundVessel["from_service_id"] = $commonResult["from_service_id"];
        $inboundVessel["time"] = $rtzHandlerResult["time"];

        if ($inboundVessel["imo"] === 0 && $inboundVessel["vessel_name"] === "") {
            throw new Exception("IMO and vessel name unknown. Cannot post inbound vessel.");
        } else {
            try {
                $this->resultPoster->postResult($apiConfig, $inboundVessel);
            } catch (\Exception $e) {
                error_log("Failed to post inbound vessel to API: " . $e->getMessage());
                $this->ainoToApiFail("inbound vessel", ["imo" => $inboundVessel["imo"]]);
                throw $e;
            }

            unlink($filename);
        }
    }

    private function processNotification(array $notification, ApiConfig $apiVisNotificationConfig)
    {
        $filename = $this->saveVisNotificationLocally($notification);

        $result = [];

        $requiredKeys = ["NotificationCreatedAt", "FromServiceId", "NotificationType", "Body", "Subject"];

        foreach ($requiredKeys as $requiredKey) {
            if (!isset($notification[$requiredKey])) {
                throw new Exception("Cannot find required key from notification " . $requiredKey);
            }
        }

        $result["time"] = $notification["NotificationCreatedAt"];
        $result["from_service_id"] = $notification["FromServiceId"];
        $result["message_id"] = "N/A";
        $result["message_type"] = "N/A";
        $result["notification_type"] = $notification["NotificationType"];
        $pregOut = [];
        if (preg_match('/(urn\:.*? )/', $notification["Body"], $pregOut)) {
            $result["message_id"] = rtrim($pregOut[0]);
            if (strpos($result["message_id"], "urn:mrn:stm:txt") !== false) {
                $result["message_type"] = "TXT";
            }
            if (strpos($result["message_id"], "urn:mrn:stm:voyage") !== false) {
                $result["message_type"] = "RTZ";
            }
        }
        $result["subject"] = $notification["Subject"];
        $result["payload"] = json_encode($notification);

        $this->postResultsAndDeleteLocalCopy($apiVisNotificationConfig, $result, $filename);
    }

    public function processNotifications(ApiConfig $apiVisNotificationConfig)
    {
        try {
            $notifications = $this->visClient->visGetNotifications();
        } catch (\Exception $e) {
            error_log("visGetNotifications exception: " . $e->getMessage());
            $this->ainoFromVisFail("visGetNotifications");
        }

        foreach ($notifications as $notification) {
            try {
                $this->processNotification($notification, $apiVisNotificationConfig);
            } catch (\Exception $e) {
                error_log("Failed to process notification: " . $e->getMessage());
            }
        }
    }

    private function processMessage(
        array $message,
        ApiConfig $apiVisMessageConfig,
        ApiConfig $apiVisVoyagePlanConfig,
        ApiConfig $apiTimestampConfig,
        ApiConfig $apiInboundVesselsConfig
    ) {
        $filename = $this->saveVisMessageLocally($message);

        $result = $this->getCommonVisMessageResults($message);

        if ($message["messageType"] === "TXT") {
            $this->postResultsAndDeleteLocalCopy($apiVisMessageConfig, $result, $filename);
        } elseif ($message["messageType"] === "RTZ") {
            if (!isset($message["stmMessage"])) {
                throw new Exception("Cannot find stmMessage from message");
            }

            if (!isset($message["stmMessage"]["message"])) {
                throw new Exception("Cannot find message from stmMessage");
            }

            $rtz = $message["stmMessage"]["message"];

            $handlerResult = $this->rtzHandler->incomingRtz($rtz);

            $result["rtz_state"] = "";
            switch ($handlerResult["status"]) {
                case VisRtzHandler::UNDEFINED:
                    throw new Exception("VisRtzHandler status is UNDEFINED");
                    break;
                case VisRtzHandler::INCOMING_RTZ_NOT_RELEVANT:
                    // Do nothing, no need to save irrelevant RTZ to database
                    // Also no need to store temporary file
                    unlink($filename);
                    break;
                case VisRtzHandler::INCOMING_RTZ_CALCULATED_SCHEDULE_NOT_FOUND:
                    $result["rtz_state"] = "CALCULATED_SCHEDULE_NOT_FOUND";
                    break;
                case VisRtzHandler::INCOMING_RTZ_SYNC_WITH_ETA_FOUND:
                    $result["rtz_state"] = "SYNC_WITH_ETA_FOUND";
                    break;
                case VisRtzHandler::INCOMING_RTZ_SYNC_WITHOUT_ETA_FOUND:
                    $result["rtz_state"] = "SYNC_WITHOUT_ETA_FOUND";
                    break;
                case VisRtzHandler::INCOMING_RTZ_SYNC_NOT_FOUND_CAN_BE_ADDED:
                    $result["rtz_state"] = "SYNC_NOT_FOUND_CAN_BE_ADDED";
                    break;
                case VisRtzHandler::INCOMING_RTZ_SYNC_NOT_FOUND_CAN_NOT_BE_ADDED:
                    $result["rtz_state"] = "SYNC_NOT_FOUND_CAN_NOT_BE_ADDED";
                    break;
                case VisRtzHandler::INCOMING_RTZ_PORT_TO_PORT:
                    $result["rtz_state"] = "PORT_TO_PORT";
                    break;
                default:
                    throw new Exception("VisRtzHandler status is unknown");
                    break;
            }

            if ($result["rtz_state"] !== "" && $result["rtz_state"] !== "PORT_TO_PORT") {
                $result["rtz_parse_results"] = json_encode($handlerResult);

                $this->postResultsAndDeleteLocalCopy($apiVisVoyagePlanConfig, $result, $filename);
            }

            if ($result["rtz_state"] === "PORT_TO_PORT") {
                $this->postPortToPortAndDeleteLocalCopy(
                    $apiInboundVesselsConfig,
                    $result,
                    $handlerResult,
                    $filename
                );
            }

            // Post normal timestamp if ETA found
            if ($result["rtz_state"] === "SYNC_WITH_ETA_FOUND") {
                $this->postEtaToApi($apiTimestampConfig, $message, $handlerResult);
            }
        } else {
            throw new Exception("Invalid message messageType: " . $message["messageType"]);
        }
    }

    public function processMessages(
        ApiConfig $apiVisMessageConfig,
        ApiConfig $apiVisVoyagePlanConfig,
        ApiConfig $apiTimestampConfig,
        ApiConfig $apiInboundVesselsConfig
    ) {
        try {
            $messages = $this->visClient->visGetMessages();
        } catch (\Exception $e) {
            error_log("visGetMessages exception: " . $e->getMessage());
            $this->ainoFromVisFail("visGetMessages");
        }

        foreach ($messages["message"] as $message) {
            try {
                $this->processMessage(
                    $message,
                    $apiVisMessageConfig,
                    $apiVisVoyagePlanConfig,
                    $apiTimestampConfig,
                    $apiInboundVesselsConfig
                );
            } catch (\Exception $e) {
                error_log("Failed to process message: " . $e->getMessage());
            }
        }
    }
}
