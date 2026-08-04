from pathlib import Path
import datetime
import hashlib
import json
import re
import subprocess
import time

root = Path('.')
version = '1.8.4.6'
previous = '1.8.4.5'
asset = '1.7.15.11'
build = '1.8.4.6-authenticated-telemetry-palette'
now = datetime.datetime.now(datetime.timezone.utc).isoformat()
unix = int(time.time())

app_js_path = root / 'public/assets/app.js'
app_js = app_js_path.read_text()
old_open = '''  function initLoginTelemetryWave(root = d) {
    let wrap = $("[data-login-telemetry-wave]", root);
    if (!wrap && d.body && !d.body.classList.contains("public")) {'''
new_open = '''  function initLoginTelemetryWave(root = d) {
    let wrap = $("[data-login-telemetry-wave]", root);
    const loginTelemetryPublic =
      d.body &&
      d.body.classList.contains("public") &&
      d.body.dataset.route === "login";
    const clinicCreateTelemetry =
      !!$("[data-onboarding-wizard],.signup-steps-card,[data-clinic-create]", root);
    const telemetryEligible =
      d.body &&
      (loginTelemetryPublic ||
        !d.body.classList.contains("public") ||
        clinicCreateTelemetry);
    if (telemetryEligible) d.body.classList.add("has-telemetry-mountains");
    if (wrap)
      wrap.classList.toggle("is-clinic-themed", !loginTelemetryPublic);
    if (
      !wrap &&
      d.body &&
      (!d.body.classList.contains("public") || clinicCreateTelemetry)
    ) {'''
if old_open not in app_js:
    raise SystemExit('initLoginTelemetryWave opening not found')
app_js = app_js.replace(old_open, new_open, 1)
start = app_js.index('  function initLoginTelemetryWave(root = d) {')
class_token = '      wrap.className = "login-telemetry-wave app-telemetry-mountains";'
class_pos = app_js.find(class_token, start)
if class_pos < 0:
    raise SystemExit('dynamic telemetry wrapper class not found')
app_js = app_js[:class_pos] + class_token.replace(
    'app-telemetry-mountains',
    'app-telemetry-mountains is-clinic-themed',
) + app_js[class_pos + len(class_token):]
app_js_path.write_text(app_js)

css_path = root / 'public/assets/design-system.css'
css = css_path.read_text()
if 'body.has-telemetry-mountains .login-telemetry-wave{' in css:
    raise SystemExit('authenticated telemetry CSS already present')
css += '''

body.has-telemetry-mountains{isolation:isolate;overflow-x:hidden}
body.has-telemetry-mountains :where(.top,.side,main#conteudo,.signup-shell,.onboarding-shell,.auth-shell){position:relative;z-index:1}
body.has-telemetry-mountains .login-telemetry-wave{
  position:fixed;
  right:0;
  bottom:0;
  left:0;
  z-index:0;
  display:block;
  width:100%;
  height:15vh;
  min-height:64px;
  max-height:180px;
  overflow:hidden;
  pointer-events:none;
  user-select:none;
  -webkit-mask-image:linear-gradient(to bottom,transparent 0,#000 34%,#000 100%);
  mask-image:linear-gradient(to bottom,transparent 0,#000 34%,#000 100%);
}
body.has-telemetry-mountains .login-telemetry-wave svg{display:block;width:100%;height:100%;overflow:visible}
body.has-telemetry-mountains .login-telemetry-wave-path{stroke:none;opacity:.3}
body.has-telemetry-mountains .app-telemetry-mountains.is-clinic-themed .login-telemetry-wave-path.is-requests{fill:var(--clinic-accent-strong,var(--brand-dark,#1f6f56))}
body.has-telemetry-mountains .app-telemetry-mountains.is-clinic-themed .login-telemetry-wave-path.is-records{fill:var(--clinic-accent,var(--brand,#347963))}
'''
css_path.write_text(css)

changelog = root / 'CHANGELOG.md'
text = changelog.read_text()
heading = '# Histórico de versões\n'
entry = '''
## 1.8.4.6 — Ondas autenticadas na paleta do consultório

- corrige a exibição das montanhas de telemetria no rodapé da área autenticada;
- aplica à camada autenticada as mesmas dimensões, recorte e opacidade usadas no login;
- inclui a tela Criar Consultório na inicialização das faixas dinâmicas;
- mantém no login as cores verdes já aprovadas;
- usa `--clinic-accent-strong` em Requisições e `--clinic-accent` em Registros na área logada e na criação do consultório;
- acompanha dinamicamente a cor de destaque selecionada para o consultório;
- preserva a camada sem interação, contornos, eixos, escalas ou tooltips;
- não altera banco de dados nem schema.
'''
if not text.startswith(heading):
    raise SystemExit('CHANGELOG heading not found')
