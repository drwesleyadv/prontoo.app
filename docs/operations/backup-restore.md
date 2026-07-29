# Backup e restauração

## Escopo do backup

- banco MySQL;
- `ssd/pdfs`;
- `ssd/img`;
- filas e estados persistentes necessários;
- configuração externa cifrada;
- identificação da versão do código.

## Requisitos

- criptografia em repouso e transporte;
- acesso mínimo;
- retenção definida;
- teste periódico de restauração;
- separação entre produção e cópias de teste;
- ausência de dados reais em ambientes não autorizados.

## Restauração

1. selecionar ponto consistente;
2. validar integridade do arquivo;
3. restaurar em ambiente isolado;
4. verificar schema e versão;
5. reconciliar arquivos e referências;
6. executar verificações de integridade;
7. liberar apenas após validação.

Backup não testado não é estratégia de recuperação.
