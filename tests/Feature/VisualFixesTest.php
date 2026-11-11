<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batch 2 visual fixes that PHPUnit can see: the skip link (AUD-093) and the password
 * rule shown under the box instead of a cut-off placeholder (AUD-065). The colours
 * (AUD-092), the Email box (AUD-064) and the tab arrows (AUD-066) are CSS, checked in
 * a real browser with axe-core.
 */
class VisualFixesTest extends TestCase
{
    use RefreshDatabase;

    private const HINT = 'At least 8 characters, with letters and numbers.';

    public function test_every_dashboard_page_starts_with_a_skip_link_to_the_content(): void
    {
        $officer = User::factory()->create(['role' => 'officer', 'status' => 'verified', 'login_id' => '2201234',
            'organization_id' => Organization::create(['code' => 'DIGITS', 'name' => 'DIGITS'])->id]);
        $signatory = User::factory()->create(['role' => 'signatory', 'status' => 'verified', 'login_id' => 'EMP-1']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'verified', 'login_id' => 'admin']);

        foreach ([[$officer, '/officer/dashboard'], [$signatory, '/signatory/review'], [$admin, '/admin/users']] as [$user, $page]) {
            $this->actingAs($user)->get($page)->assertOk()->assertSeeInOrder([
                '<a href="#main" class="dm-skip">Skip to content</a>',
                'class="dm-sidebar"',
                '<main class="dm-main" id="main" tabindex="-1">',
            ], false);
        }
    }

    public function test_the_password_rule_is_shown_under_the_box(): void
    {
        Organization::create(['code' => 'DIGITS', 'name' => 'DIGITS']);

        foreach (['/register/officer', '/register/signatory', '/reset-password/some-token?email=a%40b.c'] as $page) {
            $this->get($page)->assertOk()
                ->assertSee('aria-describedby="password_hint"', false)
                ->assertSee(self::HINT)
                ->assertDontSee('placeholder="At least 8 characters"', false);
        }

        $officer = User::factory()->create(['role' => 'officer', 'status' => 'verified', 'login_id' => '2201234']);
        $this->actingAs($officer)->get('/officer/profile')
            ->assertSee('aria-describedby="pf-new-hint"', false)
            ->assertSee(self::HINT);
    }
}
