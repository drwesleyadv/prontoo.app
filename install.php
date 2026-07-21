<?php
declare(strict_types=1);
require_once __DIR__ . "/app/Core/Install/InstallAccess.php";
\Prontoo\Core\Install\InstallAccess::assertInstallerEntry();
require __DIR__ . "/app/prontoo.php";
prontoo_require_module("Install/Installer.php");
prontoo_install();
