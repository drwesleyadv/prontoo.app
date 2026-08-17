# Central do Desenvolvedor

## Princípio

O ambiente global do Desenvolvedor é um plano de controle operacional. A interface prioriza decisões e exceções; informação normal, repetida ou de baixa frequência permanece fora do primeiro plano.

## Hierarquia

1. **Visão geral** — responde somente ao estado atual e ao que precisa de intervenção.
2. **Consultórios** — ciclo de vida e gestão, com o mesmo padrão de diretório de Pacientes: resumo, busca rápida, filtros de Todos, Atenção, Onboarding, Somente leitura e Vencendo, lista compacta e ação principal por registro.
3. **Confiabilidade** — exceções técnicas e acesso direto a Erros, Segurança e Integridade.
4. **Observabilidade** — requisições, latência, falhas, gráficos e comportamento das rotas.
5. **Administração** — hub de acesso, comunicação e auditoria; configuração e manutenção ficam progressivamente reveladas.

## Terceira passada de carga cognitiva

- A Visão geral contém apenas **Estado do Prontoo** e **Precisa de você**.
- Onboarding e vencimentos deixam de ser alertas globais e passam a filtros contextuais de Consultórios.
- `admin_onboarding` permanece apenas como alias de compatibilidade e redireciona ao filtro de onboarding.
- `admin_operations` vira **Indicadores do negócio**, relatório sob demanda acessível por Mais opções em Consultórios.
- Confiabilidade mostra somente exceções; Diagnóstico e Recuperação ficam em Ferramentas avançadas.
- Observabilidade tem uma única camada de quatro KPIs: Requisições, Latência média, Falhas e Rota mais lenta; depois gráficos e rotas.
- Administração não replica seus filhos na navegação da PageHead. O hub mostra Usuários, Mensagens internas, Avisos aos consultórios e Auditoria; Configurações e Manutenção ficam em Configuração avançada.
- O estado técnico é calculado por um único snapshot de saúde compartilhado por Visão geral e Confiabilidade.

## Regra de UX

Uma informação só ocupa espaço permanente se alterar uma decisão frequente. Drill-down existe para explicar uma exceção, não para repetir o resumo. Ferramentas raras continuam acessíveis, mas não competem visualmente com a operação diária.

## Evidência de saúde

A saúde da plataforma não é inferida por um único semáforo. O estado global é derivado de cinco dimensões independentes: Disponibilidade, Integridade, Isolamento e segurança, Desempenho e Continuidade.

Cada sinal assume exatamente um dos estados `ok`, `attention`, `fail` ou `unknown` e carrega horário de observação e validade. Evidência expirada ou uma verificação que não pôde ser concluída vira `unknown`; ausência de medição nunca é convertida em estado saudável.

A telemetria de requisições funciona como sensor passivo. Degradações transitórias de falha ou latência só promovem alerta quando persistem em observações consecutivas, e a recuperação também precisa se estabilizar. O Maestro funciona como sensor ativo: cada ciclo executa um canário de banco, storage, invariantes de mutação e isolamento e renova a evidência de continuidade.

O histórico persistente registra somente transições de estado de plataforma, dimensão ou sinal. Leituras repetidas em `ok` não geram novos eventos. A Visão geral continua minimalista e exibe apenas o estado, a idade da evidência e as exceções que exigem intervenção.
