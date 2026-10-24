<?php

namespace App\Domain\Value;

interface ValueSource
{
    /** @return array{source_timestamp: string, rows: list<array<string, mixed>>} */
    public function fetch(): array;
}
