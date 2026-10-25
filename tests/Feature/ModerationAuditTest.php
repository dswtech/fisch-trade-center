<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Catalog\CatalogAdminService;
use App\Domain\Moderation\SuspensionService;
use App\Http\Middleware\RejectSuspendedMutation;
use App\Http\Middleware\RequireOperator;
use App\Models\CatalogItem;
use App\Models\Suspension;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class ModerationAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
    }

    public function test_audit_recorder_redacts_private_values_and_keeps_correlation(): void
    {
        $actor = $this->user('operator@example.test', 'operator');
        $request = Request::create('/operator/audit', 'GET');
        $request->attributes->set('correlation_id', $correlation = (string) Str::uuid());
        (new AuditRecorder)->record($actor, 'profile.changed', 'user', $actor->public_id,
            ['usn' => 'OldSecret', 'status' => 'unverified'],
            ['ign' => 'NewSecret', 'status' => 'unverified'],
            metadata: ['email' => 'private@example.test', 'nested' => ['token' => 'secret', 'safe' => true]],
            request: $request,
        );

        $event = DB::table('audit_events')->first();
        self::assertSame($correlation, $event->correlation_id);
        self::assertStringNotContainsString('Secret', json_encode($event));
        self::assertStringNotContainsString('private@example.test', json_encode($event));
        self::assertTrue(json_decode($event->metadata, true)['nested']['safe']);
    }

    public function test_operator_or_admin_authorization_is_required(): void
    {
        $middleware = new RequireOperator;
        foreach (['operator', 'admin'] as $role) {
            $request = Request::create('/operator', 'GET');
            $request->setUserResolver(fn () => $this->user($role.'@example.test', $role));
            self::assertSame(204, $middleware->handle($request, fn () => new Response('', 204))->getStatusCode());
        }

        $request = Request::create('/operator', 'GET');
        $request->setUserResolver(fn () => $this->user('player@example.test'));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $middleware->handle($request, fn () => new Response('', 204));
    }

    public function test_suspension_blocks_trade_mutations_and_can_be_lifted_idempotently(): void
    {
        $admin = $this->user('admin@example.test', 'admin');
        $player = $this->user('suspended@example.test');
        $audit = new AuditRecorder;
        $service = new SuspensionService($audit);
        $suspension = $service->suspend($player, $admin, 'suspected_scam', 7);

        self::assertTrue(Suspension::query()->whereKey($suspension->id)->active()->exists());
        $request = Request::create('/listings', 'POST');
        $request->setUserResolver(fn () => $player);
        try {
            (new RejectSuspendedMutation)->handle($request, fn () => new Response('', 204));
            self::fail('Suspended mutation should have been rejected.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            self::assertSame(423, $exception->getStatusCode());
        }

        $service->lift($suspension, $admin);
        $service->lift($suspension->fresh(), $admin);
        self::assertSame(204, (new RejectSuspendedMutation)->handle($request, fn () => new Response('', 204))->getStatusCode());
        self::assertSame(1, DB::table('audit_events')->where('event_type', 'user.suspension_lifted')->count());
    }

    public function test_catalog_admin_updates_aliases_but_rejects_ambiguous_names(): void
    {
        $admin = $this->user('catalog-admin@example.test', 'admin');
        $categoryId = DB::table('catalog_categories')->insertGetId([
            'public_id' => (string) Str::uuid(), 'key' => 'rod_skin', 'name' => 'Rod Skin', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $first = CatalogItem::create(['category_id' => $categoryId, 'canonical_name' => 'Aurora', 'aliases' => [], 'is_active' => true]);
        CatalogItem::create(['category_id' => $categoryId, 'canonical_name' => 'Abyssal', 'aliases' => ['Deep'], 'is_active' => true]);
        $service = new CatalogAdminService(new AuditRecorder);
        $service->update($first, $admin, false, ['Northern Light']);

        self::assertFalse($first->fresh()->is_active);
        self::assertSame(['Northern Light'], $first->fresh()->aliases);
        self::assertDatabaseHas('audit_events', ['event_type' => 'catalog_item.updated']);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->update($first->fresh(), $admin, true, ['deep']);
    }

    private function user(string $email, string $role = 'player'): User
    {
        $user = User::create(['name' => ucfirst($role), 'email' => $email, 'password' => 'password123', 'role' => $role]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
