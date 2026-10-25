<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The rules the DOC-MON database enforces by itself (see database/migrations).
 * Each test runs on a fresh, empty in-memory database (phpunit.xml), so it
 * never touches your local SQLite file or TiDB.
 */
class DatabaseRulesTest extends TestCase
{
    // Runs all migrations before each test and wipes everything afterwards
    use RefreshDatabase;

    // ---------- Signatory order (organization_signatory) ----------

    public function test_an_organization_cannot_have_two_signatories_on_the_same_step(): void
    {
        $digits = $this->makeOrganization('DIGITS');
        $this->addSignatory($digits, User::factory()->create(), step: 1, title: 'Adviser');

        $this->assertThrows(
            fn () => $this->addSignatory($digits, User::factory()->create(), step: 1, title: 'VPSD'),
            QueryException::class,
        );
    }

    public function test_a_signatory_is_listed_only_once_per_organization(): void
    {
        $digits = $this->makeOrganization('DIGITS');
        $ana = User::factory()->create();
        $this->addSignatory($digits, $ana, step: 1, title: 'Adviser');

        $this->assertThrows(
            fn () => $this->addSignatory($digits, $ana, step: 2, title: 'Adviser'),
            QueryException::class,
        );
    }

    public function test_one_signatory_can_sign_for_many_organizations_at_different_steps(): void
    {
        // The "class schedule" idea: the step belongs to the pair, not the person
        $vpsd = User::factory()->create();
        $this->addSignatory($this->makeOrganization('DIGITS'), $vpsd, step: 4, title: 'VPSD');
        $this->addSignatory($this->makeOrganization('OTHER'), $vpsd, step: 3, title: 'VPSD');

        $this->assertDatabaseCount('organization_signatory', 2);
    }

    // ---------- Tickets ----------

    public function test_a_ref_no_can_only_be_used_once_per_organization(): void
    {
        $digits = $this->makeOrganization('DIGITS');
        $officer = User::factory()->create();
        $this->makeTicket($digits, $officer, refNo: '2026-09-008');

        $this->assertThrows(
            fn () => $this->makeTicket($digits, $officer, refNo: '2026-09-008'),
            QueryException::class,
        );
    }

    public function test_two_organizations_can_use_the_same_ref_no(): void
    {
        $officer = User::factory()->create();
        $this->makeTicket($this->makeOrganization('DIGITS'), $officer, refNo: '2026-09-008');
        $this->makeTicket($this->makeOrganization('OTHER'), $officer, refNo: '2026-09-008');

        $this->assertDatabaseCount('tickets', 2);
    }

    public function test_a_ticket_requires_a_ref_no(): void
    {
        $digits = $this->makeOrganization('DIGITS');
        $officer = User::factory()->create();

        $this->assertThrows(
            fn () => $this->makeTicket($digits, $officer, refNo: null),
            QueryException::class,
        );
    }

    // ---------- Documents ----------

    public function test_a_new_document_starts_pending_with_the_first_signatory(): void
    {
        $ticket = $this->makeTicket($this->makeOrganization('DIGITS'), User::factory()->create());
        $document = $this->makeDocument($ticket);

        $this->assertDatabaseHas('documents', ['id' => $document, 'status' => 'pending', 'current_step' => 1]);
    }

    public function test_an_attached_document_can_have_its_own_ref_no_but_does_not_need_one(): void
    {
        $ticket = $this->makeTicket($this->makeOrganization('DIGITS'), User::factory()->create(), refNo: '2026-09-008');
        $proposal = $this->makeDocument($ticket, refNo: 'AP-2026-015');   // attached activity proposal, own number
        $letter = $this->makeDocument($ticket);                           // no number of its own

        $this->assertDatabaseHas('documents', ['id' => $proposal, 'ref_no' => 'AP-2026-015']);
        $this->assertDatabaseHas('documents', ['id' => $letter, 'ref_no' => null]);
    }

    // ---------- Deleting (cascade vs restrict) ----------

