# Autenticação

## Fluxo

1. identificar o usuário por CPF;
2. validar estado ativo e bloqueios;
3. verificar senha no servidor;
4. executar a prontidão mínima pós-senha;
5. criar/continuar desafio pré-autenticado;
6. verificar MFA quando ativo ou obrigatório;
7. rotacionar identificador de sessão;
8. carregar contexto mínimo;
9. registrar entrada.

O formulário de login deve continuar funcional por envio HTML nativo mesmo sem JavaScript. JavaScript não é autoridade para CPF, senha, MFA, rate limit ou credenciais.

## Prontidão pós-senha

`RuntimeBootCoordinator` mantém prontidão mínima separada de manutenção profunda. O caminho síncrono do login verifica contrato de schema e `integrity lightcheck`; Maestro contract, autotestes profundos, cleanup e runtime self-check não pertencem ao hot path.

O cache de prontidão é otimização, não autoridade. Se diretório/arquivo de lock não puder ser gravado, os checks mínimos executam sem cache. Resultado indeterminado dos checks de segurança continua fail-closed.

## MFA

O estado é triádico:

- ativo;
- comprovadamente inativo;
- indisponível ou indeterminado.

O terceiro estado falha fechado. Desenvolvedor deve manter MFA ativo. Usuários regulares podem ativar proteção avançada conforme política. Elevação para escopo global exige senha e MFA recentes.

## Sessão

- cookies seguros e apropriados;
- inatividade máxima de 60 minutos;
- geração canônica vinculada ao usuário;
- logout global segue `logout_global_session_revocation_v1`: a rotação da geração canônica é tentada até três vezes;
- logout sempre destrói a sessão local; se a revogação global não puder ser confirmada, a resposta é `503` e não declara sucesso silencioso;
- alteração de segurança revoga sessões incompatíveis.

## Recuperação

Códigos de recuperação são de uso controlado, não aparecem em logs e devem ser regenerados após comprometimento.
