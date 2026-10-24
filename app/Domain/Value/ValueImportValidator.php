<?php

namespace App\Domain\Value;

final class ValueImportValidator
{
    /** @param list<array<string, mixed>> $rows
     *  @return array{valid: bool, checksum: string, errors: list<array{row: int, code: string}>}
     */
    public function validate(array $rows): array
    {
        $errors = [];
        $keys = [];

        foreach ($rows as $index => $row) {
            foreach (['external_key', 'category', 'name', 'value'] as $field) {
                if (! array_key_exists($field, $row)) {
                    $errors[] = ['row' => $index, 'code' => "missing_{$field}"];
                }
            }
            if (isset($row['value']) && (! is_int($row['value']) || $row['value'] < 0)) {
                $errors[] = ['row' => $index, 'code' => 'invalid_value'];
            }
            if (isset($row['category']) && ! in_array($row['category'], ['skin', 'boat', 'fishing_rod'], true)) {
                $errors[] = ['row' => $index, 'code' => 'invalid_category'];
            }
            if (isset($row['external_key'])) {
                if (isset($keys[$row['external_key']])) {
                    $errors[] = ['row' => $index, 'code' => 'duplicate_external_key'];
                }
                $keys[$row['external_key']] = true;
            }
        }

        $normalized = $rows;
        usort($normalized, static fn (array $a, array $b): int => ($a['external_key'] ?? '') <=> ($b['external_key'] ?? ''));

        return [
            'valid' => $errors === [],
            'checksum' => hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR)),
            'errors' => $errors,
        ];
    }
}
