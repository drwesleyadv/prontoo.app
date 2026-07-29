# Documentos

## Responsabilidades

- gerar documentos a partir de modelos;
- relacionar documentos a paciente e consultório;
- produzir PDFs;
- armazenar arquivos persistentes;
- controlar acesso e auditoria.

## Invariantes

- documento pertence a um consultório;
- conteúdo clínico não é exposto por URL previsível;
- nomes físicos não constituem autorização;
- geração e persistência devem terminar em estado consistente;
- arquivos legados podem ser migrados sem perder referência;
- exclusões devem seguir política explícita e auditável.

## Armazenamento

PDFs ficam em `ssd/pdfs` e imagens em `ssd/img`. O código publicado não deve conter documentos reais.
