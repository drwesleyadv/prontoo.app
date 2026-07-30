# Telemetria particionada

## Objetivo

A Fase 3 remove a leitura e reescrita integral do histórico bruto durante a consolidação de cada amostra. Eventos de página passam a ser acrescentados em arquivos NDJSON particionados por data UTC.

## Robustez

A escrita utiliza bloqueio exclusivo, uma linha completa por evento e `fflush`. A leitura aceita simultaneamente o arquivo JSON legado e as três partições necessárias à janela de 25 horas. Identificadores diferidos são deduplicados durante a leitura.

## Retenção

A limpeza de partições antigas ocorre no consumidor diferido, uma vez por processo, e não no caminho da requisição interativa. A telemetria agregada por rota continua sendo a fonte para avaliação operacional.

## Conformidade

Os eventos mantêm apenas rota, contadores técnicos, duração, módulos, sucesso e identificador técnico diferido. Conteúdo clínico, nomes, CPF, parâmetros SQL e textos livres não integram o formato.
