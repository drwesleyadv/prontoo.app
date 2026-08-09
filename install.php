<?php
declare(strict_types=1);
$prontooRouteStartedMonotonicNs = hrtime(true);
$prontooRouteStartedUnixUs = (int) floor(microtime(true) * 1000000);
require_once __DIR__ . "/app/Runtime/Autoload/ProntooAutoloader.php";
\Prontoo\Runtime\SupportTelemetry\SupportTelemetryRuntimeOperations01::telemetry_route_start_marker(
    "install",
    $prontooRouteStartedMonotonicNs,
    $prontooRouteStartedUnixUs,
);
unset($prontooRouteStartedMonotonicNs, $prontooRouteStartedUnixUs);
require_once __DIR__ . "/app/Core/Install/InstallAccess.php";
\Prontoo\Core\Install\InstallAccess::assertInstallerEntry();
require __DIR__ . "/app/prontoo.php";
\Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::prontoo_install();
\Prontoo\Runtime\SupportTelemetry\SupportTelemetryRuntimeOperations01::telemetry_route_finish_marker();
