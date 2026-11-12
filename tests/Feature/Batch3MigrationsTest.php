<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Batch 3 database changes: the Ref No. unique rule that frees a fully rejected
 * ticket's number (AUD-079) and the availability time stamp (AUD-088). These check the
 * DATABASE rules directly; the pages that use them come in Batch 4.
 */
class Batch3MigrationsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $digits;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->digits = Organization::create(['code' => 'DIGITS', 'name' => 'DIGITS']);
        $this->officer = User::factory()->create(['role' => 'officer', 'status' => 'verified', 'login_id' => '2201234',
            'organization_id' => $this->digits->id]);
    }

    private function ticket(string $refNo, ?Organization $org = null): Ticket
    {
        $ticket = new Ticket(['ref_no' => $refNo, 'title' => 'DIGITS Week']);
        $ticket->code = Ticket::nextCode();
        $ticket->organization()->associate($org ?? $this->digits);
        $ticket->officer()->associate($this->officer);
        $ticket->save();

        return $ticket;
    }

    public function test_a_new_ticket_holds_its_ref_no_as_the_active_one(): void
    {
        $this->assertSame('2026-11-001', $this->ticket('2026-11-001')->active_ref_no);
    }

    public function test_the_database_refuses_two_live_tickets_with_one_ref_no(): void
    {
        $this->ticket('2026-11-001');

        $this->expectException(QueryException::class);
        $this->ticket('2026-11-001');
    }

    public function test_a_released_ref_no_can_be_used_again_and_the_old_ticket_keeps_it(): void
    {
        $rejected = $this->ticket('2026-11-001');
        $rejected->forceFill(['active_ref_no' => null])->save();   // what Batch 4 does on full rejection

        $refiled = $this->ticket('2026-11-001');

        $this->assertSame('2026-11-001', $refiled->active_ref_no);
        $this->assertSame('2026-11-001', $rejected->fresh()->ref_no);
        $this->assertNull($rejected->fresh()->active_ref_no);
    }

    public function test_two_released_tickets_with_one_ref_no_are_allowed(): void
    {
        foreach ([1, 2] as $ignored) {
            $this->ticket('2026-11-001')->forceFill(['active_ref_no' => null])->save();
        }

        $this->assertSame(2, Ticket::where('ref_no', '2026-11-001')->count());
    }

    public function test_another_organization_may_use_the_same_ref_no(): void
    {
        $this->ticket('2026-11-001');
        $this->ticket('2026-11-001', Organization::create(['code' => 'OTHER', 'name' => 'Other Org']));

        $this->assertSame(2, Ticket::count());
    }

    public function test_users_have_an_empty_availability_time_stamp(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'availability_updated_at'));
        $this->assertNull($this->officer->fresh()->availability_updated_at);
    }
}
