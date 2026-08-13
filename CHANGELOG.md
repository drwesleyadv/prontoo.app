# Versão canônica

## 1.8.13.8 — Sanitização do estado canônico

- remove aliases e caminhos mantidos apenas para releases superadas.
- faz Presentation entregar somente public/assets/presentation.css.
- substitui resolução compatível de fontes por caminhos canônicos explícitos e fail-closed.
- remove mapas de migração de paths e baseline histórica do contrato PHP 8.4.
- remove importação de arquivos antigos de telemetria e mantém somente views.json e speed.json.
- mantém banco schema comportamento funcional e contratos de segurança inalterados.
