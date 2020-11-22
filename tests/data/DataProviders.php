<?php

namespace TESTS\DATA;

use SMA\PAA\FAKECURL\FakeCurlRequest;

use SMA\PAA\AGENT\VIS\VisClient;
use SMA\PAA\TOOL\GeoTool;

class DataProviders
{
    public static $dirs = [
        "FIRAU-SELandskrona",
        "Gavle-Rauma_Difficult_to_find_PBGWP",
        "Gavle-Rauma_NoSchedule",
        "SELandskrona-FIRAU_NO_Pilot_WP",
        "SELandskrona-FIRAU_Route_End_before_PBG",
        "SELandskrona-FIRAU_WP_near_PBG_1",
    ];

    public static function rtzFileContents(string $dir)
    {
        return file_get_contents(__DIR__ . "/" . $dir . "/" . $dir . ".rtz");
    }

    public static function resultFileContents(string $dir, string $result)
    {
        return file_get_contents(__DIR__ . "/" . $dir . "/" . $result . ".json");
    }

    public static function getVisClient(FakeCurlRequest $fakeCurl = null): VisClient
    {
        if ($fakeCurl === null) {
            $fakeCurl = new FakeCurlRequest();
        }

        $res = new VisClient(
            $fakeCurl,
            "gov",
            "own",
            "https://test.url/TEST",
            123,
            "urn:mrn:gov:service:instance:own:vis:testport",
            "Test port operator",
            "Test port schedule",
            61.1297155,
            21.4491551,
            3704,
            "Test sync point",
            61.11806,
            21.16778,
            1852,
            true,
            "testappid",
            "testapikey"
        );

        return $res;
    }

    public static function getFakeCurl(): FakeCurlRequest
    {
        return new FakeCurlRequest();
    }

    public static function getVisCurlUrl(): string
    {
        return "https://test.url:123/TEST/";
    }

    public static function getVisCurlGetOpt(): array
    {
        $res[CURLOPT_RETURNTRANSFER] = 1;
        $res[CURLOPT_HEADER] = 1;
        $res[CURLOPT_HTTPHEADER][0] = "Content-Type: application/json";
        $res[CURLOPT_HTTPHEADER][1] = "Accept: application/json";
        $res[CURLOPT_HTTPHEADER][2] = "Authorization: amx testappid:";

        return $res;
    }

    public static function getVisCurlPostOptCommon(): array
    {
        $res[CURLOPT_RETURNTRANSFER] = 1;
        $res[CURLOPT_HEADER] = 1;
        $res[CURLOPT_HTTPHEADER][0] = "Content-Type: application/json";
        $res[CURLOPT_HTTPHEADER][1] = "Accept: application/json";
        $res[CURLOPT_HTTPHEADER][2] = "Authorization: amx testappid:";
        $res[CURLOPT_POST] = 1;

        return $res;
    }

    public static function getGeoTool(): GeoTool
    {
        $res = new GeoTool();

        return $res;
    }

    public static function getVesselVoyageProvider(): array
    {
        $res = [];

        foreach (DataProviders::$dirs as $dir) {
            $res[$dir][0] = DataProviders::rtzFileContents($dir);
            $res[$dir][1] = DataProviders::resultFileContents($dir, "vessel_voyage");
        }

        return $res;
    }

    public static function getRouteStatusEnumProvider(): array
    {
        $res = [];

        foreach (DataProviders::$dirs as $dir) {
            $res[$dir][0] = DataProviders::rtzFileContents($dir);
            $res[$dir][1] = DataProviders::resultFileContents($dir, "route_status_enum");
        }

        return $res;
    }

    public static function getWaypointsProvider(): array
    {
        $res = [];

        foreach (DataProviders::$dirs as $dir) {
            $res[$dir][0] = DataProviders::rtzFileContents($dir);
            $res[$dir][1] = DataProviders::resultFileContents($dir, "waypoints");
        }

        return $res;
    }

    public static function getWaypointIdsProvider(): array
    {
        $res = [];

        foreach (DataProviders::$dirs as $dir) {
            $res[$dir][0] = DataProviders::rtzFileContents($dir);
            $res[$dir][1] = DataProviders::resultFileContents($dir, "waypoint_ids");
        }

        return $res;
    }

