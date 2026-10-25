<?php

namespace App\Domain\Trade;

final class WflCalculator
{
    /**
     * @param list<array{id: string, quantity: int, unit_value: int|float|null}> $give
     * @param list<array{id: string, quantity: int, unit_value: int|float|null}> $receive
     */
    public function calculate(
        array $give,
        array $receive,
        ?float $fairThresholdPercent,
        ?string $snapshotVersion,
        string $perspective = 'viewer',
    ): WflResult {
        $reasons = [];

        if ($snapshotVersion === null) {
            $reasons[] = ['code' => 'missing_snapshot'];
        }
        if ($fairThresholdPercent === null) {
            $reasons[] = ['code' => 'missing_threshold'];
        }

        foreach ([...$give, ...$receive] as $item) {
            if ($item['unit_value'] === null) {
                $reasons[] = ['code' => 'missing_value', 'item_id' => $item['id']];
            }
        }

        if ($reasons !== []) {
            return new WflResult(null, null, null, null, $snapshotVersion, $perspective, WflStatus::Unavailable, $reasons);
        }

        $total = static function (array $items): int|float {
            $value = array_sum(array_map(
                static fn (array $item): int|float => $item['quantity'] * $item['unit_value'],
                $items,
            ));

            return floor((float) $value) === (float) $value ? (int) $value : $value;
        };
        $giveTotal = $total($give);
        $receiveTotal = $total($receive);

        if ($giveTotal <= 0) {
            return new WflResult($giveTotal, $receiveTotal, null, null, $snapshotVersion, $perspective, WflStatus::Unavailable, [['code' => 'invalid_give_total']]);
        }

        $delta = $receiveTotal - $giveTotal;
        $deltaPercent = ($delta / $giveTotal) * 100;
        $status = match (true) {
            abs($deltaPercent) <= $fairThresholdPercent => WflStatus::Fair,
            $deltaPercent > $fairThresholdPercent => WflStatus::Win,
            default => WflStatus::Lose,
        };

        return new WflResult($giveTotal, $receiveTotal, $delta, round($deltaPercent, 2), $snapshotVersion, $perspective, $status);
    }
}
