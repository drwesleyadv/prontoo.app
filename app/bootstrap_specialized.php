<?php
declare(strict_types=1);
require_once __DIR__ . "/Ui/SpeedChartGeometry.php";
\Prontoo\Runtime\Modules\RuntimeModuleComposition::loader()->loadRuntimeCore(
    \Prontoo\Runtime\Modules\RuntimeBootPolicy::useLightBoot(getenv('PRONTOO_DISABLE_LIGHT_BOOT')),
);
