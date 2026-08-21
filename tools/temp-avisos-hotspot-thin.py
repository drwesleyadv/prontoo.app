from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]


def read(path):
    return (root / path).read_text()


def write(path, text):
    (root / path).write_text(text)


def replace_required(text, old, new, label, minimum=1):
    count = text.count(old)
    if count < minimum:
        raise SystemExit(f"replacement not found: {label} ({count})")
    return text.replace(old, new)


users_path = "app/Runtime/UsersPermissions/UsersPermissionsRuntimeOperations04.php"
users = read(users_path)
users = replace_required(users, " ds-collaborator-row-v31", "", "collaborator version alias")
write(users_path, users)

documents_path = "app/Runtime/Documents/DocumentsRuntimeOperations06.php"
documents = read(documents_path)
documents = replace_required(documents, " procedure-kpis-refined", "", "procedure refined alias")
write(documents_path, documents)

audit_path = "app/Runtime/AuditActivity/AuditActivityRuntimeOperations05.php"
audit = read(audit_path)
audit = replace_required(audit, 'class=\\"notice-minimal-icon\\"', 'class=\\"ds-section-icon\\"', "activity section icon")
audit = replace_required(audit, 'class=\\"activity-filter-chips\\"', 'class=\\"activity-filter-chips ds-selection-chips\\"', "activity selection group")
audit = replace_required(audit, 'class=\\"activity-filter-chip ', 'class=\\"activity-filter-chip ds-filter-chip ', "activity member chip", 2)
audit = replace_required(audit, 'class=\\"activity-period-filter\\"', 'class=\\"activity-period-filter ds-selection-chips\\"', "activity period group")
audit = replace_required(audit, 'class=\\"activity-period-chip ', 'class=\\"activity-period-chip ds-filter-chip ', "activity period chip", 2)
for expression in ('$member <= 0', '$member === $id', '$period === "date"', '$period === $value'):
    audit = audit.replace(f'{expression} ? "active" : ""', f'{expression} ? "is-active" : ""')
write(audit_path, audit)

admin_path = "app/Runtime/AdminPages/AdminPagesRuntimeOperations09.php"
admin = read(admin_path)
admin = replace_required(admin, " notice-gmail-kpis", "", "admin gmail KPI alias")
admin = replace_required(
    admin,
    'notice-filter-chips lead-filter-chips ds-notice-filters admin-alert-filters',
    'ds-notice-filters admin-alert-filters',
    "admin canonical filter group",
)
admin = replace_required(admin, 'lead-chip ds-filter-chip ', 'ds-filter-chip ', "admin canonical filter chip", 2)
admin = replace_required(admin, 'active is-active', 'is-active', "admin active filter state", 2)
admin = replace_required(admin, ' class=\\"lead-chip-label\\"', '', "admin filter label alias", 2)
write(admin_path, admin)

js_path = "public/assets/app.js"
js = read(js_path)
js = js.replace(",.procedure-kpis-refined", "")
js = js.replace(",.notice-gmail-kpis", "")
write(js_path, js)

runtime_path = "app/Runtime/TasksNotices/TasksNoticesRuntimeOperations06.php"
runtime = read(runtime_path)
old_read_block = '''        $targetParams = \\Prontoo\\Domain\\TasksNotices\\TasksNoticesDomainOperations01::notice_target_parameters($c);
        $rows = \\Prontoo\\Runtime\\Operational\\OperationalComposition::tasks()->result('operational.tasks_notices.06.page_notices.08', array_merge([$cid], $targetParams, [$uid]), [])->fetchAll();
        $reads = [];
        if ($rows) {
            $ids = \\Prontoo\\Domain\\AuditActivity\\AuditRecordPolicy::int_ids($rows, "id");
            $params = array_merge([$uid], $ids);
            foreach (
                \\Prontoo\\Runtime\\Operational\\OperationalComposition::tasks()->result('operational.tasks_notices.06.page_notices.09', $params, ['itemCount' => count($ids)])->fetchAll()
                as $r
            ) {
                $reads[(int) $r["notice_id"]] = $r;
            }
        }
'''
new_read_block = '''        $targetParams = \\Prontoo\\Domain\\TasksNotices\\TasksNoticesDomainOperations01::notice_target_parameters($c);
        $rows = \\Prontoo\\Runtime\\Operational\\OperationalComposition::tasks()->result(
            'operational.tasks_notices.06.page_notices.08',
            array_merge([$uid, $cid], $targetParams, [$uid]),
            [],
        )->fetchAll();
        $reads = [];
        foreach ($rows as $row) {
            $reads[(int) $row["id"]] = [
                "notice_id" => (int) $row["id"],
                "read_at" => $row["read_at"] ?? null,
                "ack_at" => $row["ack_at"] ?? null,
                "hidden_at" => $row["hidden_at"] ?? null,
            ];
        }
'''
runtime = replace_required(runtime, old_read_block, new_read_block, "notice read join")
write(runtime_path, runtime)

