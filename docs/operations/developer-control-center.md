# Central do Desenvolvedor

## Princípio

O ambiente global do Desenvolvedor é um plano de controle operacional. A página inicial responde primeiro se existe algo que exige intervenção; telemetria detalhada fica sob demanda.

## Navegação primária

1. **Visão geral** — estado da plataforma e fila “Precisa de você”.
2. **Consultórios** — ciclo de vida, onboarding, assinatura e operação dos consultórios.
3. **Confiabilidade** — erros, segurança, isolamento, integridade, diagnóstico e recuperação.
4. **Observabilidade** — requisições, latência, rotas e séries de desempenho.
5. **Administração** — usuários, comunicação, manutenção, configurações e auditoria.

## Autópsia funcional

| Recurso | Destino | Decisão |
|---|---|---|
| `admin_painel` | Visão geral | Manter e reescrever como fila de decisão |
| `admin_clinics` | Consultórios | Manter |
| `admin_onboarding` | Consultórios | Incorporar como ferramenta secundária |
| `admin_operations` | Consultórios | Incorporar como ferramenta secundária |
| `admin_payment_proof` | Consultórios | Rebaixar a fluxo contextual |
| `admin_health` | Confiabilidade | Manter e redefinir |
| `admin_errors` | Confiabilidade | Incorporar |
| `admin_security` | Confiabilidade | Incorporar |
| `admin_integrity` | Confiabilidade | Incorporar |
| `admin_diagnostics` | Confiabilidade | Rebaixar a diagnóstico sob demanda |
| `admin_deleted` | Confiabilidade | Rebaixar a recuperação extraordinária |
| `admin_performance` | Observabilidade | Manter e renomear |
| `admin_people` / `admin_users` | Administração | Incorporar; `admin_users` permanece alias de compatibilidade |
| `admin_alerts` | Administração | Incorporar como comunicação técnica |
| `admin_global_notices` | Administração | Incorporar como comunicação institucional |
| `admin_maintenance` | Administração | Rebaixar a ferramenta extraordinária |
| `admin_settings` | Administração | Incorporar |
| `admin_audit` | Administração | Incorporar |
| `admin_stats` | — | Remover; era apenas redirecionamento para o painel |

## Regra de UX

Informação só ganha destaque quando altera uma decisão. Estados normais são resumidos; detalhes técnicos são progressivamente revelados em Confiabilidade ou Observabilidade.
