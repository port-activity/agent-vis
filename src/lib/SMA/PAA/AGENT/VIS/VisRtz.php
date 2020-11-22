<?php
namespace SMA\PAA\AGENT\VIS;

use Exception;
use InvalidArgumentException;
use SimpleXMLElement;
use DateTime;
use DateTimeZone;

use SMA\PAA\TOOL\DateTimeTools;

class VisRtz
{
    private $simpleXml;
    private $defaultNamespace;

    public function __construct(string $rtz)
    {
        $this->simpleXml = new SimpleXMLElement($rtz);
        $this->defaultNamespace = "";

        $this->registerXPathNamespaces($this->simpleXml);
    }

    private function registerXPathNamespaces(SimpleXMLElement $xml)
    {
        // Register namespaces for XPath
        // The relevant info is inside unnamed namespace
        // Just use "a" as dummy namespace to get XPath working
        foreach ($xml->getDocNamespaces() as $prefix => $namespace) {
            if ($prefix === "") {
                $prefix="a";
                $this->defaultNamespace = $namespace;
            }

            $xml->registerXPathNamespace($prefix, $namespace);
        }
    }

    private function simpleXmlInsertAfter(SimpleXMLElement $insert, SimpleXMLElement $target)
    {
        $targetDom = dom_import_simplexml($target);
        $insertDom = $targetDom->ownerDocument->importNode(dom_import_simplexml($insert), true);
        if ($targetDom->nextSibling) {
            return $targetDom->parentNode->insertBefore($insertDom, $targetDom->nextSibling);
        } else {
            return $targetDom->parentNode->appendChild($insertDom);
        }
    }

    private function simpleXmlInsertBefore(SimpleXMLElement $insert, SimpleXMLElement $target)
    {
        $targetDom = dom_import_simplexml($target);
        $insertDom = $targetDom->ownerDocument->importNode(dom_import_simplexml($insert), true);
        return $targetDom->parentNode->insertBefore($insertDom, $targetDom);
    }

    public function toXmlString(): string
    {
        $res = "";

        $res = $this->simpleXml->asXML();

        if ($res === false) {
            throw new Exception("Cannot create XML");
        }

        return $res;
    }

    public function getVesselVoyage() : array
    {
        $res = [];

        $element = $this->simpleXml->xpath('/a:route/a:routeInfo/@vesselVoyage');
        if (empty($element)) {
            throw new Exception("Cannot find vesselVoyage");
        }
        $vesselVoyage = (string)$element[0]["vesselVoyage"];

        preg_match('/urn:mrn:stm:voyage:id:.*?:(.*$)/', $vesselVoyage, $match);
        $res["complete"] = $match[0];
        $res["postfix"] = $match[1];
        $res["prefix"] = rtrim($res["complete"], $res["postfix"]);

        return $res;
    }

    public function setVesselVoyage(string $vesselVoyage)
    {
        $element = $this->simpleXml->xpath('/a:route/a:routeInfo/@vesselVoyage');
        if (empty($element)) {
            throw new Exception("Cannot find vesselVoyage");
        }
        $element[0]["vesselVoyage"] = $vesselVoyage;
    }

    public function getRouteStatusEnum() : string
    {
        $res = "";

        $element = $this->simpleXml->xpath('/a:route/a:routeInfo/a:extensions/a:extension/@routeStatusEnum');
        if (empty($element)) {
            throw new Exception("Cannot find routeStatusEnum");
        }
        $res = (string)$element[0]["routeStatusEnum"];

        return $res;
    }

    public function setRouteStatusEnum(string $routeStatusEnum)
    {
        $element = $this->simpleXml->xpath('/a:route/a:routeInfo/a:extensions/a:extension/@routeStatusEnum');
        if (empty($element)) {
            throw new Exception("Cannot find routeStatusEnum");
        }
        $element[0]["routeStatusEnum"] = $routeStatusEnum;
    }

