<?php
declare(strict_types=1);
$prontooRouteStartedMonotonicNs = hrtime(true);
$prontooRouteStartedUnixUs = (int) floor(microtime(true) * 1000000);
require_once __DIR__ . "/app/Support/Telemetry.php";
telemetry_route_start_marker(
    "install",
    $prontooRouteStartedMonotonicNs,
    $prontooRouteStartedUnixUs,
);
unset($prontooRouteStartedMonotonicNs, $prontooRouteStartedUnixUs);
require_once __DIR__ . "/app/Core/Install/InstallAccess.php";
\Prontoo\Core\Install\InstallAccess::assertInstallerEntry();
require __DIR__ . "/app/prontoo.php";
prontoo_require_module("Install/Installer.php");
prontoo_install();
