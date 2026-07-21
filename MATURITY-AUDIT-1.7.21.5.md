# Auditoria integral de maturação — Prontoo 1.7.21.5

## Cobertura

- 147 arquivos versionados auditados, totalizando 4.177.049 bytes na linha de base 1.7.21.4.
- PHP, JavaScript, CSS, JSON, SQL, workflows, documentação e arquivos de configuração incluídos.
- Verificação de sintaxe PHP, contratos JSON, arquitetura, instalador local, congelamento de DDL e instalação real em MySQL 8.

## Achados confirmados e tratados

1. **Rotina histórica no caminho quente:** `prontoo_release_cleanup_1_7_14_8()` era executada em toda requisição, realizava verificações de arquivos, `glob()` e manutenção de marcadores de uma publicação antiga. Foi removida integralmente.
2. **Instalador carregado em páginas normais:** `Install/Installer.php` fazia parte do bootstrap comum. Agora é carregado exclusivamente pelo `install.php`, depois da validação de localhost.
3. **Lista de módulos redundante:** módulos já pertencentes ao núcleo apareciam novamente na lista de runtime completo. As duplicações foram suprimidas.
4. **I/O repetido no cache JSON:** a resolução das pastas raiz e de categoria repetia `is_dir`, criação e proteção a cada operação. Os caminhos passam a ser memoizados por requisição.
5. **N+1 na linha do tempo:** nomes de usuários, consultório e membros da equipe eram consultados repetidamente entre requisições. Esses lookups ganharam cache JSON curto, por escopo e com tags de invalidação.
6. **Ativo CSS monolítico:** foi identificado como oportunidade futura, mas permaneceu byte a byte inalterado por não haver benefício mensurável de runtime nesta rodada.

## Achados mantidos por serem defesas, não legado executável

- listas `removed_files` e verificações de ausência de classes/tabelas antigas;
- referências históricas em changelogs e documentos arquiteturais;
- rejeições explícitas de `pi_sequence`, `CleanInstallReset` e DDL fora da janela privada;
- compatibilidade de leitura necessária para registros persistidos no schema r7.

## Política de desempenho

- cache JSON somente em leituras GET;
- TTL curto ou médio conforme estabilidade;
- chaves incluem consultório e identificador;
- invalidação vinculada às tabelas de origem;
- permissões críticas, gravações, agenda em tempo real e saldos não recebem cache longo;
- modularização prioriza redução do bootstrap, não fragmentação artificial de arquivos.