changelog.write_text(heading + entry + text[len(heading):])

legacy = root / 'ChangeLog.txt'
legacy_text = legacy.read_text()
legacy_entry = '''1.8.4.6 - Ondas autenticadas na paleta do consultório
- Corrige a exibição das montanhas na área logada e na tela Criar Consultório.
- Mantém o login com os verdes aprovados.
- Usa a paleta dinâmica de destaque do consultório nas duas áreas preenchidas.
- Preserva 15% da viewport, 70% de transparência e ausência de interação.
- Sem alteração de banco de dados ou schema.

'''
legacy.write_text(legacy_entry + legacy_text)

version_path = root / 'version.json'
version_data = json.loads(version_path.read_text())
version_data.update({
    'version': version,
    'release': version,
    'generated_at_unix': unix,
    'generated_at': now,
    'updated_at': now,
    'build': build,
    'asset_version': asset,
    'previous_version': previous,
    'notes': 'Área logada e Criar Consultório exibem montanhas de telemetria com a paleta dinâmica de destaque; login preserva os verdes aprovados.',
    'deployment_sync_id': 'github-authenticated-telemetry-palette-1-8-4-6',
    'deployment_sync_requested_at': now,
    'rewrite_scope': 'authenticated_and_clinic_creation_telemetry_visibility_and_palette',
})
version_path.write_text(json.dumps(version_data, ensure_ascii=False, indent=4) + '\n')

architecture_path = root / 'app/architecture.manifest.json'
architecture = json.loads(architecture_path.read_text())
architecture['version'] = version
if 'release' in architecture:
    architecture['release'] = version
if 'updated_at' in architecture:
    architecture['updated_at'] = now
if 'generated_at' in architecture:
    architecture['generated_at'] = now
if 'notes' in architecture:
    architecture['notes'] = 'Ondas autenticadas visíveis e vinculadas à paleta dinâmica do consultório, inclusive em Criar Consultório.'
architecture_path.write_text(json.dumps(architecture, ensure_ascii=False, indent=4) + '\n')

for rel, constant in [
    ('app/prontoo.php', 'PRONTOO_VERSION_FALLBACK'),
    ('br/index.php', 'BR_LANDING_VERSION_FALLBACK'),
]:
    p = root / rel
    source = p.read_text()
    source, count = re.subn(
        rf'(const\s+{constant}\s*=\s*["\x27])[^"\x27]+(["\x27]\s*;)',
        rf'\g<1>{version}\g<2>',
        source,
        count=1,
    )
    if count != 1:
        raise SystemExit(f'version fallback not found: {rel}')
    source = source.replace(previous, version)
    source = source.replace('1.7.15.10', asset)
    p.write_text(source)

base_architecture = subprocess.check_output(
    ['git', 'show', 'origin/prontoo:.github/workflows/architecture.yml'],
    text=True,
)
(root / '.github/workflows/architecture.yml').write_text(base_architecture)
for temp in [
    root / '.github/workflows/apply-auth-telemetry-fix.yml',
    root / 'tools/apply-auth-telemetry-fix.trigger',
    root / 'tools/apply-auth-telemetry-fix.py',
]:
    if temp.exists():
        temp.unlink()

update_path = root / 'app/update.manifest.json'
update = json.loads(update_path.read_text())
update.update({
    'version': version,
    'release': version,
    'build': build,
    'asset_version': asset,
    'previous_version': previous,
    'updated_at': now,
    'notes': 'Ondas autenticadas e da tela Criar Consultório agora são visíveis e seguem a paleta dinâmica do consultório.',
    'deployment_sync_id': 'github-authenticated-telemetry-palette-1-8-4-6',
    'deployment_sync_requested_at': now,
})
files = {}
total = 0
excluded_roots = {'.git', 'ssd', 'vendor', 'node_modules'}
for p in root.rglob('*'):
    if not p.is_file() or p.is_symlink():
        continue
    rel = p.as_posix().lstrip('./')
    if rel == 'app/update.manifest.json':
        continue
    if rel.split('/', 1)[0] in excluded_roots:
        continue
    data = p.read_bytes()
    files[rel] = hashlib.sha256(data).hexdigest()
    total += len(data)
update['files'] = dict(sorted(files.items()))
update['file_count'] = len(files)
update['total_uncompressed_bytes'] = total
update_path.write_text(json.dumps(update, ensure_ascii=False, indent=4) + '\n')
