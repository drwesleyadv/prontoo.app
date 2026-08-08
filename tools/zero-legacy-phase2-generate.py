from pathlib import Path
import json

ROOT = Path(__file__).resolve().parents[1]

def read(path: str) -> str:
    return (ROOT / path).read_text()

def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content)

def replace_once(path: str, old: str, new: str) -> None:
    source = read(path)
    if old not in source:
        raise SystemExit(f'expected block not found: {path}')
    write(path, source.replace(old, new, 1))

replace_once('app/Runtime/Runner.php', "                \\enforce_action_integrity($context, $route);", "                LayeredKernel::enforceAction($route, $method, $_POST, $context);")
replace_once('app/Support/SecurityAccess.php', "function enforce_action_integrity(array $c, string $route): void\n{\n    \\Prontoo\\Presentation\\SecurityAccess\\SecurityAccessPresentationOperations01::enforce_action_integrity($c, $route);\n}\n", '')
replace_once('app/Core/Install/RuntimeContract.php', "            'enforce_action_integrity',\n", '')
replace_once('tools/architecture-check.php', "    'enforce_action_integrity($context, $route)',\n", "    'LayeredKernel::enforceAction($route, $method, $_POST, $context)',\n")

version_path = ROOT / 'version.json'
version = json.loads(version_path.read_text())
if int(version.get('architecture_native_files_min', 0)) != 278:
    raise SystemExit('unexpected canonical architecture_native_files_min before phase 2')
version['architecture_native_files_min'] = 277
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=4, separators=(',', ': ')) + '\n')

path = 'app/Presentation/SecurityAccess/SecurityAccessPresentationOperations01.php'
source = read(path)
needle = '    public static function enforce_action_integrity('
start = source.find(needle)
if start < 0: raise SystemExit(f'expected method not found: {path}')
brace = source.find('{', start)
if brace < 0: raise SystemExit(f'method brace not found: {path}')
depth = 0
end = None
for index in range(brace, len(source)):
    if source[index] == '{': depth += 1
    elif source[index] == '}':
        depth -= 1
        if depth == 0:
            end = index + 1
            break
if end is None: raise SystemExit(f'method end not found: {path}')
while end < len(source) and source[end] in '\r\n': end += 1
write(path, source[:start] + source[end:])

replace_once('app/bootstrap_architecture.php', "    __DIR__ . '/Core/Invariant/Request/ActionProof.php',\n", '')
layer_path = 'app/Core/Architecture/LayerMap.php'
layer = read(layer_path)
for candidate in ["            $path === 'Core/Invariant/Request/ActionProof.php',\n", "            $relative === 'app/Core/Invariant/Request/ActionProof.php',\n"]:
    if candidate in layer:
        layer = layer.replace(candidate, '', 1)
        break
else:
    if 'ActionProof.php' in layer: raise SystemExit('ActionProof LayerMap exception shape changed')
write(layer_path, layer)

arch_path = 'app/Core/Architecture/ArchitectureVerifier.php'
arch = read(arch_path)
bridge_start = arch.find("        $bridge = $root . '/app/Core/Invariant/Request/ActionProof.php';")
if bridge_start >= 0:
    kernel_marker = "        $kernel = $root . '/app/Core/Invariant/InvariantKernel.php';"
    bridge_end = arch.find(kernel_marker, bridge_start)
    if bridge_end < 0: raise SystemExit('architecture bridge end not found')
    arch = arch[:bridge_start] + arch[bridge_end:]
warning_old = """        $security = $root . '/app/Support/SecurityAccess.php';
        if (is_file($security) && str_contains((string) @file_get_contents($security), 'function enforce_action_integrity')) {
            $warnings[] = 'thin_compatibility_adapter_active:enforce_action_integrity';
        }
"""
warning_new = """        $security = $root . '/app/Support/SecurityAccess.php';
        if (is_file($security) && str_contains((string) @file_get_contents($security), 'function enforce_action_integrity')) {
            $errors[] = 'legacy_authorization_adapter_present:enforce_action_integrity';
        }
"""
if warning_old in arch: arch = arch.replace(warning_old, warning_new, 1)
elif 'thin_compatibility_adapter_active:enforce_action_integrity' in arch: raise SystemExit('architecture warning shape changed')
write(arch_path, arch)

manifest_path = ROOT / 'app/architecture.manifest.json'
manifest = json.loads(manifest_path.read_text())
manifest['compatibility_boundaries'] = [item for item in manifest.get('compatibility_boundaries', []) if item.get('symbol') not in ('enforce_action_integrity', 'Prontoo\\Core\\Integrity\\ActionProof')]
removed = list(manifest.get('removed_legacy_files', []))
bridge_path = 'app/Core/Invariant/Request/ActionProof.php'
if bridge_path not in removed: removed.append(bridge_path)
manifest['removed_legacy_files'] = removed
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=4, separators=(',', ': ')) + '\n')

for path in ['README.md', 'docs/architecture/overview.md', 'docs/architecture/responsibility-map.md']:
    file = ROOT / path
    if not file.exists(): continue
    file.write_text(file.read_text().replace('`enforce_action_integrity`', '`LayeredKernel::enforceAction`').replace('Prontoo\\Core\\Integrity\\ActionProof', 'Prontoo\\Runtime\\LayeredKernel'))

bridge = ROOT / 'app/Core/Invariant/Request/ActionProof.php'
if not bridge.exists(): raise SystemExit('ActionProof bridge already absent before phase 2')
bridge.unlink()

for root in ['app', 'br', 'public', 'cron', 'tools', 'docs']:
    base = ROOT / root
    if not base.exists(): continue
    for file in base.rglob('*'):
        if not file.is_file() or '.git' in file.parts: continue
        relative = file.relative_to(ROOT).as_posix()
        if relative in ('app/architecture.manifest.json', 'app/update.manifest.json', 'tools/zero-legacy-phase2-generate.py') or relative.startswith('docs/audits/'):
            continue
        try: value = file.read_text()
        except UnicodeDecodeError: continue
        if 'enforce_action_integrity' in value or 'Core/Invariant/Request/ActionProof' in value or 'Prontoo\\Core\\Integrity\\ActionProof' in value:
            if file.name == 'ArchitectureVerifier.php' and 'legacy_authorization_adapter_present:enforce_action_integrity' in value: continue
            raise SystemExit(f'legacy authorization reference remains: {relative}')
