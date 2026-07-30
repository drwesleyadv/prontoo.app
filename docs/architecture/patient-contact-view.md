# View de contato do paciente

## Objetivo

A Fase 6 remove duas cópias idênticas do formulário de contato da fachada `Patients.php` e estabelece uma única implementação em apresentação.

## Fronteira

A view recebe o paciente, o consultório e callbacks dos componentes visuais existentes. Ela não acessa HTTP, sessão, SQL ou persistência. O runtime faz a composição e a fachada mantém apenas a decisão de permissão.

## Compatibilidade

A estrutura HTML, classes, nomes dos campos, atributos obrigatórios, CSRF, endereço e ação de envio são preservados por snapshot determinístico.

## Manutenção

Mudanças futuras no formulário passam a ocorrer em um único arquivo. A fachada continua compatível durante a migração e não recebe nova lógica de apresentação.
