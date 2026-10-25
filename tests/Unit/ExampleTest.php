<?php

namespace Tests\Unit;

use App\Domain\Trade\WflCalculator;
use App\Domain\Trade\WflStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExampleTest extends TestCase
{
    public static function boundaryCases(): array
    {
        return [['positive boundary',1000,1100,WflStatus::Fair],['negative boundary',1000,900,WflStatus::Fair],['win',1000,1101,WflStatus::Win],['lose',1000,899,WflStatus::Lose]];
    }

    #[DataProvider('boundaryCases')]
    public function test_wfl_boundaries(string $case, int $give, int $receive, WflStatus $expected): void
    {
        $result=(new WflCalculator)->calculate([['id'=>'give','quantity'=>1,'unit_value'=>$give]],[['id'=>'receive','quantity'=>1,'unit_value'=>$receive]],10,'v1','viewer');
        self::assertSame($expected,$result->status,$case);
    }

    public function test_quantity_and_perspective_are_applied(): void
    {
        $result=(new WflCalculator)->calculate([['id'=>'give','quantity'=>2,'unit_value'=>500]],[['id'=>'receive','quantity'=>1,'unit_value'=>1200]],10,'v1','owner');
        self::assertSame(1000,$result->giveTotal); self::assertSame(20.0,$result->deltaPercent); self::assertSame('owner',$result->perspective); self::assertSame(WflStatus::Win,$result->status);
    }

    public function test_decimal_values_are_not_rounded(): void
    {
        $result=(new WflCalculator)->calculate([['id'=>'give','quantity'=>2,'unit_value'=>7.5]],[['id'=>'receive','quantity'=>1,'unit_value'=>16.5]],10,'v1');
        self::assertSame(15,$result->giveTotal); self::assertSame(16.5,$result->receiveTotal); self::assertSame(1.5,$result->delta); self::assertSame(10.0,$result->deltaPercent);
    }

    public function test_missing_inputs_fail_closed(): void
    {
        $result=(new WflCalculator)->calculate([['id'=>'unknown','quantity'=>1,'unit_value'=>null]],[],null,null);
        self::assertSame(WflStatus::Unavailable,$result->status);
        self::assertSame(['missing_snapshot','missing_threshold','missing_value'],array_column($result->reasons,'code'));
    }
}
