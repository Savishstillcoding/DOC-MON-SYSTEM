<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin: Organizations page (AUD-053): add, rename, delete only when empty.
 */
class AdminOrganizationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $digits;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('admin', 'admin');
        $this->digits = Organization::create(['code' => 'DIGITS', 'name' => 'Digital Integrated Group of IT Students']);
    }

    public function test_cards_show_real_numbers_and_the_pilot(): void
    {
        $adviser = $this->makeUser('signatory', 'EMP-1');
        $this->digits->signatories()->attach($adviser, ['step' => 1, 'title' => 'Adviser']);
        $officer = $this->makeUser('officer', '2201234', ['organization_id' => $this->digits->id]);
        $this->ticketFor($officer);
        Organization::create(['code' => 'SSC', 'name' => 'Supreme Student Council']);

        $this->actingAs($this->admin)->get('/admin/organizations')
            ->assertOk()
            ->assertViewHas('organizations', function ($orgs) {
                $digits = $orgs->firstWhere('code', 'DIGITS');

                return [$digits->officers_count, $digits->signatories_count, $digits->active_tickets_count] === [1, 1, 1];
            })
            ->assertSee('Pilot')
            ->assertSee('No approval route yet');   // SSC has no route

        $this->actingAs($this->admin)->get('/admin/organizations?q=Supreme')
            ->assertViewHas('organizations', fn ($orgs) => $orgs->sole()->code === 'SSC');
    }

    public function test_adding_an_organization_leads_to_its_signatory_list_and_shows_in_sign_up(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/organizations', ['code' => 'ssc', 'name' => 'Supreme Student Council']);

        $ssc = Organization::where('code', 'SSC')->sole();   // stored in capitals
        $response->assertRedirect('/admin/signatory-lists?org='.$ssc->id);

        auth()->logout();
        $this->get('/register/officer')->assertSee('SSC · Supreme Student Council');
    }

    public function test_codes_are_unique_and_simple(): void
    {
        $this->actingAs($this->admin)->post('/admin/organizations', ['code' => 'digits', 'name' => 'Copy'])->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post('/admin/organizations', ['code' => 'SS C!', 'name' => 'Bad'])->assertSessionHasErrors('code');
        $this->assertSame(1, Organization::count());
    }

    public function test_renaming(): void
    {
        $this->actingAs($this->admin)->put("/admin/organizations/{$this->digits->id}", ['code' => 'DIGITS', 'name' => 'DIGITS Society'])
            ->assertRedirect('/admin/organizations');
        $this->assertSame('DIGITS Society', $this->digits->fresh()->name);
    }

    public function test_only_an_empty_organization_can_be_deleted(): void
    {
        $this->makeUser('officer', '2201234', ['organization_id' => $this->digits->id]);

        $this->actingAs($this->admin)->delete("/admin/organizations/{$this->digits->id}")
            ->assertSessionHasErrors('organization');
        $this->assertNotNull($this->digits->fresh());

        $mistake = Organization::create(['code' => 'OOPS', 'name' => 'Added by mistake']);
        $this->actingAs($this->admin)->delete("/admin/organizations/{$mistake->id}")->assertRedirect('/admin/organizations');
        $this->assertNull($mistake->fresh());
    }

    public function test_only_the_admin_can_manage_organizations(): void
    {
        $officer = $this->makeUser('officer', '2201234', ['organization_id' => $this->digits->id]);

        $this->actingAs($officer)->get('/admin/organizations')->assertForbidden();
        $this->actingAs($officer)->post('/admin/organizations', ['code' => 'X', 'name' => 'X'])->assertForbidden();
        $this->actingAs($officer)->delete("/admin/organizations/{$this->digits->id}")->assertForbidden();
    }

    private function ticketFor(User $officer): Ticket
    {
        $ticket = new Ticket(['ref_no' => 'REF-1', 'title' => 'Ticket']);
        $ticket->code = Ticket::nextCode();
        $ticket->organization()->associate($this->digits);
        $ticket->officer()->associate($officer);
        $ticket->save();
        $ticket->documents()->create(['type' => 'Resolution', 'title' => 'Paper']);

        return $ticket;
    }

    private function makeUser(string $role, string $loginId, array $extra = []): User
    {
        return User::factory()->create($extra + ['role' => $role, 'status' => 'verified', 'login_id' => $loginId]);
    }
}