    public function getWaypoints() : array
    {
        $res = [];

        $geometryType = "";

        $element = $this->simpleXml->xpath('/a:route/a:waypoints/a:defaultWaypoint/a:leg/@geometryType');
        if (!empty($element)) {
            $geometryType = (string)$element[0]["geometryType"];
        }

        $waypoints = $this->simpleXml->xpath('/a:route/a:waypoints/a:waypoint');
        if (empty($waypoints)) {
            throw new Exception("Cannot find waypoints");
        }

        $waypointCt = 0;
        foreach ($waypoints as $waypoint) {
            $this->registerXPathNamespaces($waypoint);

            $element = $waypoint->xpath('@id');
            if (empty($element)) {
                throw new Exception("Cannot find id from waypoint");
            }
            $id = (int)$element[0]["id"];

            $element = $waypoint->xpath('./a:position/@lat');
            if (empty($element)) {
                throw new Exception("Cannot find lat from waypoint");
            }
            $lat = (float)$element[0]["lat"];

            $element = $waypoint->xpath('./a:position/@lon');
            if (empty($element)) {
                throw new Exception("Cannot find lon from waypoint");
            }
            $lon = (float)$element[0]["lon"];

            $element = $waypoint->xpath('./a:leg/@geometryType');
            if (!empty($element)) {
                $geometryType = (string)$element[0]["geometryType"];
            }

            $res[$waypointCt]["id"] = $id;
            $res[$waypointCt]["lat"] = $lat;
            $res[$waypointCt]["lon"] = $lon;
            $res[$waypointCt]["geometryType"] = $geometryType;
            $waypointCt++;
        }

        return $res;
    }

    public function getWaypointIds() : array
    {
        $res = [];

        $waypoints = $this->simpleXml->xpath('/a:route/a:waypoints/a:waypoint');
        if (empty($waypoints)) {
            throw new Exception("Cannot find waypoints");
        }

        foreach ($waypoints as $waypoint) {
            $this->registerXPathNamespaces($waypoint);

            $element = $waypoint->xpath('@id');
            if (empty($element)) {
                throw new Exception("Cannot find id from waypoint");
            }
            $res[] = (int)$element[0]["id"];
        }

        return $res;
    }

    public function newWaypoint(string $waypointName, int $insertBeforeWaypointId, array $latLon): int
    {
        $geometryType = "";
        $lat = $latLon["lat"];
        $lon = $latLon["lon"];

        $waypointIds = $this->getWaypointIds();
        rsort($waypointIds);
        $waypointId = 1 + reset($waypointIds);

        $query = '/a:route/a:waypoints/a:waypoint[@id=';
        $query .= "'$insertBeforeWaypointId']";
        $waypoint = $this->simpleXml->xpath($query);

        if (empty($waypoint)) {
            throw new Exception("Cannot find waypointId: ".$insertBeforeWaypointId);
        }

        $this->registerXPathNamespaces($waypoint[0]);
        $element = $waypoint[0]->xpath('./a:leg/@geometryType');
        if (!empty($element)) {
            $geometryType = (string)$element[0]["geometryType"];
        }

        // Namespace definition needed since we are adding the element via DOM
        $waypointStr = '<waypoint xmlns="'.$this->defaultNamespace.'" id="'.$waypointId.'" ';
        $waypointStr .= 'revision="1" name="'.$waypointName.'" radius="0.0">';
        $waypointStr .= '<position lat="'.$lat.'" lon="'.$lon.'" />';
        if ($geometryType !== "") {
            $waypointStr .= '<leg geometryType="'.$geometryType.'" />';
        }
        $waypointStr .= '</waypoint>';
        $waypointXml = new SimpleXMLElement($waypointStr);
        $this->simpleXmlInsertBefore($waypointXml, $waypoint[0]);

        return $waypointId;
    }

