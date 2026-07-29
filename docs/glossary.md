# Glossário

**Ação protegida**  
Operação mutável identificada por contrato exato e submetida à autorização central.

**Consultório**  
Unidade de isolamento multitenant. Dados operacionais pertencem a exatamente um consultório, salvo recursos explicitamente globais.

**Desenvolvedor**  
Perfil global privilegiado, sujeito a MFA obrigatório e reautenticação para elevação.

**Guardião**  
Conjunto de verificações responsáveis por integridade, isolamento e detecção de condições anormais.

**Ledger de ações**  
Registro transacional que comprova uma mutação protegida e compartilha o mesmo commit da alteração.

**Maestro**  
Processo supervisionado que executa tarefas secundárias, filas duráveis, telemetria e verificações fora do caminho crítico HTTP.

**Seq**  
Identificador interno não nulo e único, gerado nativamente pelo banco.

**Baseline**  
Estado integral reconhecido como origem canônica de uma publicação.

**Fail-closed**  
Comportamento que nega a operação quando autorização, integridade ou dependência crítica não podem ser comprovadas.

**Geração de sessão**  
Valor canônico usado para invalidar sessões, contextos e credenciais anteriores de um usuário.

**Janela estrutural**  
Escopo excepcional e controlado no qual alterações de schema podem ser autorizadas.

**Prova de mutação**  
Evidência persistida de que uma ação autorizada produziu determinada alteração.
