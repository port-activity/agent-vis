<?php

namespace SMA\PAA\TESTING;

use SMA\PAA\CURL\CurlRequest;
use SMA\PAA\AGENT\VIS\VisClient;
use SMA\PAA\AGENT\VIS\VisRtz;

use Exception;

class VisCaller
{
    private $fromId;
    private $toId;
    private $author;
    private $subject;
    private $body;
    private $imo;
    private $voyageId;
    private $visAppId;
    private $visApiKey;
    private $visClient;
    private $rtz;

    public function __construct($fromId, $toId, $author, $subject, $body, $imo, $voyageId, $visAppId, $visApiKey, $rtz)
    {
        $this->fromId = $fromId;
        $this->toId = $toId;
        $this->author = $author;
        $this->subject = $subject;
        $this->body = $body;
        $this->imo = $imo;
        $this->voyageId = $voyageId;
        $this->visAppId = $visAppId;
        $this->visApiKey = $visApiKey;
        $this->rtz = $rtz;

        $this->visClient["UNIKIE01"] = new VisClient(
            new CurlRequest(),
            "stm",
            "sma",
            "https://smavistest.stmvalidation.eu/UNIKIE01",
            444,
            "urn:mrn:stm:service:instance:sma:vis:portofrauma",
            "Port of Rauma operator",
            "Port of Rauma schedule",
            0.0,
            0.0,
            1000.0,
            "Rauma pilot boarding ground",
            0.0,
            0.0,
            1000.0,
            true,
            $this->visAppId,
            $this->visApiKey
        );
        $this->visClient["UNIKIE02"] = new VisClient(
            new CurlRequest(),
            "stm",
            "sma",
            "https://smavistest.stmvalidation.eu/UNIKIE02",
            444,
            "urn:mrn:stm:service:instance:sma:vis:portofgavle",
            "Port of Gavle operator",
            "Port of Gavle schedule",
            0.0,
            0.0,
            1000.0,
            "Gavle outer port area",
            0.0,
            0.0,
            1000.0,
            true,
            $this->visAppId,
            $this->visApiKey
        );
        $this->visClient["UNIKIE03"] = new VisClient(
            new CurlRequest(),
            "stm",
            "sma",
            "https://smavistest.stmvalidation.eu/UNIKIE03",
            444,
            "urn:mrn:stm:service:instance:sma:unikie:testship",
            "Unikie ship operator",
            "Unikie ship schedule",
            0.0,
            0.0,
            1000.0,
            "Unikie ship sync point",
            0.0,
            0.0,
            1000.0,
            true,
            $this->visAppId,
            $this->visApiKey
        );
    }