    public function getCalculatedSchedule() : ?array
    {
        $res = [];

        $schedule = $this->simpleXml->xpath('/a:route/a:schedules/a:schedule');

        if (!empty($schedule)) {
            $this->registerXPathNamespaces($schedule[0]);
            $element = $schedule[0]->xpath('@id');
            if (empty($element)) {
                throw new Exception("Cannot find id from schedule");
            }
            $scheduleId = (int)$element[0]["id"];

            $scheduleElements = $schedule[0]->xpath('./a:calculated/a:scheduleElement');

            if (empty($scheduleElements)) {
                return null;
            }

            foreach ($scheduleElements as $scheduleElement) {
                $this->registerXPathNamespaces($scheduleElement);

                $element = $scheduleElement->xpath('@waypointId');
                if (empty($element)) {
                    throw new Exception("Cannot find waypointId from scheduleElement");
                }
                $waypointId = (int)$element[0]["waypointId"];

                // ETD entries ignored, since we are only interested in ETA at the moment
                $element = $scheduleElement->xpath('@eta');
                if (!empty($element)) {
                    $eta = (string)$element[0]["eta"];
                    $res[$scheduleId][$waypointId] = $eta;
                }
            }
        } else {
            return null;
        }

        return $res;
    }

    public function deleteCalculatedSchedule()
    {
        $element = $this->simpleXml->xpath('/a:route/a:schedules/a:schedule/a:calculated');
        if (!empty($element)) {
            $parent = $element[0];
            unset($parent[0]);
        }
    }

    public function getEta(int $waypointId) : string
    {
        $res = "";

        $query = '/a:route/a:schedules/a:schedule/a:calculated/';
        $query .= '/a:scheduleElement[@waypointId=';
        $query .= "'$waypointId']/@eta";
        $element = $this->simpleXml->xpath($query);
        if (!empty($element)) {
            $dateTimeTools = new DateTimeTools();
            $time = $dateTimeTools->createDateTime((string)$element[0]["eta"]);
            $time->setTimezone(new DateTimeZone("UTC"));
            $res = $time->format("Y-m-d\TH:i:sP");
        }

        return $res;
    }

    public function getManualEtd(int $waypointId) : string
    {
        $res = "";

        $query = '/a:route/a:schedules/a:schedule/a:manual/';
        $query .= '/a:scheduleElement[@waypointId=';
        $query .= "'$waypointId']/@etd";
        $element = $this->simpleXml->xpath($query);
        if (!empty($element)) {
            $dateTimeTools = new DateTimeTools();
            $time = $dateTimeTools->createDateTime((string)$element[0]["etd"]);
            $time->setTimezone(new DateTimeZone("UTC"));
            $res = $time->format("Y-m-d\TH:i:sP");
        }

        return $res;
    }

