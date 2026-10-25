<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

final class PreviewAccountSeeder extends Seeder
{
    public function run(): void
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        $host = config("database.connections.{$connection}.host");

        if (app()->environment('production') || ($driver !== 'sqlite' && ! in_array($host, ['127.0.0.1', 'localhost'], true))) {
            throw new RuntimeException('Preview accounts may only be seeded into a local or disposable database.');
        }

        foreach ([
            ['name' => 'FTC Sysadmin', 'email' => 'sysadmin@ftc.test', 'password' => 'Sysadmin123!', 'role' => 'admin'],
            ['name' => 'FTC User', 'email' => 'user@ftc.test', 'password' => 'User12345!', 'role' => 'player'],
        ] as $account) {
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                $account,
            );
            $user->forceFill(['email_verified_at' => now()])->save();
        }
    }
}
