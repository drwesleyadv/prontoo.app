# Arquivo histórico — Fase 5: comando financeiro crítico

O financeiro foi usado como prova de que uma fronteira de Application deveria sobreviver a um caso com maior custo de erro. Recebimentos, movimentos e consolidação exigem atomicidade, escopo de tenant e invariantes numéricas que não cabem em um controller de página.

O resultado final são services e ports financeiros caracterizados, adapters de persistência e budgets reais de banco. A fase não é plano de trabalho futuro; ela registra por que o domínio financeiro ajudou a fechar a arquitetura.
