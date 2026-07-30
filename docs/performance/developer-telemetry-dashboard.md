# Painel de telemetria do Desenvolvedor

## Objetivo

A rota `admin_telemetry` fornece uma leitura operacional do desempenho e da saúde do runtime para o perfil Desenvolvedor.

## Fontes

A tela reutiliza exclusivamente métricas técnicas já registradas:

- eventos de requisição particionados em NDJSON;
- agregados de desempenho por rota e versão;
- contadores de cache JSON;
- estado dos diretórios de trabalho diferido do Maestro.

Nenhum conteúdo clínico, identificador de paciente ou payload de formulário é exibido.

## Indicadores

A visão apresenta:

- volume e tempo médio de resposta;
- tempo de banco;
- falhas por intervalo;
- eficiência e contenção do cache;
- rotas com maior latência;
- comparação entre versões;
- itens pendentes, em processamento e em fila morta.

## Segurança e custo

A rota é global, exige a capacidade `admin_telemetry` e não possui operações POST. As janelas aceitas são limitadas a 6, 24, 72 ou 168 horas. A leitura não cria tabelas, não altera o schema e não modifica os arquivos de telemetria.