    public static function newWaypointReturnProvider(): array
    {
        $res = [];

        foreach (DataProviders::$dirs as $dir) {
            $res[$dir][0] = DataProviders::rtzFileContents($dir);
            $res[$dir][1] = DataProviders::resultFileContents($dir, "new_waypoint_return");
        }

        return $res;
    }

    public static function newWaypointRtzProvider(): array
    {
        $res = [];

        foreach (DataProviders::$dirs as $dir) {
            $res[$dir][0] = DataProviders::rtzFileContents($dir);
            $res[$dir][1] = DataProviders::resultFileContents($dir, "new_waypoint_rtz");
        }

        return $res;
    }

    public static function getCalculatedScheduleProvider(): array
    {
        $res = [];

        $dir = "Calculated_Schedule";
        $res[$dir][0] = DataProviders::rtzFileContents($dir);
        $res[$dir][1] = DataProviders::resultFileContents($dir, "calculated_schedule");

        return $res;
    }

    public static function deleteCalculatedScheduleRtzProvider(): array
    {
        $res = [];

        $dir = "Calculated_Schedule";
        $res[$dir][0] = DataProviders::rtzFileContents($dir);
        $res[$dir][1] = DataProviders::resultFileContents($dir, "delete_calculated_schedule_rtz");

        return $res;
    }

    public static function getEtaProvider(): array
    {
        $res = [];

        $dir = "Calculated_Schedule";
        $res[$dir][0] = DataProviders::rtzFileContents($dir);
        $res[$dir][1] = DataProviders::resultFileContents($dir, "eta");

        return $res;
    }

    public static function setEtaRtzProvider(): array
    {
        $res = [];

        $dir = "Calculated_Schedule";
        $res[$dir][0] = DataProviders::rtzFileContents($dir);
        $res[$dir][1] = DataProviders::resultFileContents($dir, "set_eta_rtz");

        return $res;
    }

    public static function getScheduleNameProvider(): array
    {
        $res = [];

        foreach (DataProviders::$dirs as $dir) {
            $res[$dir][0] = DataProviders::rtzFileContents($dir);
            $res[$dir][1] = DataProviders::resultFileContents($dir, "schedule_name");
        }

        return $res;
    }

    public static function setScheduleNameRtzProvider(): array
    {
        $res = [];

        foreach (DataProviders::$dirs as $dir) {
            $res[$dir][0] = DataProviders::rtzFileContents($dir);
            $res[$dir][1] = DataProviders::resultFileContents($dir, "set_schedule_name_rtz");
        }

        return $res;
    }

    public static function getRouteAuthorProvider(): array
    {
        $res = [];

        foreach (DataProviders::$dirs as $dir) {
            $res[$dir][0] = DataProviders::rtzFileContents($dir);
            $res[$dir][1] = DataProviders::resultFileContents($dir, "route_author");
        }

        return $res;
    }

    public static function setRouteAuthorRtzProvider(): array
    {
        $res = [];

        foreach (DataProviders::$dirs as $dir) {
            $res[$dir][0] = DataProviders::rtzFileContents($dir);
            $res[$dir][1] = DataProviders::resultFileContents($dir, "set_route_author_rtz");
        }

        return $res;
    }

    public static function setRouteStatusRtzProvider(): array
    {
        $res = [];

        foreach (DataProviders::$dirs as $dir) {
            $res[$dir][0] = DataProviders::rtzFileContents($dir);
            $res[$dir][1] = DataProviders::resultFileContents($dir, "set_route_status_rtz");
        }

        return $res;
    }

    public static function setEtaToWaypointRtzProvider(): array
    {
        $res = [];

        $dir = "Calculated_Schedule";
        $res[$dir][0] = DataProviders::rtzFileContents($dir);
        $res[$dir][1] = DataProviders::resultFileContents($dir, "set_eta_to_waypoint_rtz");

        return $res;
    }

    public static function setEtaToNewWaypointRtzProvider(): array
    {
        $res = [];

        $dir = "Calculated_Schedule";
        $res[$dir][0] = DataProviders::rtzFileContents($dir);
        $res[$dir][1] = DataProviders::resultFileContents($dir, "set_eta_to_new_waypoint_rtz");

        return $res;
    }

