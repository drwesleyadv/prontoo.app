# Read model do histórico da recepção

## Objetivo

A Fase 4 consolida a leitura de interessados, eventos e autores associados ao paciente em uma única consulta orientada ao caso de uso.

## Contratos

A aplicação recebe consultório, paciente, pessoa e telefone normalizado. A infraestrutura comprova a existência do paciente ativo no mesmo consultório e filtra todos os interessados pelo `clinic_id`.

## Compatibilidade

Ordenação, campos e estrutura consumida pela apresentação permanecem iguais. A formatação do histórico continua na fachada legada, enquanto SQL e reconstrução do modelo ficam no adaptador PDO.

## Manutenção

O serviço não acessa sessão, HTTP ou banco. Novos campos devem ser incluídos primeiro na porta e caracterizados antes de alterar a apresentação.
