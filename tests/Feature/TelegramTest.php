<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Telegram notifications (Increment 7): connecting from the Profile page,
 * and the messages sent when papers move. Telegram itself is faked (Http::fake),
 * so nothing is really sent.
 */
class TelegramTest extends TestCase
{
    use RefreshDatabase;

    private Organization $digits;

    private User $officer;

    private User $adviser;   // step 1

    private User $vpsd;      // step 2, final say

    /** What the fake bot's getUpdates answers: the messages people sent it */
    private array $updates = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.token' => 'test-token', 'services.telegram.username' => 'DocMonTest_bot']);
        // One fake for the whole test (Laravel uses the first fake that matches,
        // so a second Http::fake() could not change the answer later)
        Http::fake(fn (Request $r) => Http::response([
            'ok' => true,
            'result' => str_ends_with($r->url(), '/getUpdates') ? $this->updates : [],
        ]));

        $this->digits = Organization::create(['code' => 'DIGITS', 'name' => 'DIGITS']);
        $this->adviser = $this->makeUser('signatory', 'EMP-1', ['first_name' => 'Ana', 'last_name' => 'Dela Cruz', 'telegram_chat_id' => '101']);
        $this->vpsd = $this->makeUser('signatory', 'EMP-2', ['first_name' => 'Liza', 'last_name' => 'Santos', 'telegram_chat_id' => '102']);
        $this->digits->signatories()->attach($this->adviser, ['step' => 1, 'title' => 'Adviser']);
        $this->digits->signatories()->attach($this->vpsd, ['step' => 2, 'title' => 'VPSD']);
        $this->officer = $this->makeUser('officer', '2201234', [
            'organization_id' => $this->digits->id, 'first_name' => 'MJ', 'last_name' => 'Labastida', 'telegram_chat_id' => '100',
        ]);
    }

    public function test_telegram_is_off_in_tests_unless_a_test_turns_it_on(): void
    {
        // phpunit.xml empties the token, so the real one in .env is never used by a test
        $this->assertSame('', env('TELEGRAM_BOT_TOKEN'));
    }

    // ---------- Connecting ----------

    #[DataProvider('roles')]
    public function test_connecting_saves_the_chat_that_pressed_start(string $role): void
    {
        $user = $role === 'officer' ? $this->officer : $this->adviser;
        $user->forceFill(['telegram_chat_id' => null])->save();

        // 1. Connect: a one-time code, shown as a t.me link
        $this->actingAs($user)->post("/{$role}/telegram/connect")->assertRedirect();
        $code = session('telegram_connect')['code'];
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{32}$/', $code);
        $this->get("/{$role}/profile")
            ->assertSee("https://t.me/DocMonTest_bot?start={$code}", false)
            ->assertSee('I pressed Start');

        // 2. The user pressed Start (among other people's messages)
        $this->updates = [
            $this->update(777, '/start someoneElsesCode'),
            $this->update(555, "/start {$code}"),
            $this->update(888, 'hello bot'),
        ];

        // 3. Check: that chat is saved, and it gets a test message
        $this->post("/{$role}/telegram/check")->assertSessionHas('status', 'Telegram connected. We sent you a test message.');

        $this->assertSame('555', $user->fresh()->telegram_chat_id);
        $this->assertNull(session('telegram_connect'));
        $this->assertSame('Connected Telegram', ActivityLog::latest('id')->first()->action);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/sendMessage') && $r['chat_id'] === '555'
            && str_contains($r['text'], 'is connected'));
        $this->get("/{$role}/profile")->assertSee('Connected')->assertSee('Disconnect');
    }

    public function test_someone_elses_start_does_not_connect_me(): void
    {
        $this->officer->forceFill(['telegram_chat_id' => null])->save();
        $this->actingAs($this->officer)->post('/officer/telegram/connect');
        $this->updates = [$this->update(777, '/start someoneElsesCode'), $this->update(778, '/start')];

        $this->post('/officer/telegram/check')->assertSessionHasErrors('telegram', null, 'telegram');

        $this->assertNull($this->officer->fresh()->telegram_chat_id);
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/sendMessage'));
    }

    public function test_check_without_a_fresh_code_asks_to_connect_again(): void
    {
        $this->officer->forceFill(['telegram_chat_id' => null])->save();

        // Never clicked Connect
        $this->actingAs($this->officer)->post('/officer/telegram/check')
            ->assertSessionHasErrors(['telegram' => 'That link expired. Click Connect Telegram again.'], null, 'telegram');

        // Clicked it 11 minutes ago
        $this->post('/officer/telegram/connect');
        $code = session('telegram_connect')['code'];
        $this->updates = [$this->update(555, "/start {$code}")];
        $this->travel(11)->minutes();

        $this->post('/officer/telegram/check')->assertSessionHasErrors('telegram', null, 'telegram');
        $this->assertNull($this->officer->fresh()->telegram_chat_id);
        Http::assertNothingSent();
    }

    public function test_disconnecting_stops_the_messages(): void
    {
        $this->actingAs($this->officer)->delete('/officer/telegram')->assertSessionHas('status');

        $this->assertNull($this->officer->fresh()->telegram_chat_id);
        $this->get('/officer/profile')->assertSee('Connect Telegram')->assertDontSee('Disconnect');
    }

    public function test_one_user_cannot_disconnect_another(): void
    {
        // The routes act on the logged-in user only: there is no user ID to change
        $this->actingAs($this->adviser)->delete('/signatory/telegram');

        $this->assertSame('100', $this->officer->fresh()->telegram_chat_id);
        $this->actingAs($this->adviser)->delete('/officer/telegram')->assertForbidden();
    }

    public function test_without_a_bot_the_panel_says_so_and_nothing_is_called(): void
    {
        config(['services.telegram.token' => null]);

        $this->actingAs($this->officer)->get('/officer/profile')->assertSee('Telegram isn\'t set up on this server yet.', false)->assertDontSee('Connected');
        $this->post('/officer/telegram/connect')->assertSessionHasErrors('telegram', null, 'telegram');
        $this->submit(['Resolution']);

        $this->assertSame(1, Ticket::count());
        Http::assertNothingSent();
    }

    // ---------- The messages ----------

    public function test_a_paper_moving_through_the_route_messages_the_right_people(): void
    {
        // Submit → the Adviser (step 1)
        $doc = $this->submit(['Resolution'])->documents->sole();
        $this->assertSentTo('101', ['A paper is waiting for your signature', 'Resolution: Paper 1', 'From Labastida, MJ (DIGITS)', '/signatory/review']);

        // Return → the officer, with the remarks
        $this->act($this->adviser, $doc, 'return', 'Fix the date');
        $this->assertSentTo('100', ['returned for revision by Adviser', 'Remarks: Fix the date', '/officer/tickets']);

        // Resubmit → the same Adviser
        $this->actingAs($this->officer)->post("/officer/documents/{$doc->id}/resubmit");
        $this->assertSentTo('101', ['Revised paper is back']);

        // Approve → the officer ("next: VPSD") and the VPSD
        $this->act($this->adviser, $doc, 'approve');
        $this->assertSentTo('100', ['Paper “Paper 1” approved by Adviser. Next: VPSD (Santos, Liza).']);
        $this->assertSentTo('102', ['A paper is waiting for your signature']);

        // Final approval → the officer only
        $this->act($this->vpsd, $doc, 'approve');
        $this->assertSentTo('100', ['fully approved (final signature by VPSD)', 'collect the paper']);

        Http::assertSentCount(6);
    }

    public function test_a_rejection_tells_the_officer_why(): void
    {
        $doc = $this->submit(['Resolution'])->documents->sole();
        $this->act($this->adviser, $doc, 'reject', 'Wrong organization');

        $this->assertSentTo('100', ['❌ Paper “Paper 1” rejected by Adviser.', 'Remarks: Wrong organization']);
    }

    public function test_approve_all_sends_one_message_not_one_per_paper(): void
    {
        $ticket = $this->submit(['Resolution', 'Budgetary', 'Resolution']);
        Http::fake([]);   // forget the message about the submit (keeps the fake above)

        $this->actingAs($this->adviser)->post("/signatory/tickets/{$ticket->id}/approve-all");

        Http::assertSentCount(2);
        $this->assertSentTo('100', ['3 papers approved by Adviser.']);
        $this->assertSentTo('102', ['3 papers are waiting for your signature', '• Budgetary: Paper 2']);
    }

    public function test_every_verified_officer_of_the_organization_hears_about_a_ticket(): void
    {
        // AUD-075 + AUD-089: shared tickets, so the Deputy hears too; a deactivated officer doesn't,
        // nor does anyone from another organization, and one Telegram gets each message once
        $this->makeUser('officer', '2202222', ['organization_id' => $this->digits->id, 'telegram_chat_id' => '200']);
        $this->makeUser('officer', '2202223', ['organization_id' => $this->digits->id, 'telegram_chat_id' => '100']);
        $this->makeUser('officer', '2202224', ['organization_id' => $this->digits->id, 'telegram_chat_id' => '300'])
            ->forceFill(['status' => 'deactivated'])->save();
        $elsewhere = Organization::create(['code' => 'OTHER', 'name' => 'Other Org']);
        $this->makeUser('officer', '2202225', ['organization_id' => $elsewhere->id, 'telegram_chat_id' => '400']);

        $doc = $this->submit(['Resolution'])->documents->sole();
        Http::fake([]);   // forget the message about the submit (keeps the fake above)

        $this->act($this->adviser, $doc, 'return', 'Fix the date');

        $this->assertSentTo('100', ['returned for revision']);
        $this->assertSentTo('200', ['returned for revision']);
        Http::assertSentCount(2);
    }

    public function test_people_without_telegram_get_nothing(): void
    {
        User::query()->update(['telegram_chat_id' => null]);

        $doc = $this->submit(['Resolution'])->documents->sole();
        $this->act($this->adviser, $doc, 'approve');

        Http::assertNothingSent();
    }

    public function test_when_telegram_is_down_the_approval_still_saves_and_the_token_stays_out_of_the_log(): void
    {
        $doc = $this->submit(['Resolution'])->documents->sole();
        Http::fake(fn () => throw new ConnectionException('cURL error 6 for https://api.telegram.org/bottest-token/sendMessage'));
        Log::spy();

        $this->act($this->adviser, $doc, 'approve')->assertSessionHas('status', fn ($s) => str_starts_with($s, 'Approved'));

        $this->assertSame(2, $doc->fresh()->current_step);
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'bot***/sendMessage')
            && ! str_contains($message, 'test-token'));
    }

    public function test_the_new_ticket_page_only_promises_telegram_when_connected(): void
    {
        // AUD-058: it promised "an SMS and Telegram message" before either existed
        $this->actingAs($this->officer)->get('/officer/tickets/new')
            ->assertSee("You'll get a Telegram message every time", false)
            ->assertDontSee('SMS');

        $this->officer->forceFill(['telegram_chat_id' => null])->save();
        $this->actingAs($this->officer)->get('/officer/tickets/new')
            ->assertSee('Connect Telegram on your Profile.')
            ->assertDontSee("You'll get a Telegram message", false);
    }

    // ---------- The 🔔 in page headers (AUD-059) ----------

    public static function bellPages(): array
    {
        return [
            'officer dashboard' => ['officer', '/officer/dashboard'],
            'new ticket' => ['officer', '/officer/tickets/new'],
            'signatories' => ['officer', '/officer/signatories'],
            'signatory dashboard' => ['signatory', '/signatory/dashboard'],
        ];
    }

    #[DataProvider('bellPages')]
    public function test_the_bell_links_to_telegram_and_shows_if_it_is_on(string $role, string $page): void
    {
        $user = $role === 'officer' ? $this->officer : $this->adviser;
        $link = 'href="'.url("/{$role}/profile#telegram").'" class="dm-icon-btn dm-bell"';

        // Was a <button> that did nothing
        $this->actingAs($user)->get($page)->assertOk()
            ->assertSee($link, false)
            ->assertSee('Telegram notifications are on')
            ->assertSee('dm-bell-dot', false)
            ->assertDontSee('aria-label="Notifications"', false);

        $user->forceFill(['telegram_chat_id' => null])->save();
        $this->actingAs($user->fresh())->get($page)
            ->assertSee($link, false)
            ->assertSee('Telegram notifications are off')
            ->assertDontSee('dm-bell-dot', false);
    }

    // ---------- Helpers ----------

    public static function roles(): array
    {
        return ['officer' => ['officer'], 'signatory' => ['signatory']];
    }

    private function update(int $chatId, string $text): array
    {
        static $id = 1;

        return ['update_id' => $id++, 'message' => ['chat' => ['id' => $chatId, 'type' => 'private'], 'text' => $text]];
    }

    private function assertSentTo(string $chatId, array $parts): void
    {
        Http::assertSent(function (Request $r) use ($chatId, $parts) {
            if (! str_ends_with($r->url(), '/sendMessage') || $r['chat_id'] !== $chatId) {
                return false;
            }

            foreach ($parts as $part) {
                if (! str_contains($r['text'], $part)) {
                    return false;
                }
            }

            return true;
        });
    }

    private function submit(array $types): Ticket
    {
        $this->actingAs($this->officer)->post('/officer/tickets', [
            'ref_no' => 'REF-'.(Ticket::count() + 1),
            'title' => 'DIGITS Week',
            'documents' => collect($types)->map(fn ($type, $i) => ['type' => $type, 'title' => 'Paper '.($i + 1)])->all(),
        ])->assertSessionHasNoErrors();

        return Ticket::with('documents')->latest('id')->first();
    }

    private function act(User $signatory, Document $doc, string $action, ?string $remarks = null)
    {
        return $this->actingAs($signatory)->post("/signatory/documents/{$doc->id}/action", array_filter(['action' => $action, 'remarks' => $remarks]));
    }

    private function makeUser(string $role, string $loginId, array $extra = []): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'verified', 'login_id' => $loginId] + $extra);
    }
}
