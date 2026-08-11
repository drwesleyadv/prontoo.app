# Modelo de ameaças

O Prontoo protege dados pessoais, informações clínicas, rotinas internas e movimentos financeiros. O modelo assume que erros de aplicação são mais prováveis do que comprometimento físico do servidor e prioriza barreiras contra acesso indevido, cruzamento de tenant, sessão residual e mutações inconsistentes.

## Ameaças principais

- usuário autenticado acessando dados de outro consultório;
- sessão roubada ou antiga permanecendo válida após logout;
- autorização inferida por rota sem validar ação/contexto;
- SQL ou transação introduzidos em página e escapando de invariantes comuns;
- escrita financeira parcial ou duplicada;
- trabalho diferido executado sem contexto original;
- telemetria/auditoria expondo conteúdo sensível;
- instalação ou debug disponíveis fora da janela prevista.

## Controles

Tenant isolation, action catalog, geração global de sessão, MFA, constraints, Application Services transacionais, action ledger, spool supervisionado e gates arquiteturais formam defesa em profundidade. HTTPS e host canônico protegem transporte e origem pública.

## Princípio de falha

Quando o sistema não consegue provar uma condição de segurança — por exemplo, confirmar revogação global — prefere negar/indicar degradação a continuar como se nada tivesse ocorrido.
