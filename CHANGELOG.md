# Versão canônica

## 1.9.16.3 — Dia bloqueado renderizado pelo servidor

- Detecta no servidor o bloqueio integral do expediente na visão semanal.
- Renderiza no HTML os marcadores Dia bloqueado a cada 30 minutos nas visões diária e semanal.
- Remove o cartão integral de bloqueio da coluna semanal quando o dia inteiro está bloqueado.
- Desabilita no servidor os atalhos de criação da coluna semanal bloqueada.
- Mantém a rotina JavaScript apenas como reforço idempotente do estado já entregue pelo servidor.
