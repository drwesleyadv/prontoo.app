# Domínio de documentos

Documentos combinam modelos, conteúdo do paciente, geração e apresentação. A arquitetura separa identificação/tipo de documento, regras de template e renderização concreta.

## Fluxo

Runtime recebe a intenção. Application coordena emissão quando a operação tem significado de caso de uso. Domain contém políticas de identificador, tipo e template. Presentation/Infrastructure cuidam de HTML, PDF e mecanismos concretos.

## Segurança

Um documento pertence ao contexto do consultório e do paciente aplicável. Caminhos de arquivo não devem ser aceitos como autorização. PDFs persistentes ficam em `ssd/pdfs`, fora da árvore de código.

## Integridade

Geração deve ser determinística quanto aos dados fornecidos e não modificar o domínio durante uma leitura. Se emissão produzir efeitos auditáveis, eles precisam ser explícitos no caso de uso.
