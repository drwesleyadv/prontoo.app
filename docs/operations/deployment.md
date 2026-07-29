# Implantação

## Antes do deploy

- branch correto e commit identificado;
- CI aprovado;
- versão e manifestos consistentes;
- backup verificado;
- plano de rollback definido;
- mudanças de banco explicitamente avaliadas;
- janela operacional comunicada quando necessária.

## Procedimento

1. obter artefato do commit aprovado;
2. validar hashes;
3. sincronizar código sem substituir `ssd`;
4. aplicar configuração externa;
5. confirmar permissões;
6. executar smoke tests;
7. observar erros, latência e filas;
8. registrar versão implantada.

## Smoke tests

- login e MFA;
- abertura da Agenda;
- leitura de Pacientes;
- operação protegida sem mutação destrutiva;
- execução do Maestro;
- acesso a documento autorizado;
- bloqueio de rota administrativa sem capacidade.

## Pós-deploy

Compare métricas com a baseline anterior e mantenha rollback disponível até estabilidade confirmada.
