# Rollback

## Princípio

Rollback de código não deve destruir dados criados por uma versão mais nova. Antes de reverter, confirme compatibilidade do schema e dos contratos persistentes.

## Procedimento

1. interromper novas implantações;
2. identificar commit e versão afetados;
3. preservar logs e evidências;
4. avaliar compatibilidade de banco;
5. restaurar artefato anterior validado;
6. manter `ssd` e banco intactos, salvo plano específico;
7. limpar apenas caches regeneráveis;
8. executar smoke tests;
9. reativar tráfego;
10. registrar causa e decisão.

## Quando não reverter automaticamente

- mudança estrutural incompatível;
- corrupção de dados ainda não delimitada;
- comprometimento de segredo;
- fila antiga incompatível;
- artefato anterior sem correção de segurança indispensável.

Nesses casos, prefira correção direta ou modo de contenção.
