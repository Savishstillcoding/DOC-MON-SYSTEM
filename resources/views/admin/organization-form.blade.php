{{--
    DOC-MON · Admin · Add / Manage organization (Admin\OrganizationController@create / @edit)
    $organization: a new one (Add) or an existing one with its counts (Manage).
    Add → POST /admin/organizations · Save → PUT /admin/organizations/{id} · Delete → DELETE (only when empty)
--}}
@php $isNew = ! $organization->exists; @endphp

<x-docmon.admin-layout :title="$isNew ? 'Add Organization' : 'Manage ' . $organization->code" active="Organizations">

    <header class="dm-page-header">
        <div>
            <h1 class="dm-page-title">{{ $isNew ? 'Add Organization' : 'Manage ' . $organization->code }}</h1>
            <p class="dm-page-sub">{{ $isNew ? 'After adding it, set up its approval route on Signatory Lists.' : $organization->name }}</p>
        </div>
        <div class="dm-header-actions">
            <a href="{{ url('/admin/organizations') }}" class="dm-btn dm-btn-outline">Back to Organizations</a>
        </div>
    </header>

    @error('organization') <p class="dm-form-alert" role="alert">{{ $message }}</p> @enderror

    <form method="POST" action="{{ url('/admin/organizations' . ($isNew ? '' : '/' . $organization->id)) }}">
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <section class="dm-card dm-panel">
            <div class="dm-panel-head">
                <div>
                    <h2 class="dm-panel-title">Organization Details</h2>
                    <p class="dm-panel-sub">The code is the short name shown everywhere (e.g. DIGITS, SSC).</p>
                </div>
                <button type="submit" class="dm-btn dm-btn-primary dm-btn-sm">{{ $isNew ? 'Add Organization' : 'Save Changes' }}</button>
            </div>

            <div class="pf-grid">
                <div class="dm-field">
                    <label for="og-code">Code</label>
                    <input id="og-code" name="code" type="text" maxlength="20" pattern="[A-Za-z0-9-]+" placeholder="e.x. SSC"
                           value="{{ old('code', $organization->code) }}" required>
                    @error('code') <p class="dm-field-error">{{ $message }}</p> @enderror
                </div>
                <div class="dm-field">
                    <label for="og-name">Full Name</label>
                    <input id="og-name" name="name" type="text" maxlength="255" placeholder="e.x. Supreme Student Council"
                           value="{{ old('name', $organization->name) }}" required>
                    @error('name') <p class="dm-field-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>
    </form>

    @unless ($isNew)
        @php $empty = ! $organization->officers_count && ! $organization->tickets_count && ! $organization->signatories_count; @endphp
        <section class="dm-card dm-panel og-manage">
            <dl class="dm-facts">
                <div><dt>Officers</dt><dd>{{ $organization->officers_count }}</dd></div>
                <div><dt>Signatories on its route</dt><dd>{{ $organization->signatories_count }}</dd></div>
                <div><dt>Tickets (all time)</dt><dd>{{ $organization->tickets_count }}</dd></div>
                <div><dt>Active tickets</dt><dd>{{ $organization->active_tickets_count }}</dd></div>
            </dl>
            <div class="og-actions">
                <a href="{{ url('/admin/signatory-lists?org=' . $organization->id) }}" class="dm-btn dm-btn-primary dm-btn-sm">Signatory List</a>
                <form method="POST" action="{{ url('/admin/organizations/' . $organization->id) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="dm-btn dm-btn-danger dm-btn-sm" @disabled(! $empty)
                            onclick="return confirm(@js('Delete ' . $organization->code . '? This cannot be undone.'))">Delete Organization</button>
                </form>
            </div>
            @unless ($empty)
                <p class="nt-note">It can't be deleted because it has officers, tickets or a route. Only an empty organization (e.g. one added by mistake) can be deleted.</p>
            @endunless
        </section>
    @endunless

</x-docmon.admin-layout>
