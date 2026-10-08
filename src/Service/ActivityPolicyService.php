<?php

namespace Wexample\SymfonyActivity\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Wexample\SymfonyActivity\Enum\ActivityExpiry;

/**
 * The categories the application declared, and what it asked of each. A
 * category it did not declare, or declared disabled, records nothing: the
 * package keeps only what it is told to.
 */
class ActivityPolicyService
{
    /**
     * @param array<string, array{enabled: bool, retention_days: ?int, merge_within: ?string, on_expiry: string}> $categories
     */
    public function __construct(
        #[Autowire(param: 'wexample_symfony_activity.categories')]
        private readonly array $categories = [],
    ) {
    }

    public function isEnabled(string $category): bool
    {
        return $this->categories[$category]['enabled'] ?? false;
    }

    /**
     * Days an entry of $category is kept, or null to keep it for good.
     */
    public function getRetentionDays(string $category): ?int
    {
        return $this->categories[$category]['retention_days'] ?? null;
    }

    /**
     * The interval within which a repeat of $category is counted rather than
     * written, or null when every entry is written.
     */
    public function getMergeWithin(string $category): ?string
    {
        return $this->categories[$category]['merge_within'] ?? null;
    }

    public function getExpiry(string $category): ActivityExpiry
    {
        return ActivityExpiry::from($this->categories[$category]['on_expiry'] ?? ActivityExpiry::DELETE->value);
    }

    /**
     * The categories with a retention, which the purge goes through — a
     * disabled one included: what it recorded before still expires.
     *
     * @return list<string>
     */
    public function getExpiringCategories(): array
    {
        return array_keys(array_filter(
            $this->categories,
            static fn (array $category) => $category['retention_days'] !== null
        ));
    }
}