    public function setEta(string $waypointId, string $eta, string $windowBefore, string $windowAfter)
    {
        $dateTimeTools = new DateTimeTools();
        $newTime = $dateTimeTools->createDateTime($eta);
        $newTime->setTimezone(new DateTimeZone("UTC"));
        $eta = $newTime->format("Y-m-d\TH:i:sP");

        $waypointIds = $this->getWaypointIds();
        $pos = array_search($waypointId, $waypointIds);

        if ($pos === false) {
            throw new Exception("Cannot find waypoint ID: ".$waypointId);
        }

        $beforeIds = array_slice($waypointIds, 0, $pos);
        $afterIds = array_slice($waypointIds, $pos+1);

        // Check if manual schedule exists
        $query = '/a:route/a:schedules/a:schedule/a:manual';
        $manual = $this->simpleXml->xpath($query);

        // If manual schedule does not exist, try to create it
        if (empty($manual)) {
            $query = '/a:route/a:schedules/a:schedule';
            $schedule = $this->simpleXml->xpath($query);

            if (!empty($schedule)) {
                $schedule[0]->addChild("manual");
            }

            $query = '/a:route/a:schedules/a:schedule/a:manual';
            $manual = $this->simpleXml->xpath($query);
        }

        // If manual schedule still does not exist, throw error
        if (empty($manual)) {
            throw new Exception("Cannot create manual section to schedule");
        }

        // Check if given waypoint exists in manual schedule
        $query = '/a:route/a:schedules/a:schedule/a:manual/';
        $query .= '/a:scheduleElement[@waypointId=';
        $query .= "'$waypointId']";
        $existingScheduleElement = $this->simpleXml->xpath($query);

        $scheduleElementAdded = false;
        if (!empty($existingScheduleElement)) {
            $this->registerXPathNamespaces($existingScheduleElement[0]);
            // Check if waypoint already has ETA attribute
            $existingEta = $existingScheduleElement[0]->xpath('@eta');
            if (!empty($existingEta)) {
                // Change existing ETA, retain timezone if set
                try {
                    $oldTime = new DateTime($existingEta[0]["eta"]);
                    $oldTimezone = $oldTime->getTimezone();
                    if ($oldTimezone !== false) {
                        $newTime->setTimezone(new DateTimeZone($oldTimezone));
                        $eta = $newTime->format("Y-m-d\TH:i:sP");
                    }
                } catch (Exception $e) {
                    // Do nothing. If the existing ETA was not valid time, just overwrite it.
                }
                $existingEta[0]["eta"] = $eta;
            } else {
                // Add new ETA attribute
                $existingScheduleElement[0]->addAttribute("eta", $eta);
            }

            if ($windowBefore !== "") {
                $existingEtaWindowBefore = $existingScheduleElement[0]->xpath('@etaWindowBefore');
                if (!empty($existingEtaWindowBefore)) {
                    $existingEtaWindowBefore[0]["etaWindowBefore"] = $windowBefore;
                } else {
                    $existingScheduleElement[0]->addAttribute("etaWindowBefore", $windowBefore);
                }
            }

            if ($windowAfter !== "") {
                $existingEtaWindowAfter = $existingScheduleElement[0]->xpath('@etaWindowAfter');
                if (!empty($existingEtaWindowAfter)) {
                    $existingEtaWindowAfter[0]["etaWindowAfter"] = $windowAfter;
                } else {
                    $existingScheduleElement[0]->addAttribute("etaWindowAfter", $windowAfter);
                }
            }

            $scheduleElementAdded = true;
        }

        // If needed add new waypoint to manual schedule to correct location
        // Also clear speeds from waypoints before and purge manual schedule
        $query = '/a:route/a:schedules/a:schedule/a:manual/a:scheduleElement';
        $scheduleElements = $this->simpleXml->xpath($query);

        $scheduleElementDeleteList = [];
        // Namespace definition needed since we are adding the element via DOM
        $scheduleElementStr = '<scheduleElement xmlns="'.$this->defaultNamespace.'" ';
        $scheduleElementStr .= 'waypointId="'.$waypointId.'" ';
        $scheduleElementStr .= 'eta="'.$eta.'" ';
        if ($windowBefore !== "") {
            $scheduleElementStr .= 'etaWindowBefore="'.$windowBefore.'" ';
        }
        if ($windowAfter !== "") {
            $scheduleElementStr .= 'etaWindowAfter="'.$windowAfter.'" ';
        }
        $scheduleElementStr .= '/>';
        $scheduleElementXml = new SimpleXMLElement($scheduleElementStr);
        foreach ($scheduleElements as $scheduleElement) {
            $this->registerXPathNamespaces($scheduleElement);
            $scheduleElementWaypointIdAttribute = $scheduleElement->xpath('./@waypointId');

            if (empty($scheduleElementWaypointIdAttribute)) {
                throw new Exception("Cannot find waypointId from manual schedule element");
            }
            $scheduleElementWaypointId = $scheduleElementWaypointIdAttribute[0]["waypointId"];

            if (array_search($scheduleElementWaypointId, $afterIds) !== false) {
                if (!$scheduleElementAdded) {
                    $scheduleElementAdded = true;
                    $this->simpleXmlInsertBefore($scheduleElementXml, $scheduleElement);
                }
            }

            // Clear speeds from waypoints before
            if (array_search($scheduleElementWaypointId, $beforeIds) !== false) {
                $scheduleElementSpeedAttribute = $scheduleElement->xpath('./@speed');

                if (!empty($scheduleElementSpeedAttribute)) {
                    $parent = $scheduleElementSpeedAttribute[0];
                    unset($parent[0]);
                }

                // Check if we cleared all attributes
                // If cleared then add schedule element to delete list
                $scheduleElementAttributes = $scheduleElement->xpath('./@*');
                if (count($scheduleElementAttributes) == 1 &&
                    isset($scheduleElementAttributes[0]["waypointId"])
                ) {
                    $scheduleElementDeleteList[] = $scheduleElement;
                }
            }
        }

        // If still not added, then add as last schedule element
        if (!$scheduleElementAdded) {
            if (isset($scheduleElement)) {
                $this->simpleXmlInsertAfter($scheduleElementXml, $scheduleElement);
            } else {
                $newScheduleElement = $manual[0]->addChild("scheduleElement");
                $newScheduleElement->addAttribute("waypointId", $waypointId);
                $newScheduleElement->addAttribute("eta", $eta);
                if ($windowBefore !== "") {
                    $newScheduleElement->addAttribute("etaWindowBefore", $windowBefore);
                }
                if ($windowAfter !== "") {
                    $newScheduleElement->addAttribute("etaWindowAfter", $windowAfter);
                }
            }
        }

        // Purge delete list
        foreach ($scheduleElementDeleteList as $scheduleElementDelete) {
            $parent = $scheduleElementDelete[0];
            unset($parent[0]);
        }
    }

