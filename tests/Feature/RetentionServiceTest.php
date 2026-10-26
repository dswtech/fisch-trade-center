<?php

namespace Tests\Feature;

use App\Domain\Operations\RetentionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RetentionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-10-26 12:00:00');
        Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
        Artisan::call('db:seed', ['--force' => true]);
        config(['operations.retention_enabled' => true, 'operations.retention_chunk' => 2]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_dry_run_never_changes_data_and_execute_requires_feature_flag(): void
    {
        $negotiation = $this->negotiation(now()->subDays(181));
        $this->proposal($negotiation['id'], now()->subDays(181));

        self::assertSame(0, Artisan::call('ops:retention'));
        self::assertDatabaseHas('negotiations', ['id' => $negotiation['id']]);

        config(['operations.retention_enabled' => false]);
        self::assertSame(1, Artisan::call('ops:retention', ['--execute' => true]));
        self::assertDatabaseHas('negotiations', ['id' => $negotiation['id']]);
    }

    public function test_retention_honors_boundaries_protects_reports_and_is_replay_safe(): void
    {
        $oldDeal = $this->dealGraph(now()->subDays(366), now()->subDays(366));
        $boundaryDeal = $this->dealGraph(now()->subDay(), now()->subDays(90));
        $recentDeal = $this->dealGraph(now()->subDay(), now()->subDays(90)->addSecond());

        $oldNegotiation = $this->negotiation(now()->subDays(180));
        $oldProposal = $this->proposal($oldNegotiation['id'], now()->subDays(180));

        $openNegotiation = $this->negotiation(now()->subDays(181));
        $this->proposal($openNegotiation['id'], now()->subDays(181));
        $this->report($openNegotiation['public_id'], 'open', null);

        $resolvedNegotiation = $this->negotiation(now()->subDays(181));
        $this->proposal($resolvedNegotiation['id'], now()->subDays(181));
        $resolvedReport = $this->report($resolvedNegotiation['public_id'], 'resolved', now()->subDays(180));

        $recentReportNegotiation = $this->negotiation(now()->subDays(181));
        $this->proposal($recentReportNegotiation['id'], now()->subDays(181));
        $recentReport = $this->report($recentReportNegotiation['public_id'], 'dismissed', now()->subDays(180)->addSecond());

        $auditId = DB::table('audit_events')->insertGetId([
            'public_id' => (string) Str::uuid(), 'actor_id' => null, 'event_type' => 'test.old',
            'subject_type' => 'test', 'subject_public_id' => null, 'metadata' => '{}', 'created_at' => now()->subDays(365),
        ]);
        $suspensionId = $this->completedSuspension(now()->subDays(365));
        $snapshotCount = DB::table('value_snapshots')->count();
        $catalogCount = DB::table('catalog_items')->count();

        $deleted = app(RetentionService::class)->execute();

        self::assertGreaterThanOrEqual(1, $deleted['deal_graphs']);
        self::assertGreaterThanOrEqual(2, $deleted['negotiation_graphs']);
        self::assertDatabaseMissing('negotiations', ['id' => $oldDeal['negotiation_id']]);
        self::assertDatabaseMissing('negotiations', ['id' => $oldNegotiation['id']]);
        self::assertDatabaseMissing('proposal_versions', ['id' => $oldProposal]);
        self::assertDatabaseMissing('reports', ['id' => $resolvedReport]);
        self::assertDatabaseMissing('negotiations', ['id' => $resolvedNegotiation['id']]);
        self::assertDatabaseHas('negotiations', ['id' => $openNegotiation['id']]);
        self::assertDatabaseHas('reports', ['id' => $recentReport]);
        self::assertDatabaseHas('negotiations', ['id' => $recentReportNegotiation['id']]);
        self::assertDatabaseMissing('deal_identity_snapshots', ['deal_id' => $boundaryDeal['deal_id']]);
        self::assertDatabaseMissing('reveal_entitlements', ['deal_id' => $boundaryDeal['deal_id']]);
        self::assertDatabaseHas('deal_identity_snapshots', ['deal_id' => $recentDeal['deal_id']]);
        self::assertDatabaseMissing('audit_events', ['id' => $auditId]);
        self::assertDatabaseMissing('suspensions', ['id' => $suspensionId]);
        self::assertSame($snapshotCount, DB::table('value_snapshots')->count());
        self::assertSame($catalogCount, DB::table('catalog_items')->count());

        $replay = app(RetentionService::class)->execute();
        self::assertSame(0, array_sum($replay));
    }

    public function test_graph_chunk_rolls_back_atomically_when_a_delete_fails(): void
    {
        $negotiation = $this->negotiation(now()->subDays(181));
        $proposal = $this->proposal($negotiation['id'], now()->subDays(181));
        DB::statement("CREATE TRIGGER retention_failure BEFORE DELETE ON proposal_versions BEGIN SELECT RAISE(ABORT, 'forced retention failure'); END");

        try {
            app(RetentionService::class)->execute();
            self::fail('Expected retention failure was not raised.');
        } catch (QueryException) {
            self::assertDatabaseHas('negotiations', ['id' => $negotiation['id']]);
            self::assertDatabaseHas('proposal_versions', ['id' => $proposal]);
        } finally {
            DB::statement('DROP TRIGGER IF EXISTS retention_failure');
        }
    }

    private function negotiation($timestamp): array
    {
        $listing = DB::table('listings')->first();
        $initiator = DB::table('users')->where('email', 'e2e@example.test')->first();
        $owner = DB::table('users')->where('email', 'owner-fixture@example.test')->first();
        $publicId = (string) Str::uuid();
        $id = DB::table('negotiations')->insertGetId([
            'public_id' => $publicId, 'listing_id' => $listing->id, 'initiator_id' => $initiator->id,
            'owner_id' => $owner->id, 'value_snapshot_id' => $listing->value_snapshot_id, 'status' => 'expired',
            'active_proposal_version' => 1, 'initiator_alias' => 'Angler A', 'owner_alias' => 'Angler B',
            'last_activity_at' => $timestamp, 'expires_at' => $timestamp, 'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);

        return ['id' => $id, 'public_id' => $publicId];
    }

    private function proposal(int $negotiationId, $timestamp): int
    {
        $proposer = DB::table('users')->where('email', 'e2e@example.test')->first();

        return DB::table('proposal_versions')->insertGetId([
            'public_id' => (string) Str::uuid(), 'negotiation_id' => $negotiationId, 'proposer_id' => $proposer->id,
            'value_snapshot_id' => DB::table('value_snapshots')->value('id'), 'version' => 1, 'status' => 'expired',
            'initiator_value' => 2400, 'owner_value' => 2400, 'expires_at' => $timestamp,
            'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
    }

    private function dealGraph($updatedAt, $revealedAt): array
    {
        $negotiation = $this->negotiation($updatedAt);
        $proposal = $this->proposal($negotiation['id'], $updatedAt);
        $proposer = DB::table('users')->where('email', 'e2e@example.test')->first();
        $owner = DB::table('users')->where('email', 'owner-fixture@example.test')->first();
        $preDeal = DB::table('pre_deal_versions')->insertGetId([
            'public_id' => (string) Str::uuid(), 'negotiation_id' => $negotiation['id'], 'proposal_version_id' => $proposal,
            'proposer_id' => $proposer->id, 'version' => 1, 'server_type' => 'public', 'status' => 'accepted',
            'fee_quote' => 0, 'expires_at' => $updatedAt, 'created_at' => $updatedAt, 'updated_at' => $updatedAt,
        ]);
        $deal = DB::table('deals')->insertGetId([
            'public_id' => (string) Str::uuid(), 'negotiation_id' => $negotiation['id'], 'proposal_version_id' => $proposal,
            'pre_deal_version_id' => $preDeal, 'status' => 'revealed', 'fee_quote' => 0, 'snapshot' => '{}',
            'expires_at' => $updatedAt, 'revealed_at' => $revealedAt, 'created_at' => $updatedAt, 'updated_at' => $updatedAt,
        ]);
        DB::table('deal_identity_snapshots')->insert([
            'deal_id' => $deal, 'user_id' => $owner->id, 'usn' => 'secret-usn', 'ign' => 'secret-ign',
        ]);
        DB::table('reveal_entitlements')->insert([
            'deal_id' => $deal, 'viewer_user_id' => $proposer->id, 'subject_user_id' => $owner->id, 'expires_at' => now()->addDay(),
        ]);

        return ['deal_id' => $deal, 'negotiation_id' => $negotiation['id']];
    }

    private function report(string $targetPublicId, string $status, $resolvedAt): int
    {
        $reporter = DB::table('users')->where('email', 'e2e@example.test')->first();

        return DB::table('reports')->insertGetId([
            'public_id' => (string) Str::uuid(), 'reporter_id' => $reporter->id, 'target_type' => 'negotiation',
            'target_public_id' => $targetPublicId, 'reason_code' => 'suspected_scam', 'status' => $status,
            'resolved_at' => $resolvedAt, 'created_at' => now()->subDays(181), 'updated_at' => now()->subDays(181),
        ]);
    }

    private function completedSuspension($timestamp): int
    {
        $user = DB::table('users')->where('email', 'e2e@example.test')->first();
        $operator = DB::table('users')->where('role', 'admin')->first();

        return DB::table('suspensions')->insertGetId([
            'public_id' => (string) Str::uuid(), 'user_id' => $user->id, 'reason_code' => 'test',
            'starts_at' => $timestamp->copy()->subDay(), 'ends_at' => $timestamp, 'lifted_at' => null,
            'created_by' => $operator->id, 'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
    }
}