    public static function isVoyagePlanRelevantProvider(): array
    {
        $res = [];

        $waypointsTrue = [
            ["lat" => 1.2, "lon" => 2.3],
            ["lat" => 1.2, "lon" => 2.3],
            ["lat" => 61.129, "lon" => 21.449]];
        $waypointsFalse = [
            ["lat" => 1.2, "lon" => 2.3],
            ["lat" => 1.2, "lon" => 2.3],
            ["lat" => 62.129, "lon" => 22.449]];

        $res["routeStatusEnum true, waypoints true"] = ["7", $waypointsTrue, true];
        $res["routeStatusEnum true, waypoints false"] = ["7", $waypointsFalse, false];
        $res["routeStatusEnum false, waypoints false"] = ["5", $waypointsFalse, false];
        $res["routeStatusEnum false, waypoints true"] = ["5", $waypointsTrue, false];

        return $res;
    }

    public static function findSyncPointProvider(): array
    {
        $res = [];

        foreach (DataProviders::$dirs as $dir) {
            $res[$dir][0] = json_decode(DataProviders::resultFileContents($dir, "waypoints"), true);
            $res[$dir][1] = DataProviders::resultFileContents($dir, "find_sync_point");
        }

        return $res;
    }

    public static function matchSyncPointProvider(): array
    {
        $res = [];

        foreach (DataProviders::$dirs as $dir) {
            $res[$dir][0] = json_decode(DataProviders::resultFileContents($dir, "waypoints"), true);
            $res[$dir][1] = DataProviders::resultFileContents($dir, "match_sync_point");
        }

        return $res;
    }

    public static function incomingRtzProvider(): array
    {
        $res = [];

        foreach (DataProviders::$dirs as $dir) {
            $res[$dir][0] = DataProviders::rtzFileContents($dir);
            $res[$dir][1] = DataProviders::resultFileContents($dir, "incoming_rtz");
        }

        return $res;
    }

    public static function getPublicSideStatusCodeProvider(): array
    {
        $res = [];

        $dir = "Vis_Client_Data";
        $res["Status Code 200"][0] = DataProviders::resultFileContents($dir, "public_side_body_status_200");
        $res["Status Code 200"][1] = "200";

        return $res;
    }

    public static function getPublicSideBodyProvider(): array
    {
        $res = [];

        $dir = "Vis_Client_Data";
        $res["Status Code 200"][0] = DataProviders::resultFileContents($dir, "public_side_body_status_200");
        $res["Status Code 200"][1] = "Text message was successfully uploaded";

        return $res;
    }

    public static function throwPublicSideStatusCodeErrorProvider(): array
    {
        $res = [];

        $dir = "Vis_Client_Data";
        $res["Status Code 200"][0] = DataProviders::resultFileContents($dir, "public_side_body_status_200");

        return $res;
    }

    public static function visGetMessagesProvider(): array
    {
        $res = [];

        $dir = "Vis_Client_Data";
        $res["VIS get message successful"][0] =
        DataProviders::resultFileContents($dir, "vis_get_message_response");
        $res["VIS get message successful"][1] =
        DataProviders::resultFileContents($dir, "vis_client_get_messages_result");

        return $res;
    }

    public static function visGetNotificationsProvider(): array
    {
        $res = [];

        $dir = "Vis_Client_Data";
        $res["VIS get notification successful"][0] =
        DataProviders::resultFileContents($dir, "vis_get_notification_response");
        $res["VIS get notification successful"][1] =
        DataProviders::resultFileContents($dir, "vis_client_get_notifications_result");

        return $res;
    }

    public static function visUploadTextMessageProvider(): array
    {
        $res = [];

        $dir = "Vis_Client_Data";
        $res["VIS upload text message successful"][0] =
        DataProviders::resultFileContents($dir, "vis_upload_text_message_response");
        $res["VIS upload text message successful"][1] =
        DataProviders::resultFileContents($dir, "vis_client_upload_text_message_post_fields");

        return $res;
    }

    public static function visUploadVoyagePlanProvider(): array
    {
        $res = [];

        $dir = "Calculated_Schedule";
        $res["VIS upload voyage plan successful"][0] = DataProviders::rtzFileContents($dir);
        $dir = "Vis_Client_Data";
        $res["VIS upload voyage plan successful"][1] =
        DataProviders::resultFileContents($dir, "vis_upload_voyage_plan_response");
        $res["VIS upload voyage plan successful"][2] =
        DataProviders::resultFileContents($dir, "vis_client_upload_voyage_plan_post_fields");

        return $res;
    }

