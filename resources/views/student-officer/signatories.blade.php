{{--
    DOC-MON · Student Officer · Signatories (Officer\SignatoryController)
    The organization's approval route, each signatory's live availability, and where
    THIS officer's papers are. From the controller: $organization, $signatories (in route
    order: ->step, ->title, ->name, ->initials, ->availability, ->back_at, ->pending, ->approved, ->returned).
--}}
<x-docmon.officer-layout title="Signatories" active="Signatories" :live="true">

    <header class="dm-page-header">
        <div>
            <h1 class="dm-page-title">Signatories</h1>
            <p class="dm-page-sub">{{ $organization?->code }} approval route and who is available right now.</p>
        </div>
        <div class="dm-header-actions">
            <x-docmon.notify-bell />
        </div>
    </header>

    {{-- One live box for the whole route: it keeps the page's spacing (dm-stack) --}}
    <div class="dm-stack" data-live="signatories">
    @if ($signatories->isEmpty())
        <p class="nt-note">Your organization has no approval route yet. Ask the admin to add its signatories.</p>
    @else
    {{-- Approval order, built from the list so it always matches the cards below --}}
    <p class="sl-banner">
        <x-docmon.icon name="list" size="20" />
        <span>
            Tickets move in this order:
            @foreach ($signatories as $s)
                {{ $s->title }}@if (! $loop->last) <x-docmon.icon name="arrow-right" size="16" class="sl-arrow" />@else.@endif
            @endforeach
            The {{ $signatories->last()->title }} has the final say.
        </span>
    </p>

    <section class="sl-grid">
        @foreach ($signatories as $s)
            <article class="dm-card sl-card">
                <div class="sl-card-top">
                    <span class="sl-step">STEP {{ $s->step }}</span>
                    <x-docmon.availability :status="$s->availability" />
                </div>

                <span class="dm-avatar sl-avatar">{{ $s->initials }}</span>
                <h2 class="sl-name">{{ $s->name }}</h2>
                <p class="sl-role">{{ $s->title }}</p>

                <dl class="dm-facts">
                    <div><dt>Back at</dt><dd>{{ $s->back_at ?? '—' }}</dd></div>
                    <div><dt>Your papers with them</dt><dd>{{ $s->pending }}</dd></div>
                </dl>
            </article>
        @endforeach
    </section>

    <section class="dm-card dm-panel">
        <h2 class="dm-panel-title">Papers per Signatory</h2>
        <p class="dm-panel-sub">Waiting with each signatory now, and what each one did with your papers so far</p>

        <div class="dm-table-wrap sl-table">
            <table class="dm-table">
                <thead>
                    <tr>
                        <th>Signatory</th>
                        <th class="sl-num">Pending</th>
                        <th class="sl-num">Approved</th>
                        <th class="sl-num">Returned</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($signatories as $s)
                        <tr>
                            <td>
                                <p class="dm-cell-main">{{ $s->name }}</p>
                                <p class="dm-cell-sub">Step {{ $s->step }} · {{ $s->title }}</p>
                            </td>
                            <td class="sl-num">{{ $s->pending }}</td>
                            <td class="sl-num">{{ $s->approved }}</td>
                            <td class="sl-num">{{ $s->returned }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @endif
    </div>

</x-docmon.officer-layout>
