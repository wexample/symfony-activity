<?php

namespace Wexample\SymfonyActivity\Helper;

use InvalidArgumentException;

/**
 * Reads one entry a browser reported. What reaches the table is bounded
 * here: a stable type, a flat context of scalars, strings cut short, a
 * fingerprint that is a hash — anything else is refused, with the reason.
 */
class ActivityReportHelper
{
    public const int MAX_CONTEXT_KEYS = 30;

    public const int MAX_KEY_LENGTH = 64;

    public const int MAX_VALUE_LENGTH = 1000;

    private const string TYPE_PATTERN = '/^[a-z0-9_]+(\.[a-z0-9_-]+)*$/';

    private const string FINGERPRINT_PATTERN = '/^[a-f0-9]{8,64}$/';

    /**
     * @return array{category: string, type: string, context: array<string, scalar|null>, fingerprint: ?string}
     *
     * @throws InvalidArgumentException naming what is wrong: `shape`, `type`, `context`, `fingerprint`
     */
    public static function read(mixed $entry): array
    {
        if (! is_array($entry) || ! is_string($entry['category'] ?? null) || ! is_string($entry['type'] ?? null)) {
            throw new InvalidArgumentException('shape');
        }

        if (strlen($entry['type']) > 128 || ! preg_match(self::TYPE_PATTERN, $entry['type'])) {
            throw new InvalidArgumentException('type');
        }

        $fingerprint = $entry['fingerprint'] ?? null;
        if ($fingerprint !== null && (! is_string($fingerprint) || ! preg_match(self::FINGERPRINT_PATTERN, $fingerprint))) {
            throw new InvalidArgumentException('fingerprint');
        }

        return [
            'category' => $entry['category'],
            'type' => $entry['type'],
            'context' => self::context($entry['context'] ?? []),
            'fingerprint' => $fingerprint,
        ];
    }

    /**
     * @return array<string, scalar|null>
     */
    private static function context(mixed $context): array
    {
        if (! is_array($context) || count($context) > self::MAX_CONTEXT_KEYS) {
            throw new InvalidArgumentException('context');
        }

        $read = [];
        foreach ($context as $key => $value) {
            if (! is_string($key) || strlen($key) > self::MAX_KEY_LENGTH || ! ($value === null || is_scalar($value))) {
                throw new InvalidArgumentException('context');
            }

            $read[$key] = is_string($value) ? mb_substr($value, 0, self::MAX_VALUE_LENGTH) : $value;
        }

        return $read;
    }
}
