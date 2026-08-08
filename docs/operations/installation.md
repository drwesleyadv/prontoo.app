# Instalação

## Pré-requisitos

- PHP **8.4.x** no web e CLI;
- MySQL 8.0.30 ou superior;
- `pdo_mysql`;
- HTTPS e host canônico;
- banco completamente vazio;
- diretório `ssd` gravável e fora de exposição indevida;
- segredos fora do repositório.

## Modelo de comissionamento

A instalação limpa é a única origem de schema. `/install.php` só pode ficar publicamente acessível durante a janela temporária explicitamente versionada no contrato de release, em HTTPS/host canônico e estado fresco. Encerrada a janela, a política é 404 automático sem intervenção manual.

## Procedimento

1. publicar o commit validado e conferir manifestos;
2. configurar segredos externos;
3. criar banco vazio e usuário com privilégios mínimos;
4. confirmar a janela de comissionamento aprovada no contrato vigente;
5. executar `/install.php`;
6. confirmar criação do `install.lock` e estado de instalação;
7. validar login do Desenvolvedor e cadastro obrigatório de MFA;
8. executar verificações pós-instalação e smoke tests;
9. confirmar bloqueio/404 automático do instalador após a janela.

## Schema

O runtime comum não executa DDL. Mutação estrutural é restrita ao instalador na janela autorizada ou ao contexto CLI de GitHub Actions reconhecido pelos marcadores do contrato. Reset destrutivo foi removido do runtime.

## Restrições

A instalação não pode apagar banco existente. Banco com tabelas preexistentes não é tratado como instalação limpa. Falha parcial deve ser investigada; não contorne os locks nem transforme estado indeterminado em sucesso.
