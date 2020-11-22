<?php

$envs = [
    "API_KEY" => "API_KEY"
    ,"API_URL_NOTIFICATIONS" => "API_URL_NOTIFICATIONS"
    ,"API_URL_MESSAGES" => "API_URL_MESSAGES"
    ,"API_URL_VOYAGE_PLANS" => "API_URL_VOYAGE_PLANS"
    ,"API_URL_TIMESTAMPS" => "API_URL_TIMESTAMPS"
    ,"API_URL_VIS_VESSELS" => "API_URL_VIS_VESSELS"
    ,"API_URL_INBOUND_VESSELS" => "API_URL_INBOUND_VESSELS"
    ,"VIS_SECURE_COMMUNICATIONS" => true or false
    ,"VIS_APP_ID" => "VIS_APP_ID"
    ,"VIS_API_KEY" => "VIS_API_KEY"
    ,"VIS_GOVERNING_ORG" => "gov"
    ,"VIS_OWN_ORG" => "own"
    ,"VIS_SERVICE_INSTANCE_URL" => "https://own.gov.eu/ENDPOINT"
    ,"VIS_PRIVATE_SIDE_PORT" => 123
    ,"VIS_SERVICE_INSTANCE_URN" => "urn:mrn:gov:service:instance:own:vis:instance"
    ,"VIS_RTZ_ROUTE_AUTHOR" => "Port operator"
    ,"VIS_RTZ_SCHEDULE_NAME" => "Port schedule"
    ,"VIS_PORT_LAT" => 0.00
    ,"VIS_PORT_LON" => 0.00
    ,"VIS_PORT_RADIUS" => 1000.0
    ,"VIS_SYNC_POINT_NAME" => "Sync point name"
    ,"VIS_SYNC_POINT_LAT" => 0.00
    ,"VIS_SYNC_POINT_LON" => 0.00
    ,"VIS_SYNC_POINT_RADIUS" => 1000.0
    ,"VIS_OUTPUT_DIRECTORY" => ""
    ,"AINO_API_KEY" => ""
];

foreach ($envs as $k => $v) {
    putenv("$k=$v");
};
