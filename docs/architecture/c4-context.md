# C4 — Contexto do sistema

## Pessoas e sistemas ao redor

O Prontoo é usado por profissionais e colaboradores de consultórios para administrar rotina clínica e administrativa. Pacientes são entidades centrais do domínio, mas o sistema é operado por usuários autenticados vinculados a consultórios. A hospedagem fornece runtime PHP, MySQL, filesystem persistente e execução agendada do Maestro.

## Sistema Prontoo

Como sistema único, o Prontoo concentra agenda, cadastro e ficha de pacientes, documentos, tarefas, equipe, financeiro, auditoria, telemetria e rotinas de supervisão. Ele mantém isolamento por consultório mesmo quando o mesmo runtime atende múltiplos tenants.

## Fronteiras externas

HTTP/HTTPS é a fronteira de interação. MySQL é a persistência relacional. `ssd/` é a raiz persistente para artefatos e estados que não pertencem ao banco. O cron/server cycle aciona o Maestro. GitHub e CI governam publicação e contratos do código, não participam do fluxo clínico em runtime.

## Objetivo arquitetural

O contexto externo é pequeno de propósito. Complexidade fica encapsulada dentro do monólito modular para que operação e deploy permaneçam simples, enquanto as fronteiras internas protegem regras sensíveis.
