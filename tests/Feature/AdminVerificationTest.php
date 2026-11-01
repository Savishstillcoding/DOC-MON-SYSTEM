<?php

namespace Tests\Feature;

use App\Models\IdDocument;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin checks a pending account's ID, then verifies or rejects it (Increment 6).
 */
class AdminVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pending;

    protected function setUp(): void
    {
        parent::setUp();

        $digits = Organization::create(['code' => 'DIGITS', 'name' => 'DIGITS']);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'verified', 'login_id' => 'admin', 'first_name' => 'System', 'last_name' => 'Admin']);
        $this->pending = User::factory()->create([
            'role' => 'officer', 'status' => 'pending', 'login_id' => '2201234',
            'first_name' => 'MJ', 'last_name' => 'Labastida', 'organization_id' => $digits->id, 'position' => 'Secretary',
        ]);
        $this->pending->idDocument()->save((new IdDocument)->forceFill(['mime' => 'image/png', 'size' => 4, 'data' => "\x89PNG"]));
    }

    public function test_the_dashboard_lists_pending_accounts_without_loading_the_files(): void
    {
        $this->actingAs($this->admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Labastida, MJ')
            ->assertSee("/admin/users/{$this->pending->id}/id-document")
            ->assertViewHas('pending', fn ($p) => $p->sole()->idDocument->getAttributes() === array_intersect_key(
                $p->sole()->idDocument->getAttributes(), array_flip(['id', 'user_id', 'mime', 'size'])))
            ->assertViewHas('stats', fn ($s) => $s['pending'] === 1);
    }

    public function test_the_admin_opens_the_id_and_it_is_never_cached(): void
    {
        $response = $this->actingAs($this->admin)->get("/admin/users/{$this->pending->id}/id-document");

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame("\x89PNG", $response->getContent());
    }

    public function test_only_the_admin_can_open_an_id(): void
    {
        $officer = User::factory()->create(['role' => 'officer', 'status' => 'verified', 'login_id' => '2209999']);
        $signatory = User::factory()->create(['role' => 'signatory', 'status' => 'verified', 'login_id' => 'EMP-1']);

        $this->get("/admin/users/{$this->pending->id}/id-document")->assertRedirect('/login');
        $this->actingAs($officer)->get("/admin/users/{$this->pending->id}/id-document")->assertForbidden();
        $this->actingAs($signatory)->get("/admin/users/{$this->pending->id}/id-document")->assertForbidden();
        $this->actingAs($officer)->post("/admin/users/{$this->pending->id}/verify")->assertForbidden();
        $this->assertSame('pending', $this->pending->fresh()->status);
    }

    public function test_verifying_lets_them_log_in_and_deletes_the_id(): void
    {
        $this->actingAs($this->admin)->from('/admin/dashboard')
            ->post("/admin/users/{$this->pending->id}/verify")
            ->assertRedirect('/admin/dashboard')
            ->assertSessionHas('status', 'Verified Labastida, MJ. They can log in now.');

        $user = $this->pending->fresh();
        $this->assertSame('verified', $user->status);
        $this->assertSame($this->admin->id, $user->reviewed_by);
        $this->assertNotNull($user->reviewed_at);
        $this->assertSame(0, IdDocument::count());   // data minimization: the photo is gone

        auth()->logout();
        $this->post('/login/officer', ['student_id' => '2201234', 'password' => 'password'])
            ->assertRedirect('/officer/dashboard');
    }

    public function test_rejecting_blocks_log_in_and_deletes_the_id(): void
    {
        $this->actingAs($this->admin)->post("/admin/users/{$this->pending->id}/reject");

        $this->assertSame('rejected', $this->pending->fresh()->status);
        $this->assertSame(0, IdDocument::count());

        auth()->logout();
        $this->post('/login/officer', ['student_id' => '2201234', 'password' => 'password'])->assertSessionHasErrors('student_id');
        $this->assertGuest();
    }

    public function test_only_pending_accounts_can_be_decided(): void
    {
        $this->actingAs($this->admin)->post("/admin/users/{$this->pending->id}/verify");
        // A second click (or a second admin) can't flip it to rejected
        $this->actingAs($this->admin)->post("/admin/users/{$this->pending->id}/reject")->assertSessionHasErrors('verify');
        $this->assertSame('verified', $this->pending->fresh()->status);

        // The admin account itself can't be rejected through this page
        $this->actingAs($this->admin)->post("/admin/users/{$this->admin->id}/reject")->assertSessionHasErrors('verify');
        $this->assertSame('verified', $this->admin->fresh()->status);
    }

    public function test_recent_activity_shows_decisions(): void
    {
        $this->actingAs($this->admin)->post("/admin/users/{$this->pending->id}/verify");

        $this->actingAs($this->admin)->get('/admin/dashboard')
            ->assertSee('Verified Labastida, MJ (Student Officer)')
            ->assertSee('by Admin, System')
            ->assertSee('No accounts are waiting for verification.');
    }
}
