<?php
namespace SMA\PAA\RESULTPOSTER;

use SMA\PAA\AGENT\ApiConfig;

interface IResultPoster
{
    public function postResult(ApiConfig $apiConfig, array $result): bool;
}
