<?php
namespace SMA\PAA\AGENT\VIS;

use Exception;
use InvalidArgumentException;
use DateTime;
use DateTimeZone;
use SimpleXMLElement;

use SMA\PAA\CURL\ICurlRequest;
use SMA\PAA\TOOL\MessageAuthenticationTools;

class VisClient
{
    private $curlRequest;
    private $governingOrganization;
    private $ownOrganization;
    private $serviceInstanceUrl;
    private $privateSidePort;
    private $serviceInstanceUrn;
    private $rtzRouteAuthor;
    private $rtzScheduleName;
    private $secureCommunications;
    private $portLat;
    private $portLon;
    private $portRadius;
    private $syncPointName;
    private $syncPointLat;
    private $syncPointLon;
    private $syncPointRadius;
    private $appId;
    private $apiKey;
    private $privateSideUrl;
    private $validVisCalls;

    public function __construct(
        ICurlRequest $curlRequest,
        string $governingOrganization,
        string $ownOrganization,
        string $serviceInstanceUrl,
        int $privateSidePort,
        string $serviceInstanceUrn,
        string $rtzRouteAuthor,
        string $rtzScheduleName,
        float $portLat,
        float $portLon,
        float $portRadius,
        string $syncPointName,
        float $syncPointLat,
        float $syncPointLon,
        float $syncPointRadius,
        bool $secureCommunications = false,
        string $appId = "",
        string $apiKey = ""
    ) {
        $this->curlRequest = $curlRequest;
        $this->governingOrganization = $governingOrganization;
        $this->ownOrganization = $ownOrganization;
        $this->serviceInstanceUrl = $serviceInstanceUrl;
        $this->privateSidePort = $privateSidePort;
        $this->serviceInstanceUrn = $serviceInstanceUrn;
        $this->rtzRouteAuthor = $rtzRouteAuthor;
        $this->rtzScheduleName = $rtzScheduleName;
        $this->portLat = $portLat;
        $this->portLon = $portLon;
        $this->portRadius = $portRadius;
        $this->syncPointName = $syncPointName;
        $this->syncPointLat = $syncPointLat;
        $this->syncPointLon = $syncPointLon;
        $this->syncPointRadius = $syncPointRadius;
        $this->secureCommunications = $secureCommunications;
        $this->appId = $appId;
        $this->apiKey = $apiKey;

        $config = require("VisClientConfig.php");
        $this->validVisCalls = $config["validVisCalls"];

        $parsedUrl = parse_url($this->serviceInstanceUrl);

        if (!isset($parsedUrl["scheme"]) || !isset($parsedUrl["host"])) {
            throw new InvalidArgumentException("Service instance URL malformed: " . $this->serviceInstanceUrl);
        }

        $this->privateSideUrl = $parsedUrl["scheme"] . "://";
        $this->privateSideUrl .= $parsedUrl["host"] . ":";
        $this->privateSideUrl .= strval($this->privateSidePort);
        if (isset($parsedUrl["path"])) {
            $this->privateSideUrl .= $parsedUrl["path"];
        }
    }

    public static function createFromEnv(
        ICurlRequest $curlRequest
    ): VisClient {
        $config = require("VisClientConfig.php");

        $env = getenv();

        $validKeys = array_flip($config["configParameters"]);
        $diff = array_diff_key($validKeys, $env);
        if ($diff) {
            throw new InvalidArgumentException(
                "Missing parameter(s) in environment variables: ".implode(", ", array_keys($diff))
            );
        }

        $visClient = new VisClient(
            $curlRequest,
            $env["VIS_GOVERNING_ORG"],
            $env["VIS_OWN_ORG"],
            $env["VIS_SERVICE_INSTANCE_URL"],
            $env["VIS_PRIVATE_SIDE_PORT"],
            $env["VIS_SERVICE_INSTANCE_URN"],
            $env["VIS_RTZ_ROUTE_AUTHOR"],
            $env["VIS_RTZ_SCHEDULE_NAME"],
            $env["VIS_PORT_LAT"],
            $env["VIS_PORT_LON"],
            $env["VIS_PORT_RADIUS"],
            $env["VIS_SYNC_POINT_NAME"],
            $env["VIS_SYNC_POINT_LAT"],
            $env["VIS_SYNC_POINT_LON"],
            $env["VIS_SYNC_POINT_RADIUS"],
            $env["VIS_SECURE_COMMUNICATIONS"],
            $env["VIS_APP_ID"],
            $env["VIS_API_KEY"]
        );

        return $visClient;
    }

