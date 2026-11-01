<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin: the Users page (edit, deactivate) and Signatory Lists (route editing with safe rules).
 */
class AdminUsersAndRoutesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $digits;

    private User $officer;

    private User $adviser;   // step 1

    private User $vpsd;      // step 2

    private User $spare;     // verified signatory, not on the route

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('admin', 'admin', ['first_name' => 'System', 'last_name' => 'Admin']);
        $this->digits = Organization::create(['code' => 'DIGITS', 'name' => 'DIGITS']);
        $this->adviser = $this->makeUser('signatory', 'EMP-1', ['first_name' => 'Ana', 'last_name' => 'Dela Cruz']);
        $this->vpsd = $this->makeUser('signatory', 'EMP-2', ['first_name' => 'Liza', 'last_name' => 'Santos']);
        $this->spare = $this->makeUser('signatory', 'EMP-3', ['first_name' => 'Nora', 'last_name' => 'Cruz']);
        $this->digits->signatories()->attach($this->adviser, ['step' => 1, 'title' => 'Adviser']);
        $this->digits->signatories()->attach($this->vpsd, ['step' => 2, 'title' => 'VPSD']);
        $this->officer = $this->makeUser('officer', '2201234', [
            'organization_id' => $this->digits->id, 'position' => 'Secretary', 'first_name' => 'MJ', 'last_name' => 'Labastida',
        ]);
    }

    // ---------- Users ----------

    public function test_the_users_page_lists_filters_and_searches(): void
    {
        $other = Organization::create(['code' => 'SSC', 'name' => 'SSC']);
        $this->makeUser('officer', '2205555', ['organization_id' => $other->id, 'last_name' => 'Mendoza']);

        $this->actingAs($this->admin)->get('/admin/users')
            ->assertOk()
            ->assertViewHas('counts', fn ($c) => $c->all() === ['All' => 6, 'Student Officers' => 2, 'Signatories' => 3, 'Admins' => 1]);

        // DIGITS = its officer + the two signatories on its route
        $this->actingAs($this->admin)->get("/admin/users?org={$this->digits->id}")
            ->assertViewHas('users', fn ($p) => $p->total() === 3);
        $this->actingAs($this->admin)->get('/admin/users?q=Mendoza')
            ->assertViewHas('users', fn ($p) => $p->total() === 1);
        $this->actingAs($this->admin)->get('/admin/users?tab=Signatories')
            ->assertViewHas('users', fn ($p) => $p->total() === 3);
    }

    public function test_the_admin_edits_details_but_never_role_status_or_password(): void
    {
        $hash = $this->officer->password;

        $this->actingAs($this->admin)->put("/admin/users/{$this->officer->id}", [
            'first_name' => 'MJ Bryan', 'last_name' => 'Labastida', 'login_id' => '2201234',
            'email' => 'mj@lnu.edu.ph', 'phone' => '09171234567', 'position' => 'Deputy Secretary',
            'organization_id' => $this->digits->id,
            'role' => 'admin', 'status' => 'pending', 'password' => 'hacked123',
        ])->assertRedirect('/admin/users');

        $u = $this->officer->fresh();
        $this->assertSame(['MJ Bryan', 'Deputy Secretary', 'mj@lnu.edu.ph'], [$u->first_name, $u->position, $u->email]);
        $this->assertSame(['officer', 'verified', $hash], [$u->role, $u->status, $u->password]);

        // ID numbers stay unique
        $this->actingAs($this->admin)->put("/admin/users/{$this->officer->id}", [
            'first_name' => 'MJ', 'last_name' => 'L', 'login_id' => 'EMP-1', 'email' => 'mj@lnu.edu.ph',
            'position' => 'Secretary', 'organization_id' => $this->digits->id,
        ])->assertSessionHasErrors('login_id');
    }

    public function test_deactivating_blocks_log_in_and_reactivating_restores_it(): void
    {
        $this->actingAs($this->admin)->post("/admin/users/{$this->officer->id}/deactivate")
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'Deactivated'));
        $this->assertSame('deactivated', $this->officer->fresh()->status);

        auth()->logout();
        $this->post('/login/officer', ['student_id' => '2201234', 'password' => 'password'])
            ->assertSessionHasErrors(['student_id' => 'Your account was deactivated. Please contact the admin.']);

        $this->actingAs($this->admin)->post("/admin/users/{$this->officer->id}/reactivate");
        auth()->logout();
        $this->post('/login/officer', ['student_id' => '2201234', 'password' => 'password'])->assertRedirect('/officer/dashboard');
    }

    public function test_deactivation_guards(): void
    {
        // A signatory still on a route: papers would get stuck with them
        $this->actingAs($this->admin)->post("/admin/users/{$this->adviser->id}/deactivate")
            ->assertSessionHasErrors(['user' => 'Dela Cruz, Ana is on an approval route (DIGITS). Replace them on Signatory Lists first, so no papers get stuck with them.']);
        // Yourself, and pending accounts
        $this->actingAs($this->admin)->post("/admin/users/{$this->admin->id}/deactivate")->assertSessionHasErrors('user');
        $pending = $this->makeUser('officer', '2209999', ['status' => 'pending']);
        $this->actingAs($this->admin)->post("/admin/users/{$pending->id}/deactivate")->assertSessionHasErrors('user');

        $this->assertSame(['verified', 'verified', 'pending'],
            [$this->adviser->fresh()->status, $this->admin->fresh()->status, $pending->fresh()->status]);

        // A signatory NOT on any route can be deactivated
        $this->actingAs($this->admin)->post("/admin/users/{$this->spare->id}/deactivate")->assertSessionHasNoErrors();
    }

    public function test_only_the_admin_can_use_these_pages(): void
    {
        foreach (['/admin/users', '/admin/signatory-lists', '/admin/verification', "/admin/users/{$this->officer->id}/edit"] as $page) {
            $this->actingAs($this->officer)->get($page)->assertForbidden();
            $this->actingAs($this->adviser)->get($page)->assertForbidden();
        }
        $this->actingAs($this->officer)->post("/admin/users/{$this->vpsd->id}/deactivate")->assertForbidden();
    }

    // ---------- Signatory Lists ----------

    public function test_the_page_shows_the_route_with_final_say_on_the_last_step(): void
    {
        $this->actingAs($this->admin)->get('/admin/signatory-lists')
            ->assertOk()
            ->assertSee('DIGITS – Signatory List')
            ->assertSee('value="Adviser"', false)
            ->assertSee('value="VPSD"', false)
            ->assertSee('so you can add, remove and reorder steps freely');
    }

    public function test_with_no_papers_in_progress_steps_can_be_added_and_reordered(): void
    {
        $this->save([[$this->vpsd, 'VPSD'], [$this->spare, 'ODSOA'], [$this->adviser, 'Adviser']])
            ->assertRedirect("/admin/signatory-lists?org={$this->digits->id}");

        $route = $this->digits->fresh()->signatories;
        $this->assertSame([[1, 'EMP-2', 'VPSD'], [2, 'EMP-3', 'ODSOA'], [3, 'EMP-1', 'Adviser']],
            $route->map(fn ($s) => [$s->pivot->step, $s->login_id, $s->pivot->title])->all());
    }

    public function test_while_papers_are_in_progress_a_person_can_be_replaced_and_their_papers_follow(): void
    {
        $doc = $this->paperAtStep(1);

        $this->save([[$this->spare, 'Adviser'], [$this->vpsd, 'VPSD']])
            ->assertSessionHas('status', fn ($m) => str_contains($m, '1 waiting paper at step 1 now goes to the new signatory'));

        // The paper now waits for the new Adviser, and the old one can't act on it
        $this->assertTrue($doc->fresh()->isWaitingFor($this->spare));
        $this->assertFalse($doc->fresh()->isWaitingFor($this->adviser));
    }

    public function test_while_papers_are_in_progress_steps_cannot_be_added_removed_or_swapped(): void
    {
        $this->paperAtStep(1);

        $this->save([[$this->adviser, 'Adviser'], [$this->vpsd, 'VPSD'], [$this->spare, 'ODSOA']])->assertSessionHasErrors('steps');
        $this->assertStringContainsString("can't be added or removed", session('errors')->first('steps'));
        $this->save([[$this->adviser, 'Adviser']])
            ->assertSessionHasErrors('steps');
        $this->save([[$this->vpsd, 'VPSD'], [$this->adviser, 'Adviser']])->assertSessionHasErrors('steps');
        $this->assertStringContainsString("can't change places", session('errors')->first('steps'));

        // Unchanged
        $this->assertSame(['EMP-1', 'EMP-2'], $this->digits->fresh()->signatories->pluck('login_id')->all());

        // Renaming a title is still fine
        $this->save([[$this->adviser, 'Org Adviser'], [$this->vpsd, 'VPSD']])->assertSessionHasNoErrors();
        $this->assertSame('Org Adviser', $this->digits->fresh()->signatories->first()->pivot->title);
    }

    public function test_route_rules(): void
    {
        $officer = $this->officer;
        $pending = $this->makeUser('signatory', 'EMP-8', ['status' => 'pending']);

        $this->save([[$this->adviser, 'Adviser'], [$this->adviser, 'VPSD']])->assertSessionHasErrors('steps.1.user_id');
        $this->save([[$officer, 'Adviser']])->assertSessionHasErrors('steps.0.user_id');    // not a signatory
        $this->save([[$pending, 'Adviser']])->assertSessionHasErrors('steps.0.user_id');    // not verified
        $this->save([[$this->adviser, '']])->assertSessionHasErrors('steps.0.title');
        $this->actingAs($this->admin)->put("/admin/signatory-lists/{$this->digits->id}", ['steps' => []])->assertSessionHasErrors('steps');

        $this->assertSame(2, $this->digits->fresh()->signatories->count());
    }

    // ---------- Helpers ----------

    /** @param  array<int, array{0: User, 1: string}>  $steps */
    private function save(array $steps)
    {
        return $this->actingAs($this->admin)->put("/admin/signatory-lists/{$this->digits->id}", [
            'steps' => array_map(fn ($s) => ['user_id' => $s[0]->id, 'title' => $s[1]], $steps),
        ]);
    }

    private function paperAtStep(int $step): Document
    {
        $ticket = new Ticket(['ref_no' => 'REF-1', 'title' => 'Ticket']);
        $ticket->code = Ticket::nextCode();
        $ticket->organization()->associate($this->digits);
        $ticket->officer()->associate($this->officer);
        $ticket->save();
        $doc = $ticket->documents()->create(['type' => 'Resolution', 'title' => 'Paper']);
        $doc->forceFill(['current_step' => $step])->save();

        return $doc;
    }

    private function makeUser(string $role, string $loginId, array $extra = []): User
    {
        // $extra first: with "+", the LEFT array wins when both have the same key
        return User::factory()->create($extra + ['role' => $role, 'status' => 'verified', 'login_id' => $loginId]);
    }
}
