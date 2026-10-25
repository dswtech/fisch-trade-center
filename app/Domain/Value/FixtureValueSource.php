<?php

namespace App\Domain\Value;

use RuntimeException;

final class FixtureValueSource implements ValueSource
{
    public function __construct(private readonly string $path) {}

    public function fetch(): array
    {
        if (! is_file($this->path)) {
            throw new RuntimeException('Fixture value source is unavailable.');
        }

        $decoded = json_decode((string) file_get_contents($this->path), true, flags: JSON_THROW_ON_ERROR);

        if (! isset($decoded['source_timestamp'], $decoded['rows']) || ! is_array($decoded['rows'])) {
            throw new RuntimeException('Fixture value source has an invalid schema.');
        }

        return [...$decoded, 'source' => ['key' => 'fixture-v1', 'category' => 'mixed']];
    }
}
