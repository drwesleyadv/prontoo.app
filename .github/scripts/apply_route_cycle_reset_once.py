from pathlib import Path

path = Path('app/Support/Telemetry.php')
text = path.read_text(encoding='utf-8')

anchor = '''function telemetry_route_cycle_file(): string
{
    return telemetry_storage_dir() . "/route-cycles-v2.json";
}
'''
replacement = '''function telemetry_route_cycle_file(): string
{
    return telemetry_storage_dir() . "/route-cycles-v2.json";
}
function telemetry_route_cycle_reset_generation(): string
{
    return "route-cycles-reset-2026-08-02-1";
}
'''
if 'function telemetry_route_cycle_reset_generation' not in text:
    if anchor not in text:
        raise SystemExit('Âncora da fonte route_cycles_v2 não encontrada.')
    text = text.replace(anchor, replacement, 1)

state_anchor = '''        if (!is_array($state) || (int) ($state["version"] ?? 0) !== 2) {
            $state = ["version" => 2, "timezone" => "America/Cuiaba", "pending" => [], "days" => []];
        }
        $result = $mutator($state);
'''
state_replacement = '''        if (!is_array($state) || (int) ($state["version"] ?? 0) !== 2) {
            $state = ["version" => 2, "timezone" => "America/Cuiaba", "pending" => [], "days" => []];
        }
        $resetGeneration = telemetry_route_cycle_reset_generation();
        if (!hash_equals($resetGeneration, (string) ($state["reset_generation"] ?? ""))) {
            $state["pending"] = [];
            $state["days"] = [];
            $state["reset_generation"] = $resetGeneration;
            $state["reset_applied_at_us"] = (int) round(microtime(true) * 1000000);
        }
        $result = $mutator($state);
'''
if 'reset_applied_at_us' not in text:
    if state_anchor not in text:
        raise SystemExit('Âncora da mutação route_cycles_v2 não encontrada.')
    text = text.replace(state_anchor, state_replacement, 1)

path.write_text(text, encoding='utf-8')
