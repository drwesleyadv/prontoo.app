# Arquivo histórico — Conformidade PHP 8.4

A versão `1.8.6.1` tornou explícita uma decisão operacional que continua vigente: o Prontoo suporta a família PHP 8.4 de forma exata, e não uma faixa aberta “8.4 ou superior”.

A auditoria revisou a base para APIs e depreciações da família escolhida e deixou um contrato permanente de lint/conformidade. O objetivo não era congelar PHP para sempre, mas impedir upgrade acidental sem validação sistêmica.

Hoje `version.json`, o bootstrap e a CI repetem a mesma política. Uma migração futura para outra família deve ser tratada como mudança consciente, com auditoria e testes próprios.
