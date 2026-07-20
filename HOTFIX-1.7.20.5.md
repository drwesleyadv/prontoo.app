# Hotfix de runtime arquitetural — candidato 1.7.20.5

## Incidente

O servidor retornou `architecture_transitional_files_above_ceiling:80` durante o carregamento da rota `admin_painel`.

## Causa

O verificador de maturação percorria todos os arquivos PHP existentes no sistema de arquivos. Em produção existe `app/config.php`, arquivo ambiental deliberadamente ignorado pelo Git e ausente no checkout do CI. Por isso:

- CI: 79 arquivos transitórios;
- produção: 80 arquivos transitórios.

Além disso, o teto de migração — uma meta de evolução do repositório — era tratado como invariante fatal do runtime.

## Correção

- `app/config.php` passa a ser excluído explicitamente do inventário arquitetural do produto;
- metas `native_files_min` e `transitional_files_max` permanecem obrigatórias no CI;
- no runtime, essas duas métricas são registradas como diagnóstico, mas não derrubam a aplicação;
- violações reais de versão, camada, dependência, contrato de ação, arquivo essencial e legado continuam fatais.

## Validação

- lint PHP dos dois arquivos alterados;
- simulação com `app/config.php` presente confirmou que ele não entra na contagem;
- arquivos reais do produto permanecem no inventário;
- o teto arquitetural não foi elevado de 79 para 80.

## Publicação

O branch prepara o código do candidato `1.7.20.5`. A sincronização final da versão e o merge dependem de autorização expressa específica para esta publicação.
