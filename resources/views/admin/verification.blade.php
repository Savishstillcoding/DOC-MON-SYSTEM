{{--
    DOC-MON · Admin · User Verification (Admin\VerificationController@index)
    From the controller: $requests (pending accounts, oldest first, 5 per page), $counts (per tab),
    $tab, $search, $selected (the account in the panel).
    The panel's form → POST /admin/users/{id}/review with decision = verify | reject.
    Approve needs all three checklist boxes; Reject needs a reason (shown to the person at log-in).
--}}
@php
    $tabUrl = fn ($label) => url('/admin/verification') . '?' . http_build_query(array_filter(['tab' => $label === 'All' ? null : $label, 'q' => $search]));
    $selectUrl = fn ($user) => url('/admin/verification') . '?' . http_build_query(array_filter([
        'tab' => $tab === 'All' ? null : $tab, 'q' => $search, 'page' => request('page'), 'user' => $user->id,
    ]));
@endphp

<x-docmon.admin-layout title="User Verification" active="User Verification">

    <header class="dm-page-header">
        <div>
            <h1 class="dm-page-title">User Verification</h1>
            <p class="dm-page-sub">Approve real accounts, reject fake ones.</p>
        </div>
        <form method="GET" action="{{ url('/admin/verification') }}" class="dm-header-actions">
            @if ($tab !== 'All') <input type="hidden" name="tab" value="{{ $tab }}"> @endif
            <label class="dm-search">
                <x-docmon.icon name="search" size="18" />
                <input type="search" name="q" value="{{ $search }}" placeholder="Search name or ID No." aria-label="Search requests">
            </label>
        </form>
    </header>

    @error('verify') <p class="dm-form-alert" role="alert">{{ $message }}</p> @enderror

    <div class="dm-columns">
        {{-- Requests --}}
        <section class="dm-card dm-panel dm-align-start">
            <div class="dm-panel-head">
                <div>
                    <h2 class="dm-panel-title">Requests</h2>
                    <p class="dm-panel-sub">
                        {{ $counts['All'] }} waiting for review
                        @if ($search !== '') · matching "{{ $search }}" · <a href="{{ url('/admin/verification') }}">clear</a> @endif
                    </p>
                </div>
            </div>

            <nav class="dm-tabs">
                @foreach ($counts as $label => $count)
                    <a href="{{ $tabUrl($label) }}" class="dm-tab {{ $label === $tab ? 'is-active' : '' }}">{{ $label }} ({{ $count }})</a>
                @endforeach
            </nav>

            @if ($requests->isEmpty())
                <p class="nt-note">{{ $search !== '' ? 'Nothing matches your search.' : 'No accounts are waiting for verification.' }}</p>
            @else
                <ul class="rv-queue">
                    @foreach ($requests as $u)
                        <li>
                            <a href="{{ $selectUrl($u) }}" class="rv-item {{ $selected && $selected->is($u) ? 'is-selected' : '' }}">
                                <div>
                                    <p class="dm-cell-main">{{ $u->displayName() }}</p>
                                    <p class="rv-title">{{ $u->roleLabel() }} · {{ collect([$u->organization?->code, $u->position])->filter()->implode(' ') }}</p>
                                </div>
                                <div class="rv-item-side">
                                    <span class="dm-cell-sub">{{ $u->created_at->format('M j') }}</span>
                                    <x-docmon.status-badge :status="$u->statusLabel()" />
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <footer class="dm-table-foot">
                    <span>Showing {{ $requests->firstItem() }}–{{ $requests->lastItem() }} of {{ $requests->total() }}</span>
                    <div class="dm-actions">
                        @if ($requests->onFirstPage())
                            <span class="dm-btn dm-btn-outline dm-btn-sm is-disabled" aria-disabled="true">‹ Prev</span>
                        @else
                            <a href="{{ $requests->previousPageUrl() }}" class="dm-btn dm-btn-outline dm-btn-sm">‹ Prev</a>
                        @endif
                        @if ($requests->hasMorePages())
                            <a href="{{ $requests->nextPageUrl() }}" class="dm-btn dm-btn-outline dm-btn-sm">Next ›</a>
                        @else
                            <span class="dm-btn dm-btn-outline dm-btn-sm is-disabled" aria-disabled="true">Next ›</span>
                        @endif
                    </div>
                </footer>
            @endif
        </section>

        {{-- The selected request --}}
        <aside class="dm-side">
            @if ($selected)
                @php $doc = $selected->idDocument; $idUrl = url('/admin/users/' . $selected->id . '/id-document'); @endphp
                <section class="dm-card dm-panel">
                    <div class="dm-panel-head">
                        <div>
                            <h2 class="dm-panel-title">{{ $selected->displayName() }}</h2>
                            <p class="dm-panel-sub">{{ $selected->roleLabel() }}@if ($selected->organization) · {{ $selected->organization->code }}@endif</p>
                        </div>
                        <x-docmon.status-badge :status="$selected->statusLabel()" />
                    </div>

                    {{-- The uploaded ID itself, so it can be compared with the details below --}}
                    <div class="uv-id">
                        @if (! $doc)
                            <p class="dm-cell-sub">No ID uploaded</p>
                        @elseif ($doc->mime === 'application/pdf')
                            <x-docmon.icon name="file-text" size="28" />
                            <p class="dm-cell-sub">PDF · {{ number_format($doc->size / 1024) }} KB</p>
                            <a href="{{ $idUrl }}" target="_blank" rel="noopener" class="dm-btn dm-btn-outline dm-btn-sm">Open full size</a>
                        @else
                            <img src="{{ $idUrl }}" alt="Uploaded ID of {{ $selected->displayName() }}">
                            <a href="{{ $idUrl }}" target="_blank" rel="noopener" class="dm-btn dm-btn-outline dm-btn-sm uv-open">Open full size</a>
                        @endif
                    </div>

                    <dl class="dm-facts">
                        <div><dt>{{ $selected->role === 'officer' ? 'Student ID' : 'Employee ID' }}</dt><dd>{{ $selected->login_id }}</dd></div>
                        @if ($selected->organization)
                            <div><dt>Organization</dt><dd>{{ $selected->organization->code }}</dd></div>
                        @endif
                        <div><dt>Position</dt><dd>{{ $selected->position ?: '—' }}</dd></div>
                        <div><dt>Email</dt><dd>{{ $selected->email }}</dd></div>
                        <div><dt>Phone</dt><dd>{{ $selected->phone ?: '—' }}</dd></div>
                        <div><dt>Submitted</dt><dd>{{ $selected->created_at->format('M j, Y · g:i A') }}</dd></div>
                    </dl>

                    <form method="POST" action="{{ url('/admin/users/' . $selected->id . '/review') }}" class="uv-form" data-review-form>
                        @csrf
                        <h3 class="rv-docs-title">Checklist</h3>
                        @foreach (['name_matches' => 'Name matches the ID', 'id_matches' => 'ID number matches the ID', 'photo_clear' => 'Photo is clear and not edited'] as $field => $label)
                            <label class="uv-check">
                                <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field)) data-check>
                                {{ $label }}
                            </label>
                        @endforeach
                        @if ($errors->hasAny(['name_matches', 'id_matches', 'photo_clear']))
                            <p class="dm-field-error">Tick every checklist item before approving.</p>
                        @endif

                        <label class="rv-label" for="uv-note">Reason (if rejecting)</label>
                        <textarea id="uv-note" name="review_note" rows="3" maxlength="500"
                                  placeholder="e.x. ID photo is blurry, please re-upload">{{ old('review_note') }}</textarea>
                        @error('review_note') <p class="dm-field-error">{{ $message }}</p> @enderror

                        <div class="rv-two">
                            <button type="submit" name="decision" value="reject" class="dm-btn dm-btn-danger"
                                    onclick="return confirm('Reject this account? Their ID will be deleted and they will see your reason.')">Reject</button>
                            <button type="submit" name="decision" value="verify" class="dm-btn dm-btn-success" data-approve>Approve Account</button>
                        </div>
                    </form>
                </section>
            @endif
        </aside>
    </div>

    {{-- Approve stays disabled until all three boxes are ticked (the server checks again) --}}
    <script>
        document.querySelectorAll('[data-review-form]').forEach(function (form) {
            var boxes = form.querySelectorAll('[data-check]');
            var approve = form.querySelector('[data-approve]');
            function sync() { approve.disabled = !Array.prototype.every.call(boxes, function (b) { return b.checked; }); }
            boxes.forEach(function (b) { b.addEventListener('change', sync); });
            sync();
        });
    </script>

</x-docmon.admin-layout>
