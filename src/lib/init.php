<?php
namespace SMA\PAA\AGENT;

if (file_exists(__DIR__ . "/init_local.php")) {
    require(__DIR__ . "/init_local.php");
}

require_once __DIR__ . "/../../vendor/autoload.php";

spl_autoload_register(
    function ($className) {
        $pathToFind = str_replace("\\", "/", $className);
        $dirs = ["/"];
        foreach ($dirs as $dir) {
            $file  = __DIR__ . $dir . $pathToFind . '.php';
            if (file_exists($file)) {
                include $file;
                return true;
            }
        }

        return false;
    }
);
