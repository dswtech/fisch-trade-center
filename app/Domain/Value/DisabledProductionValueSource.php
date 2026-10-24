<?php

namespace App\Domain\Value;

use RuntimeException;

final class DisabledProductionValueSource implements ValueSource
{
    public function fetch(): array
    {
        throw new RuntimeException('Production value source is disabled until source access and permission are approved.');
    }
}
