<?php
namespace SMA\PAA\TOOL;

use League\Geotools\Geotools;
use League\Geotools\Coordinate\Ellipsoid;
use League\Geotools\Coordinate\Coordinate;
use League\Geotools\Vertex\Vertex;
use League\Geotools\Distance\Distance;

use Exception;
use InvalidArgumentException;

class GeoTool implements IGeoTool
{
    private $geoTools;
    private $ellipsoid;
    private $angularUnit;
    private $earthRadius;

    public function __construct(string $ellipsoid = "WGS84", string $angularUnit = "degrees")
    {
        $this->geoTools = new Geotools();
        $this->ellipsoid = $ellipsoid;
        $this->angularUnit = $angularUnit;

        $this->unitCheck();

        $this->earthRadius = Ellipsoid::createFromName($ellipsoid)->getA();
    }

    private function unitCheck()
    {
        if (!($this->ellipsoid == "WGS84" && $this->angularUnit == "degrees")) {
            throw new InvalidArgumentException("Only WGS84 and degrees supported");
        }
    }

    private function createCoord(array $latLon): Coordinate
    {
        if (!isset($latLon["lat"])) {
            throw new InvalidArgumentException("Latitude missing from coordinate array");
        }

        if (!isset($latLon["lon"])) {
            throw new InvalidArgumentException("Longitude missing from coordinate array");
        }

        return new Coordinate([$latLon["lat"], $latLon["lon"]]);
    }

    private function createVertex(array $startLatLon, array $endLatLon): Vertex
    {
        $coordStartLatLon = $this->createCoord($startLatLon);
        $coordEndLatLon = $this->createCoord($endLatLon);

        return $this->geoTools->vertex()->setFrom($coordStartLatLon)->setTo($coordEndLatLon);
    }

    private function createDist(Coordinate $from, Coordinate $to): Distance
    {
        return $this->geoTools->distance()->setFrom($from)->setTo($to);
    }

    public function createLatLon(float $lat, float $lon): array
    {
        $res = [];

        $res["lat"] = $lat;
        $res["lon"] = $lon;

        return $res;
    }

    public function subLatLon(array $startLatLon, float $endLatLon): array
    {
        $res = [];

        $res["lat"] = $endLatLon["lat"] - $startLatLon["lat"];
        $res["lon"] = $endLatLon["lon"] - $startLatLon["lon"];

        return $res;
    }

    public function pointDistanceFlat(array $fromLatLon, array $toLatLon): float
    {
        $this->unitCheck();

        $coordFrom = $this->createCoord($fromLatLon);
        $coordTo = $this->createCoord($toLatLon);
        $distance = $this->createDist($coordFrom, $coordTo);

        return $distance->flat();
    }

    public function pointDistanceGreatCircle(array $fromLatLon, array $toLatLon): float
    {
        $this->unitCheck();

        $coordFrom = $this->createCoord($fromLatLon);
        $coordTo = $this->createCoord($toLatLon);
        $distance = $this->createDist($coordFrom, $coordTo);

        return $distance->greatCircle();
    }

    public function pointDistanceHaversine(array $fromLatLon, array $toLatLon): float
    {
        $this->unitCheck();

        $coordFrom = $this->createCoord($fromLatLon);
        $coordTo = $this->createCoord($toLatLon);
        $distance = $this->createDist($coordFrom, $coordTo);

        return $distance->haversine();
    }

    public function pointDistanceVincenty(array $fromLatLon, array $toLatLon): float
    {
        $this->unitCheck();

        $coordFrom = $this->createCoord($fromLatLon);
        $coordTo = $this->createCoord($toLatLon);
        $distance = $this->createDist($coordFrom, $coordTo);

        return $distance->vincenty();
    }

