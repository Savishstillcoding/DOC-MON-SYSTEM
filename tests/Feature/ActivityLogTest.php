<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\IdDocument;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

/**
 * Activity Logs: every important action writes one permanent line, and the admin
 * can browse them by category, date range and search.
 */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $digits;

    private User $officer;

    private User $adviser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('admin', 'admin', ['first_name' => 'System', 'last_name' => 'Admin']);
        $this->digits = Organization::create(['code' => 'DIGITS', 'name' => 'DIGITS']);
        $this->adviser = $this->makeUser('signatory', 'EMP-1', ['first_name' => 'Ana', 'last_name' => 'Dela Cruz']);
        $this->digits->signatories()->attach($this->adviser, ['step' => 1, 'title' => 'Adviser']);
        $this->officer = $this->makeUser('officer', '2201234', ['organization_id' => $this->digits->id, 'position' => 'Secretary', 'first_name' => 'MJ', 'last_name' => 'Labastida']);
    }

    public function test_the_ticket_flow_is_logged(): void
    {
        $this->actingAs($this->officer)->post('/officer/tickets', [
            'ref_no' => '2026-11-001', 'title' => 'DIGITS Week', 'documents' => [['type' => 'Resolution', 'title' => 'Resolution No. 1']],
        ]);
        $doc = Ticket::sole()->documents->sole();
        $this->actingAs($this->adviser)->post("/signatory/documents/{$doc->id}/action", ['action' => 'return', 'remarks' => 'Fix the date']);
        $this->actingAs($this->officer)->post("/officer/documents/{$doc->id}/resubmit");
        $this->actingAs($this->adviser)->post("/signatory/documents/{$doc->id}/action", ['action' => 'approve']);

        $this->assertSame([
            ['Labastida, MJ', 'Submitted ticket'],
            ['Dela Cruz, Ana', 'Returned document'],
            ['Labastida, MJ', 'Resubmitted document'],
            ['Dela Cruz, Ana', 'Approved document (final)'],
        ], ActivityLog::orderBy('id')->get()->map(fn ($l) => [$l->actor_name, $l->action])->all());
        $this->assertStringContainsString('Fix the date', ActivityLog::where('action', 'Returned document')->sole()->details);
    }

    public function test_sign_up_and_verification_are_logged(): void
    {
        $this->post('/register/officer', [
            'privacy_accepted' => '1',
            'first_name' => 'Juan', 'last_name' => 'Test', 'student_id' => '2200001', 'organization_id' => $this->digits->id,
            'email' => 'juan@lnu.edu.ph', 'phone' => '09170000000', 'position' => 'Secretary', 'password' => 'Secret#2026',
            'id_document' => UploadedFile::fake()->image('id.jpg'),
        ]);
        $juan = User::where('login_id', '2200001')->sole();
        $this->actingAs($this->admin)->post("/admin/users/{$juan->id}/review", ['decision' => 'reject', 'review_note' => 'ID photo did not match']);

        $this->assertSame(['Signed up', 'Rejected account'], ActivityLog::orderBy('id')->pluck('action')->all());
        $this->assertSame('Test, Juan (Student Officer · DIGITS · Secretary) — ID photo did not match',
            ActivityLog::where('action', 'Rejected account')->sole()->details);
        $this->assertSame(0, IdDocument::count());
    }

    public function test_account_changes_are_logged(): void
    {
        $this->actingAs($this->adviser)->put('/signatory/availability', ['availability' => 'In a Meeting', 'back_at' => '2:00 PM']);
        $this->actingAs($this->adviser)->put('/signatory/availability', ['availability' => 'In a Meeting', 'back_at' => '2:00 PM']);   // no change: no line
        // fresh(): load the user like the real app does (the factory object has no "phone" key at all)
        $this->actingAs($this->officer->fresh())->put('/officer/profile', ['position' => 'Secretary', 'email' => 'new@lnu.edu.ph', 'phone' => $this->officer->phone]);
        $this->actingAs($this->officer)->put('/officer/profile/password', ['current_password' => 'password', 'password' => 'NewPass#2026', 'password_confirmation' => 'NewPass#2026']);
        $spare = $this->makeUser('signatory', 'EMP-9');
        $this->actingAs($this->admin)->post("/admin/users/{$spare->id}/deactivate");
        $this->actingAs($this->admin)->post("/admin/users/{$spare->id}/reactivate");

        $this->assertSame(['Changed availability', 'Updated profile', 'Changed password', 'Deactivated account', 'Reactivated account'],
            ActivityLog::orderBy('id')->pluck('action')->all());
        $this->assertSame('Available → In a Meeting (back 2:00 PM)', ActivityLog::where('action', 'Changed availability')->sole()->details);
        // Field names only, never the values (no emails or phone numbers in the log)
        $this->assertSame('Changed email', ActivityLog::where('action', 'Updated profile')->sole()->details);
        $this->assertStringNotContainsString('NewPass', ActivityLog::where('action', 'Changed password')->sole()->details);
    }

    public function test_route_and_organization_changes_are_logged(): void
    {
        $nora = $this->makeUser('signatory', 'EMP-2', ['first_name' => 'Nora', 'last_name' => 'Cruz']);
        $this->actingAs($this->admin)->put("/admin/signatory-lists/{$this->digits->id}", ['steps' => [['user_id' => $nora->id, 'title' => 'Adviser']]]);
        $this->actingAs($this->admin)->put("/admin/signatory-lists/{$this->digits->id}", ['steps' => [
            ['user_id' => $nora->id, 'title' => 'Adviser'], ['user_id' => $this->adviser->id, 'title' => 'VPSD'],
        ]]);
        $this->actingAs($this->admin)->post('/admin/organizations', ['code' => 'SSC', 'name' => 'Supreme Student Council']);

        $this->assertSame([
            'DIGITS · step 1: Dela Cruz, Ana → Cruz, Nora',
            'DIGITS · new route: Adviser (Cruz, Nora) → VPSD (Dela Cruz, Ana)',
            'SSC · Supreme Student Council',
        ], ActivityLog::orderBy('id')->pluck('details')->all());
    }

    public function test_log_lines_cannot_be_changed_or_deleted(): void
    {
        $line = ActivityLog::record($this->admin, 'accounts', 'Test', 'details');

        $this->assertThrows(fn () => $line->update(['details' => 'edited']), LogicException::class);
        $this->assertThrows(fn () => $line->delete(), LogicException::class);
        $this->assertSame('details', $line->fresh()->details);
    }

    public function test_the_page_filters_by_tab_range_and_search(): void
    {
        ActivityLog::record($this->officer, 'tickets', 'Submitted ticket', 'DM-2026-00001 · DIGITS Week');
        ActivityLog::record($this->admin, 'verification', 'Approved account', 'Cruz, Nora (Signatory)');
        $old = ActivityLog::record($this->admin, 'accounts', 'Deactivated account', 'Old event');
        $old->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();

        $this->actingAs($this->admin)->get('/admin/activity-logs')
            ->assertOk()
            ->assertViewHas('logs', fn ($p) => $p->total() === 2)   // default: last 7 days
            ->assertSee('Approved account');
        $this->actingAs($this->admin)->get('/admin/activity-logs?range=all')->assertViewHas('logs', fn ($p) => $p->total() === 3);
        $this->actingAs($this->admin)->get('/admin/activity-logs?tab=tickets')->assertViewHas('logs', fn ($p) => $p->sole()->action === 'Submitted ticket');
        $this->actingAs($this->admin)->get('/admin/activity-logs?q=Nora')->assertViewHas('logs', fn ($p) => $p->total() === 1);
        $this->actingAs($this->officer)->get('/admin/activity-logs')->assertForbidden();
    }

    public function test_the_migration_copies_in_earlier_events(): void
    {
        // Events that existed before the log: a ticket and an approval
        $ticket = new Ticket(['ref_no' => 'REF-1', 'title' => 'Old Ticket']);
        $ticket->code = 'DM-2026-00009';
        $ticket->organization()->associate($this->digits);
        $ticket->officer()->associate($this->officer);
        $ticket->save();
        $doc = $ticket->documents()->create(['type' => 'Resolution', 'title' => 'Old Paper']);
        $doc->actions()->make(['step' => 1, 'action' => 'approved'])->user()->associate($this->adviser)->save();

        // Re-run the migration as if the log table were brand new
        $migration = require database_path('migrations/2026_11_01_130000_create_activity_logs_table.php');
        Schema::drop('activity_logs');
        $migration->up();

        $actions = ActivityLog::pluck('action');
        $this->assertTrue($actions->contains('Submitted ticket'));
        $this->assertTrue($actions->contains('Approved document'));
        $this->assertTrue($actions->contains('Signed up'));
        $this->assertSame('DM-2026-00009 · Old Paper', ActivityLog::where('action', 'Approved document')->sole()->details);
    }

    private function makeUser(string $role, string $loginId, array $extra = []): User
    {
        return User::factory()->create($extra + ['role' => $role, 'status' => 'verified', 'login_id' => $loginId]);
    }
}
