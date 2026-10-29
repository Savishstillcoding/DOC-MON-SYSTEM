{{--
    DOC-MON · Student Officer · New Ticket
    Posts to TicketController@store. Fields:
      ref_no, title, date_needed, purpose,
      documents[0][type], documents[0][title], documents[0][ref_no] (optional), documents[1][type], ...
    The organization is shown but not sent: the server takes it from the logged-in officer.
    From the controller: $organization (with its signatories), $documentTypes
--}}
@php
    $menu = [
        ['label' => 'Dashboard',   'icon' => 'house',     'href' => url('/officer/dashboard')],
        ['label' => 'New Ticket',  'icon' => 'file-plus', 'href' => url('/officer/tickets/new')],
        ['label' => 'My Tickets',  'icon' => 'file-text', 'href' => url('/officer/tickets')],
        ['label' => 'Signatories', 'icon' => 'users',     'href' => url('/officer/signatories')],
        ['label' => 'Profile',     'icon' => 'user',      'href' => url('/officer/profile')],
    ];

    // Document rows: what was typed before a failed submit, or one empty row (Single)
    $rows = old('documents', [['type' => $documentTypes[0], 'title' => '', 'ref_no' => '']]);

    // Error messages about the document rows (documents, documents.0.title, ...)
    $documentErrors = collect($errors->get('documents'))->merge(collect($errors->get('documents.*'))->flatten());
@endphp