catalog_path = "app/Infrastructure/Operational/TasksNoticesSqlCatalog06.php"
catalog = read(catalog_path)
old_query = '''            'operational.tasks_notices.06.page_notices.08' => (
                "SELECT n.id,n.title,n.body,n.requires_ack,n.target_scope,n.target_role,n.target_user_id,n.created_by,n.created_at FROM pi_notices n WHERE n.clinic_id=? AND " . NoticeQuerySql::targetOrCreator('n') . " ORDER BY n.id DESC LIMIT 160"
            ),
            'operational.tasks_notices.06.page_notices.09' => (
                "SELECT notice_id,read_at,ack_at,hidden_at FROM pi_notice_reads WHERE user_id=? AND notice_id IN (" . OperationalSequenceSql::placeholders((int) $itemCount) . ")"
            ),
'''
new_query = '''            'operational.tasks_notices.06.page_notices.08' => (
                "SELECT n.id,n.title,n.body,n.requires_ack,n.target_scope,n.target_role,n.target_user_id,n.created_by,n.created_at,r.read_at,r.ack_at,r.hidden_at FROM pi_notices n LEFT JOIN pi_notice_reads r ON r.notice_id=n.id AND r.user_id=? WHERE n.clinic_id=? AND " . NoticeQuerySql::targetOrCreator('n') . " ORDER BY n.id DESC LIMIT 160"
            ),
'''
catalog = replace_required(catalog, old_query, new_query, "notice read SQL consolidation")
write(catalog_path, catalog)

css_path = "design/styles/application.css"
css = read(css_path)
css = css.replace(".ds-collaborator-row-v31", ".ds-collaborator-row")
css = css.replace(".notice-minimal-icon", ".ds-section-icon")

for legacy in ("procedure-kpis-refined", "notice-gmail-kpis"):
    token = "." + legacy
    previous = None
    while previous != css:
        previous = css
        css = re.sub(rf"{re.escape(token)}\s*,\s*", "", css)
        css = re.sub(rf",\s*{re.escape(token)}(?=\s*[,):{{])", "", css)
    if token in css:
        raise SystemExit(f"legacy KPI selector remains: {token}")

simple_rule = re.compile(r"([^{}]+)\{([^{}]*)\}")
forbidden_selector = re.compile(r"\.(?:agenda-route-(?:dialog|overlay|backdrop)-retired|notice-minimal-[A-Za-z0-9_-]+|notice-gmail-[A-Za-z0-9_-]+)")

def remove_abandoned_rule(match):
    selector = match.group(1)
    if forbidden_selector.search(selector):
        return ""
    return match.group(0)

css = simple_rule.sub(remove_abandoned_rule, css)
write(css_path, css)

for path in (
    users_path,
    documents_path,
    audit_path,
    admin_path,
    js_path,
    runtime_path,
    catalog_path,
    css_path,
):
    text = read(path)
    for forbidden in (
        "ds-collaborator-row-v31",
        "procedure-kpis-refined",
        "notice-gmail-kpis",
        "notice-minimal-icon",
        "agenda-route-dialog-retired",
        "agenda-route-overlay-retired",
        "agenda-route-backdrop-retired",
    ):
        if forbidden in text:
            raise SystemExit(f"forbidden legacy token remains in {path}: {forbidden}")

if "operational.tasks_notices.06.page_notices.09" in read(runtime_path) or "operational.tasks_notices.06.page_notices.09" in read(catalog_path):
    raise SystemExit("abandoned notice read operation remains")

print("global-design-system-consolidation: source migration complete")
