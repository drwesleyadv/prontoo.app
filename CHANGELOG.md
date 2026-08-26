# Versão canônica

## 1.8.26.1 — Homologação VPS e correção do MFA

- permite o host HTTPS srv.prontoo.app como ambiente explícito de homologação.
- preserva prontoo.app como domínio canônico de produção e mantém o redirecionamento de hosts desconhecidos.
- corrige o callback de hash dos códigos de recuperação durante o primeiro cadastro do MFA.
- corrige o mesmo callback nos fluxos de regeneração e substituição do autenticador.
- preserva banco, schema, credenciais locais e política de MFA obrigatório do Desenvolvedor.
