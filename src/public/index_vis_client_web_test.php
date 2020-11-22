<?php
require_once __DIR__ . "/../lib/init.php";
require_once __DIR__ . "/vis_client_web_test.php";

use SMA\PAA\TESTING\VisCaller;

$fromId = isset($_POST["fromId"]) ? $_POST["fromId"]: "";
$toId = isset($_POST["toId"]) ? $_POST["toId"]: "";
$author = isset($_POST["author"]) ? $_POST["author"]: "";
$subject = isset($_POST["subject"]) ? $_POST["subject"]: "";
$body = isset($_POST["body"]) ? $_POST["body"]: "";
$imo = isset($_POST["imo"]) ? $_POST["imo"]: "";
$voyageId = isset($_POST["voyageId"]) ? $_POST["voyageId"]: "";
$visAppId = isset($_POST["visAppId"]) ? $_POST["visAppId"]: "";
$visApiKey = isset($_POST["visApiKey"]) ? $_POST["visApiKey"]: "";

$action = isset($_POST["action"]) ? $_POST["action"]: "";

$rtz = "";

if ($_FILES["rtz"]["error"] == UPLOAD_ERR_OK
    && is_uploaded_file($_FILES["rtz"]["tmp_name"])) {
    $rtz = file_get_contents($_FILES["rtz"]["tmp_name"]);
}

$actions = [
    "uploadTextMessage"
    ,"uploadVoyagePlan"
    ,"findServices"
    ,"getMessages"
    ,"getNotifications"
    ,"subscribeVoyagePlan"
    ,"publishVoyagePlanWithAutoSubscription"
    ,"publishVoyagePlan"
    ,"getSubscription"
    ,"authorizeIdentities"
];
?>
<b>VIS testing tool</b><br>
<pre>
UNIKIE01 - Port_of_Rauma
UNIKIE02 - Port_of_Gavle
UNIKIE03 - Unikie_testship, MMSI:245010101, IMO: 7010101

POST DATA<br>
Action: <?=$action?><br>
From ID: <?=$fromId?><br>
To ID: <?=$toId?><br>
</pre>
<br />
TEST FORM<br>
<form action="" method="POST" enctype="multipart/form-data">
    From:
    <input type="text" name="fromId" value="<?=$fromId ? $fromId : "UNIKIE01"?>" />
    Originating URL<br />
    To:
    <input type="text" name="toId" value="<?=$toId ? $toId : "UNIKIE01"?>" />
    Receiving URL (for uploads)<br />
    Author:
    <input type="text" name="author" value="<?=$author ? $author : "Matti Meikäläinen"?>" />
    Author of message (only for messages)<br />
    Subject:
    <input type="text" name="subject" value="<?=$subject ? $subject : "Test subject"?>" />
    Subject of message (only for messages)<br />
    Body:
    <input type="text" name="body" value="<?=$body ? $body : "Test body"?>" />
    Body of message (only for messages)<br />
    Filter IMO:
    <input type="text" name="imo" value="<?=$imo ? $imo : "7010101"?>" />
    Vessel IMO (only for service find, empty for no filtering)<br />
    Voyage ID:
    <input type="text" name="voyageId" value="<?=$voyageId ? $voyageId : "1"?>" /><br />
    VIS App ID
    <input type="text" name="visAppId" value="<?=$visAppId ? $visAppId : ""?>" /><br />
    VIS API KEY
    <input type="text" name="visApiKey" value="<?=$visApiKey ? $visApiKey : ""?>" /><br />
    RTZ:
    <input type="file" name="rtz" /><br />
<?php
foreach ($actions as $a) {
    echo '<input type="radio" name="action" '
    . ($action === $a ? "checked" : "") . ' value="' . $a . '" /> '
    . $a . ' <br />';
}
?>
    <input type="submit" />
</form>


<?php

if ($action) {
    $client = new VisCaller($fromId, $toId, $author, $subject, $body, $imo, $voyageId, $visAppId, $visApiKey, $rtz);

    try {
        $client->$action();
    } catch (\Exception $e) {
        print("\n<br><pre>\n");
        print("ERROR:" . $e->getMessage());
        print("</pre>\n");
    }
}
