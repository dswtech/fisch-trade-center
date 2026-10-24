<?php

namespace App\Domain\Trade;

final readonly class WflResult
{
    /** @param list<array{code: string, item_id?: string}> $reasons */
    public function __construct(
        public ?int $giveTotal,
        public ?int $receiveTotal,
        public ?int $delta,
        public ?float $deltaPercent,
        public ?string $snapshotVersion,
        public string $perspective,
        public WflStatus $status,
        public array $reasons = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'giveTotal' => $this->giveTotal,
            'receiveTotal' => $this->receiveTotal,
            'delta' => $this->delta,
            'deltaPercent' => $this->deltaPercent,
            'snapshotVersion' => $this->snapshotVersion,
            'perspective' => $this->perspective,
            'status' => $this->status->value,
            'reasons' => $this->reasons,
        ];
    }
}
