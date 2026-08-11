# Domínio de pacientes

Paciente é uma entidade central do Prontoo e aparece em cadastro, ficha, recepção, agenda, documentos e financeiro. Por isso, o domínio evita uma “classe paciente” monolítica e usa casos de uso focados para leitura e comando.

## Leituras

Services de leitura entregam dados necessários às telas sem expor SQL. Histórico de recepção possui leitura própria porque custo e forma de consulta são diferentes do cadastro básico. Budgets MySQL protegem caminhos críticos.

## Comandos

Alteração de contato e comandos de ficha usam ports específicos. A escrita precisa respeitar tenant e identidade do paciente. Runtime coleta intenção; Application coordena; Infrastructure persiste.

## Isolamento

Um identificador de paciente não é suficiente para acesso. O consultório ativo faz parte do contexto e deve ser validado em toda leitura/escrita.

## Evolução

Quando uma nova função da ficha for criada, prefira ampliar um caso de uso coerente ou criar um novo recorte sem devolver persistência genérica à página.
