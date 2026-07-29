# Baseline de caracterização da Fase 1

## Objetivo

Provar que a reorganização de funções puras preserva entradas, saídas e contratos públicos sem alterar banco, interface ou fluxo de requisição.

## Casos cobertos

### Identidade

- CPF válido e inválido;
- rejeição de CPF repetido;
- CNPJ válido e inválido;
- data válida;
- data inexistente;
- data futura;
- data vazia.

### Pacientes

- formatação canônica de CPF;
- preservação da entrada quando o CPF não possui onze dígitos;
- remoção de HTML e normalização de espaços no nome de aba;
- limite de sessenta caracteres;
- geração de `tab_*` e `extra*`;
- catálogo de vínculos de responsáveis;
- fallback de vínculo desconhecido;
- data vazia e futura;
- identificação de menoridade por data recente.

## Contrato estrutural

Os testes também verificam que:

- as funções globais permanecem definidas nos arquivos legados;
- as fachadas delegam aos componentes extraídos;
- componentes puros não acessam superglobais;
- componentes puros não executam SQL, PDO, redirecionamentos ou respostas HTTP.

## Execução

A caracterização integra `tools/architecture-check.php` e é executada no contrato arquitetural do CI.
