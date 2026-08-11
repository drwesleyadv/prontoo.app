# Backup e restauração

O estado do Prontoo é composto por MySQL e armazenamento persistente `ssd/`. Um backup completo precisa considerar os dois.

## O que preservar

Banco relacional, PDFs, imagens, arquivos persistentes necessários, telemetria quando exigida operacionalmente e spools/estado do Maestro conforme a política de recuperação. Código não precisa ser incluído no backup de dados porque é reconstituível pelo repositório.

## Consistência

Idealmente, banco e filesystem devem representar um ponto temporal compatível. Em restaurações críticas, pause ou coordene writes para evitar que o dump faça referência a arquivo ainda não copiado ou vice-versa.

## Restauração

Restaure em ambiente controlado, valide versão compatível, permissões de `ssd/`, schema contract e integridade antes de abrir tráfego. Não use o instalador sobre o banco restaurado.

## Teste de backup

Backup não testado é apenas uma hipótese. Execute restaurações periódicas fora de produção e registre tempo, lacunas e procedimentos manuais encontrados.
