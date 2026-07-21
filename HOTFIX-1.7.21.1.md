# Hotfix 1.7.21.1 — instalação em banco vazio

O parser utilizado pelo instalador ainda exigia 75 tabelas. O schema r7 possui 62. O hotfix centraliza o total canônico e faz o CI executar `install_fresh_schema()` em MySQL 8 com banco inicialmente vazio. Não há alteração no schema r7, dados operacionais, interface ou ativos.
