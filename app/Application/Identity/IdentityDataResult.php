<?php
declare(strict_types=1);

namespace Prontoo\Application\Identity;

use PDO;

final class IdentityDataResult
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

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT): array
    {
        if ($this->cursor === 0) {
            $this->cursor = count($this->rows);
            $rows = $this->rows;
        } else {
            $rows = array_slice($this->rows, $this->cursor);
            $this->cursor = count($this->rows);
        }
        if ($mode === PDO::FETCH_COLUMN) {
            return array_map(
                static fn(array $row): mixed => array_values($row)[0] ?? false,
                $rows,
            );
        }
        if ($mode === PDO::FETCH_KEY_PAIR) {
            $pairs = [];
            foreach ($rows as $row) {
                $values = array_values($row);
                if (array_key_exists(0, $values) && array_key_exists(1, $values)) {
                    $pairs[$values[0]] = $values[1];
                }
            }
            return $pairs;
        }
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