    public function getServiceInstanceUrn(): string
    {
        return $this->serviceInstanceUrn;
    }

    public function getPortCoordinates(): array
    {
        $res = [];

        $res["lat"] = $this->portLat;
        $res["lon"] = $this->portLon;

        return $res;
    }

    public function getPortRadius(): float
    {
        return $this->portRadius;
    }

    public function getSyncPointName(): string
    {
        return $this->syncPointName;
    }

    public function getSyncPointCoordinates(): array
    {
        $res = [];

        $res["lat"] = $this->syncPointLat;
        $res["lon"] = $this->syncPointLon;

        return $res;
    }

    public function getSyncPointRadius(): float
    {
        return $this->syncPointRadius;
    }

    public function getRtzRouteAuthor(): string
    {
        return $this->rtzRouteAuthor;
    }

    public function getRtzScheduleName(): string
    {
        return $this->rtzScheduleName;
    }

    public function callVis(
        string $call,
        string $method,
        string $json = "",
        string $parameters = ""
    ): string {
        if (!in_array($call, $this->validVisCalls)) {
            throw new InvalidArgumentException("Invalid VIS call: " . $call);
        }

        if (!($method === "GET" || $method === "POST")) {
            throw new InvalidArgumentException("Invalid method: " . $method);
        }

        $url = $this->privateSideUrl . "/" . $call . $parameters;
        $this->curlRequest->init($url);

        $this->curlRequest->setOption(CURLOPT_RETURNTRANSFER, 1);
        #$this->curlRequest->setOption(CURLOPT_VERBOSE, 1);
        $this->curlRequest->setOption(CURLOPT_HEADER, 1);

        $header = [];
        $header[] = "Content-Type: application/json";
        $header[] = "Accept: application/json";

        if ($this->secureCommunications) {
            if ($this->appId === "") {
                throw new Exception("Secure communications requested but App ID not defined");
            }

            if ($this->apiKey === "") {
                throw new Exception("Secure communications requested but API key not defined");
            }

            $msgAuthTools = new MessageAuthenticationTools();

            $header[] =
                "Authorization: "
                .$msgAuthTools->createVisAuthorizationHeader(
                    $this->appId,
                    $this->apiKey,
                    $method,
                    $url,
                    $json
                );
        }

        $this->curlRequest->setOption(CURLOPT_HTTPHEADER, $header);

        if ($method === "POST") {
            $this->curlRequest->setOption(CURLOPT_POST, 1);
            $this->curlRequest->setOption(CURLOPT_POSTFIELDS, $json);
        }

        $response = $this->curlRequest->execute();
        $info = $this->curlRequest->getInfo();
        $decoded = json_decode($response, true);
        $headerSize = $this->curlRequest->getInfo(CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        $this->curlRequest->close();

        if ($info["http_code"] !== 200) {
            throw new Exception(
                "Error occured during curl exec.\ncurl_getinfo returns:\n" . print_r($info, true) . "\n"
                . "Response body:\n". print_r($body, true) . "\n"
            );
        }

        return $body;
    }

    public function getPublicSideStatusCode(string $body): int
    {
        $res = -1;

        $decoded = json_decode($body, true);

        if (!isset($decoded["statusCode"])) {
            throw new Exception("Cannot find statusCode from given body");
        }

        $res = $decoded["statusCode"];

        return $res;
    }

    public function getPublicSideBody(string $body): string
    {
        $res = "";

        $decoded = json_decode($body, true);

        if (!isset($decoded["body"])) {
            throw new Exception("Cannot find body from given body");
        }

        $res = $decoded["body"];

        $simpleXml = @simplexml_load_string($res);

        if ($simpleXml !== false) {
            if (!isset($simpleXml->body)) {
                throw new Exception("Cannot find body from given xml");
            }

            $res = (string)$simpleXml->body;
        }

        return $res;
    }

    public function throwPublicSideStatusCodeError(string $body)
    {
        $statusCode = $this->getPublicSideStatusCode($body);
        $innerBody = $this->getPublicSideBody($body);

        throw new Exception(
            "Unexcpected response from public side. statusCode: "
            . $statusCode . ", body: \"" . $innerBody . "\""
        );
    }

    public function visGetMessages(): array
    {
        $res = json_decode($this->callVis("getMessage", "GET"), true);

        if ($res === null) {
            throw new Exception("Cannot json_decode getMessage GET response");
        }

        return $res;
    }

    public function visGetNotifications(): array
    {
        $res = json_decode($this->callVis("getNotification", "GET"), true);

        if ($res === null) {
            throw new Exception("Cannot json_decode getNotification GET response");
        }

        return $res;
    }

    public function visUploadTextMessage(
        string $receiverUrl,
        string $author,
        string $subject,
        string $body,
        string $informationObjectReferenceId = null,
        string $informationObjectReferenceType = null,
        string $area = null
    ): array {
        $msgAuthTools = new MessageAuthenticationTools();
        $uuid = $msgAuthTools->createRandomUuid();

        $messageIdUrn = "urn:mrn:stm:txt:";
        $messageIdUrn .= $this->ownOrganization . ":";
        $messageIdUrn .= $uuid;

        $time = new DateTime();
        $time->setTimeZone(new DateTimeZone("UTC"));
        $created = $time->format("Y-m-d\TH:i:s\Z");

        $data["body"] = "<textMessage xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" ";
        $data["body"] .= "xmlns=\"http://stmvalidation.eu/schemas/textMessageSchema_1_3.xsd\">";
        $data["body"] .= "<textMessageId>" . $messageIdUrn . "</textMessageId>";
        if (isset($informationObjectReferenceId)) {
            $data["body"] .= "<informationObjectReferenceId>"
            . $informationObjectReferenceId
            . "</informationObjectReferenceId>";
        }
        if (isset($informationObjectReferenceType)) {
            $data["body"] .= "<informationObjectReferenceType>"
            . $informationObjectReferenceType
            . "</informationObjectReferenceType>";
        }
        $data["body"] .= "<author>" . $author . "</author>";
        $data["body"] .= "<from>" . $this->serviceInstanceUrn . "</from>";
        $data["body"] .= "<createdAt>" . $created . "</createdAt>";
        $data["body"] .= "<subject>" . $subject . "</subject>";
        $data["body"] .= "<body>" . $body . "</body>";
        if (isset($area)) {
            $data["body"] .= "<area>" . $area . "</area>";
        }
        $data["body"] .= "</textMessage>";
        $data["endpointMethod"] = $receiverUrl . "/textMessage";
        $data["endpointMethod"] .= "?deliveryAckEndPoint=" . rawurlencode($this->serviceInstanceUrl);
        $data["headers"][0]["key"] = "content-type";
        $data["headers"][0]["value"] = "text/xml; charset=utf-8";
        $data["requestType"] = "POST";

        $json = json_encode($data);

        $body = $this->callVis("callService", "POST", $json);

        if ($this->getPublicSideStatusCode($body) !== 200) {
            $this->throwPublicSideStatusCodeError($body);
        }

        return [
            "response_body" => $body,
            "message_time" => $created,
            "message_id" => $messageIdUrn,
            "message_body" => $data["body"]
        ];
    }

    public function visUploadVoyagePlan(string $receiverUrl, string $xml): string
    {
        $data["body"] = $xml;
        $data["endpointMethod"] = $receiverUrl . "/voyagePlans";
        $data["endpointMethod"] .= "?deliveryAckEndPoint=" . rawurlencode($this->serviceInstanceUrl);
        $data["headers"][0]["key"] = "content-type";
        $data["headers"][0]["value"] = "text/xml; charset=utf-8";
        $data["requestType"] = "POST";

        $json = json_encode($data);

        $body = $this->callVis("callService", "POST", $json);

        if ($this->getPublicSideStatusCode($body) !== 200) {
            $this->throwPublicSideStatusCodeError($body);
        }

        return $body;
    }

    public function visFindServices(array $filters): array
    {
        $data = [];
        $data["page"] = 0;
        $data["pageSize"] = 1000;

        if (isset($filters)) {
            $data["filter"] = [];

            foreach ($filters as $key => $value) {
                $data["filter"][$key] = $value;
            }
        }

        // We are only interested in released VIS services
        $data["filter"]["serviceStatus"] = "released";
        $data["filter"]["serviceDesignId"] = "urn:mrn:mcp:service:navelink:navelink:design:vis:rest:2.2";

        $json = json_encode($data);

        error_log("Finding services with data: " . $json);

        $services = json_decode($this->callVis("findServices", "POST", $json), true);

        error_log("Service find results: " . json_encode($services));

        $res = [];

        // VIS call can return null for servicesInstances when server error occurs
        if (!isset($services["servicesInstances"])) {
            return $res;
        }

        foreach ($services["servicesInstances"] as $instance) {
            // Double loading of XML beacuse it seems that local namespaces are not working
            $xml = new SimpleXMLElement($instance["instanceAsXml"]["content"]);
            $schemaUrl = $xml->getNamespaces()["ServiceInstanceSchema"];
            $xml = new SimpleXMLElement($instance["instanceAsXml"]["content"], 0, false, $schemaUrl);

            $data["name"] = $instance["name"];
            $data["url"] = $instance["endpointUri"];
            $data["comment"] = $instance["comment"];
            if (isset($xml->IMO)) {
                $data["imo"] = (int)$xml->IMO;
            } else {
                $data["imo"] = 0;
            }

            $data["instanceId"] = $instance["instanceId"];
            $res[] = $data;
        }

        return $res;
    }

    public function visSubscribeVoyagePlan(string $receiverUrl, string $uvid): string
    {
        $data["endpointMethod"] = $receiverUrl . "/voyagePlans/subscription";
        $data["endpointMethod"] .= "?callbackEndpoint=" . rawurlencode($this->serviceInstanceUrl);
        $data["endpointMethod"] .= "&uvid=" . rawurlencode($uvid);
        $data["headers"][0]["key"] = "content-type";
        $data["headers"][0]["value"] = "text/xml; charset=utf-8";
        $data["requestType"] = "POST";

        $json = json_encode($data);

        $body = $this->callVis("callService", "POST", $json);

        // todo: handle 403 and 404
        if ($this->getPublicSideStatusCode($body) !== 200) {
            $this->throwPublicSideStatusCodeError($body);
        }

        return $body;
    }

    public function visPublishMessage(string $dataId, string $messageType, string $message): string
    {
        $parameters = "?dataId=" . rawurlencode($dataId);
        $parameters .= "&messageType=" . rawurlencode($messageType);

        $json = json_encode($message);

        $body = json_decode($this->callVis("publishMessage", "POST", $json, $parameters), true);

        if (!isset($body["dataId"])) {
            throw new Exception("publishMessage POST did not return dataId");
        }

        return $body["dataId"];
    }

    public function visSubscription(string $dataId, array $subscribers): string
    {
        $parameters = "?dataId=" . rawurlencode($dataId);

        $json = json_encode($subscribers);

        $body = json_decode($this->callVis("subscription", "POST", $json, $parameters), true);

        if (!isset($body["dataId"])) {
            throw new Exception("subscription POST did not return dataId");
        }

        return $body["dataId"];
    }

    public function visGetSubscription(string $dataId): array
    {
        $parameters = "?dataId=" . rawurlencode($dataId);

        $identities = json_decode($this->callVis("subscription", "GET", "", $parameters), true);

        return $identities;
    }

    public function visAuthorizeIdentities(string $dataId, array $identities)
    {
        $parameters = "?dataId=" . rawurlencode($dataId);

        $json = json_encode($identities);

        $body = json_decode($this->callVis("authorizeIdentities", "POST", $json, $parameters), true);

        if (!isset($body["dataId"])) {
            throw new Exception("authorizeIdentities POST did not return dataId");
        }

        return $body["dataId"];
    }
}
