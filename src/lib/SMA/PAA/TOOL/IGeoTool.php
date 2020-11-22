<?php
namespace SMA\PAA\TOOL;

interface IGeoTool
{
    public function createLatLon(float $lat, float $lon): array;
    public function subLatLon(array $startLatLon, float $endLatLon): array;
    public function pointDistanceFlat(array $fromLatLon, array $toLatLon): float;
    public function pointDistanceGreatCircle(array $fromLatLon, array $toLatLon): float;
    public function pointDistanceHaversine(array $fromLatLon, array $toLatLon): float;
    public function pointDistanceVincenty(array $fromLatLon, array $toLatLon): float;
    public function crossTrackDistanceToArc(array $startLatLon, array $endLatLon, array $pointLatLon): ?float;
    public function alongTrackDistance(
        array $startLatLon,
        array $endLatLon,
        array $pointLatLon,
        float $crossTrackDistance
    ): ?float;
    public function destination(array $startLatLon, array $endLatLon, float $distance): array;
    public function crossTrackToRhumbLine(array $startLatLon, array $endLatLon, array $pointLatLon): array;
}
