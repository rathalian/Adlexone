<?php
declare(strict_types=1);

namespace Adlexone\Data;

/**
 * A SQL statement and the values that belong in its placeholders.
 */
final class BoundQuery
{
    /**
     * @param list<mixed> $params
     */
    public function __construct(
        public readonly string $sql,
        public readonly array $params = [],
    ) {
    }
}
