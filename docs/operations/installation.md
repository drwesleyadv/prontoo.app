# Instalação

## Pré-requisitos

- PHP 8.4;
- MySQL 8.0.30;
- `pdo_mysql`;
- HTTPS;
- host configurado;
- banco completamente vazio;
- diretório `ssd` gravável e fora de exposição indevida.

## Procedimento

1. publicar a baseline validada;
2. configurar segredos fora do repositório;
3. criar banco vazio e usuário com privilégios mínimos;
4. habilitar a janela de comissionamento aprovada;
5. executar a rota de instalação;
6. confirmar criação do `install.lock`;
7. validar login do Desenvolvedor e cadastro de MFA;
8. executar verificações pós-instalação;
9. confirmar bloqueio automático do instalador.

## Restrições

A instalação não pode apagar banco existente. O runtime comum não pode executar DDL. Falha parcial deve ser tratada como instalação não concluída e investigada antes de nova tentativa.
