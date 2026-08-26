from pathlib import Path
import subprocess

root = Path.cwd()
path = root / "app/Core/Architecture/ArchitectureVerifier.php"
text = path.read_text()
old = """            if (LayerMap::isNativePath($relative)) {
                $nativeFiles[] = $relative;
            } else {
                $transitionalFiles[] = $relative;
            }
            if (LayerMap::isNativePath($relative)) {
                self::inspectDependencies($root, $relative, $layer, $errors);
                self::inspectLayerNativeFile($root, $relative, $layer, $errors);
            }
"""
new = """            $isNative = LayerMap::isNativePath($relative);
            if ($isNative) {
                $nativeFiles[] = $relative;
            } elseif (str_starts_with($relative, 'app/') && $compositionRole === null) {
                $transitionalFiles[] = $relative;
            }
            if ($isNative) {
                self::inspectDependencies($root, $relative, $layer, $errors);
                self::inspectLayerNativeFile($root, $relative, $layer, $errors);
            }
"""
if text.count(old) != 1:
    raise SystemExit("bloco esperado do ArchitectureVerifier não localizado exatamente uma vez")
path.write_text(text.replace(old, new, 1))

for relative in [
    ".github/workflows/pr371-architecture-diagnostic.yml",
    ".github/workflows/pr371-architecture-repair.yml",
    ".github/workflows/pr371-architecture-repair-simple.yml",
    "tools/pr371_architecture_repair.py",
]:
    target = root / relative
    if target.exists():
        target.unlink()

commands = [
    ["php", "-l", "app/Core/Architecture/ArchitectureVerifier.php"],
    ["php", "tools/release-contract-reconcile", "--write"],
    ["php", "tools/release-contract-reconcile", "--write"],
    ["php", "tools/release-contract-reconcile", "--check"],
    ["php", "tools/architecture-check.php"],
    ["php", "tools/quality-gate", "--fast"],
    ["git", "diff", "--check"],
]
for command in commands:
    subprocess.run(command, check=True)

subprocess.run(["git", "config", "user.name", "Wesley Ferreira"], check=True)
subprocess.run(["git", "config", "user.email", "48494254+drwesleyadv@users.noreply.github.com"], check=True)
subprocess.run(["git", "add", "-A"], check=True)
subprocess.run([
    "git", "commit", "-m",
    "fix: separa superfícies de composição da dívida arquitetural",
], check=True)
subprocess.run([
    "git", "push", "origin", "HEAD:fix/vps-homologation-mfa",
], check=True)
