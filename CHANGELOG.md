# Versão canônica

## 1.8.18.5 — Design Tokens DTCG e tema por consultório

- adota Design Tokens DTCG 2025.10 em camadas Reference, System e Component como fonte canônica do Design System.
- usa Style Dictionary 5.5.0 para gerar deterministicamente os arquivos CSS de tokens e retira deles a autoridade de edição manual.
- resolve aliases dependentes da identidade no escopo runtime do consultório para eliminar tokens presos à cor padrão.
- mantém sucesso, aviso, erro e informação como cores semânticas independentes da identidade do consultório.
- adiciona contrato multitema obrigatório com verde, azul, vinho e violeta além do baseline computado já existente.
- preserva geometria, tipografia, espaçamento e fluxos do tema padrão sem alterar banco ou schema.