    public static function visFindServicesProvider(): array
    {
        $res = [];

        $dir = "Vis_Client_Data";
        $res["VIS find services successful"][0] =
        DataProviders::resultFileContents($dir, "vis_find_services_response");
        $res["VIS find services successful"][1] =
        DataProviders::resultFileContents($dir, "vis_client_find_services_post_fields");
        $res["VIS find services successful"][2] =
        DataProviders::resultFileContents($dir, "vis_client_find_services_result");

        return $res;
    }

    public static function visSubscribeVoyagePlanProvider(): array
    {
        $res = [];

        $dir = "Vis_Client_Data";
        $res["VIS subscribe voyage plan successful"][0] =
        DataProviders::resultFileContents($dir, "vis_subscribe_voyage_plan_response");
        $res["VIS subscribe voyage plan successful"][1] =
        DataProviders::resultFileContents($dir, "vis_client_subscribe_voyage_plan_post_fields");

        return $res;
    }

    public static function visPublishMessageProvider(): array
    {
        $res = [];

        $dir = "Calculated_Schedule";
        $res["VIS publish message successful"][0] = DataProviders::rtzFileContents($dir);
        $dir = "Vis_Client_Data";
        $res["VIS publish message successful"][1] =
        DataProviders::resultFileContents($dir, "vis_publish_message_response");

        return $res;
    }

    public static function visSubscriptionProvider(): array
    {
        $res = [];

        $dir = "Vis_Client_Data";
        $res["VIS subscription successful"][0] =
        DataProviders::resultFileContents($dir, "vis_subscription_response");
        $res["VIS subscription successful"][1] =
        DataProviders::resultFileContents($dir, "vis_client_subscription_post_fields");

        return $res;
    }

    public static function visGetSubscriptionProvider(): array
    {
        $res = [];

        $dir = "Vis_Client_Data";
        $res["VIS get subscription successful"][0] =
        DataProviders::resultFileContents($dir, "vis_get_subscription_response");

        return $res;
    }

    public static function visAuthorizeIdentitiesProvider(): array
    {
        $res = [];

        $dir = "Vis_Client_Data";
        $res["VIS authorize identities successful"][0] =
        DataProviders::resultFileContents($dir, "vis_authorize_identities_response");
        $res["VIS authorize identities successful"][1] =
        DataProviders::resultFileContents($dir, "vis_client_authorize_identities_post_fields");

        return $res;
    }

    public static function createDateTimeValidDateTimeStringInputProvider(): array
    {
        $res = [];

        $res["yyyyMMddZ"][0] = "19990322+0100";
        $res["yyyyMMddZ"][1] = "1999-03-22T00:00:00+01:00";

        $res["yyyyMMdd"][0] = "19990322";
        $res["yyyyMMdd"][1] = "1999-03-22T00:00:00+00:00";

        $res["yyyy-MM-ddXXX"][0] = "1999-03-22+01:00";
        $res["yyyy-MM-ddXXX"][1] = "1999-03-22T00:00:00+01:00";

        $res["yyyy-MM-dd'T'HH:mm:ss.SSS"][0] = "1999-03-22T05:06:07.000";
        $res["yyyy-MM-dd'T'HH:mm:ss.SSS"][1] = "1999-03-22T05:06:07+00:00";

        $res["yyyy-MM-dd'T'HH:mm:ss"][0] = "1999-03-22T05:06:07";
        $res["yyyy-MM-dd'T'HH:mm:ss"][1] = "1999-03-22T05:06:07+00:00";

        $res["yyyy-MM-dd'T'HH:mm:ss.SSS'Z'"][0] = "1999-03-22T05:06:07.000Z";
        $res["yyyy-MM-dd'T'HH:mm:ss.SSS'Z'"][1] = "1999-03-22T05:06:07+00:00";

        $res["yyyy-MM-dd'T'HH:mm:ss.SSSXXX"][0] = "1999-03-22T05:06:07.000+01:00";
        $res["yyyy-MM-dd'T'HH:mm:ss.SSSXXX"][1] = "1999-03-22T05:06:07+01:00";

        $res["yyyy-MM-dd'T'HH:mm:ssXXX"][0] = "1999-03-22T05:06:07+01:00";
        $res["yyyy-MM-dd'T'HH:mm:ssXXX"][1] = "1999-03-22T05:06:07+01:00";

        return $res;
    }

