from pathlib import Path

path = Path('app/Support/Telemetry.php')
text = path.read_text(encoding='utf-8')

old_generation = 'return "route-cycles-reset-2026-08-02-1";'
new_generation = 'return "route-cycles-reset-2026-08-02-2";'
if old_generation not in text:
    raise SystemExit('Geração anterior do reset não localizada.')
text = text.replace(old_generation, new_generation, 1)

old_reset = '''            $state["pending"] = [];
            $state["days"] = [];
            $state["reset_generation"] = $resetGeneration;
            $state["reset_applied_at_us"] = (int) round(microtime(true) * 1000000);
'''
new_reset = '''            $state["pending"] = [];
            $state["days"] = [];
            $state["reset_discard_next"] = true;
            $state["reset_generation"] = $resetGeneration;
            $state["reset_applied_at_us"] = (int) round(microtime(true) * 1000000);
'''
if old_reset not in text:
    raise SystemExit('Bloco de reset atual não localizado.')
text = text.replace(old_reset, new_reset, 1)

old_pending = '''        $pending[$id] = ["route" => $route, "started_ns" => $startedNs, "started_us" => $startedUs];
        $state["pending"] = $pending;
'''
new_pending = '''        $discard = !empty($state["reset_discard_next"]);
        unset($state["reset_discard_next"]);
        $pending[$id] = [
            "route" => $route,
            "started_ns" => $startedNs,
            "started_us" => $startedUs,
            "discard" => $discard ? 1 : 0,
        ];
        $state["pending"] = $pending;
'''
if old_pending not in text:
    raise SystemExit('Registro atual do marcador inicial não localizado.')
text = text.replace(old_pending, new_pending, 1)

old_finish = '''        $durationNs = $finishedNs - $startedNs;
        $day = (new DateTimeImmutable("@" . intdiv($finishedUs, 1000000)))->setTimezone(telemetry_cuiaba_tz())->format("Y-m-d");
'''
new_finish = '''        $durationNs = $finishedNs - $startedNs;
        if (!empty($row["discard"])) {
            $state["pending"] = $pending;
            $state["updated_at_us"] = $finishedUs;
            $state["reset_completed_at_us"] = $finishedUs;
            return true;
        }
        $day = (new DateTimeImmutable("@" . intdiv($finishedUs, 1000000)))->setTimezone(telemetry_cuiaba_tz())->format("Y-m-d");
'''
if old_finish not in text:
    raise SystemExit('Ponto de conclusão do ciclo não localizado.')
text = text.replace(old_finish, new_finish, 1)

path.write_text(text, encoding='utf-8')
