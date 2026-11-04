<?php

namespace App\Services;

class LegacyDataParser
{
    /**
     * Parse all rows from INSERT statements targeting $tableName in SQL content.
     *
     * @return array<int, array<int, mixed>>
     */
    public function parseInsertRows(string $sql, string $tableName): array
    {
        $escapedTableName = preg_quote($tableName, '/');
        // Cari baris/blok yang dimulai dengan INSERT INTO `tableName` VALUES
        $pattern = "/INSERT\s+INTO\s+[`\"]?{$escapedTableName}[`\"]?\s+VALUES\s*(.+?);/is";

        if (!preg_match_all($pattern, $sql, $matches)) {
            return [];
        }

        $allRows = [];
        foreach ($matches[1] as $valuesBlock) {
            $parsed = $this->tokenizeValuesBlock($valuesBlock);
            foreach ($parsed as $row) {
                $allRows[] = $row;
            }
        }

        return $allRows;
    }

    /**
     * Tokenize "(row1), (row2), ..." into an array of parsed rows.
     *
     * @return array<int, array<int, mixed>>
     */
    public function tokenizeValuesBlock(string $values): array
    {
        $rows = [];
        $len = strlen($values);
        $i = 0;

        while ($i < $len) {
            // Cari pembuka tuple '('
            while ($i < $len && $values[$i] !== '(') {
                $i++;
            }
            if ($i >= $len) {
                break;
            }
            $i++; // Lewati '('

            $currentRow = [];
            $inString = false;
            $token = '';
            $isString = false;

            while ($i < $len) {
                $char = $values[$i];

                if ($inString) {
                    if ($char === '\\') {
                        // Handle escape sequence
                        if ($i + 1 < $len) {
                            $nextChar = $values[$i + 1];
                            switch ($nextChar) {
                                case "'":
                                    $token .= "'";
                                    break;
                                case '"':
                                    $token .= '"';
                                    break;
                                case '\\':
                                    $token .= '\\';
                                    break;
                                case 'n':
                                    $token .= "\n";
                                    break;
                                case 'r':
                                    $token .= "\r";
                                    break;
                                case 't':
                                    $token .= "\t";
                                    break;
                                default:
                                    $token .= $nextChar;
                                    break;
                            }
                            $i += 2;
                            continue;
                        }
                    } elseif ($char === "'") {
                        // Cek apakah escaped single quote ''
                        if ($i + 1 < $len && $values[$i + 1] === "'") {
                            $token .= "'";
                            $i += 2;
                            continue;
                        }
                        // Selesai string
                        $inString = false;
                        $i++;
                        continue;
                    }
                    $token .= $char;
                    $i++;
                } else {
                    if ($char === "'") {
                        $inString = true;
                        $isString = true;
                        $token = '';
                        $i++;
                        continue;
                    } elseif ($char === ',' || $char === ')') {
                        // Selesai satu kolom
                        $val = $this->castToken($token, $isString);
                        $currentRow[] = $val;
                        $token = '';
                        $isString = false;

                        if ($char === ')') {
                            $rows[] = $currentRow;
                            $i++;
                            break;
                        }
                        $i++;
                        continue;
                    } elseif ($char === ' ' || $char === "\n" || $char === "\r" || $char === "\t") {
                        if (strlen($token) > 0) {
                            $token .= $char;
                        }
                        $i++;
                        continue;
                    } else {
                        $token .= $char;
                        $i++;
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * Cast raw string token to native PHP type.
     */
    protected function castToken(string $token, bool $isString): mixed
    {
        if ($isString) {
            return $token;
        }

        $trimmed = trim($token);
        if (strtoupper($trimmed) === 'NULL' || $trimmed === '') {
            return null;
        }

        if (is_numeric($trimmed)) {
            return str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;
        }

        return $trimmed;
    }
}
