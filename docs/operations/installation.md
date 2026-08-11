# Instalação

A instalação do Prontoo parte de banco vazio e schema canônico. Ela é uma operação administrativa excepcional, não um modo normal de inicialização do runtime.

## Requisitos

PHP deve ser da família 8.4 e MySQL deve ser 8.0.30 ou superior. O host público precisa usar HTTPS e o domínio canônico esperado. Diretórios persistentes em `ssd/` precisam ser graváveis pelo runtime conforme a política de hospedagem.

## Banco

O instalador cria o schema limpo sob um lock explícito de mutação. Não execute instalação contra banco que contenha dados de produção. O contrato `requires_empty_database` existe para tornar essa pré-condição inequívoca.

## Janela de acesso

O endpoint de instalação só deve ficar disponível dentro de janela administrativa declarada no release metadata; fora dela, a política é 404/fechamento automático. Não prolongue janela apenas para contornar um problema de configuração.

## Pós-instalação

Valide schema contract, login, criação/vínculo inicial e estado do Maestro antes de considerar o ambiente pronto.
