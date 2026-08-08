from pathlib import Path

root = Path(__file__).resolve().parents[1]

legacy_require = 'require_once __DIR__ . "/app/Support/Telemetry.php";'
native_require = 'require_once __DIR__ . "/app/Runtime/Autoload/ProntooAutoloader.php";'
legacy_installer_load = '\\Prontoo\\Runtime\\Modules\\RuntimeModuleComposition::loader()->requireModule("Install/Installer.php");\n'
replacements = {
    'telemetry_route_start_marker(': r'\Prontoo\Runtime\SupportTelemetry\SupportTelemetryRuntimeOperations01::telemetry_route_start_marker(',
    'telemetry_route_finish_marker(': r'\Prontoo\Runtime\SupportTelemetry\SupportTelemetryRuntimeOperations01::telemetry_route_finish_marker(',
    'prontoo_install(': r'\Prontoo\Runtime\InstallInstaller\InstallInstallerRuntimeOperations02::prontoo_install(',
}

for rel in ('index.php', 'install.php'):
    path = root / rel
    source = path.read_text()
    if legacy_require not in source:
        raise SystemExit(f'{rel}: telemetry facade require shape changed')
    source = source.replace(legacy_require, native_require, 1)
    source = source.replace(legacy_installer_load, '')
    for legacy, native in replacements.items():
        source = source.replace(legacy, native)
    residual = [token for token in (legacy_require, 'Install/Installer.php', *replacements.keys()) if token in source]
    if residual:
        raise SystemExit(f'{rel}: residual legacy tokens: {residual}')
    path.write_text(source)

print({'root_entrypoints_migrated': 2})
