<?php

namespace App\Http\Controllers\Auth;

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
 * Sign-up for student officers and signatories (AUD-044). The account is saved as
 * "pending" together with the uploaded ID (AUD-012); it can't log in until the
 * admin has checked the ID. There is no admin sign-up.
 */
class RegisterController extends Controller
{
    private const FORMS = [
        'officer' => 'auth.studentofficer-signup',
        'signatory' => 'auth.signatory-signup',
    ];

    /** The ID number field on each form: officers have a student ID, signatories an employee ID. */
    private const ID_FIELDS = [
        'officer' => 'student_id',
        'signatory' => 'employee_id',
    ];

    public function create(string $role): View
    {
        return view(self::FORMS[$role], [
            'organizations' => Organization::orderBy('code')->get(['id', 'code', 'name']),
            'positions' => User::OFFICER_POSITIONS,
        ]);
    }

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
            'phone' => ['required', 'regex:/^09\d{9}$/'],
            'password' => ['required', 'string', Password::defaults()],
            // mimes checks the file's real content, not just its name; the browser
            // check in register.js can be skipped, so this is the one that counts
            'id_document' => ['required', 'file', 'mimes:'.implode(',', IdDocument::MIMES), 'max:'.IdDocument::MAX_KB],
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
                    'phone' => $data['phone'],
                    'position' => $data['position'],
                    'organization_id' => $role === 'officer' ? $data['organization_id'] : null,
                    'password' => $data['password'],   // hashed by the User model's cast
                ]);
                // Not fillable on purpose: set by the app only, never by the form
                $user->forceFill(['role' => $role, 'status' => 'pending'])->save();

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