    public function test_deleting_an_untouched_ticket_also_deletes_its_documents(): void
    {
        $ticket = $this->makeTicket($this->makeOrganization('DIGITS'), User::factory()->create());
        $this->makeDocument($ticket);
        $this->makeDocument($ticket);

        DB::table('tickets')->where('id', $ticket)->delete();

        $this->assertDatabaseCount('documents', 0);   // cascade
    }

    public function test_a_ticket_cannot_be_deleted_once_a_signatory_has_acted_on_it(): void
    {
        $ticket = $this->makeTicket($this->makeOrganization('DIGITS'), User::factory()->create());
        $document = $this->makeDocument($ticket);
        $this->recordAction($document, User::factory()->create(), 'approved');

        $this->assertThrows(
            fn () => DB::table('tickets')->where('id', $ticket)->delete(),
            QueryException::class,
        );
        $this->assertDatabaseCount('document_actions', 1);   // history is still there
    }

    public function test_an_officer_with_tickets_cannot_be_deleted(): void
    {
        $officer = User::factory()->create();
        $this->makeTicket($this->makeOrganization('DIGITS'), $officer);

        $this->assertThrows(fn () => $officer->delete(), QueryException::class);
    }

    public function test_a_signatory_who_signed_something_cannot_be_deleted(): void
    {
        $ticket = $this->makeTicket($this->makeOrganization('DIGITS'), User::factory()->create());
        $ana = User::factory()->create();
        $this->recordAction($this->makeDocument($ticket), $ana, 'approved');

        $this->assertThrows(fn () => $ana->delete(), QueryException::class);
    }

    // ---------- Users ----------

    public function test_sign_up_data_cannot_make_someone_an_admin_or_verified(): void
    {
        // Pretend a sign-up form was tampered with to send role and status
        $user = User::create([
            'first_name' => 'MJ',
            'last_name' => 'Labastida',
            'login_id' => '2201234',
            'email' => 'mj@example.com',
            'password' => 'secret123',
            'role' => 'admin',
            'status' => 'verified',
        ]);

        $this->assertSame('officer', $user->fresh()->role);
        $this->assertSame('pending', $user->fresh()->status);
    }

    public function test_passwords_are_stored_hashed(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $this->assertNotSame('secret123', $user->password);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    public function test_two_accounts_cannot_share_a_login_id(): void
    {
        User::factory()->create(['login_id' => '2201234']);

        $this->assertThrows(
            fn () => User::factory()->create(['login_id' => '2201234']),
            QueryException::class,
        );
    }

    // ---------- Helpers: build test data in one line ----------
    // (Plain DB inserts for now. Once we create models for these tables in
    // the next lesson, these become things like Ticket::factory()->create().)

    private function makeOrganization(string $code): int
    {
        return DB::table('organizations')->insertGetId(['code' => $code, 'name' => $code]);
    }

    private function addSignatory(int $organizationId, User $user, int $step, string $title): void
    {
        DB::table('organization_signatory')->insert([
            'organization_id' => $organizationId,
            'user_id' => $user->id,
            'step' => $step,
            'title' => $title,
        ]);
    }

    private function makeTicket(int $organizationId, User $officer, ?string $refNo = '2026-09-008'): int
    {
        static $number = 0;
        $number++;

        return DB::table('tickets')->insertGetId([
            'code' => 'DM-TEST-'.$number,
            'ref_no' => $refNo,
            'organization_id' => $organizationId,
            'officer_id' => $officer->id,
            'title' => 'Test ticket',
        ]);
    }

    private function makeDocument(int $ticketId, ?string $refNo = null): int
    {
        return DB::table('documents')->insertGetId([
            'ticket_id' => $ticketId,
            'type' => 'Activity Proposal',
            'title' => 'Test document',
            'ref_no' => $refNo,
        ]);
    }

    private function recordAction(int $documentId, User $user, string $action): void
    {
        DB::table('document_actions')->insert([
            'document_id' => $documentId,
            'user_id' => $user->id,
            'step' => 1,
            'action' => $action,
        ]);
    }
}
