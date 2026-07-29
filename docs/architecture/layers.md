# Camadas

## Core

Contém invariantes, políticas canônicas, integridade, tempo, escopo, workflows e decisões que não dependem de detalhes externos.

Pode depender apenas de linguagem, tipos próprios e conceitos internos estáveis.

## Domain

Contém conceitos e regras de negócio: agenda, pessoas, financeiro, documentos, tarefas, permissões e Maestro.

Não conhece HTTP, sessão, HTML, PDO ou arquivos de configuração.

## Application

Coordena casos de uso e define portas. Recebe comandos já interpretados pela apresentação e devolve resultados de aplicação.

Não implementa detalhes de persistência.

## Infrastructure

Implementa acesso a banco, credenciais, armazenamento, filas e outros adaptadores externos. Depende das portas definidas para dentro.

## Presentation

Interpreta requisições, valida forma, converte entradas, chama casos de uso e renderiza respostas. Não deve conter regra de negócio ou SQL.

## Composition e Runtime

Monta dependências, inicializa serviços e conecta camadas. É a única região autorizada a conhecer simultaneamente componentes de todas as camadas.

## Diretórios transitórios

Arquivos em `Pages`, `Admin`, `Auth` e `Ui` podem exercer mais de uma responsabilidade por compatibilidade. Toda alteração deve:

- impedir novo SQL na apresentação;
- impedir nova regra de negócio na borda;
- delegar autorização ao ponto central;
- reduzir ou manter a complexidade existente.
