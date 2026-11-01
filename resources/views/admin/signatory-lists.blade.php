{{--
    DOC-MON · Admin · Signatory Lists (Admin\SignatoryListController@index)
    From the controller: $organizations (with signatories_count), $selected (with its route),
    $inProgress (papers of $selected still moving), $signatories (verified, for the dropdowns).
    Save Changes → PUT /admin/signatory-lists/{organization} with steps[i][user_id], steps[i][title];
    the order of the rows is the order of the steps. The last step always has the final say.
--}}
@php
    // After a validation error, show what the admin typed; otherwise the saved route
    $rows = old('steps') !== null
        ? collect(old('steps'))->values()->map(fn ($s) => ['user_id' => (int) ($s['user_id'] ?? 0), 'title' => $s['title'] ?? ''])
        : ($selected?->signatories->map(fn ($s) => ['user_id' => $s->id, 'title' => $s->pivot->title]) ?? collect());
@endphp

<x-docmon.admin-layout title="Signatory Lists" active="Signatory Lists">

    <header class="dm-page-header">
        <div>
            <h1 class="dm-page-title">Signatory Lists</h1>
            <p class="dm-page-sub">Each organization has its own approval route.</p>
        </div>
    </header>

    <div class="sg-lists">
        {{-- Organizations --}}
        <section class="dm-card dm-panel dm-align-start">
            <h2 class="dm-panel-title">Organizations</h2>
            @if ($organizations->isEmpty())
                <p class="nt-note">No organizations yet.</p>
            @else
                <ul class="rv-queue sg-orgs">
                    @foreach ($organizations as $org)
                        <li>
                            <a href="{{ url('/admin/signatory-lists?org=' . $org->id) }}" class="rv-item {{ $selected && $selected->is($org) ? 'is-selected' : '' }}">
                                <div>
                                    <p class="dm-cell-main">{{ $org->code }}</p>
                                    <p class="dm-cell-sub">{{ $org->signatories_count }} {{ Str::plural('signatory', $org->signatories_count) }}</p>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- The selected organization's route --}}
        @if ($selected)
            <form method="POST" action="{{ url('/admin/signatory-lists/' . $selected->id) }}" class="dm-card dm-panel" data-route-form>
                @csrf
                @method('PUT')

                <div class="dm-panel-head">
                    <div>
                        <h2 class="dm-panel-title">{{ $selected->code }} – Signatory List</h2>
                        <p class="dm-panel-sub">Tickets go through these steps in order. The last step has the final say.</p>
                    </div>
                    <button type="submit" class="dm-btn dm-btn-primary dm-btn-sm">Save Changes</button>
                </div>

                @if ($errors->any())
                    <ul class="dm-form-alert">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                @endif

                @if ($signatories->isEmpty())
                    <p class="nt-note">No verified signatories yet. Verify signatory accounts on User Verification first.</p>
                @endif

                <ol class="sg-steps" data-steps>
                    @foreach ($rows as $i => $row)
                        @include('admin.partials.signatory-step', ['i' => $i, 'row' => $row])
                    @endforeach
                </ol>

                <button type="button" class="dm-btn dm-btn-outline" data-add-step @disabled($signatories->isEmpty())>
                    <x-docmon.icon name="plus" size="16" /> Add Step
                </button>

                {{-- A new, empty row for "Add Step" (copied by the script below) --}}
                <template data-step-template>
                    @include('admin.partials.signatory-step', ['i' => '__INDEX__', 'row' => ['user_id' => 0, 'title' => '']])
                </template>

                <p class="sg-note">
                    <x-docmon.icon name="clock" size="18" />
                    @if ($inProgress > 0)
                        <span>
                            <b>{{ $inProgress }} {{ Str::plural('paper', $inProgress) }} of {{ $selected->code }} {{ $inProgress === 1 ? 'is' : 'are' }} in progress.</b>
                            You can replace the person at a step (their waiting papers go to the new person) or rename a title.
                            Adding, removing or reordering steps is possible once no papers are in progress.
                        </span>
                    @else
                        <span>No papers of {{ $selected->code }} are in progress, so you can add, remove and reorder steps freely.
                            Replacing a person later moves their waiting papers to the new person.</span>
                    @endif
                </p>
            </form>
        @endif
    </div>

    <script>
        // Add, remove and reorder steps; then renumber "1, 2, 3", the field names
        // (steps[0][...], steps[1][...]) and the "Final say" label on the last step.
        document.querySelectorAll('[data-route-form]').forEach(function (form) {
            var list = form.querySelector('[data-steps]');
            var template = form.querySelector('[data-step-template]');
            var dragged = null;

            function renumber() {
                var rows = list.querySelectorAll('[data-step]');
                rows.forEach(function (row, i) {
                    row.querySelector('[data-step-no]').textContent = i + 1;
                    row.querySelectorAll('[name]').forEach(function (field) {
                        field.name = field.name.replace(/steps\[[^\]]*\]/, 'steps[' + i + ']');
                    });
                    row.querySelector('[data-final]').hidden = i !== rows.length - 1;
                });
            }

            form.querySelector('[data-add-step]').addEventListener('click', function () {
                list.insertAdjacentHTML('beforeend', template.innerHTML);
                renumber();
                list.lastElementChild.querySelector('select').focus();
            });

            list.addEventListener('click', function (e) {
                var remove = e.target.closest('[data-remove]');
                if (remove) { remove.closest('[data-step]').remove(); renumber(); }
            });

            // Keyboard: focus the handle and press the up/down arrow keys
            list.addEventListener('keydown', function (e) {
                var handle = e.target.closest('[data-handle]');
                if (!handle || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) return;
                e.preventDefault();
                var row = handle.closest('[data-step]');
                var other = e.key === 'ArrowUp' ? row.previousElementSibling : row.nextElementSibling;
                if (other) { e.key === 'ArrowUp' ? list.insertBefore(row, other) : list.insertBefore(other, row); renumber(); handle.focus(); }
            });

            // Mouse: drag a row by its handle
            list.addEventListener('dragstart', function (e) { dragged = e.target.closest('[data-step]'); });
            list.addEventListener('dragover', function (e) {
                var over = e.target.closest('[data-step]');
                if (!dragged || !over || over === dragged) return;
                e.preventDefault();
                var after = e.clientY > over.getBoundingClientRect().top + over.offsetHeight / 2;
                list.insertBefore(dragged, after ? over.nextSibling : over);
            });
            list.addEventListener('dragend', function () { dragged = null; renumber(); });

            renumber();
        });
    </script>

</x-docmon.admin-layout>
