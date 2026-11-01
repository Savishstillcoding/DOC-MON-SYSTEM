{{--
    DOC-MON · Admin · Edit user (Admin\UserController@edit)
    From the controller: $account, $organizations, $positions.  → PUT /admin/users/{id}
    Role, status and password are not editable here (deactivate on Users; passwords:
    php artisan docmon:reset-password). A signatory's step title is set on Signatory Lists.
--}}
<x-docmon.admin-layout title="Edit User" active="Users">

    <header class="dm-page-header">
        <div>
            <h1 class="dm-page-title">Edit User</h1>
            <p class="dm-page-sub">{{ $account->displayName() }} · {{ $account->roleLabel() }} · <x-docmon.status-badge :status="$account->statusLabel()" /></p>
        </div>
        <div class="dm-header-actions">
            <a href="{{ url('/admin/users') }}" class="dm-btn dm-btn-outline">Back to Users</a>
        </div>
    </header>

    <form method="POST" action="{{ url('/admin/users/' . $account->id) }}">
        @csrf
        @method('PUT')

        <section class="dm-card dm-panel">
            <div class="dm-panel-head">
                <div>
                    <h2 class="dm-panel-title">Account Details</h2>
                    <p class="dm-panel-sub">Fix typos or update contact details. Change a name or ID number only if it matches their real ID.</p>
                </div>
                <button type="submit" class="dm-btn dm-btn-primary dm-btn-sm">Save Changes</button>
            </div>

            <div class="pf-grid">
                @foreach (['first_name' => 'First Name', 'last_name' => 'Last Name'] as $field => $label)
                    <div class="dm-field">
                        <label for="ue-{{ $field }}">{{ $label }}</label>
                        <input id="ue-{{ $field }}" name="{{ $field }}" type="text" maxlength="100" value="{{ old($field, $account->$field) }}" required>
                        @error($field) <p class="dm-field-error">{{ $message }}</p> @enderror
                    </div>
                @endforeach

                <div class="dm-field">
                    <label for="ue-login">{{ ['officer' => 'Student ID', 'signatory' => 'Employee ID'][$account->role] ?? 'Username' }}</label>
                    <input id="ue-login" name="login_id" type="text" maxlength="30" value="{{ old('login_id', $account->login_id) }}" required>
                    @error('login_id') <p class="dm-field-error">{{ $message }}</p> @enderror
                </div>

                @if ($account->role === 'officer')
                    <div class="dm-field">
                        <label for="ue-org">Organization</label>
                        <select id="ue-org" name="organization_id" class="dm-select" required>
                            @foreach ($organizations as $org)
                                <option value="{{ $org->id }}" @selected((int) old('organization_id', $account->organization_id) === $org->id)>{{ $org->code }} · {{ $org->name }}</option>
                            @endforeach
                        </select>
                        @error('organization_id') <p class="dm-field-error">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="dm-field">
                    <label for="ue-position">Position</label>
                    @if ($account->role === 'officer')
                        <select id="ue-position" name="position" class="dm-select" required>
                            @foreach ($positions as $position)
                                <option @selected(old('position', $account->position) === $position)>{{ $position }}</option>
                            @endforeach
                        </select>
                    @else
                        <input id="ue-position" name="position" type="text" maxlength="100" value="{{ old('position', $account->position) }}">
                    @endif
                    @error('position') <p class="dm-field-error">{{ $message }}</p> @enderror
                </div>

                <div class="dm-field">
                    <label for="ue-email">Email</label>
                    <input id="ue-email" name="email" type="email" maxlength="255" value="{{ old('email', $account->email) }}" required>
                    @error('email') <p class="dm-field-error">{{ $message }}</p> @enderror
                </div>

                <div class="dm-field">
                    <label for="ue-phone">Phone Number</label>
                    <input id="ue-phone" name="phone" type="tel" inputmode="numeric" maxlength="11" pattern="09[0-9]{9}" title="Enter an 11-digit number starting with 09" value="{{ old('phone', $account->phone) }}">
                    @error('phone') <p class="dm-field-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>
    </form>

</x-docmon.admin-layout>
