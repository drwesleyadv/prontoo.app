from pathlib import Path

root = Path(__file__).resolve().parents[1]
op5_path = root / "app/Runtime/TasksNotices/TasksNoticesRuntimeOperations05.php"
op6_path = root / "app/Runtime/TasksNotices/TasksNoticesRuntimeOperations06.php"

op5 = op5_path.read_text()
op6 = op6_path.read_text()

method_marker = "    public static function readonly_support_notice_screen(array $c, string $view = \"sent\"): string\n"
if method_marker not in op5:
    raise SystemExit("readonly support method marker not found")
if "public static function global_notices_by_severity" in op5:
    raise SystemExit("global notices helper already exists")

helper = '''    public static function global_notices_by_severity(string $severity): array
    {
        return \\Prontoo\\Runtime\\Operational\\OperationalComposition::tasks()->result(
            'operational.tasks_notices.06.page_notices.11',
            [$severity],
            [],
        )->fetchAll();
    }

'''
op5 = op5.replace(method_marker, helper + method_marker, 1)

old = "                $globals = \\Prontoo\\Runtime\\Operational\\OperationalComposition::tasks()->result('operational.tasks_notices.06.page_notices.11', [$sev], [])->fetchAll();"
new = "                $globals = \\Prontoo\\Runtime\\TasksNotices\\TasksNoticesRuntimeOperations05::global_notices_by_severity($sev);"
if op6.count(old) != 1:
    raise SystemExit(f"expected one direct global notices gateway call, found {op6.count(old)}")
op6 = op6.replace(old, new, 1)

if "operational.tasks_notices.06.page_notices.11" in op6:
    raise SystemExit("global notices query still remains in Operations06")

op5_path.write_text(op5)
op6_path.write_text(op6)
Path(__file__).unlink()
