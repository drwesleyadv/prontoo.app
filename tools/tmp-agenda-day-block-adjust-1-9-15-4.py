from pathlib import Path

root = Path(__file__).resolve().parents[1]
path = root / "app/Application/Operational/AppointmentCommandService.php"
text = path.read_text(encoding="utf-8")
old = '''            if (!$blocked) {
                $this->data->result(
                    'operational.appointments.05.page_appointments.45',
                    [
                        $userId,
                        'Dia desbloqueado pela Agenda diária.',
                        $clinicId,
                        $doctorUserId,
                        $blockStartAt,
                        $blockEndAt,
                    ],
                );
                return 0;
            }
            $existing = $this->data->row(
                'operational.appointments.05.page_appointments.44',
                [$clinicId, $doctorUserId, $blockStartAt, $blockEndAt],
            );
            if ($existing) {
                return (int) ($existing['id'] ?? 0);
            }
'''
new = '''            $existing = $this->data->row(
                'operational.appointments.05.page_appointments.44',
                [$clinicId, $doctorUserId, $blockStartAt, $blockEndAt],
            );
            if (!$blocked) {
                $this->data->result(
                    'operational.appointments.05.page_appointments.45',
                    [
                        $userId,
                        'Dia desbloqueado pela Agenda diária.',
                        $clinicId,
                        $doctorUserId,
                        $blockStartAt,
                        $blockEndAt,
                    ],
                );
                return (int) ($existing['id'] ?? 0);
            }
            if ($existing) {
                return (int) ($existing['id'] ?? 0);
            }
'''
if text.count(old) != 1:
    raise SystemExit(f"day-block service adjustment expected once, found {text.count(old)}")
path.write_text(text.replace(old, new, 1), encoding="utf-8")
