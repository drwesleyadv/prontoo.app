from pathlib import Path
p=Path('app/Admin/AdminPages.php')
s=p.read_text(encoding='utf-8')
s=s.replace('function_exists("telemetry_seven_day_comparison")', 'function_exists("telemetry_status_cards_snapshot")')
s=s.replace('<div class="status-section-heading"><div><span>Últimos 7 dias</span><h2 id="status-summary-title">Resumo operacional</h2></div><p>Medição exata dos ciclos concluídos no dia atual.</p></div>', '<div class="status-section-heading"><div><span>Hoje</span><h2 id="status-summary-title">Resumo operacional</h2></div><p>Medição exata dos ciclos concluídos no dia atual.</p></div>')
p.write_text(s,encoding='utf-8')
