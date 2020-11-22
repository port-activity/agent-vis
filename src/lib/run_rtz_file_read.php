<?php
namespace SMA\PAA\AGENT;

require_once "init.php";

use SMA\PAA\CURL\CurlRequest;
use SMA\PAA\TOOL\GeoTool;
use SMA\PAA\AGENT\VIS\VisClient;
use SMA\PAA\AGENT\VIS\VisRtzFileRead;
use Exception;

if (!isset($argv[1])) {
    throw new Exception("Output directory not given as argument!");
}

if (!isset($argv[2])) {
    throw new Exception("Input file not given as argument!");
}

$visClient = VisClient::createFromEnv(new CurlRequest());

$visRtzFileRead = new VisRtzFileRead($visClient, new GeoTool(), $argv[1], $argv[2]);
try {
    $visRtzFileRead->execute();
} catch (\Exception $e) {
    print("ERROR:" . $e->getMessage());
}
