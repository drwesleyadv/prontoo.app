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

## Recuperação

Códigos de recuperação são de uso controlado, não devem aparecer em logs e precisam ser regenerados após comprometimento.
