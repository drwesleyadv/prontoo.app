    if (!str_contains($leads, $required)) {
        throw new RuntimeException('Proteção de Interessados ausente: ' . $required);
    }
}
if (substr_count($leads, 'security_submission_token_consume(') < 4) {
    throw new RuntimeException('Nem todas as mutações de Interessados consomem token.');
}
$javascript = (string) file_get_contents('public/assets/app.js');
foreach (['PRONTOO_GLOBAL_POST_SUBMIT_LOCK', 'method === "post"', 'form.dataset.submitPending', '[data-submit-repeat]'] as $required) {
    if (!str_contains($javascript, $required)) {
        throw new RuntimeException('Bloqueio global de reenvio ausente: ' . $required);
    }
}
$appointments = (string) file_get_contents('app/Domain/Appointments/Appointments.php');
foreach (['procedure_submission_token', 'data-submit-once'] as $required) {
    if (!str_contains($appointments, $required)) {
        throw new RuntimeException('Proteção de Procedimentos regrediu: ' . $required);
    }
}
PHP'''
step = (
    "      - name: Duplicate submission regressions\n"
    "        shell: bash\n"
    "        run: |\n"
    "          php <<'PHP'\n"
    + "\n".join("          " + line for line in php_step.splitlines())
    + "\n\n"
)
if source.count(anchor) != 1:
    raise RuntimeError("Âncora JSON contracts ausente")
source = source.replace(anchor, step + anchor, 1)
write(path, source)


# 5) Versionamento e contratos.
path = "app/prontoo.php"
source = read(path)
source = replace_once(
    source,
    'const PRONTOO_VERSION_FALLBACK = "1.7.27.6";',
    'const PRONTOO_VERSION_FALLBACK = "1.7.27.7";',
    "fallback runtime",
)
source = replace_once(
    source,
    'const PRONTOO_PREVIOUS_VERSION = "1.7.27.5";',
    'const PRONTOO_PREVIOUS_VERSION = "1.7.27.6";',
    "versão anterior runtime",
)
write(path, source)

path = "br/index.php"
source = read(path)
source, count = re.subn(
    r'(const BR_LANDING_VERSION_FALLBACK = ["\'])1\.7\.27\.6(["\'];)',
    r'\g<1>1.7.27.7\g<2>',
    source,
    count=1,
)
if count != 1:
    raise RuntimeError("fallback da landing não atualizado")
write(path, source)

changelog = read("ChangeLog.txt")
entry = '''Prontoo 1.7.27.7 — gravações únicas e contatos consistentes

- Impede que o mesmo contato de Interessado seja registrado duas vezes por clique repetido, reenvio do navegador ou repetição da mesma requisição.
- Protege também cadastro, conversão e arquivamento de Interessados com tokens assinados, vinculados à sessão, ao escopo e ao registro.
- Aplica bloqueio preventivo a todos os formulários POST do projeto, com opt-out explícito apenas para fluxos que admitam repetição deliberada.
- Mantém a proteção específica já existente no cadastro de Procedimentos e acrescenta regressão permanente ao CI.
- Banco de dados e schema permanecem inalterados.

'''
write("ChangeLog.txt", entry + changelog)

version = json.loads(read("version.json"))
version.update({
    "version": VERSION,
    "release": VERSION,
    "generated_at_unix": int(time.time()),
    "generated_at": stamp,
    "updated_at": stamp,
    "build": VERSION + "-submission-idempotency",
    "package_type": "submission_idempotency",
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": False,
    "functional_equivalence_policy": "business_workflows_database_schema_permissions_and_user_navigation_are_preserved_while_duplicate_post_submissions_are_blocked_client_side_and_interested_mutations_are_single_use_server_side",
    "notes": "Impede duplicidade no registro de contatos de Interessados, protege as demais mutações desse fluxo e aplica bloqueio preventivo a todos os formulários POST.",
    "previous_version": PREVIOUS,
    "deployment_sync_id": "hostoo-submission-idempotency-" + stamp_compact,
    "deployment_sync_requested_at": stamp,
})
for stale_key in ["baseline_source", "full_baseline_rewrite", "rewrite_scope"]:
    version.pop(stale_key, None)
write("version.json", json.dumps(version, ensure_ascii=False, indent=2) + "\n")

architecture = json.loads(read("app/architecture.manifest.json"))
architecture.update({
    "version": VERSION,
    "function_documentation_units": 1940,
    "function_documentation_named": 1714,
    "logic_changes": True,
    "visual_changes": False,
    "submission_idempotency_policy": "all_post_forms_client_locked_by_default_and_append_only_interested_mutations_require_signed_session_scoped_single_use_tokens",
    "generated_at": stamp,
    "updated_at": stamp,
})
write("app/architecture.manifest.json", json.dumps(architecture, ensure_ascii=False, indent=2) + "\n")

update = json.loads(read("app/update.manifest.json"))
update.update({
    "version": VERSION,
    "release": VERSION,
    "build": VERSION + "-submission-idempotency",
    "package_type": "submission_idempotency",
    "generated_at": stamp,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": False,
    "documentation_changes": True,
    "documented_function_units": 1940,
})
for stale_key in ["baseline_source", "full_baseline_rewrite", "rewrite_scope"]:
    update.pop(stale_key, None)
write("app/update.manifest.json", json.dumps(update, ensure_ascii=False, indent=2) + "\n")

# Remove ferramentas transitórias antes de fechar o contrato da release.
Path(".github/workflows/agent-idempotency-1.7.27.7.yml").unlink()
Path("tools/agent-idempotency-patch.py").unlink(missing_ok=True)

update = json.loads(read("app/update.manifest.json"))
files = update.get("files", {})
for file in files:
    files[file] = hashlib.sha256(Path(file).read_bytes()).hexdigest()
update["files"] = files
update["file_count"] = len(files)
update["total_uncompressed_bytes"] = sum(Path(file).stat().st_size for file in files)
write("app/update.manifest.json", json.dumps(update, ensure_ascii=False, indent=2) + "\n")
if "app/update.manifest.json" in files:
    raise RuntimeError("O manifesto não deve hashear a si próprio")
