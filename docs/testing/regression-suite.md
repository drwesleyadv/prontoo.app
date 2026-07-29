# Suíte regressiva

## Verificadores principais

- lint PHP;
- `tools/code-comment-check.php`;
- `tools/documentation-check.php`;
- `tools/security-regression-check.php`;
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

## Manutenção

Quando o comportamento muda legitimamente, atualize teste e documentação no mesmo pull request. Não enfraqueça a asserção apenas para obter CI verde.
