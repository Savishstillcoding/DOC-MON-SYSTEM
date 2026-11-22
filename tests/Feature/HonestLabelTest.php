<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DOC-MON knows whose signature is NEXT, not who physically holds the paper
 * (the officer carries it). So the pages say "Waiting for", never "With" or "Signatory".
 */
class HonestLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_officer_pages_say_who_the_paper_is_waiting_for(): void
    {
        $digits = Organization::create(['code' => 'DIGITS', 'name' => 'DIGITS']);
        $adviser = User::factory()->create(['role' => 'signatory', 'status' => 'verified', 'login_id' => 'EMP-1',
            'first_name' => 'Ana', 'last_name' => 'Dela Cruz']);
        $digits->signatories()->attach($adviser, ['step' => 1, 'title' => 'Adviser']);
        $officer = User::factory()->create(['role' => 'officer', 'status' => 'verified', 'login_id' => '2201234',
            'organization_id' => $digits->id]);

        $ticket = new Ticket(['ref_no' => 'REF-1', 'title' => 'DIGITS Week']);
        $ticket->code = Ticket::nextCode();
        $ticket->organization()->associate($digits);
        $ticket->officer()->associate($officer);
        $ticket->save();
        $ticket->documents()->create(['type' => 'Resolution', 'title' => 'Program Flow']);

        $this->actingAs($officer)->get('/officer/dashboard')
            ->assertSee('Waiting for: Dela Cruz, Ana (Adviser)')
            ->assertDontSee('Signatory: Dela Cruz');

        $this->actingAs($officer)->get('/officer/tickets')
            ->assertSee('Waiting For')
            ->assertSee('Waiting for Dela Cruz, Ana (Adviser) to sign, step 1 of 1')
            ->assertDontSee('Current Signatory')
            ->assertDontSee('With Dela Cruz');

        $this->actingAs($officer)->get('/officer/signatories')
            ->assertSee('Waiting for them')
            ->assertDontSee('Papers with them');

        $this->actingAs($adviser)->get('/signatory/review')
            ->assertSee('the officer brings each paper to you');
    }
}