    public function uploadTextMessage()
    {
        $this->visClient[$this->fromId]->visUploadTextMessage(
            "https://smavistest.stmvalidation.eu/" . $this->toId,
            $this->author,
            $this->subject,
            $this->body
        );
    }
    public function uploadVoyagePlan()
    {
        $xml = $this->rtz;

        $visRtz = new VisRtz($xml);
        $oldVoyageId = $visRtz->getVesselVoyage();
        $newVoyageId = $oldVoyageId["prefix"] . $this->voyageId;
        $visRtz->setVesselVoyage($newVoyageId);
        $xml = $visRtz->toXmlString();

        $this->visClient[$this->fromId]->visUploadVoyagePlan(
            "https://smavistest.stmvalidation.eu/" . $this->toId,
            $xml
        );
    }
    public function findServices()
    {
        $filters = [];

        if (isset($this->imo)) {
            $filters["imo"] = $this->imo;
        }

        $services = $this->visClient[$this->fromId]->visFindServices($filters);

        print("<hr>");
        foreach ($services as $service) {
            print("<b>Name:</b>" . $service["name"] . "<br>");
            print("<b>URL:</b>" . $service["url"] . "<br>");
            print("<b>Comment:</b>" . $service["comment"] . "<br>");
            print("<b>IMO:</b>" . $service["imo"] . "<br>");
            print("<b>Instance ID:</b>" . $service["instanceId"] . "<br>");
            print("<hr>");
        }
    }
    public function getMessages()
    {
        $messages = $this->visClient[$this->fromId]->visGetMessages();

        $html = <<<OUT
        <table>
            <tr>
                <th>Time</th>
                <th>From</th>
                <th>Type</th>
                <th>Author</th>
                <th>Subject</th>
                <th>Body</th>
            </tr>
OUT;
        print($html);

        foreach ($messages as $message) {
            // TODO: Proper message printing
            print("\n");
            print_r($message);
            print("\n");
            /*print("<tr>");
            print("<td>" . $message["time"] . "</td>");
            print("<td>" . $message["from"] . "</td>");
            print("<td>" . $message["type"] . "</td>");
            print("<td>" . $message["author"] . "</td>");
            print("<td>" . $message["subject"] . "</td>");
            print("<td>" . $message["body"] . "</td>");
            print("</tr>");*/
        }

        print("</table>");
    }
    public function getNotifications()
    {
        $notifications = $this->visClient[$this->fromId]->visGetNotifications();

        $html = <<<OUT
        <table>
            <tr>
                <th>Time</th>
                <th>From</th>
                <th>Type</th>
                <th>Subject</th>
            </tr>
OUT;
        print($html);

        foreach ($notifications as $notification) {
            print("<tr>");
            print("<td>" . $notification["ReceivedAt"] . "</td>");
            print("<td>" . $notification["FromServiceId"] . "</td>");
            print("<td>" . $notification["NotificationType"] . "</td>");
            print("<td>" . $notification["Subject"] . "</td>");
            print("</tr>");
        }
        print("</table>");
    }
    public function subscribeVoyagePlan()
    {
        $xml = $this->rtz;

        $visRtz = new VisRtz($xml);
        $oldVoyageId = $visRtz->getVesselVoyage();
        $newVoyageId = $oldVoyageId["prefix"] . $this->voyageId;

        $this->visClient[$this->fromId]->visSubscribeVoyagePlan(
            "https://smavistest.stmvalidation.eu/" . $this->toId,
            $newVoyageId
        );
    }
    public function publishVoyagePlanWithAutoSubscription()
    {
        $xml = $this->rtz;

        $visRtz = new VisRtz($xml);
        $oldVoyageId = $visRtz->getVesselVoyage();
        $newVoyageId = $oldVoyageId["prefix"] . $this->voyageId;
        $visRtz->setVesselVoyage($newVoyageId);
        $xml = $visRtz->toXmlString();

        $this->visClient[$this->fromId]->visPublishMessage(
            $newVoyageId,
            "RTZ",
            $xml
        );

        $identities = [];
        $identities[0]["identityId"] = "urn:mrn:stm:org:sma";
        $identities[0]["identityName"] = "Swedish Maritime Administration";

        $this->visClient[$this->fromId]->visAuthorizeIdentities(
            $newVoyageId,
            $identities
        );

        $subscribers = [];
        $subscribers[0]["IdentityId"] = "urn:mrn:stm:org:sma";
        $subscribers[0]["IdentityName"] = "Swedish Maritime Administration";
        $subscribers[0]["EndpointURL"] = "https://smavistest.stmvalidation.eu/UNIKIE01";
        $subscribers[1]["IdentityId"] = "urn:mrn:stm:org:sma";
        $subscribers[1]["IdentityName"] = "Swedish Maritime Administration";
        $subscribers[1]["EndpointURL"] = "https://smavistest.stmvalidation.eu/UNIKIE02";

        $this->visClient[$this->fromId]->visSubscription(
            $newVoyageId,
            $subscribers
        );
    }
    public function publishVoyagePlan()
    {
        $xml = $this->rtz;

        $visRtz = new VisRtz($xml);
        $oldVoyageId = $visRtz->getVesselVoyage();
        $newVoyageId = $oldVoyageId["prefix"] . $this->voyageId;
        $visRtz->setVesselVoyage($newVoyageId);
        $xml = $visRtz->toXmlString();

        $this->visClient[$this->fromId]->visPublishMessage(
            $newVoyageId,
            "RTZ",
            $xml
        );
    }
    public function getSubscription()
    {
        $xml = $this->rtz;

        $visRtz = new VisRtz($xml);
        $oldVoyageId = $visRtz->getVesselVoyage();
        $newVoyageId = $oldVoyageId["prefix"] . $this->voyageId;

        $this->visClient[$this->fromId]->visGetSubscription($newVoyageId);
    }
    public function authorizeIdentities()
    {
        $xml = $this->rtz;

        $visRtz = new VisRtz($xml);
        $oldVoyageId = $visRtz->getVesselVoyage();
        $newVoyageId = $oldVoyageId["prefix"] . $this->voyageId;

        $identities = [];
        $identities[0]["identityId"] = "urn:mrn:stm:org:sma";
        $identities[0]["identityName"] = "Swedish Maritime Administration";

        $this->visClient[$this->fromId]->visAuthorizeIdentities(
            $newVoyageId,
            $identities
        );
    }
}