    public function crossTrackDistanceToArc(array $startLatLon, array $endLatLon, array $pointLatLon): ?float
    {
        // dxt = asin( sin(δ13) ⋅ sin(θ13−θ12) ) ⋅ R
        // where  δ13 is (angular) distance from start point to third point
        // θ13 is (initial) bearing from start point to third point
        // θ12 is (initial) bearing from start point to end point
        // R is the earth’s radius

        $this->unitCheck();

        $dxt = 0.0;

        $R = $this->earthRadius;

        // Angular distance from start to point in radians
        $d13 = $this->pointDistanceVincenty($startLatLon, $pointLatLon) / $R;

        // Angle from start to point
        $vertex13 = $this->createVertex($startLatLon, $pointLatLon);
        $t13 = deg2rad($vertex13->initialBearing());

        // Angle from start to end
        $vertex12 = $this->createVertex($startLatLon, $endLatLon);
        $t12 = deg2rad($vertex12->initialBearing());

        // Angle from end to point
        $vertex23 = $this->createVertex($endLatLon, $pointLatLon);
        $t23 = deg2rad($vertex23->initialBearing());

        // Angle from end to start
        $vertex21 = $this->createVertex($endLatLon, $startLatLon);
        $t21 = deg2rad($vertex21->initialBearing());

        // Check that both angles are acute
        // If not then cross track distance to arc defined by leg is not valid
        if (abs($t13 - $t12) > M_PI_2 || abs($t23 - $t21) > M_PI_2) {
            return null;
        }

        // Cross track distance in units speficied by earth radius
        $dxt = asin(sin($d13) * sin($t13 - $t12)) * $R;

        // Sign is irrelevant in our use cases
        return abs($dxt);
    }

    public function alongTrackDistance(
        array $startLatLon,
        array $endLatLon,
        array $pointLatLon,
        float $crossTrackDistance
    ): ?float {
        // dat = acos( cos(δ13) / cos(δxt) ) ⋅ R
        // where  δ13 is (angular) distance from start point to third point
        // δxt is (angular) cross-track distance
        // R is the earth’s radius

        $this->unitCheck();

        $dat = 0.0;

        $R = $this->earthRadius;

        // Angular distance from start to point in radians
        $d13 = $this->pointDistanceVincenty($startLatLon, $pointLatLon) / $R;

        // Angular cross track distance
        $dxt = $crossTrackDistance / $R;

        // Along track distance in units speficied by earth radius
        $dat = acos(cos($d13) / cos($dxt)) * $R;

        // Check if distance is within leg distance
        $legLength = $this->pointDistanceVincenty($startLatLon, $endLatLon);
        if ($dat > $legLength) {
            return null;
        }

        return $dat;
    }

    public function destination(array $startLatLon, array $endLatLon, float $distance): array
    {
        $res = [];

        $vertex = $this->createVertex($startLatLon, $endLatLon);
        $bearing = $vertex->initialBearing();
        $destination = $vertex->destination($bearing, $distance);

        $res["lat"] = $destination->getLatitude();
        $res["lon"] = $destination->getLongitude();

        return $res;
    }

    public function crossTrackToRhumbLine(array $startLatLon, array $endLatLon, array $pointLatLon): array
    {
        // Brute force closest point along rhumb line to given point

        $res = [];

        $segments = ceil($this->pointDistanceVincenty($startLatLon, $endLatLon) / 10);

        $dLat = ($endLatLon["lat"] - $startLatLon["lat"]) / $segments;
        $dLon = ($endLatLon["lon"] - $startLatLon["lon"]) / $segments;

        $lat = $startLatLon["lat"];
        $lon = $startLatLon["lon"];
        $distArray = [];
        for ($i = 0; $i <= $segments; $i++) {
            $rhumbLinePoint = $this->createLatLon($lat, $lon);
            $dist = $this->pointDistanceVincenty($rhumbLinePoint, $pointLatLon);
            $distArray[$i] = $dist;
            $lat = $lat + $dLat;
            $lon = $lon + $dLon;
        }
        asort($distArray);
        $closestPoint = reset($distArray);
        $i = key($distArray);

        $lat = $startLatLon["lat"] + ($i * $dLat);
        $lon = $startLatLon["lon"] + ($i * $dLon);

        $res = $this->createLatLon($lat, $lon);

        return $res;
    }
}