    public function getScheduleName() : string
    {
        $res = "";

        $query = '/a:route/a:schedules/a:schedule/@name';
        $element = $this->simpleXml->xpath($query);
        if (!empty($element)) {
            $res = (string)$element[0]["name"];
        }

        return $res;
    }

    public function setScheduleName(string $name)
    {
        $query = '/a:route/a:schedules/a:schedule';
        $schedule = $this->simpleXml->xpath($query);

        if (!empty($schedule)) {
            $query = '/a:route/a:schedules/a:schedule/@name';
            $nameAttribute = $this->simpleXml->xpath($query);
            if (!empty($nameAttribute)) {
                $nameAttribute[0]["name"] = $name;
            } else {
                $schedule[0]->addAttribute("name", $name);
            }
        }
    }

    public function getRouteAuthor() : string
    {
        $res = "";

        $element = $this->simpleXml->xpath('/a:route/a:routeInfo/@routeAuthor');
        if (!empty($element)) {
            $res = (string)$element[0]["routeAuthor"];
        }

        return $res;
    }

    public function setRouteAuthor(string $routeAuthor)
    {
        $routeInfo = $this->simpleXml->xpath('/a:route/a:routeInfo');

        if (!empty($routeInfo)) {
            $routeAuthorAttribute = $this->simpleXml->xpath('/a:route/a:routeInfo/@routeAuthor');
            if (!empty($routeAuthorAttribute)) {
                $routeAuthorAttribute[0]["routeAuthor"] = $routeAuthor;
            } else {
                $routeInfo[0]->addAttribute("routeAuthor", $routeAuthor);
            }
        }
    }

    public function setRouteStatus(string $routeStatus)
    {
        $routeInfo = $this->simpleXml->xpath('/a:route/a:routeInfo');

        if (!empty($routeInfo)) {
            $routeStatusAttribute = $this->simpleXml->xpath('/a:route/a:routeInfo/@routeStatus');
            if (!empty($routeStatusAttribute)) {
                $routeStatusAttribute[0]["routeStatus"] = $routeStatus;
            } else {
                $routeInfo[0]->addAttribute("routeStatus", $routeStatus);
            }
        }
    }

    public function getVesselImo() : int
    {
        $res = 0;

        $element = $this->simpleXml->xpath('/a:route/a:routeInfo/@vesselIMO');
        if (!empty($element)) {
            $res = (int)$element[0]["vesselIMO"];
        }

        return $res;
    }

    public function getVesselName() : string
    {
        $res = "";

        $element = $this->simpleXml->xpath('/a:route/a:routeInfo/@vesselName');
        if (!empty($element)) {
            $res = (string)$element[0]["vesselName"];
        }

        return $res;
    }
}
