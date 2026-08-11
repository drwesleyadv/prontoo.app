<?php
declare(strict_types=1);

namespace Prontoo\Application\Financial;

final class FinancialDataResult
{
    private int $cursor = 0;

    public function __construct(
        private readonly array $rows,
        private readonly int $affectedRows,
    ) {
    }

    public function fetch(): array|false
    {
        if (!isset($this->rows[$this->cursor])) {
            return false;
        }
        return $this->rows[$this->cursor++];
    }

    public function fetchAll(): array
    {
        if ($this->cursor === 0) {
            $this->cursor = count($this->rows);
            return $this->rows;
        }
        $rows = array_slice($this->rows, $this->cursor);
        $this->cursor = count($this->rows);
        return $rows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = $this->fetch();
        if ($row === false) {
            return false;
        }
        $values = array_values($row);
        return $values[$column] ?? false;
    }

    public function rowCount(): int
    {
        return $this->affectedRows;
    }

    public function closeCursor(): bool
    {
        $this->cursor = count($this->rows);
        return true;
    }
}