    public static function createDateTimeInvalidDateTimeStringInputProvider(): array
    {
        $res = [];

        $res["yyyyMMMddZ"][0] = "199900322+0100";
        $res["yyyyMMddd"][0] = "199903222";
        $res["yyyy-MM-ddXXXX"][0] = "1999-03-22+001:00";
        $res["yyyy-MM-dd'T'HH:mm:sss.SSS"][0] = "1999-03-22T05:06:007.000";
        $res["yyyy-MM-dd'T'HH:mmm:ss"][0] = "1999-03-22T05:006:07";
        $res["yyyy-MM-dd'T'HHmmss.SSS'Z'"][0] = "1999-03-22T050607.000Z";
        $res["yyyyMMdd'T'HH:mm:ss.SSSXXX"][0] = "19990322T05:06:07.000+01:00";
        $res["yyyy-MM-dd'T'HHH:mm:ssXXX"][0] = "1999-03-22T005:06:07+01:00";

        return $res;
    }

    public static function dateTimeDifferenceInXsdDurationValidDateTimeStringsInputProvider(): array
    {
        $res = [];

        # P%yY%mM%dDT%hH%iM%sS"
        $res["yyyyMMddZ"][0] = "19990322+0100";
        $res["yyyyMMddZ"][1] = "19990322+0100";
        $res["yyyyMMddZ"][2] = "P0Y0M0DT0H0M0S";

        $res["yyyyMMdd"][0] = "19990322";
        $res["yyyyMMdd"][1] = "20000422";
        $res["yyyyMMdd"][2] = "P1Y1M0DT0H0M0S";

        $res["yyyy-MM-ddXXX"][0] = "1999-03-22+02:00";
        $res["yyyy-MM-ddXXX"][1] = "1999-03-22+01:00";
        $res["yyyy-MM-ddXXX"][2] = "P0Y0M0DT1H0M0S";

        $res["yyyy-MM-dd'T'HH:mm:ss.SSS"][0] = "2000-03-22T08:07:06.000";
        $res["yyyy-MM-dd'T'HH:mm:ss.SSS"][1] = "1999-03-22T05:06:07.000";
        $res["yyyy-MM-dd'T'HH:mm:ss.SSS"][2] = "P1Y0M0DT3H0M59S";

        $res["yyyy-MM-dd'T'HH:mm:ss"][0] = "1999-03-22T05:06:07";
        $res["yyyy-MM-dd'T'HH:mm:ss"][1] = "2000-04-23T04:07:08";
        $res["yyyy-MM-dd'T'HH:mm:ss"][2] = "P1Y1M0DT23H1M1S";

        $res["yyyy-MM-dd'T'HH:mm:ss.SSS'Z'"][0] = "1999-03-23T05:06:07.000Z";
        $res["yyyy-MM-dd'T'HH:mm:ss.SSS'Z'"][1] = "1999-03-22T05:06:07.000Z";
        $res["yyyy-MM-dd'T'HH:mm:ss.SSS'Z'"][2] = "P0Y0M1DT0H0M0S";

        $res["yyyy-MM-dd'T'HH:mm:ss.SSSXXX"][0] = "1999-03-23T05:06:07.000+03:00";
        $res["yyyy-MM-dd'T'HH:mm:ss.SSSXXX"][1] = "1999-03-22T05:06:07.000+01:00";
        $res["yyyy-MM-dd'T'HH:mm:ss.SSSXXX"][2] = "P0Y0M0DT22H0M0S";

        $res["yyyy-MM-dd'T'HH:mm:ssXXX"][0] = "1999-03-22T07:08:09+02:00";
        $res["yyyy-MM-dd'T'HH:mm:ssXXX"][1] = "1999-03-22T05:06:07+01:00";
        $res["yyyy-MM-dd'T'HH:mm:ssXXX"][2] = "P0Y0M0DT1H2M2S";

        return $res;
    }
}
