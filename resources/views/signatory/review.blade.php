{{--
    DOC-MON · Signatory · For Review (Signatory\ReviewController)
    From the controller: $queue (one item per ticket: ->ticket, ->documents waiting for me, ->since),
    $counts (per tab), $tab, $selected (the ticket shown in the panel), $route (->step, ->total, ->title, ->next).
    Each waiting document has its own form → POST /signatory/documents/{id}/action
    (Batch rule: every paper is approved separately). "Approve all" → POST /signatory/tickets/{id}/approve-all.
--}}
<x-docmon.signatory-layout title="For Review" active="For Review">

    <header class="dm-page-header">
        <div>
            <h1 class="dm-page-title">For Review</h1>
            <p class="dm-page-sub">Update the status of the papers handed to you.</p>
        </div>
    </header>

    @error('action') <p class="dm-form-alert" role="alert">{{ $message }}</p> @enderror

    <div class="dm-columns">
        {{-- Queue --}}
        <section class="dm-card dm-panel dm-align-start">
            <div class="dm-panel-head">
                <div>
                    <h2 class="dm-panel-title">Queue</h2>
                    <p class="dm-panel-sub">{{ $counts['All'] }} waiting · oldest first</p>
                </div>
            </div>

            <nav class="dm-tabs">
                @foreach ($counts as $label => $count)
                    <a href="{{ url('/signatory/review') . ($label === 'All' ? '' : '?type=' . $label) }}"
                       class="dm-tab {{ $label === $tab ? 'is-active' : '' }}">{{ $label }} ({{ $count }})</a>
                @endforeach
            </nav>

            @if ($queue->isEmpty())
                <p class="nt-note">Nothing to sign right now. New papers appear here when they reach your step.</p>
            @else
                <ul class="rv-queue">
                    @foreach ($queue as $item)
                        @php $t = $item->ticket; @endphp
                        <li>
                            <a href="{{ url('/signatory/review') . '?' . http_build_query(array_filter(['type' => $tab === 'All' ? null : $tab, 'ticket' => $t->id])) }}"
                               class="rv-item {{ $selected && $selected->ticket->is($t) ? 'is-selected' : '' }}">
                                <div>
                                    <p class="dm-cell-main">{{ $t->code }}</p>
                                    <p class="rv-title">{{ $t->title }}</p>
                                    <p class="dm-cell-sub">
                                        {{ $t->documents_count > 1 ? 'Batch (' . $t->documents_count . ')' : 'Single' }} · {{ $t->officer->displayName() }}
                                    </p>
                                </div>
                                <div class="rv-item-side">
                                    <span class="dm-cell-sub">{{ $item->since->format('M j') }}</span>
                                    <x-docmon.status-badge status="For Review" />
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Selected ticket --}}
        <aside class="dm-side">
            @if ($selected)
                @php $t = $selected->ticket; @endphp
                <section class="dm-card dm-panel">
                    <div class="dm-panel-head">
                        <div>
                            <h2 class="dm-panel-title">{{ $t->code }}</h2>
                            <p class="dm-panel-sub">{{ $t->title }}</p>
                        </div>
                        <x-docmon.status-badge status="For Review" />
                    </div>

                    <dl class="dm-facts rv-facts">
                        <div><dt>Officer</dt><dd>{{ $t->officer->displayName() }}</dd></div>
                        <div><dt>Ref No.</dt><dd>{{ $t->ref_no }}</dd></div>
                        <div><dt>Type</dt><dd>{{ $t->documents_count > 1 ? 'Batch · ' . $t->documents_count . ' documents' : 'Single' }}</dd></div>
                        <div><dt>Submitted</dt><dd>{{ $t->created_at->format('M j, Y · g:i A') }}</dd></div>
                        @if ($t->date_needed)
                            <div><dt>Date needed</dt><dd>{{ $t->date_needed->format('M j, Y') }}</dd></div>
                        @endif
                        <div><dt>Route</dt><dd>Step {{ $route->step }} of {{ $route->total }} · {{ $route->title }}</dd></div>
                    </dl>

                    @if ($t->purpose)
                        <p class="rv-purpose">{{ $t->purpose }}</p>
                    @endif

                    <h3 class="rv-docs-title">
                        Waiting for you: {{ $selected->documents->count() }} {{ Str::plural('document', $selected->documents->count()) }}
                        @if ($selected->documents->count() < $t->documents_count)
                            <span class="dm-cell-sub">({{ $t->documents_count - $selected->documents->count() }} other {{ Str::plural('paper', $t->documents_count - $selected->documents->count()) }} of this ticket {{ $t->documents_count - $selected->documents->count() === 1 ? 'is' : 'are' }} not at your step)</span>
                        @endif
                    </h3>

                    {{-- Above the list, so a big Batch (e.g. 12 certificates) doesn't hide it --}}
                    @if ($selected->documents->count() > 1)
                        <form method="POST" action="{{ url('/signatory/tickets/' . $t->id . '/approve-all') }}" class="rv-all">
                            @csrf
                            <button type="submit" class="dm-btn dm-btn-primary dm-btn-block"
                                    onclick="return confirm('Approve all {{ $selected->documents->count() }} documents of {{ $t->code }}?')">
                                Approve all {{ $selected->documents->count() }} documents
                            </button>
                        </form>
                    @endif

                    @foreach ($selected->documents as $doc)
                        @php $isMine = (int) old('document_id') === $doc->id; @endphp
                        <form method="POST" action="{{ url('/signatory/documents/' . $doc->id . '/action') }}" class="rv-doc">
                            @csrf
                            <input type="hidden" name="document_id" value="{{ $doc->id }}">
                            <p class="dm-cell-main">{{ $doc->type }}: {{ $doc->title }}</p>
                            @if ($doc->ref_no) <p class="dm-cell-sub">Ref No. {{ $doc->ref_no }}</p> @endif

                            <label class="rv-label" for="rv-remarks-{{ $doc->id }}">Remarks</label>
                            <textarea id="rv-remarks-{{ $doc->id }}" name="remarks" rows="2" maxlength="1000"
                                      placeholder="Required when returning or rejecting">{{ $isMine ? old('remarks') : '' }}</textarea>
                            @if ($isMine) @error('remarks') <p class="dm-field-error">{{ $message }}</p> @enderror @endif

                            <button type="submit" name="action" value="approve" class="dm-btn dm-btn-success dm-btn-block">Mark as Approved</button>
                            <div class="rv-two">
                                <button type="submit" name="action" value="return" class="dm-btn dm-btn-outline">Return for Revision</button>
                                <button type="submit" name="action" value="reject" class="dm-btn dm-btn-danger"
                                        onclick="return confirm('Reject is final: the officer must file a new ticket. Reject this document?')">Reject</button>
                            </div>
                        </form>
                    @endforeach


                    <p class="nt-note">
                        @if ($route->next)
                            After you approve, the papers move to Step {{ $route->next->pivot->step }} · {{ $route->next->pivot->title }} ({{ $route->next->displayName() }}).
                        @else
                            You are the last signatory: your approval is final.
                        @endif
                    </p>
                </section>
            @endif
        </aside>
    </div>

</x-docmon.signatory-layout>
