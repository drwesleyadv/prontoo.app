# C4 — Containers lógicos

O Prontoo é implantado como uma aplicação PHP única, mas pode ser entendido por quatro containers lógicos.

## Aplicação web PHP

Recebe requisições, autentica usuários, coordena casos de uso e renderiza respostas. Internamente contém Runtime, Application, Domain, Infrastructure e Presentation.

## MySQL

Armazena estado relacional, incluindo identidades, vínculos de consultório, pacientes, agenda, financeiro, tarefas e estruturas de integridade. O schema canônico é de instalação limpa e não recebe DDL arbitrário durante requests normais.

## Armazenamento persistente `ssd/`

Guarda arquivos, imagens, PDFs, telemetria e filas/estados operacionais que não devem depender do diretório efêmero do código. Backups precisam considerar banco e `ssd/` como partes complementares do estado.

## Maestro

É executado server-side em ciclos supervisionados. Consome trabalho diferido, aplica retry/backoff, preserva escopo de consultório e registra estado operacional. Não é um segundo produto; é um modo de execução da mesma base de código.

## CI/CD

GitHub Actions não é container de produção, mas é uma fronteira de governança: executa contratos de arquitetura, documentação, MySQL, segurança e runtime antes do merge.
