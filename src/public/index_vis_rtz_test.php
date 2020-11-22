<?php
require_once __DIR__ . "/../lib/init.php";
require_once __DIR__ . "/vis_rtz_test.php";

use SMA\PAA\TESTING\VisRtzTest;

$rta = isset($_POST["rta"]) ? $_POST["rta"]: "";
$windowBefore = isset($_POST["windowBefore"]) ? $_POST["windowBefore"]: "";
$windowAfter = isset($_POST["windowAfter"]) ? $_POST["windowAfter"]: "";

$rtz = "";
if ($_FILES["rtz"]["error"] == UPLOAD_ERR_OK
    && is_uploaded_file($_FILES["rtz"]["tmp_name"])) {
    $rtz = file_get_contents($_FILES["rtz"]["tmp_name"]);
}

if ($rtz !== "") {
    $visRtzTest = new VisRtzTest($rtz, $rta, $windowBefore, $windowAfter);
    try {
        $visRtzTest->execute();
    } catch (\Exception $e) {
        print("\n<br><pre>\n");
        print("ERROR:" . $e->getMessage());
        print("</pre>\n");
    }
}
?>

<b>VIS RTZ test</b><br>

<form action="" method="POST" enctype="multipart/form-data">
    RTA:
    <input type="text" name="rta" value="<?=$rta ? $rta : "2019-12-24T21:20:19+00:00"?>" />
    <br>Window before:
    <input type="text" name="windowBefore" value="<?=$windowBefore ? $windowBefore : "PT30M"?>" />
    <br>Window after:
    <input type="text" name="windowAfter" value="<?=$windowAfter ? $windowAfter : "PT20M"?>" />
    <br>RTZ:
    <input type="file" name="rtz" />
    <br><input type="submit" />
</form>