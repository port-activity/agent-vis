<?php
namespace SMA\PAA\AGENT\VIS;

use PHPUnit\Framework\TestCase;

use TESTS\DATA\DataProviders;

final class VisClientTest extends TestCase
{
    public function testBasicGetters(): void
    {
        $fakeCurl = DataProviders::getFakeCurl();
        $visClient = DataProviders::getVisClient($fakeCurl);

        $this->assertEquals("urn:mrn:gov:service:instance:own:vis:testport", $visClient->getServiceInstanceUrn());
        $this->assertEquals(["lat" => 61.1297155, "lon" => 21.4491551], $visClient->getPortCoordinates());
        $this->assertEquals(3704, $visClient->getPortRadius());
        $this->assertEquals("Test sync point", $visClient->getSyncPointName());
        $this->assertEquals(["lat" => 61.11806, "lon" => 21.16778], $visClient->getSyncPointCoordinates());
        $this->assertEquals(1852, $visClient->getSyncPointRadius());
        $this->assertEquals("Test port operator", $visClient->getRtzRouteAuthor());
        $this->assertEquals("Test port schedule", $visClient->getRtzScheduleName());
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::getPublicSideStatusCodeProvider
     */
    public function testGetPublicSideStatusCode($body, $expectedResult): void
    {
        $fakeCurl = DataProviders::getFakeCurl();
        $visClient = DataProviders::getVisClient($fakeCurl);
        $result = $visClient->getPublicSideStatusCode($body);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::getPublicSideBodyProvider
     */
    public function testGetPublicSideBody($body, $expectedResult): void
    {
        $fakeCurl = DataProviders::getFakeCurl();
        $visClient = DataProviders::getVisClient($fakeCurl);
        $result = $visClient->getPublicSideBody($body);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::throwPublicSideStatusCodeErrorProvider
     *@expectedException Exception
     *@expectedExceptionMessage Unexcpected response from public side
     */
    public function testThrowPublicSideStatusCodeError($body): void
    {
        $fakeCurl = DataProviders::getFakeCurl();
        $visClient = DataProviders::getVisClient($fakeCurl);
        $result = $visClient->throwPublicSideStatusCodeError($body);
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::visGetMessagesProvider
     */
    public function testVisGetMessages($response, $expectedResult): void
    {
        $fakeCurl = DataProviders::getFakeCurl();

        $fakeCurl->executeReturn = $response;
        $fakeCurl->getInfoReturn["http_code"] = 200;

        $visClient = DataProviders::getVisClient($fakeCurl);
        $result = $visClient->visGetMessages();

        $this->assertEquals(DataProviders::getVisCurlUrl() . "getMessage", $fakeCurl->url);
        // Need to truncate changing secret part for comparison
        $fakeCurl->optArray[CURLOPT_HTTPHEADER][2] = substr($fakeCurl->optArray[CURLOPT_HTTPHEADER][2], 0, 29);
        $this->assertEquals(DataProviders::getVisCurlGetOpt(), $fakeCurl->optArray);
        $this->assertEquals($expectedResult, json_encode($result));
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::visGetNotificationsProvider
     */
    public function testVisGetNotifications($response, $expectedResult): void
    {
        $fakeCurl = DataProviders::getFakeCurl();

        $fakeCurl->executeReturn = $response;
        $fakeCurl->getInfoReturn["http_code"] = 200;

        $visClient = DataProviders::getVisClient($fakeCurl);
        $result = $visClient->visGetNotifications();

        $this->assertEquals(DataProviders::getVisCurlUrl() . "getNotification", $fakeCurl->url);
        // Need to truncate changing secret part for comparison
        $fakeCurl->optArray[CURLOPT_HTTPHEADER][2] = substr($fakeCurl->optArray[CURLOPT_HTTPHEADER][2], 0, 29);
        $this->assertEquals(DataProviders::getVisCurlGetOpt(), $fakeCurl->optArray);
        $this->assertEquals($expectedResult, json_encode($result));
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::visUploadTextMessageProvider
     */
    public function testVisUploadTextMessage($response, $expectedPostFields): void
    {
        $fakeCurl = DataProviders::getFakeCurl();

        $fakeCurl->executeReturn = $response;
        $fakeCurl->getInfoReturn["http_code"] = 200;

        $visClient = DataProviders::getVisClient($fakeCurl);
        $result = $visClient->visUploadTextMessage("TESTRECEIVERURL", "Test Author", "Test subject", "Test Body");

        $expectedCurlOpt = DataProviders::getVisCurlPostOptCommon();
        $expectedCurlOpt[CURLOPT_POSTFIELDS] = $expectedPostFields;

        $this->assertEquals(DataProviders::getVisCurlUrl() . "callService", $fakeCurl->url);
        // Need to truncate changing secret part for comparison
        $fakeCurl->optArray[CURLOPT_HTTPHEADER][2] = substr($fakeCurl->optArray[CURLOPT_HTTPHEADER][2], 0, 29);
        // Need to truncate changing message parts for comparison
        $fakeCurl->optArray[CURLOPT_POSTFIELDS] =
        preg_replace(
            "/(<textMessageId>urn:mrn:stm:txt:own:).*?(<\\\\\/textMessageId>)/",
            "$1$2",
            $fakeCurl->optArray[CURLOPT_POSTFIELDS]
        );
        $fakeCurl->optArray[CURLOPT_POSTFIELDS] =
        preg_replace("/(<createdAt>).*?(<\\\\\/createdAt>)/", "$1$2", $fakeCurl->optArray[CURLOPT_POSTFIELDS]);

        $this->assertEquals($expectedCurlOpt, $fakeCurl->optArray);
        // We should get the body back which is same as execute return
        $this->assertEquals($response, $result["response_body"]);
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::visUploadVoyagePlanProvider
     */
    public function testVisUploadVoyagePlan($xml, $response, $expectedPostFields): void
    {
        $fakeCurl = DataProviders::getFakeCurl();

        $fakeCurl->executeReturn = $response;
        $fakeCurl->getInfoReturn["http_code"] = 200;

        $visClient = DataProviders::getVisClient($fakeCurl);
        $result = $visClient->visUploadVoyagePlan("TESTRECEIVERURL", $xml);

        $expectedCurlOpt = DataProviders::getVisCurlPostOptCommon();
        $expectedCurlOpt[CURLOPT_POSTFIELDS] = $expectedPostFields;

        $this->assertEquals(DataProviders::getVisCurlUrl() . "callService", $fakeCurl->url);
        // Need to truncate changing secret part for comparison
        $fakeCurl->optArray[CURLOPT_HTTPHEADER][2] = substr($fakeCurl->optArray[CURLOPT_HTTPHEADER][2], 0, 29);

        $this->assertEquals($expectedCurlOpt, $fakeCurl->optArray);
        // We should get the body back which is same as execute return
        $this->assertEquals($response, $result);
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::visFindServicesProvider
     */
    public function testVisFindServices($response, $expectedPostFields, $expectedResult): void
    {
        $fakeCurl = DataProviders::getFakeCurl();

        $fakeCurl->executeReturn = $response;
        $fakeCurl->getInfoReturn["http_code"] = 200;

        $visClient = DataProviders::getVisClient($fakeCurl);
        $result = $visClient->visFindServices(["imo" => "7010101"]);

        $expectedCurlOpt = DataProviders::getVisCurlPostOptCommon();
        $expectedCurlOpt[CURLOPT_POSTFIELDS] = $expectedPostFields;

        $this->assertEquals(DataProviders::getVisCurlUrl() . "findServices", $fakeCurl->url);
        // Need to truncate changing secret part for comparison
        $fakeCurl->optArray[CURLOPT_HTTPHEADER][2] = substr($fakeCurl->optArray[CURLOPT_HTTPHEADER][2], 0, 29);

        $this->assertEquals($expectedCurlOpt, $fakeCurl->optArray);
        $this->assertEquals($expectedResult, json_encode($result));
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::visSubscribeVoyagePlanProvider
     */
    public function testVisSubscribeVoyagePlan($response, $expectedPostFields): void
    {
        $fakeCurl = DataProviders::getFakeCurl();

        $fakeCurl->executeReturn = $response;
        $fakeCurl->getInfoReturn["http_code"] = 200;

        $visClient = DataProviders::getVisClient($fakeCurl);
        $result = $visClient->visSubscribeVoyagePlan("https://test.org/TEST01", "urn:mrn:gov:voyage:id:own:1");

        $expectedCurlOpt = DataProviders::getVisCurlPostOptCommon();
        $expectedCurlOpt[CURLOPT_POSTFIELDS] = $expectedPostFields;

        $this->assertEquals(DataProviders::getVisCurlUrl() . "callService", $fakeCurl->url);
        // Need to truncate changing secret part for comparison
        $fakeCurl->optArray[CURLOPT_HTTPHEADER][2] = substr($fakeCurl->optArray[CURLOPT_HTTPHEADER][2], 0, 29);

        $this->assertEquals($expectedCurlOpt, $fakeCurl->optArray);
        // We should get the body back which is same as execute return
        $this->assertEquals($response, $result);
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::visPublishMessageProvider
     */
    public function testVisPublishMessage($xml, $response): void
    {
        $fakeCurl = DataProviders::getFakeCurl();

        $fakeCurl->executeReturn = $response;
        $fakeCurl->getInfoReturn["http_code"] = 200;

        $visClient = DataProviders::getVisClient($fakeCurl);
        $result = $visClient->visPublishMessage("urn:mrn:gov:voyage:id:own:1", "rtz", $xml);

        $expectedCurlOpt = DataProviders::getVisCurlPostOptCommon();
        $expectedCurlOpt[CURLOPT_POSTFIELDS] = json_encode($xml);

        $this->assertEquals(
            DataProviders::getVisCurlUrl() .
            "publishMessage?dataId=urn%3Amrn%3Agov%3Avoyage%3Aid%3Aown%3A1&messageType=rtz",
            $fakeCurl->url
        );
        // Need to truncate changing secret part for comparison
        $fakeCurl->optArray[CURLOPT_HTTPHEADER][2] = substr($fakeCurl->optArray[CURLOPT_HTTPHEADER][2], 0, 29);

        $this->assertEquals($expectedCurlOpt, $fakeCurl->optArray);
        $this->assertEquals("urn:mrn:gov:voyage:id:own:1", $result);
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::visSubscriptionProvider
     */
    public function testVisSubscription($response, $expectedPostFields): void
    {
        $fakeCurl = DataProviders::getFakeCurl();

        $fakeCurl->executeReturn = $response;
        $fakeCurl->getInfoReturn["http_code"] = 200;

        $visClient = DataProviders::getVisClient($fakeCurl);
        $subscribers = [];
        $subscribers[0]["IdentityId"] = "urn:mrn:gov:org:own";
        $subscribers[0]["IdentityName"] = "Testing Administration";
        $subscribers[0]["EndpointURL"] = "https://test.org/TEST01";
        $subscribers[1]["IdentityId"] = "urn:mrn:gov:org:own";
        $subscribers[1]["IdentityName"] = "Testing Administration";
        $subscribers[1]["EndpointURL"] = "https://test.org/TEST02";
        $result = $visClient->visSubscription("urn:mrn:gov:voyage:id:own:1", $subscribers);

        $expectedCurlOpt = DataProviders::getVisCurlPostOptCommon();
        $expectedCurlOpt[CURLOPT_POSTFIELDS] = $expectedPostFields;

        $this->assertEquals(
            DataProviders::getVisCurlUrl() .
            "subscription?dataId=urn%3Amrn%3Agov%3Avoyage%3Aid%3Aown%3A1",
            $fakeCurl->url
        );
        // Need to truncate changing secret part for comparison
        $fakeCurl->optArray[CURLOPT_HTTPHEADER][2] = substr($fakeCurl->optArray[CURLOPT_HTTPHEADER][2], 0, 29);

        $this->assertEquals($expectedCurlOpt, $fakeCurl->optArray);
        $this->assertEquals("urn:mrn:gov:voyage:id:own:1", $result);
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::visGetSubscriptionProvider
     */
    public function testVisGetSubscription($response): void
    {
        $fakeCurl = DataProviders::getFakeCurl();

        $fakeCurl->executeReturn = $response;
        $fakeCurl->getInfoReturn["http_code"] = 200;

        $visClient = DataProviders::getVisClient($fakeCurl);
        $result = $visClient->visGetSubscription("urn:mrn:gov:voyage:id:own:1");

        $this->assertEquals(
            DataProviders::getVisCurlUrl() .
            "subscription?dataId=urn%3Amrn%3Agov%3Avoyage%3Aid%3Aown%3A1",
            $fakeCurl->url
        );
        // Need to truncate changing secret part for comparison
        $fakeCurl->optArray[CURLOPT_HTTPHEADER][2] = substr($fakeCurl->optArray[CURLOPT_HTTPHEADER][2], 0, 29);
        $this->assertEquals(DataProviders::getVisCurlGetOpt(), $fakeCurl->optArray);
        // We should get the body back which is same as execute return
        $this->assertEquals($response, json_encode($result, JSON_UNESCAPED_SLASHES));
    }

    /**
     * @dataProvider \TESTS\DATA\DataProviders::visAuthorizeIdentitiesProvider
     */
    public function testVisAuthorizeIdentities($response, $expectedPostFields): void
    {
        $fakeCurl = DataProviders::getFakeCurl();

        $fakeCurl->executeReturn = $response;
        $fakeCurl->getInfoReturn["http_code"] = 200;

        $visClient = DataProviders::getVisClient($fakeCurl);
        $identities = [];
        $identities[0]["identityId"] = "urn:mrn:gov:org:own";
        $identities[0]["identityName"] = "Test Administration";
        $result = $visClient->visAuthorizeIdentities("urn:mrn:gov:voyage:id:own:1", $identities);

        $expectedCurlOpt = DataProviders::getVisCurlPostOptCommon();
        $expectedCurlOpt[CURLOPT_POSTFIELDS] = $expectedPostFields;

        $this->assertEquals(
            DataProviders::getVisCurlUrl() .
            "authorizeIdentities?dataId=urn%3Amrn%3Agov%3Avoyage%3Aid%3Aown%3A1",
            $fakeCurl->url
        );
        // Need to truncate changing secret part for comparison
        $fakeCurl->optArray[CURLOPT_HTTPHEADER][2] = substr($fakeCurl->optArray[CURLOPT_HTTPHEADER][2], 0, 29);

        $this->assertEquals($expectedCurlOpt, $fakeCurl->optArray);
        // We should get the body back which is same as execute return
        $this->assertEquals("urn:mrn:gov:voyage:id:own:1", $result);
    }
}
