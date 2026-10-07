<?php

namespace Wexample\SymfonyActivity\Helper;

/**
 * Reads the diffs auditor stores into what an ActivityEntry holds: one line
 * per field, its values worded — a related entity by its label, a structure
 * as JSON —, and the label of what was created or removed.
 */
final class ActivityChangeHelper
{
    /**
     * @return array<string, scalar|null>
     */
    public static function context(array $diffs): array
    {
        // An insert describes its entity under `@source`, a remove is nothing
        // but that description.
        $label = $diffs['@source']['label'] ?? $diffs['label'] ?? null;

        return $label === null ? [] : ['label' => (string) $label];
    }

    /**
     * @param string $type auditor's own: insert, update, remove, associate, dissociate
     *
     * @return list<array{field: string, old: ?string, new: ?string}>
     */
    public static function changes(string $type, array $diffs): array
    {
        return match ($type) {
            'insert', 'update' => self::fields($diffs),
            'associate', 'dissociate' => [[
                'field' => (string) ($diffs['source']['field'] ?? ''),
                'old' => $type === 'dissociate' ? self::word($diffs['target'] ?? null) : null,
                'new' => $type === 'associate' ? self::word($diffs['target'] ?? null) : null,
            ]],
            default => [],
        };
    }

    /**
     * @return list<array{field: string, old: ?string, new: ?string}>
     */
    private static function fields(array $diffs, string $prefix = ''): array
    {
        $lines = [];

        foreach ($diffs as $field => $change) {
            if ($field === '@source' || ! is_array($change)) {
                continue;
            }

            // A JSON field is diffed key by key, each key a field of its own.
            if (array_diff(array_keys($change), ['old', 'new']) !== []) {
                array_push($lines, ...self::fields($change, $prefix.$field.'.'));

                continue;
            }

            $lines[] = [
                'field' => $prefix.$field,
                'old' => self::word($change['old'] ?? null),
                'new' => self::word($change['new'] ?? null),
            ];
        }

        return $lines;
    }

    private static function word(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            isset($value['label']) => (string) $value['label'],
            default => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        };
    }
}
