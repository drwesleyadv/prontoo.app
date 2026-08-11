# Autenticação e sessão

Autenticação é um ciclo, não apenas a validação de senha. O Prontoo combina credencial, MFA conforme política, rotação de ID de sessão, tempo de inatividade e uma geração persistente que permite invalidar sessões emitidas anteriormente.

## Login

A senha é validada server-side e o ID de sessão é rotacionado para impedir fixation. Estados de MFA distinguem inativo, ativo e indisponível; indisponibilidade não deve ser interpretada como MFA dispensado quando a política exigir validação.

## Sessão

Contexto autenticado inclui usuário e vínculo com consultório. Guards verificam a geração persistente antes de processamento protegido. Sessões antigas são eliminadas cedo.

## Logout global

A política `logout_global_session_revocation_v1` faz até três tentativas para rotacionar a geração. Sucesso produz revogação global; outras sessões se tornam obsoletas. Se a persistência não confirmar a rotação, a sessão local é destruída e o usuário recebe HTTP 503 com degradação explícita.

## Teste

`tools/login-logout-http-smoke` cobre CSRF, rotação de sessão, duas sessões simultâneas, revogação e falha simulada da escrita canônica.
