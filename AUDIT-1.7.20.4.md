# Auditoria de maturação — Prontoo 1.7.20.4

## Escopo

Auditoria da arquitetura em camadas publicada na versão 1.7.20.3, com foco em autorização, credenciais, persistência das provas, múltiplos cargos, cobertura estática e honestidade das métricas arquiteturais.

## Achados corrigidos

### 1. Credencial global dependente do contexto

A capacidade `admin:*` aceitava o escopo global e a identidade do usuário sem revalidar, no momento da ação, o atributo `is_global_admin` no banco. A versão auditada exige usuário ativo, contexto administrativo e confirmação viva de administrador global.

### 2. Credencial clínica e múltiplos cargos

A resolução de capacidade considerava prioritariamente o cargo principal do contexto. A versão auditada consulta os vínculos ativos do usuário no consultório atual e admite capacidades de qualquer cargo ativo, mantendo falha fechada quando usuário, consultório ou vínculo deixam de ser válidos.

### 3. Ação permitida sem prova persistida

A falha ao gravar `pi_action_proofs` era registrada no log, mas não impedia a execução de uma ação autorizada. A versão auditada exige a prova antes do handler: se a autorização foi concedida e a prova não puder ser persistida, a ação é bloqueada com HTTP 503 e nenhuma alteração é executada.

Ações recusadas continuam retornando HTTP 403 mesmo quando a prova negativa não puder ser gravada.

### 4. Contratos de ação verificados globalmente

A auditoria estática comparava o conjunto global de tokens e contratos. Um token homônimo em outro módulo poderia mascarar uma ação sem contrato no arquivo correto. A política v2 confronta cada ação com os contratos declarados para o mesmo arquivo-fonte.

### 5. Métrica arquitetural superestimada

A cobertura de 100% significava que todos os arquivos PHP estavam classificados em uma camada, não que todos já fossem componentes nativos e namespaced. A versão auditada separa:

- cobertura classificatória obrigatória de 100%;
- arquivos nativos em camadas, com piso de 11;
- arquivos procedurais transitórios, com teto de 79.

Esses limites impedem regressão e tornam explícita a dívida técnica remanescente.

## Autotestes adicionados

- administrador global removido ou inativo é recusado;
- vínculo clínico removido ou inativo é recusado;
- capacidade de cargo secundário ativo é reconhecida;
- ação permitida sem prova persistente é bloqueada;
- ação recusada permanece recusada sem prova;
- requisição de leitura não tenta persistir prova.

## Compatibilidade

Não há alteração de banco de dados, schema, interface ou ativos. As fronteiras globais de compatibilidade permanecem delegadoras e a fonte de verdade continua sendo `Prontoo\Runtime\LayeredKernel`.
