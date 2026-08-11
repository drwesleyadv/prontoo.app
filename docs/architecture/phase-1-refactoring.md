# Arquivo histórico — Fase 1: separação inicial de responsabilidades

Este documento não descreve trabalho pendente. Ele registra a primeira ideia que permaneceu na arquitetura final: separar decisão de entrada, regra de negócio, persistência e apresentação.

A fase inicial mostrou que arquivos grandes eram menos problemáticos pelo tamanho do que pela mistura de razões para mudar. A consequência durável foi o mapa de camadas hoje aplicado por contratos.

No estado atual, qualquer nova refatoração deve ser motivada por caso de uso, falha de invariante ou redução mensurável de dívida. O plano desta fase não deve ser repetido como campanha autônoma.
