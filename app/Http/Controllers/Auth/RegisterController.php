<?php

namespace App\Http\Controllers\Auth;

/*
| READING GUIDE 8/15: RegisterController
|
| WHAT: a controller (the code that answers a page or form): the sign-up
|       pages.
| USED FOR: creating officer and signatory accounts as "pending", with an ID
|           photo.
| KEY IDEA: it can't log in until the admin checks the ID, so fake accounts
|           can't pass as real officers or signatories.
*/

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\IdDocument;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * RegisterController = the sign-up pages for student officers and signatories.
 * New accounts are saved as "pending" with an ID photo and can't log in until the
 * admin checks the ID. There is no admin sign-up.
 */
class RegisterController extends Controller
{
    private const FORMS = [
        'officer' => 'auth.studentofficer-signup',
        'signatory' => 'auth.signatory-signup',
    ];

    /** ID_FIELDS = the name of the ID box on each form: officers type a student ID, signatories an employee ID. */
    private const ID_FIELDS = [
        'officer' => 'student_id',
        'signatory' => 'employee_id',
    ];

    /** create() = shows the sign-up form (with the list of organizations). */
    public function create(string $role): View
    {
        return view(self::FORMS[$role], [
            'organizations' => Organization::orderBy('code')->get(['id', 'code', 'name']),
            'positions' => User::OFFICER_POSITIONS,
        ]);
    }

    /**
     * store() = what happens when someone presses Sign Up.
     * How it works:
     *   1. Check every field (required, unique ID and email, ID photo max 2 MB, ...).
     *      Anything wrong → back to the form with the error messages.
     *   2. Save everything together (all or nothing):
     *        - remove an older REJECTED sign-up with the same ID or email
     *        - create the account as "pending"
     *        - save the ID photo
     *   3. Go to the log-in page: "The admin will check your ID."
     */
    public function store(Request $request, string $role): RedirectResponse
    {
        $idField = self::ID_FIELDS[$role];

        // A rejected account doesn't block its ID number or email, so the person
        // can sign up again (e.g. with a clearer photo); see the cleanup below
        $notRejected = fn ($query) => $query->where('status', '!=', 'rejected');

        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            $idField => ['required', 'string', 'max:30', Rule::unique('users', 'login_id')->where($notRejected)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->where($notRejected)],
            // Optional: no feature uses the phone number since SMS was dropped.
            // The regex means: "09" followed by exactly 9 more digits, e.g. 09171234567
            'phone' => ['nullable', 'regex:/^09\d{9}$/'],
            // Password::defaults() = our password rules (at least 8 characters, letters and
            // numbers, not a known leaked password); set in AppServiceProvider
            'password' => ['required', 'string', Password::defaults()],
            // mimes: checks what the file REALLY is by looking inside it, not just its name,
            // so a virus renamed to "id.jpg" is refused. register.js checks in the browser
            // too, but a browser check can be skipped; this server check can't.
            'id_document' => ['required', 'file', 'mimes:'.implode(',', IdDocument::MIMES), 'max:'.IdDocument::MAX_KB],
            // The privacy notice checkbox must be ticked; the time is saved on the account
            'privacy_accepted' => ['accepted'],
        ];

        $rules += $role === 'officer'
            ? [
                'organization_id' => ['required', Rule::exists('organizations', 'id')],
                'position' => ['required', Rule::in(User::OFFICER_POSITIONS)],
            ]
            : [
                // Signatories type their title; the admin puts them on an organization's route
                'position' => ['required', 'string', 'max:100'],
            ];

        $data = $request->validate($rules, [
            "{$idField}.unique" => 'This ID number already has an account.',
            'privacy_accepted.accepted' => 'Please read and accept the Privacy Notice to sign up.',
            'email.unique' => 'Another account already uses this email.',
            'phone.regex' => 'Enter an 11-digit number starting with 09.',
            'organization_id.required' => 'Choose your organization.',
            'id_document.required' => 'Upload a photo or PDF of your ID.',
            'id_document.mimes' => 'Upload a photo (JPG, PNG or WEBP) or a PDF.',
            'id_document.max' => 'The ID file must be 2 MB or smaller.',
        ]);

        $file = $request->file('id_document');

        try {
            DB::transaction(function () use ($data, $role, $idField, $file) {
                // Remove an earlier REJECTED sign-up with the same ID number or email.
                // It never logged in, so it has no tickets or history to lose.
                User::where('status', 'rejected')
                    ->where(fn ($q) => $q->where('login_id', $data[$idField])->orWhere('email', $data['email']))
                    ->delete();

                $user = new User([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'login_id' => $data[$idField],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'position' => $data['position'],
                    'organization_id' => $role === 'officer' ? $data['organization_id'] : null,
                    'password' => $data['password'],   // saved as a bcrypt hash automatically (casts() in User.php)
                ]);
                // forceFill: role and status are not fillable (see User.php), so we set them
                // here on purpose. The form itself can never set them.
                $user->forceFill(['role' => $role, 'status' => 'pending', 'privacy_accepted_at' => now()])->save();

                ActivityLog::record($user, 'verification', 'Signed up',
                    collect([$user->organization?->code, $user->position])->filter()->implode(' · ').' · waiting for verification');

                // Same transaction: no account without its ID, and no ID without its account
                $user->idDocument()->save((new IdDocument)->forceFill([
                    'mime' => $file->getMimeType(),   // detected from the content, not the browser's claim
                    'size' => $file->getSize(),
                    'data' => $file->get(),
                ]));
            });
        } catch (UniqueConstraintViolationException) {
            // Two sign-ups with the same ID or email at the same moment: the database stopped the second
            return back()->withInput($request->except('password'))
                ->withErrors([$idField => 'This ID number or email already has an account.']);
        }

        return redirect("/login/{$role}")->with('status',
            'Account created! The admin will check your ID. You can log in once your account is verified.');
    }
}
