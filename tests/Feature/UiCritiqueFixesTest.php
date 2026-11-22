<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Whole-app UI critique, 2026-11-17:
 * the officer's five number cards became one line of counts, the signatory's review table
 * stacks on phones, the gradient/glow/float effects are gone, and the log-in taglines are plain.
 * How it looks is checked in a real browser; these tests pin the markup and the CSS rules.
 */
class UiCritiqueFixesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $digits;

    private User $adviser;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->digits = Organization::create(['code' => 'DIGITS', 'name' => 'DIGITS']);
        $this->adviser = User::factory()->create(['role' => 'signatory', 'status' => 'verified', 'login_id' => 'EMP-1']);
        $this->digits->signatories()->attach($this->adviser, ['step' => 1, 'title' => 'Adviser']);
        $this->officer = User::factory()->create(['role' => 'officer', 'status' => 'verified', 'login_id' => '2201234',
            'organization_id' => $this->digits->id]);

        $ticket = new Ticket(['ref_no' => 'REF-1', 'title' => 'DIGITS Week']);
        $ticket->code = Ticket::nextCode();
        $ticket->organization()->associate($this->digits);
        $ticket->officer()->associate($this->officer);
        $ticket->save();
        $ticket->documents()->create(['type' => 'Resolution', 'title' => 'Program Flow']);   // Pending, at the Adviser
    }

    public function test_the_officer_sees_one_line_of_counts_that_open_my_tickets(): void
    {
        $page = $this->actingAs($this->officer)->get('/officer/dashboard')->assertOk();

        $page->assertSee('<nav class="so-counts" data-live="stats" aria-label="My tickets by status">', false)
            ->assertDontSee('dm-stat-card', false)
            ->assertSeeInOrder(['<b>1</b> ticket</a>', '<b>1</b> Pending', '<b>0</b> Needs revision', '<b>0</b> Approved', '<b>0</b> Rejected'], false)
            ->assertSee('href="'.url('/officer/tickets?status=Needs+Revision').'"', false)
            ->assertSee('so-count-dot so-count-pending', false);

        // No Partly Approved ticket, so no "Partly approved" count
        $page->assertDontSee('Partly approved');
    }

    public function test_the_signatory_review_table_can_stack_on_phones(): void
    {
        $this->actingAs($this->adviser)->get('/signatory/dashboard')->assertOk()
            ->assertSee('<table class="dm-table dm-table-stack">', false)
            ->assertSeeInOrder(['<td data-label="Ticket">', 'dm-cell-main dm-nowrap', '<td data-label="Document">',
                '<td data-label="Waiting since">', '<td class="dm-cell-actions">', 'value="approve"'], false);

        $css = file_get_contents(public_path('css/docmon-dashboard.css'));
        $phone = substr($css, strpos($css, 'a table that stacks on phones'));
        $this->assertStringContainsString('.dm-table-stack thead { position: absolute;', $phone);
        $this->assertStringContainsString('content: attr(data-label);', $phone);
    }

    public function test_no_gradient_buttons_glows_or_floating_cards(): void
    {
        $css = file_get_contents(public_path('css/docmon-dashboard.css'));

        $this->assertStringNotContainsString('linear-gradient', $css);
        $this->assertStringNotContainsString('--dm-glow', $css);
        $this->assertStringNotContainsString('translateY(-4px)', $css);
        $this->assertStringNotContainsString('dm-menu-label', $css);
        $this->assertStringContainsString('.dm-btn-primary { background: var(--dm-purple); color: #fff; }', $css);

        // The sidebar has no "MENU" label; the menu itself is named for screen readers
        $this->actingAs($this->officer)->get('/officer/dashboard')
            ->assertDontSee('>MENU<', false)
            ->assertSee('<nav class="dm-menu" aria-label="Main menu">', false);
    }

    public function test_log_in_and_sign_up_pages_use_plain_taglines(): void
    {
        $pages = ['/login', '/login/officer', '/login/signatory', '/login/admin', '/register/officer', '/register/signatory'];
        foreach ($pages as $page) {
            $html = $this->get($page)->assertOk()->getContent();
            foreach ([':)', ':D', 'Welcome back', 'Testpassword', 'e.x.'] as $old) {
                $this->assertStringNotContainsString($old, $html, "{$page} still says \"{$old}\"");
            }
        }

        $this->get('/login/officer')->assertSee('Track every paper your organization hands in.');
        $this->get('/register/signatory')->assertSee('The admin checks your ID before you can log in.');
    }
}
