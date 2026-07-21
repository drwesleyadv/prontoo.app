<?php
declare(strict_types=1);
if (!function_exists("public_web_escape")) {
    function public_web_escape($v): string
    {
        return htmlspecialchars(
            (string) $v,
            ENT_QUOTES | ENT_SUBSTITUTE,
            "UTF-8",
        );
    }
}
function page_mobile_web_access(): void
{
    redirect("login", ["source" => "mobile_web"]);
}
