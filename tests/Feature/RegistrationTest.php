<?php

namespace Tests\Feature;

use App\Models\IdDocument;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Sign-up for officers and signatories (AUD-044, AUD-012, AUD-013, AUD-004).
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $digits;

    protected function setUp(): void
    {
        parent::setUp();
        $this->digits = Organization::create(['code' => 'DIGITS', 'name' => 'Digital Integrated Group of IT Students']);
    }

    public function test_an_officer_signs_up_as_pending_with_their_id_photo(): void
    {
        $this->post('/register/officer', $this->officerForm())
            ->assertRedirect('/login/officer')
            ->assertSessionHas('status', fn ($msg) => str_contains($msg, 'Account created'));

        $user = User::where('login_id', '2201234')->sole();
        $this->assertSame(['officer', 'pending', $this->digits->id, 'Secretary'],
            [$user->role, $user->status, $user->organization_id, $user->position]);
        $this->assertTrue(Hash::check('Secret#2026', $user->password));   // hashed, not plain text

        $id = $user->idDocument;
        $this->assertSame('image/jpeg', $id->mime);
        $this->assertGreaterThan(0, $id->size);
        $this->assertSame($id->size, strlen($id->data));   // the file itself is in the database
    }

    public function test_a_signatory_signs_up_with_an_employee_id_and_a_pdf(): void
    {
        $this->post('/register/signatory', [
            'first_name' => 'Ana', 'last_name' => 'Dela Cruz', 'employee_id' => 'EMP-0451',
            'email' => 'ana@lnu.edu.ph', 'phone' => '09181234567', 'position' => 'Adviser',
            'password' => 'Secret#2026', 'id_document' => $this->pdf(),
            'organization_id' => $this->digits->id,   // ignored: the admin puts signatories on routes
        ])->assertRedirect('/login/signatory');

        $user = User::where('login_id', 'EMP-0451')->sole();
        $this->assertSame(['signatory', 'pending', null], [$user->role, $user->status, $user->organization_id]);
        $this->assertSame('application/pdf', $user->idDocument->mime);
    }

    public function test_a_new_account_cannot_log_in_until_verified(): void
    {
        $this->post('/register/officer', $this->officerForm());

        $this->post('/login/officer', ['student_id' => '2201234', 'password' => 'Secret#2026'])
            ->assertSessionHasErrors('student_id');
        $this->assertGuest();
    }

    public function test_sign_up_cannot_choose_its_own_role_or_status(): void
    {
        $this->post('/register/officer', $this->officerForm(['role' => 'admin', 'status' => 'verified']));

        $user = User::where('login_id', '2201234')->sole();
        $this->assertSame(['officer', 'pending'], [$user->role, $user->status]);
    }

    public function test_the_id_file_is_checked_on_the_server(): void
    {
        // A PHP script renamed to .jpg: the content gives it away.
        // (A real file, not UploadedFile::fake(): fake files report their type from the NAME,
        // which would hide exactly the check this test is about.)
        $this->post('/register/officer', $this->officerForm([
            'id_document' => $this->realFile('id.jpg', '<?php system($_GET["c"]); ?>'),
        ]))->assertSessionHasErrors(['id_document' => 'Upload a photo (JPG, PNG or WEBP) or a PDF.']);

        // A real PDF, but over 2 MB (the browser check can be skipped)
        $this->post('/register/officer', $this->officerForm([
            'id_document' => $this->realFile('id.pdf', "%PDF-1.4\n".str_repeat('0', 2100 * 1024)),
        ]))->assertSessionHasErrors(['id_document' => 'The ID file must be 2 MB or smaller.']);

        // No file at all
        $this->post('/register/officer', $this->officerForm(['id_document' => null]))->assertSessionHasErrors('id_document');

        $this->assertSame(0, User::count());
        $this->assertSame(0, IdDocument::count());
    }

    public function test_the_form_rules(): void
    {
        $this->post('/register/officer', $this->officerForm([
            'password' => 'short', 'phone' => '12345', 'position' => 'President', 'organization_id' => 999,
        ]))->assertSessionHasErrors(['password', 'phone', 'position', 'organization_id']);

        $this->assertSame(0, User::count());
    }

    public function test_an_id_number_or_email_can_only_be_used_once(): void
    {
        $this->post('/register/officer', $this->officerForm());

        $this->post('/register/officer', $this->officerForm(['email' => 'other@lnu.edu.ph']))
            ->assertSessionHasErrors(['student_id' => 'This ID number already has an account.']);
        $this->post('/register/officer', $this->officerForm(['student_id' => '2209999']))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::count());
    }

    public function test_a_rejected_person_can_sign_up_again(): void
    {
        $this->post('/register/officer', $this->officerForm());
        User::where('login_id', '2201234')->update(['status' => 'rejected']);

        $this->post('/register/officer', $this->officerForm(['first_name' => 'MJ Bryan']))->assertRedirect('/login/officer');

        $user = User::where('login_id', '2201234')->sole();   // the old rejected account is gone
        $this->assertSame(['pending', 'MJ Bryan'], [$user->status, $user->first_name]);
    }

    public function test_the_officer_form_lists_real_organizations_and_there_is_no_admin_sign_up(): void
    {
        $this->get('/register/officer')->assertOk()->assertSee('DIGITS · Digital Integrated Group of IT Students')
            ->assertSee('name="id_document"', false);
        $this->get('/register/signatory')->assertOk()->assertSee('name="employee_id"', false)
            ->assertDontSee('name="organization', false);
        $this->get('/register/admin')->assertNotFound();
        $this->post('/register/admin', [])->assertNotFound();
    }

    public function test_sign_up_is_rate_limited(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post('/register/officer', $this->officerForm(['student_id' => "22000{$i}", 'email' => "u{$i}@lnu.edu.ph"]));
        }

        $this->post('/register/officer', $this->officerForm(['student_id' => '2200099', 'email' => 'u99@lnu.edu.ph']))
            ->assertStatus(429);
        $this->assertSame(5, User::count());
    }

    // ---------- Helpers ----------

    private function officerForm(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'MJ', 'last_name' => 'Labastida', 'student_id' => '2201234',
            'organization_id' => $this->digits->id, 'email' => 'mj@lnu.edu.ph', 'phone' => '09171234567',
            'position' => 'Secretary', 'password' => 'Secret#2026',
            'id_document' => UploadedFile::fake()->image('school-id.jpg', 600, 380),
        ], $overrides);
    }

    private function pdf(): UploadedFile
    {
        return $this->realFile('employee-id.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    }

    /** An upload whose type is detected from its CONTENT, like a real browser upload. */
    private function realFile(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'docmon');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }
}
