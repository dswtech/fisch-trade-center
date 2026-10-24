<?php
namespace Tests\Unit;

use App\Domain\Value\ValueImportValidator;
use PHPUnit\Framework\TestCase;

final class ValueImportValidatorTest extends TestCase
{
    public function test_checksum_is_order_independent_and_idempotent(): void
    {
        $a=['external_key'=>'a','category'=>'skin','name'=>'A','value'=>10]; $b=['external_key'=>'b','category'=>'boat','name'=>'B','value'=>20]; $validator=new ValueImportValidator;
        self::assertSame($validator->validate([$a,$b])['checksum'],$validator->validate([$b,$a])['checksum']);
    }
    public function test_invalid_rows_are_rejected(): void
    {
        $result=(new ValueImportValidator)->validate([['external_key'=>'x','category'=>'unknown','name'=>'X','value'=>-1],['external_key'=>'x','category'=>'skin','name'=>'Y','value'=>1]]);
        self::assertFalse($result['valid']); self::assertContains('invalid_value',array_column($result['errors'],'code')); self::assertContains('duplicate_external_key',array_column($result['errors'],'code'));
    }
}
