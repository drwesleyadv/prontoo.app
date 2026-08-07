# Autenticação

## Fluxo

1. identificar o usuário por CPF;
2. validar estado ativo e bloqueios;
3. verificar senha;
4. criar desafio pré-autenticado;
5. verificar MFA quando ativo ou obrigatório;
6. rotacionar identificador de sessão;
7. carregar contexto mínimo;
8. registrar entrada.

## MFA

O estado é triádico:

- ativo;
- comprovadamente inativo;
- indisponível ou indeterminado.

O terceiro estado falha fechado. Desenvolvedor deve manter MFA ativo. Usuários regulares podem ativar proteção avançada conforme política.

## Sessão

- cookies seguros e apropriados;
- inatividade máxima de 60 minutos;
- geração canônica vinculada ao usuário;
- elevação global exige senha e MFA recentes;
- alteração de segurança revoga sessões incompatíveis.

## Limitação de tentativas

O throttling do login é um controle obrigatório de segurança. A persistência de `pi_login_locks` combina identidade e IP, identidade entre IPs e IP entre identidades. Se esse estado não puder ser lido, incrementado ou limpo com segurança, a autenticação falha fechada com indisponibilidade temporária; a aplicação não reduz a proteção para um contador apenas de sessão.

## Logout

O comando **Sair** adota a política `logout_global_session_revocation_v1`: encerra sempre a sessão local e, para usuário autenticado, rotaciona a geração canônica individual para invalidar as demais sessões no primeiro uso seguinte. Portanto, “Sair” é deliberadamente um logout global do usuário, não apenas a remoção do cookie do navegador atual.

A revogação global é tentada até três vezes e é registrada separadamente da destruição local e da auditoria. A sessão local é destruída mesmo se a persistência estiver degradada. Se, depois das tentativas, a rotação global não puder ser confirmada, a resposta é 503 e não é apresentada como logout global bem-sucedido; o evento de auditoria registra `global_revocation_confirmed=false` e o log operacional recebe a falha.

## Contrato regressivo

A CI executa `tools/login-logout-http-smoke` contra MySQL real e o entrypoint HTTP. O contrato cobre obtenção e validação de CSRF, autenticação por CPF e senha, rotação do identificador de sessão contra fixation, acesso autenticado, rejeição de logout com CSRF inválido, logout válido, invalidação de uma segunda sessão pela geração canônica e falha fechada do login quando `pi_login_locks` fica indisponível.

## Recuperação

Códigos de recuperação são de uso controlado, não devem aparecer em logs e precisam ser regenerados após comprometimento.
