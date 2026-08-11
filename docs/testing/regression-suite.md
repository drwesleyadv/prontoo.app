# Suíte regressiva

## Verificadores principais

- lint PHP;
- `tools/code-comment-check.php`;
- `tools/documentation-check.php`;
- `tools/security-regression-check.php`;
- `tools/application-test-contract-check`;
- `tools/test-fast`;
- `tools/architecture-check.php`;
- `tools/schema-check.php`;
- `tools/install-security-check.php`;
- matriz de autorização;
- contratos JSON;
- propriedades matemáticas.

## Critérios

Uma regressão deve:

- reproduzir a falha antes da correção;
- passar após a correção;
- testar o limite de segurança relacionado;
- ter nome que descreva o contrato;
- ser determinística;
- não depender de dados de produção.

Todo entry point público de um `*Service` em `app/Application` deve constar em `app/application.test-contract.json`. Cada caso crítico precisa de assertivas nomeadas que a suíte rápida comprove ter executado; transações e locks são caracterizados separadamente em MySQL real.

## Manutenção

Quando o comportamento muda legitimamente, atualize teste e documentação no mesmo pull request. Não enfraqueça a asserção apenas para obter CI verde.
