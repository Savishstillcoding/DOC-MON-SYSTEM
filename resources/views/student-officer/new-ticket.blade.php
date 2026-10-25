{{--
    DOC-MON · Student Officer · New Ticket
    The form is not connected yet: set the form's action to the store route later.
    Field names are ready for the controller:
      ref_no, title, organization, date_needed, purpose,
      documents[0][type], documents[0][title], documents[0][ref_no] (optional), documents[1][type], ...
--}}
@php
    $user = ['name' => 'Labastida, MJ Bryan', 'initials' => 'MJ', 'subtitle' => 'Secretary · DIGITS'];

    $menu = [
        ['label' => 'Dashboard',   'icon' => 'house',     'href' => url('/officer/dashboard')],
        ['label' => 'New Ticket',  'icon' => 'file-plus', 'href' => url('/officer/tickets/new')],
        ['label' => 'My Tickets',  'icon' => 'file-text', 'href' => url('/officer/tickets')],
        ['label' => 'Signatories', 'icon' => 'users',     'href' => url('/officer/signatories')],
        ['label' => 'Profile',     'icon' => 'user',      'href' => url('/officer/profile')],
    ];

    // The documents student organizations submit (confirmed list, 2026-10-22).
    // "Other (specify)" is the fallback for papers an org has that aren't on
    // the list: the Document Title then says what the paper is.
    $documentTypes = ['Resolution', 'Activity Proposal', 'Budgetary', 'Narrative/Accomplishment Report', 'Sem-End Report', 'Year-End Report', 'Other (specify)'];

    // Example rows: a resolution with its attached proposal and budgetary
    $rows = [
        ['type' => 'Resolution',        'title' => 'Resolution approving DIGITS Week 2026'],
        ['type' => 'Activity Proposal', 'title' => 'DIGITS Week Activity Proposal'],
        ['type' => 'Budgetary',         'title' => 'DIGITS Week Budgetary'],
    ];

    $route = [
        ['badge' => '1', 'name' => 'Dela Cruz, Ana', 'role' => 'Adviser',       'status' => 'Meeting',       'note' => '2 PM'],
        ['badge' => '2', 'name' => 'Reyes, Paolo',   'role' => 'SSC President', 'status' => 'Available',     'note' => null],
        ['badge' => '3', 'name' => 'Garcia, Ramon',  'role' => 'ODSOA',         'status' => 'Out of Office', 'note' => null],
        ['badge' => '4', 'name' => 'Santos, Liza',   'role' => 'VPSD',          'status' => 'Available',     'note' => null],
    ];
@endphp

<x-docmon.dashboard-layout title="New Ticket" :user="$user" :menu="$menu" active="New Ticket">

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
        <form class="nt-form" method="POST" action="#">
            @csrf

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
                    </div>
                    <div class="dm-field">
                        <label for="nt-title">Ticket Title</label>
                        <input id="nt-title" name="title" type="text" placeholder="e.x. DIGITS Week 2026 Requirements">
                    </div>
                </div>

                <div class="nt-two-cols">
                    <div class="dm-field">
                        <label for="nt-org">Organization</label>
                        <input id="nt-org" name="organization" type="text" value="DIGITS" readonly>
                    </div>
                    <div class="dm-field">
                        <label for="nt-date">Date Needed</label>
                        <input id="nt-date" name="date_needed" type="date">
                    </div>
                </div>

                <div class="dm-field">
                    <label for="nt-purpose">Purpose / Notes for Signatories</label>
                    <textarea id="nt-purpose" name="purpose" rows="3" placeholder="e.x. For the approval of the DIGITS Week activities..."></textarea>
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

                <div id="nt-doc-rows">
                    @foreach ($rows as $i => $row)
                        <div class="nt-doc-row">
                            <span class="nt-doc-num">{{ $i + 1 }}</span>
                            <select name="documents[{{ $i }}][type]" class="dm-select" data-field="type">
                                @foreach ($documentTypes as $type)
                                    <option @if ($type === $row['type']) selected @endif>{{ $type }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="documents[{{ $i }}][title]" value="{{ $row['title'] }}" placeholder="e.x. Program Flow" data-field="title">
                            <input type="text" name="documents[{{ $i }}][ref_no]" maxlength="20" placeholder="Optional" aria-label="Document Ref No. (optional)" data-field="ref_no">
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
                <p class="dm-panel-sub">DIGITS signatories, in order</p>
                <div class="dm-route dm-route-plain">
                    @foreach ($route as $s)
                        <x-docmon.signatory-row :badge="$s['badge']" :name="$s['name']" :role="$s['role']" :status="$s['status']" :note="$s['note']" />
                    @endforeach
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
            <select class="dm-select" data-field="type">
                @foreach ($documentTypes as $type)
                    <option>{{ $type }}</option>
                @endforeach
            </select>
            <input type="text" data-field="title" placeholder="e.x. Program Flow">
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
