<?php
namespace SMA\PAA\AGENT\VIS;

use Exception;
use InvalidArgumentException;

use SMA\PAA\TOOL\DateTimeTools;
use SMA\PAA\TOOL\GeoTool;
use SMA\PAA\TOOL\MessageAuthenticationTools;
use SMA\PAA\AGENT\VIS\VisClient;
use SMA\PAA\AGENT\VIS\VisRtz;
use SMA\PAA\AGENT\VIS\VisRtzHandler;
use SMA\PAA\AGENT\VIS\VisPollSave;
use SMA\PAA\AGENT\ApiConfig;
use SMA\PAA\RESULTPOSTER\IResultPoster;
use SMA\PAA\AINO\AinoClient;

class VisClientApi
{
    private $visClient;
    private $apiVisVesselsConfig;
    private $apiVisMessagesConfig;
    private $apiVisVoyagePlansConfig;
    private $apiVisNotificationsConfig;
    private $apiTimestampsConfig;
    private $apiInboundVesselsConfig;
    private $resultPoster;
    private $outputDirectory;
    private $ainoToVis;
    private $ainoFromVis;
    private $ainoToApi;
    private $ainoFromApi;

    public function __construct(
        VisClient $visClient,
        ApiConfig $apiVisVesselsConfig,
        ApiConfig $apiVisMessagesConfig,
        ApiConfig $apiVisVoyagePlansConfig,
        ApiConfig $apiVisNotificationsConfig,
        ApiConfig $apiTimestampsConfig,
        ApiConfig $apiInboundVesselsConfig,
        IResultPoster $resultPoster,
        string $outputDirectory,
        AinoClient $ainoToVis = null,
        AinoClient $ainoFromVis = null,
        AinoClient $ainoToApi = null,
        AinoClient $ainoFromApi = null
    ) {
        $this->visClient = $visClient;
        $this->apiVisVesselsConfig = $apiVisVesselsConfig;
        $this->apiVisMessagesConfig = $apiVisMessagesConfig;
        $this->apiVisVoyagePlansConfig = $apiVisVoyagePlansConfig;
        $this->apiVisNotificationsConfig = $apiVisNotificationsConfig;
        $this->apiTimestampsConfig = $apiTimestampsConfig;
        $this->apiInboundVesselsConfig = $apiInboundVesselsConfig;
        $this->resultPoster = $resultPoster;
        $this->outputDirectory = $outputDirectory;
        $this->ainoToVis = $ainoToVis;
        $this->ainoFromVis = $ainoFromVis;
        $this->ainoToApi = $ainoToApi;
        $this->ainoFromApi = $ainoFromApi;
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

    private function inputCheck(array $validInput, array $input)
    {
        if (count($input) !== count($validInput)) {
            throw new InvalidArgumentException("Invalid number of parameters");
        }

        foreach ($validInput as $validParameter) {
            if (!isset($input[$validParameter])) {
                throw new InvalidArgumentException("Missing parameter: "  .$validParameter);
            }
        }

        foreach ($input as $inputKey => $inputValue) {
            if (!in_array($inputKey, $validInput)) {
                throw new InvalidArgumentException("Invalid parameter: " . $inputKey);
            }
        }
    }

    private function inputCheckWithOptional(array $mandatoryInput, array $optionalInput, array $input)
    {
        $minCt = count($mandatoryInput);
        $maxCt = $minCt + count($optionalInput);

        if (count($input) < $minCt || count($input) > $maxCt) {
            throw new InvalidArgumentException("Invalid number of parameters");
        }

        foreach ($mandatoryInput as $mandatoryParameter) {
            if (!isset($input[$mandatoryParameter])) {
                throw new InvalidArgumentException("Missing parameter: "  .$mandatoryParameter);
            }
        }

        foreach ($input as $inputKey => $inputValue) {
            if (!in_array($inputKey, $mandatoryInput) && !in_array($inputKey, $optionalInput)) {
                throw new InvalidArgumentException("Invalid parameter: " . $inputKey);
            }
        }
    }

    private function storeTextMessageToApiDatabase(
        string $time,
        string $messageId,
        string $toServiceId,
        string $body
    ) {
        $result = [];
        $result["time"] = $time;
        $result["message_id"] = $messageId;
        $result["from_service_id"] = $this->visClient->getServiceInstanceUrn();
        $result["to_service_id"] = $toServiceId;
        $result["message_type"] = "TXT";
        $payload = [];
        $payload["stmMessage"]["message"] = $body;
        $result["payload"] = json_encode($payload);

        try {
            $this->resultPoster->postResult($this->apiVisMessagesConfig, $result);
        } catch (\Exception $e) {
            error_log("Cannot store text message to API: " . $e->getMessage());
            $this->ainoToApiFail("VIS message", ["message_id" => $messageId]);
            throw $e;
        }
    }

    private function storeVoyagePlanToApiDatabase(
        string $time,
        string $messageId,
        string $toServiceId,
        string $rtz,
        string $rta,
        string $etaMin,
        string $etaMax
    ) {
        $result = [];
        $result["time"] = $time;
        $result["message_id"] = $messageId;
        $result["from_service_id"] = $this->visClient->getServiceInstanceUrn();
        $result["to_service_id"] = $toServiceId;
        $result["message_type"] = "RTZ";
        $result["rtz_state"] = "RTA_SENT";
        $result["rtz_parse_results"] = json_encode("");
        $payload = [];
        $payload["rta"] = $rta;
        $payload["eta_min"] = $etaMin;
        $payload["eta_max"] = $etaMax;
        $payload["stmMessage"]["message"] = $rtz;
        $result["payload"] = json_encode($payload);

        try {
            $this->resultPoster->postResult($this->apiVisVoyagePlansConfig, $result);
        } catch (\Exception $e) {
            error_log("Cannot store voyage plan to API: " . $e->getMessage());
            $this->ainoToApiFail("VIS voyage plan", ["message_id" => $messageId]);
            throw $e;
        }
    }

    /*
    private function postRtaToApi(ApiConfig $apiConfig, string $time, array $rtzParseResults)
    {
        $timestamp = [];
        $timestamp["time"] = $time;
        $timestamp["time_type"] = "Recommended";
        $timestamp["state"] = "Arrival_Vessel_PortArea";
        $timestamp["payload"] = ["source" => "vis"];

        // Fallback to services search in case RTZ does not contain vessel imo or name
        if ($rtzParseResults["vessel_imo"] === 0 || $rtzParseResults["vessel_name"] === "") {
            $filters["serviceInstanceId"] = $visMessage["FromServiceId"];
            $services = $this->visClient->visFindServices($filters);

            if (!empty($services)) {
                if ($rtzParseResults["vessel_imo"] === 0) {
                    $rtzParseResults["vessel_imo"] = $services[0]["imo"];
                }

                if ($rtzParseResults["vessel_name"] === "") {
                    $rtzParseResults["vessel_name"] = $services[0]["name"];
                }
            }
        }

        $timestamp["imo"] = $rtzParseResults["vessel_imo"];
        $timestamp["vessel_name"] = $rtzParseResults["vessel_name"];

        if ($timestamp["imo"] === 0 && $timestamp["vessel_name"] === "") {
            throw new Exception("IMO and vessel name unknown. Cannot post timestamp.");
        } else {
            $this->resultPoster->postResult($apiConfig, $timestamp);
        }
    }
    */

    public function findServices(array $parameters, array $input = null)
    {
        if (count($parameters) !== 1 || $input !== null) {
            throw new InvalidArgumentException("Invalid number of parameters");
        }

        if (empty($parameters["service-id"]) &&
            empty($parameters["imo"])
            ) {
            throw new InvalidArgumentException("Invalid parameters");
        }

        if (isset($parameters["service-id"])) {
            $filters["serviceInstanceId"] = $parameters["service-id"];
        }

        if (isset($parameters["imo"])) {
            $filters["imo"] = $parameters["imo"];
        }

        try {
            $services = $this->visClient->visFindServices($filters);
        } catch (\Exception $e) {
            error_log("visFindServices exception: " . $e->getMessage());
            $this->ainoFromVisFail("visFindServices", [], $filters);
            throw $e;
        }

        if (!empty($services)) {
            $result["imo"] = $services[0]["imo"];
            $result["vessel_name"] = $services[0]["name"];
            $result["service_id"] = $services[0]["instanceId"];
            $result["service_url"] = $services[0]["url"];
            try {
                $this->resultPoster->postResult($this->apiVisVesselsConfig, $result);
            } catch (\Exception $e) {
                error_log("Cannot store VIS vessel to API: " . $e->getMessage());
                $this->ainoToApiFail("VIS vessel", ["service_id" => $result["service_id"]]);
                throw $e;
            }
        } else {
            return ["result" => "ERROR"];
        }

        return ["result" => "OK"];
    }

    public function findServicesByCoordinates(array $parameters, array $input = null)
    {
        if (count($parameters) !== 0) {
            throw new InvalidArgumentException("Invalid number of parameters");
        }

        $validInput = ["lat", "lon"];
        $this->inputCheck($validInput, $input);

        $filters["coverageArea"] = [];
        $filters["coverageArea"]["coverageType"] = "WKT";
        $filters["coverageArea"]["value"] = "POINT(" . $input["lon"] . " " . $input["lat"] . ")";
        $filters["serviceType"] = "(\"Enhanced Monitoring\" OR \"Port Call Synchronization\")";

        try {
            $services = $this->visClient->visFindServices($filters);
        } catch (\Exception $e) {
            error_log("visFindServices exception: " . $e->getMessage());
            $this->ainoFromVisFail("visFindServices", [], $filters);
            throw $e;
        }

        $res = [];
        if (!empty($services)) {
            foreach ($services as $service) {
                $result["imo"] = $service["imo"];
                $result["vessel_name"] = $service["name"];
                $result["service_id"] = $service["instanceId"];
                $result["service_url"] = $service["url"];
                try {
                    $this->resultPoster->postResult($this->apiVisVesselsConfig, $result);
                } catch (\Exception $e) {
                    error_log("Cannot store VIS vessel to API: " . $e->getMessage());
                    $this->ainoToApiFail("VIS vessel", ["service_id" => $result["service_id"]]);
                    throw $e;
                }
                $innerRes = [];
                $innerRes["name"] = $service["name"];
                $innerRes["service_id"] = $service["instanceId"];
                $res[] = $innerRes;
            }
        }

        return $res;
    }

    public function findInterPortServicesByLocode(array $parameters, array $input = null)
    {
        if (count($parameters) !== 1 || $input !== null) {
            throw new InvalidArgumentException("Invalid number of parameters");
        }

        if (empty($parameters["locode"])) {
            throw new InvalidArgumentException("Invalid parameters");
        }

        $filters["unLoCode"] = $parameters["locode"];
        $filters["serviceType"] = "\"Inter-Port Synchronization\"";

        try {
            $services = $this->visClient->visFindServices($filters);
        } catch (\Exception $e) {
            error_log("visFindServices exception: " . $e->getMessage());
            $this->ainoFromVisFail("visFindServices", [], $filters);
            throw $e;
        }

        $res = [];

        if (!empty($services)) {
            foreach ($services as $service) {
                $result["imo"] = $service["imo"];
                $result["vessel_name"] = $service["name"];
                $result["service_id"] = $service["instanceId"];
                $result["service_url"] = $service["url"];
                try {
                    $this->resultPoster->postResult($this->apiVisVesselsConfig, $result);
                } catch (\Exception $e) {
                    error_log("Cannot store VIS vessel to API: " . $e->getMessage());
                    $this->ainoToApiFail("VIS vessel", ["service_id" => $result["service_id"]]);
                    throw $e;
                }
                $innerRes = [];
                $innerRes["name"] = $service["name"];
                $innerRes["service_id"] = $service["instanceId"];
                $res[] = $innerRes;
            }

            return $res;
        }

        // Fallback to keyword search
        unset($filters["unLoCode"]);
        $filters["keywords"][] = "\"" . $parameters["locode"] . "\"";

        try {
            $services = $this->visClient->visFindServices($filters);
        } catch (\Exception $e) {
            error_log("visFindServices exception: " . $e->getMessage());
            $this->ainoFromVisFail("visFindServices", [], $filters);
            throw $e;
        }

        if (!empty($services)) {
            foreach ($services as $service) {
                $result["imo"] = $service["imo"];
                $result["vessel_name"] = $service["name"];
                $result["service_id"] = $service["instanceId"];
                $result["service_url"] = $service["url"];
                try {
                    $this->resultPoster->postResult($this->apiVisVesselsConfig, $result);
                } catch (\Exception $e) {
                    error_log("Cannot store VIS vessel to API: " . $e->getMessage());
                    $this->ainoToApiFail("VIS vessel", ["service_id" => $result["service_id"]]);
                    throw $e;
                }
                $innerRes = [];
                $innerRes["name"] = $service["name"];
                $innerRes["service_id"] = $service["instanceId"];
                $res[] = $innerRes;
            }

            return $res;
        }

        return $res;
    }

    public function uploadTextMessage(array $parameters, array $input)
    {
        if (count($parameters) !== 0) {
            throw new InvalidArgumentException("Invalid number of parameters");
        }

        $mandatoryInput = [
            "to_service_id",
            "to_url",
            "author",
            "subject",
            "body"
        ];
        $optionalInput = [
            "information_object_reference_id",
            "information_object_reference_type",
            "area"
        ];
        $this->inputCheckWithOptional($mandatoryInput, $optionalInput, $input);

        $toServiceId = $input["to_service_id"];
        $toUrl = $input["to_url"];
        $author = $input["author"];
        $subject = $input["subject"];
        $body = $input["body"];
        $informationObjectReferenceId =
            isset($input["information_object_reference_id"]) ? $input["information_object_reference_id"] : null;
        $informationObjectReferenceType =
            isset($input["information_object_reference_type"]) ? $input["information_object_reference_type"] : null;
        $area = isset($input["area"]) ? $input["area"] : null;

        $response = "";
        try {
            $response = $this->visClient->visUploadTextMessage(
                $toUrl,
                $author,
                $subject,
                $body,
                $informationObjectReferenceId,
                $informationObjectReferenceType,
                $area
            );
        } catch (\Exception $e) {
            error_log("visUploadTextMessage exception: " . $e->getMessage());
            $this->ainoFromVisFail("visUploadTextMessage", ["to_service_id" => $toServiceId]);
            throw $e;
        }

        $this->storeTextMessageToApiDatabase(
            $response["message_time"],
            $response["message_id"],
            $toServiceId,
            $response["message_body"]
        );

        return ["result" => "OK"];
    }

    public function sendRta(array $parameters, array $input)
    {
        if (count($parameters) !== 0) {
            throw new InvalidArgumentException("Invalid number of parameters");
        }

        $validInput = ["to_service_id", "to_url", "rtz_parse_results", "rtz", "rta", "eta_min", "eta_max"];
        $this->inputCheck($validInput, $input);

        $dateTimeTools = new DateTimeTools();
        $rtaDateTime = $dateTimeTools->createDateTime($input["rta"]);
        $etaMin = $dateTimeTools->createDateTime($input["eta_min"]);
        $etaMax = $dateTimeTools->createDateTime($input["eta_max"]);

        if ($etaMin > $etaMax) {
            throw new InvalidArgumentException("eta_min must be earlier than eta_max");
        }

        if ($etaMin > $rtaDateTime) {
            throw new InvalidArgumentException("eta_min must be earlier than rta");
        }

        if ($etaMax < $rtaDateTime) {
            throw new InvalidArgumentException("eta_max must be later than rta");
        }

        $windowBefore = $dateTimeTools->dateTimeDifferenceInXsdDuration($rtaDateTime, $etaMin);
        $windowAfter = $dateTimeTools->dateTimeDifferenceInXsdDuration($etaMax, $rtaDateTime);

        $toServiceId = $input["to_service_id"];
        $toUrl = $input["to_url"];
        $rtzParseResults = json_decode($input["rtz_parse_results"], true);
        $rtz = $input["rtz"];
        $rta = $input["rta"];

        if (empty($rtzParseResults["status"])) {
            throw new InvalidArgumentException("No status in RTZ parse results");
        }

        $syncPointName = $this->visClient->getSyncPointName();
        $geoTool = new GeoTool();
        $rtzHandler = new VisRtzHandler($this->visClient, $geoTool);
        $newRtz = "";

        switch ($rtzParseResults["status"]) {
            case VisRtzHandler::INCOMING_RTZ_SYNC_WITH_ETA_FOUND:
                if (!isset($rtzParseResults["waypointId"]) ||
                    empty($rtzParseResults["eta"])
                ) {
                    throw new InvalidArgumentException(
                        "Invalid RTZ parse results for INCOMING_RTZ_SYNC_WITH_ETA_FOUND"
                    );
                }

                $waypointId = $rtzParseResults["waypointId"];
                $newRtz = $rtzHandler->setEtaToWaypoint($rta, $windowBefore, $windowAfter, $waypointId, $rtz);
                break;
            case VisRtzHandler::INCOMING_RTZ_SYNC_WITHOUT_ETA_FOUND:
                if (!isset($rtzParseResults["waypointId"])) {
                    throw new InvalidArgumentException(
                        "Invalid RTZ parse results for INCOMING_RTZ_SYNC_WITHOUT_ETA_FOUND"
                    );
                }

                $waypointId = $rtzParseResults["waypointId"];
                $newRtz = $rtzHandler->setEtaToWaypoint($rta, $windowBefore, $windowAfter, $waypointId, $rtz);
                break;
            case VisRtzHandler::INCOMING_RTZ_SYNC_NOT_FOUND_CAN_BE_ADDED:
                if (!isset($rtzParseResults["legEndWaypointId"]) ||
                    empty($rtzParseResults["lat"]) ||
                    empty($rtzParseResults["lon"])
                ) {
                    throw new InvalidArgumentException(
                        "Invalid RTZ parse results for INCOMING_RTZ_SYNC_NOT_FOUND_CAN_BE_ADDED"
                    );
                }

                $waypointId = $rtzParseResults["legEndWaypointId"];
                $latLon = $geoTool->createLatLon($rtzParseResults["lat"], $rtzParseResults["lon"]);
                $newRtz = $rtzHandler->setEtaToNewWaypoint(
                    $rta,
                    $windowBefore,
                    $windowAfter,
                    $syncPointName,
                    $waypointId,
                    $latLon,
                    $rtz
                );
                break;
            default:
                throw new Exception("Invalid RTZ parse results");
                break;
        }

        if ($newRtz !== "") {
            try {
                $this->visClient->visUploadVoyagePlan($toUrl, $newRtz);
            } catch (\Exception $e) {
                error_log("visUploadVoyagePlan exception: " . $e->getMessage());
                $this->ainoFromVisFail("visUploadVoyagePlan", ["to_service_id" => $toServiceId]);
                throw $e;
            }

            $visRtz = new VisRtz($newRtz);
            $messageId = $visRtz->getVesselVoyage();

            $this->storeVoyagePlanToApiDatabase(
                $dateTimeTools->nowUtcString(),
                $messageId["complete"],
                $toServiceId,
                $newRtz,
                $input["rta"],
                $input["eta_min"],
                $input["eta_max"]
            );
        } else {
            throw new Exception("Cannot create RTZ with given data");
        }

        // $this->postRtaToApi($this->apiTimestampsConfig, $rta, $rtzParseResults);

        return ["result" => "OK"];
    }

    public function pollSave(array $parameters, array $input = null)
    {
        if (count($parameters) !== 0 || $input !== null) {
            throw new InvalidArgumentException("Invalid number of parameters");
        }

        $visPollSave = new VisPollSave(
            $this->resultPoster,
            $this->visClient,
            new GeoTool(),
            $this->outputDirectory,
            $this->ainoToVis,
            $this->ainoFromVis,
            $this->ainoToApi,
            $this->ainoFromApi
        );

        try {
            $visPollSave->execute(
                $this->apiVisNotificationsConfig,
                $this->apiVisMessagesConfig,
                $this->apiVisVoyagePlansConfig,
                $this->apiTimestampsConfig,
                $this->apiInboundVesselsConfig
            );
        } catch (\Exception $e) {
            throw $e;
        }

        return ["result" => "OK"];
    }

    public function getConfig(array $parameters, array $input = null)
    {
        $res = [];

        if (count($parameters) !== 0 || $input !== null) {
            throw new InvalidArgumentException("Invalid number of parameters");
        }

        $res["SPAA API key"] = substr(getenv("API_KEY"), 0, 4) . "****";
        $res["SPAA notifications URL"] = getenv("API_URL_NOTIFICATIONS");
        $res["SPAA messages URL"] = getenv("API_URL_MESSAGES");
        $res["SPAA voyage plans URL"] = getenv("API_URL_VOYAGE_PLANS");
        $res["SPAA timestamps URL"] = getenv("API_URL_TIMESTAMPS");
        $res["SPAA VIS vessels URL"] = getenv("API_URL_VIS_VESSELS");
        $res["SPAA inbound vessels URL"] = getenv("API_URL_INBOUND_VESSELS");
        $res["VIS secure communications"] = print_r(getenv("VIS_SECURE_COMMUNICATIONS"), true);
        $res["VIS APP ID"] = substr(getenv("VIS_APP_ID"), 0, 4) . "****";
        $res["VIS API key"] = substr(getenv("VIS_API_KEY"), 0, 4) . "****";
        $res["VIS governing org"] = getenv("VIS_GOVERNING_ORG");
        $res["VIS own org"] = getenv("VIS_OWN_ORG");
        $res["VIS service instance URL"] = getenv("VIS_SERVICE_INSTANCE_URL");
        $res["VIS private side port"] = getenv("VIS_PRIVATE_SIDE_PORT");
        $res["VIS service instance URN"] = getenv("VIS_SERVICE_INSTANCE_URN");
        $res["RTZ route author"] = getenv("VIS_RTZ_ROUTE_AUTHOR");
        $res["RTZ schedule name"] = getenv("VIS_RTZ_SCHEDULE_NAME");
        $res["Port coordinates"] = getenv("VIS_PORT_LAT") . "," .  getenv("VIS_PORT_LON");
        $res["Port search radius"] = getenv("VIS_PORT_RADIUS");
        $res["Sync point name"] = getenv("VIS_SYNC_POINT_NAME");
        $res["Sync point coordinates"] = getenv("VIS_SYNC_POINT_LAT") . "," .  getenv("VIS_SYNC_POINT_LON");
        $res["Sync point search radius"] = getenv("VIS_SYNC_POINT_RADIUS");
        $res["Output directory"] = getenv("VIS_OUTPUT_DIRECTORY");

        return $res;
    }

    private function departureTemplate(): string
    {
// phpcs:disable
        $template = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<route version="1.1" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.cirm.org/RTZ/1/1" xmlns:stm="http://stmvalidation.eu/STM/1/0/0">
    <routeInfo routeName="[routeName]" vesselVoyage="urn:mrn:stm:voyage:id:unikieporttoport:[voyageUUID]" vesselIMO="[vesselIMO]" vesselName="[vesselName]" routeAuthor="[routeAuthor]">
    <extensions>
        <extension xsi:type="stm:RouteInfoExtension" manufacturer="STM" version="1.0.0" name="routeInfoEx" routeStatusEnum="2"  depPort="[depPort]" arrPort="[arrPort]"/>
    </extensions>
    </routeInfo>
    <waypoints>
    <defaultWaypoint radius="0">
    </defaultWaypoint>
    <waypoint id="1" revision="1" name="From">
        <position lat="[fromLat]" lon="[fromLon]" />
    </waypoint>
    <waypoint id="2" revision="1" name="To">
        <position lat="[toLat]" lon="[toLon]" />
    </waypoint>
    </waypoints>
    <schedules>
    <schedule id="1" name="Default">
        <manual>
        <scheduleElement waypointId="1" etd="[time]" />
        </manual>
    </schedule>
    </schedules>
</route>
EOT;
// phpcs:enable

        return $template;
    }

    private function fillDepartureTemplate(
        string $routeName,
        string $voyageUUID,
        string $vesselIMO,
        string $vesselName,
        string $routeAuthor,
        string $fromLat,
        string $fromLon,
        string $toLat,
        string $toLon,
        string $time,
        string $depPort,
        string $arrPort
    ): string {
        $map = [
            "[routeName]" => $routeName
            ,"[voyageUUID]" => $voyageUUID
            ,"[vesselIMO]" => $vesselIMO
            ,"[vesselName]" => $vesselName
            ,"[routeAuthor]" => $routeAuthor
            ,"[fromLat]" => $fromLat
            ,"[fromLon]" => $fromLon
            ,"[toLat]" => $toLat
            ,"[toLon]" => $toLon
            ,"[time]" => $time
            ,"[depPort]" => $depPort
            ,"[arrPort]" => $arrPort
        ];
        return str_replace(array_keys($map), $map, $this->departureTemplate());
    }

    public function sendDeparture(array $parameters, array $input)
    {
        if (count($parameters) !== 0) {
            throw new InvalidArgumentException("Invalid number of parameters");
        }

        $validInput = [
            "to_service_id",
            "to_url",
            "from_locode",
            "to_locode",
            "vessel_imo",
            "vessel_name",
            "to_lat",
            "to_lon",
            "time"
        ];
        $this->inputCheck($validInput, $input);

        // Check that time is valid
        $dateTimeTools = new DateTimeTools();
        $dateTimeTools->createDateTime($input["time"]);

        $toServiceId = $input["to_service_id"];
        $toUrl = $input["to_url"];
        $routeName = $input["from_locode"] . "-" . $input["to_locode"];
        $msgAuthTools = new MessageAuthenticationTools();
        $uuid = $msgAuthTools->createRandomUuid();
        $voyageUUID = $routeName . "-" . $uuid;
        $vesselIMO = $input["vessel_imo"];
        $vesselName = $input["vessel_name"];
        $routeAuthor = $this->visClient->getRtzRouteAuthor();
        $fromLat = $this->visClient->getPortCoordinates()["lat"];
        $fromLon = $this->visClient->getPortCoordinates()["lon"];
        $toLat = $input["to_lat"];
        $toLon = $input["to_lon"];
        $time = $input["time"];
        $depPort = $input["from_locode"];
        $arrPort = $input["to_locode"];

        $rtz = $this->fillDepartureTemplate(
            $routeName,
            $voyageUUID,
            $vesselIMO,
            $vesselName,
            $routeAuthor,
            $fromLat,
            $fromLon,
            $toLat,
            $toLon,
            $time,
            $depPort,
            $arrPort
        );

        error_log(
            "Trying to send departure with data:"
            . " " . $routeName
            . "," . $voyageUUID
            . "," . $vesselIMO
            . "," . $vesselName
            . "," . $routeAuthor
            . "," . $fromLat
            . "," . $fromLon
            . "," . $toLat
            . "," . $toLon
            . "," . $time
            . "," . $depPort
            . "," . $arrPort
        );

        try {
            $this->visClient->visUploadVoyagePlan($toUrl, $rtz);
        } catch (\Exception $e) {
            error_log("Port to port visUploadVoyagePlan exception: " . $e->getMessage());
            $this->ainoFromVisFail("Port to port visUploadVoyagePlan", ["to_service_id" => $toServiceId]);
            throw $e;
        }

        return ["result" => "OK"];
    }
}
