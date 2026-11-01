<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Show / hide password (AUD-056): every page with a password box loads the eye button.
 * (Clicking it is checked in a browser: PHPUnit can't run JavaScript.)
 */
class PasswordToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_in_and_sign_up_pages_load_the_eye_button(): void
    {
        Organization::create(['code' => 'DIGITS', 'name' => 'DIGITS']);   // the officer sign-up dropdown

        foreach (['/login/officer', '/login/signatory', '/login/admin', '/register/officer', '/register/signatory'] as $url) {
            $this->assertLoadsToggle($this->get($url), $url);
        }
    }

    public function test_profile_pages_load_the_eye_button(): void
    {
        $organization = Organization::create(['code' => 'DIGITS', 'name' => 'DIGITS']);
        $officer = User::factory()->create(['role' => 'officer', 'status' => 'verified', 'login_id' => '2201234', 'organization_id' => $organization->id]);
        $signatory = User::factory()->create(['role' => 'signatory', 'status' => 'verified', 'login_id' => 'EMP-1']);

        $this->assertLoadsToggle($this->actingAs($officer)->get('/officer/profile'), 'officer profile');
        $this->assertLoadsToggle($this->actingAs($signatory)->get('/signatory/profile'), 'signatory profile');
    }

    private function assertLoadsToggle($response, string $page): void
    {
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('type="password"', $html, "{$page}: has a password box");
        $this->assertStringContainsString('css/password-toggle.css', $html, "{$page}: loads the button style");
        $this->assertStringContainsString('js/password-toggle.js', $html, "{$page}: loads the button script");
        // The style must be the LAST stylesheet: otherwise the page's own input padding
        // (same strength) wins, and the text runs under the eye button
        preg_match_all('/<link rel="stylesheet" href="([^"]+)"/', $html, $sheets);
        $this->assertStringContainsString('css/password-toggle.css', end($sheets[1]), "{$page}: button style is linked last");
    }
}
