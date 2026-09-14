# Versão canônica

## 1.9.14.1 — Consolidação pós-auditoria

- corrige callbacks MFA convertidos para métodos estáticos nos fluxos de cadastro, regeneração e substituição do autenticador.
- corrige callbacks do Maestro para usar o método estático canônico de chave de rotina.
- classifica como transitórios apenas arquivos internos de app que realmente pertencem à migração arquitetural.
- faz o manifesto de release enumerar exclusivamente arquivos rastreados pelo Git e ignorar arquivos operacionais locais.
- calcula hashes do manifesto a partir do conteúdo final dos artefatos derivados para convergir em uma única execução.
- remove metadados históricos hardcoded do gerador de versão e exige metadados explícitos por publicação.
- preserva schema, banco, aparência, navegação e contratos visuais.
