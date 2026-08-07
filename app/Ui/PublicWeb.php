<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/Runtime/Autoload/ProntooAutoloader.php';
if (!function_exists("public_web_escape")) {
    function public_web_escape($v): string
    {
        return \Prontoo\Presentation\Legacy\PublicWeb\PublicWebEscapeOperation::escape($v);
    }
}
function page_mobile_web_access(): void
{
    \Prontoo\Runtime\Legacy\PublicWeb\PublicWebRuntimeOperations01::page_mobile_web_access();
}