<x-docmon.dashboard-layout title="New Ticket" :menu="$menu" active="New Ticket">

    <header class="dm-page-header">
        <div>
            <h1 class="dm-page-title">New Ticket</h1>
            <p class="dm-page-sub">Add one document for a Single ticket, or more for a Batch ticket.</p>
        </div>
        <div class="dm-header-actions">
            <button type="button" class="dm-icon-btn" aria-label="Notifications"><x-docmon.icon name="bell" size="18" /></button>
        </div>
    </header>

    <div class="dm-columns">
        <form class="nt-form" method="POST" action="{{ url('/officer/tickets') }}">
            @csrf

            @error('ticket')
                <p class="dm-form-alert" role="alert">{{ $message }}</p>
            @enderror

            {{-- Ticket Details --}}
            <section class="dm-card dm-panel">
                <h2 class="dm-panel-title">Ticket Details</h2>
                <p class="dm-panel-sub">Basic info about this request</p>

                {{-- Ref No. = the resolution number the Secretary/Deputy assigns. Required, and
                     unique per organization in the database (tickets.ref_no). maxlength matches that column. --}}
                <div class="nt-two-cols">
                    <div class="dm-field">
                        <label for="nt-ref">Ref No. (Resolution No.)</label>
                        <input id="nt-ref" name="ref_no" type="text" maxlength="20" placeholder="e.x. 2026-09-008" value="{{ old('ref_no') }}" required>
                        @error('ref_no') <p class="dm-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="dm-field">
                        <label for="nt-title">Ticket Title</label>
                        <input id="nt-title" name="title" type="text" maxlength="255" placeholder="e.x. DIGITS Week 2026 Requirements" value="{{ old('title') }}" required>
                        @error('title') <p class="dm-field-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="nt-two-cols">
                    <div class="dm-field">
                        <label for="nt-org">Organization</label>
                        <input id="nt-org" type="text" value="{{ $organization?->code }}" readonly>
                    </div>
                    <div class="dm-field">
                        <label for="nt-date">Date Needed</label>
                        <input id="nt-date" name="date_needed" type="date" min="{{ today()->toDateString() }}" value="{{ old('date_needed') }}">
                        @error('date_needed') <p class="dm-field-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="dm-field">
                    <label for="nt-purpose">Purpose / Notes for Signatories</label>
                    <textarea id="nt-purpose" name="purpose" rows="3" maxlength="2000" placeholder="e.x. For the approval of the DIGITS Week activities...">{{ old('purpose') }}</textarea>
                    @error('purpose') <p class="dm-field-error">{{ $message }}</p> @enderror
                </div>
            </section>

            {{-- Documents --}}
            <section class="dm-card dm-panel">
                <div class="dm-panel-head">
                    <div>
                        <h2 class="dm-panel-title">Documents</h2>
                        <p class="dm-panel-sub">List the physical documents you'll hand in · one row = Single, more = Batch</p>
                    </div>
                    <span class="nt-count" id="nt-count">Batch · {{ count($rows) }} documents</span>
                </div>

                <div class="nt-doc-labels">
                    <span></span><span>Document Type</span><span>Document Title</span><span>Ref No. (if any)</span><span></span>
                </div>

                @if ($documentErrors->isNotEmpty())
                    <ul class="dm-form-alert" role="alert">
                        @foreach ($documentErrors as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                @endif

                <div id="nt-doc-rows">
                    @foreach (array_values($rows) as $i => $row)
                        <div class="nt-doc-row">
                            <span class="nt-doc-num">{{ $i + 1 }}</span>
                            <select name="documents[{{ $i }}][type]" class="dm-select" data-field="type" aria-label="Document type">
                                @foreach ($documentTypes as $type)
                                    <option @selected($type === ($row['type'] ?? null))>{{ $type }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="documents[{{ $i }}][title]" value="{{ $row['title'] ?? '' }}" maxlength="255" placeholder="e.x. Program Flow" aria-label="Document title" data-field="title" required>
                            <input type="text" name="documents[{{ $i }}][ref_no]" value="{{ $row['ref_no'] ?? '' }}" maxlength="20" placeholder="Optional" aria-label="Document Ref No. (optional)" data-field="ref_no">
                            <button type="button" class="dm-icon-btn nt-remove" aria-label="Remove document"><x-docmon.icon name="x" size="18" /></button>
                        </div>
                    @endforeach
                </div>

                <button type="button" class="dm-btn dm-btn-outline" id="nt-add"><x-docmon.icon name="plus" size="16" /> Add Document</button>
            </section>

            <div class="nt-actions">
                <a href="{{ url('/officer/dashboard') }}" class="dm-btn dm-btn-outline">Cancel</a>
                <button type="submit" class="dm-btn dm-btn-primary">Submit Ticket</button>
            </div>
        </form>

        <div class="dm-side">
            {{-- Approval Route --}}
            <section class="dm-card dm-panel">
                <h2 class="dm-panel-title">Approval Route</h2>
                <p class="dm-panel-sub">{{ $organization?->code }} signatories, in order</p>
                <div class="dm-route dm-route-plain">
                    @forelse ($organization?->signatories ?? [] as $signatory)
                        <x-docmon.signatory-row :badge="$signatory->pivot->step" :name="$signatory->displayName()" :role="$signatory->pivot->title"
                            :status="$signatory->availability ?? 'Available'" :note="$signatory->back_at" />
                    @empty
                        <p class="nt-note">No signatories yet. Ask the admin to set up your organization's approval route.</p>
                    @endforelse
                </div>
            </section>

            {{-- Notifications info --}}
            <section class="dm-card dm-panel">
                <h2 class="dm-panel-title">Notifications</h2>
                <p class="nt-note">You'll get an SMS and Telegram message every time a signatory approves, returns, or rejects this ticket.</p>
            </section>
        </div>
    </div>

    {{-- Add / remove document rows and keep the numbering + Single/Batch label correct --}}
    <template id="nt-row-template">
        <div class="nt-doc-row">
            <span class="nt-doc-num"></span>
            <select class="dm-select" data-field="type" aria-label="Document type">
                @foreach ($documentTypes as $type)
                    <option>{{ $type }}</option>
                @endforeach
            </select>
            <input type="text" data-field="title" maxlength="255" placeholder="e.x. Program Flow" aria-label="Document title" required>
            <input type="text" data-field="ref_no" maxlength="20" placeholder="Optional" aria-label="Document Ref No. (optional)">
            <button type="button" class="dm-icon-btn nt-remove" aria-label="Remove document"><x-docmon.icon name="x" size="18" /></button>
        </div>
    </template>

    <script>
        (function () {
            const rows = document.getElementById('nt-doc-rows');
            const count = document.getElementById('nt-count');

            function renumber() {
                const all = rows.querySelectorAll('.nt-doc-row');
                all.forEach((row, i) => {
                    row.querySelector('.nt-doc-num').textContent = i + 1;
                    // every field says which one it is (data-field="type" / "title" / "ref_no")
                    row.querySelectorAll('[data-field]').forEach((field) => {
                        field.name = `documents[${i}][${field.dataset.field}]`;
                    });
                });
                count.textContent = all.length > 1 ? `Batch · ${all.length} documents` : 'Single · 1 document';
            }

            document.getElementById('nt-add').addEventListener('click', () => {
                const row = document.getElementById('nt-row-template').content.firstElementChild.cloneNode(true);
                rows.appendChild(row);
                renumber();
            });

            rows.addEventListener('click', (e) => {
                const btn = e.target.closest('.nt-remove');
                if (btn && rows.children.length > 1) {   // always keep at least one document
                    btn.closest('.nt-doc-row').remove();
                    renumber();
                }
            });

            renumber();
        })();
    </script>

</x-docmon.dashboard-layout>
