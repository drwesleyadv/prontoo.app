<?php
declare(strict_types=1);

namespace Prontoo\Application\Operational;

use Prontoo\Domain\Documents\DocumentIdentifierPolicy;
use RuntimeException;

final class DocumentIssuanceService
{
    public function __construct(private OperationalUseCaseService $data)
    {
    }

    public function assignIdentifier(int $clinicId, int $documentId): string
    {
        $this->assertIdentity($clinicId, $documentId);
        return (string) $this->data->atomic(
            fn(): string => $this->assignWithinTransaction($clinicId, $documentId),
        );
    }

    public function confirmIssue(int $clinicId, int $documentId): string
    {
        $this->assertIdentity($clinicId, $documentId);
        return (string) $this->data->atomic(function () use ($clinicId, $documentId): string {
            $this->data->result(
                'operational.documents.01.confirm_document_issue.01',
                [$documentId, $clinicId],
            );
            return $this->assignWithinTransaction($clinicId, $documentId);
        });
    }

    private function assignWithinTransaction(int $clinicId, int $documentId): string
    {
        $document = $this->data->row(
            'operational.documents.01.document_assign_identifier.01',
            [$documentId, $clinicId],
        );
        if ($document === null) {
            throw new RuntimeException('Documento não encontrado para identificação.');
        }
        $current = DocumentIdentifierPolicy::document_identifier_display(
            $document['document_identifier'] ?? '',
        );
        if ($current !== '') {
            return $current;
        }
        $clinic = $this->data->row(
            'operational.documents.01.document_assign_identifier.02',
            [$clinicId],
        );
        if ($clinic === null) {
            throw new RuntimeException('Consultório não encontrado para identificação documental.');
        }
        $alphabet = strtoupper(mb_trim((string) ($clinic['document_code_alphabet'] ?? '')));
        if (!DocumentIdentifierPolicy::document_identifier_valid_alphabet($alphabet)) {
            $alphabet = DocumentIdentifierPolicy::document_identifier_random_alphabet();
        }
        $sequence = max(
            (int) ($clinic['document_sequence'] ?? 0),
            (int) ($this->data->scalar(
                'operational.documents.01.document_assign_identifier.03',
                [$clinicId],
            ) ?? 0),
        ) + 1;
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $identifier = DocumentIdentifierPolicy::document_identifier_encode(
                $sequence,
                $alphabet,
            );
            $exists = (int) ($this->data->scalar(
                'operational.documents.01.document_assign_identifier.04',
                [$clinicId, $identifier, $documentId],
            ) ?? 0);
            if ($exists === 0) {
                $this->data->result(
                    'operational.documents.01.document_assign_identifier.05',
                    [$alphabet, $sequence, $clinicId],
                );
                $this->data->result(
                    'operational.documents.01.document_assign_identifier.06',
                    [$sequence, $identifier, $documentId, $clinicId],
                );
                return $identifier;
            }
            $sequence++;
        }
        throw new RuntimeException('Não foi possível gerar identificador documental único.');
    }

    private function assertIdentity(int $clinicId, int $documentId): void
    {
        if ($clinicId <= 0 || $documentId <= 0) {
            throw new RuntimeException('Documento inválido para identificação.');
        }
    }
}
