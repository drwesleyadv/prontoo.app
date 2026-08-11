# Segurança do Prontoo

A segurança do Prontoo é tratada como propriedade arquitetural. Controles importantes são fail-closed sempre que uma decisão permissiva sem evidência persistente poderia ampliar acesso, atravessar consultórios ou manter uma sessão indevidamente válida.

## Fronteiras protegidas

As principais fronteiras são identidade, sessão, autorização, isolamento por consultório, persistência, auditoria e instalação. O Runtime não pode contornar essas fronteiras com SQL ou PDO local. A camada Application expressa casos de uso e ports; Infrastructure implementa acesso a dados e mecanismos criptográficos; invariantes centrais impedem dependências proibidas.

## Autenticação e sessão

O fluxo usa senha e ciclo de MFA conforme a política canônica. Sessões carregam uma geração de autenticação persistente. Logout global tenta rotacionar essa geração até três vezes; se a revogação global não puder ser confirmada, a sessão local é destruída e a resposta é HTTP 503, em vez de simular sucesso. Uma sessão com geração obsoleta é rejeitada cedo, antes de continuar o processamento normal.

## Autorização e tenant

Permissão não é inferida apenas pela presença de uma sessão. A ação solicitada, o vínculo com o consultório e o contexto precisam ser coerentes. Consultas e comandos que dependem de `clinic_id` são protegidos por contratos de escopo. O objetivo é impedir tanto vazamento de leitura quanto escrita cruzada entre tenants.

## Auditoria

Eventos críticos usam ledger e trilhas de auditoria. Trabalho diferido é supervisionado pelo Maestro e pode usar spool persistente, retry e dead-letter. Falhas de auditoria não devem ser confundidas com autorização concedida.

## Vulnerabilidades

Não publique dados sensíveis, credenciais, dumps de banco ou provas contendo informações pessoais em issues abertas. Ao relatar uma vulnerabilidade, descreva pré-condição, impacto, caminho de reprodução mínimo e evidência sanitizada. A correção deve incluir regressão automatizada sempre que a falha puder ser reproduzida de modo determinístico.

## Fonte normativa

`docs/security/` explica o modelo. `tools/security-regression-check.php`, os smokes HTTP, os contratos de tenant e `app/architecture.manifest.json` são a evidência executável de que as regras continuam ativas.
