<?php

namespace Tests\Feature;

use Database\Seeders\PreviewAccountSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PreviewAccountSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
    }

    public function test_preview_accounts_are_verified_idempotent_and_can_log_in(): void
    {
        (new PreviewAccountSeeder)->run();
        (new PreviewAccountSeeder)->run();

        self::assertSame(2, DB::table('users')->count());
        self::assertNotNull(DB::table('users')->where('email', 'sysadmin@ftc.test')->where('role', 'admin')->value('email_verified_at'));
        self::assertNotNull(DB::table('users')->where('email', 'user@ftc.test')->where('role', 'player')->value('email_verified_at'));

        $this->post('/login', ['email' => 'sysadmin@ftc.test', 'password' => 'Sysadmin123!'])->assertRedirect('/');
        $this->post('/logout')->assertRedirect('/');
        $this->post('/login', ['email' => 'user@ftc.test', 'password' => 'User12345!'])->assertRedirect('/');
    }
}
