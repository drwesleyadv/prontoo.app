# Regra de dependências

## Regra principal

Dependências de código apontam para o centro da arquitetura. Um componente interno não conhece detalhes externos.

## Relações permitidas

| Origem | Destinos permitidos |
|---|---|
| Core | Core |
| Domain | Core e Domain |
| Application | Core, Domain e portas próprias |
| Infrastructure | Core, Domain, Application |
| Presentation | Core, Domain, Application |
| Composition | todas as camadas |

## Relações proibidas

- `Core` chamando PDO;
- `Domain` lendo `$_POST`, `$_SESSION` ou `$_SERVER`;
- `Application` renderizando HTML;
- `Presentation` executando SQL;
- adaptadores externos decidindo autorização;
- páginas criando regras alternativas às invariantes.

## Compatibilidade

Fronteiras legadas podem delegar para componentes modernos. A delegação deve ser fina e não conter lógica paralela.

## Verificação

`tools/architecture-check.php` e o workflow de arquitetura são os contratos executáveis. Alterações na política exigem ADR.
