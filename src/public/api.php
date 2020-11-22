<?php
require_once __DIR__ . "/../lib/init.php";

use SMA\PAA\CURL\CurlRequest;
use SMA\PAA\RESULTPOSTER\ResultPoster;
use SMA\PAA\AGENT\ApiConfig;
use SMA\PAA\AGENT\VIS\VisClient;
use SMA\PAA\AGENT\VIS\VisClientApi;
use SMA\PAA\AINO\AinoClient;

header('Access-Control-Allow-Methods: POST,GET');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept');

$apiKey = getenv("API_KEY");
$apiUrlVisVessels = getenv("API_URL_VIS_VESSELS");
$apiVisVesselsParameters = ["imo", "vessel_name", "service_id", "service_url"];
$apiVisVesselsConfig = new ApiConfig($apiKey, $apiUrlVisVessels, $apiVisVesselsParameters);
$apiUrlVisMessages = getenv("API_URL_MESSAGES");
$apiVisMessagesParameters = ["time", "from_service_id", "to_service_id", "message_id", "message_type", "payload"];
$apiVisMessagesConfig = new ApiConfig($apiKey, $apiUrlVisMessages, $apiVisMessagesParameters);
$apiUrlVoyagePlans = getenv("API_URL_VOYAGE_PLANS");
$apiVisVoyagePlansParameters =
["time", "from_service_id", "to_service_id", "message_id", "message_type", "rtz_state", "rtz_parse_results", "payload"];
$apiVisVoyagePlansConfig = new ApiConfig($apiKey, $apiUrlVoyagePlans, $apiVisVoyagePlansParameters);
$apiUrlVisNotifications = getenv("API_URL_NOTIFICATIONS");
$apiVisNotificationParameters = [
    "time",
    "from_service_id",
    "message_id",
    "message_type",
    "notification_type",
    "subject",
    "payload"
];
$apiVisNotificationsConfig = new ApiConfig($apiKey, $apiUrlVisNotifications, $apiVisNotificationParameters);
$apiUrlTimestamps = getenv("API_URL_TIMESTAMPS");
$apiTimestampParameters = ["imo", "vessel_name", "time_type", "state", "time", "payload"];
$apiTimestampsConfig = new ApiConfig($apiKey, $apiUrlTimestamps, $apiTimestampParameters);
$apiUrlInboundVessels = getenv("API_URL_INBOUND_VESSELS");
$apiInboundVesselsParameters = ["time", "imo", "vessel_name", "from_service_id"];
$apiInboundVesselsConfig = new ApiConfig($apiKey, $apiUrlInboundVessels, $apiInboundVesselsParameters);
$outputDirectory = getenv("VIS_OUTPUT_DIRECTORY");
$ainoApiKey = getenv("AINO_API_KEY");
$ainoToVis = null;
$ainoFromVis = null;
$ainoToApi = null;
$ainoFromApi = null;
if ($ainoApiKey) {
    $toApplication = parse_url($apiUrlVisVessels, PHP_URL_HOST);
    $ainoToVis = new AinoClient($ainoApiKey, "VIS agent", "VIS service");
    $ainoFromVis = new AinoClient($ainoApiKey, "VIS service", "VIS agent");
    $ainoToApi = new AinoClient($ainoApiKey, "VIS agent", $toApplication);
    $ainoFromApi = new AinoClient($ainoApiKey, $toApplication, "VIS agent");
}

$visClient = VisClient::createFromEnv(new CurlRequest());
$visClientApi = new VisClientApi(
    $visClient,
    $apiVisVesselsConfig,
    $apiVisMessagesConfig,
    $apiVisVoyagePlansConfig,
    $apiVisNotificationsConfig,
    $apiTimestampsConfig,
    $apiInboundVesselsConfig,
    new ResultPoster(new CurlRequest()),
    $outputDirectory,
    $ainoToVis,
    $ainoFromVis,
    $ainoToApi,
    $ainoFromApi
);

$method = $_SERVER["REQUEST_METHOD"];
$request = explode("/", trim($_SERVER["PATH_INFO"], "/"));
$input = json_decode(file_get_contents("php://input"), true);

$call = preg_replace("/[^a-z0-9_-]+/i", "", array_shift($request));

$parameters = [];
foreach ($request as $parameter) {
    $keyvalue = explode(":", $parameter, 2);
    $key = preg_replace("/[^a-z0-9_-]+/i", "", $keyvalue[0]);
    $value = preg_replace("/[^a-z0-9_:-]+/i", "", $keyvalue[1]);
    $parameters[$key] = $value;
}

$function = "";
switch ($call) {
    case "config":
        if ($method === "GET") {
            $function = "getConfig";
        }
        break;
    case "poll-save":
        if ($method === "GET") {
            $function = "pollSave";
        }
        break;
    case "find-services":
        if ($method === "GET") {
            $function = "findServices";
        }
        break;
    case "find-services-by-coordinates":
        if ($method === "POST") {
            $function = "findServicesByCoordinates";
        }
        break;
    case "find-inter-port-services-by-locode":
        if ($method === "GET") {
            $function = "findInterPortServicesByLocode";
        }
        break;
    case "upload-text-message":
        if ($method === "POST") {
            $function = "uploadTextMessage";
        }
        break;
    case "send-rta":
        if ($method === "POST") {
            $function = "sendRta";
        }
        break;
    case "send-departure":
        if ($method === "POST") {
            $function = "sendDeparture";
        }
        break;
    default:
        break;
}

if ($method === "POST") {
    if ($input === null) {
        http_response_code(400);
        echo json_encode(["error" => "Malformed or missing POST payload"]);
        exit(0);
    }
}

if ($function !== "") {
    try {
        echo json_encode($visClientApi->$function($parameters, $input));
    } catch (InvalidParameterException $e) {
        error_log($e->getMessage());
        http_response_code(400);
        echo json_encode(["error" => $e->getMessage()]);
    } catch (Exception $e) {
        error_log($e->getMessage());
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["error" => "Unknown call: " . $method . " " . $call]);
}
