{{--
    Small number card.
    Usage: <x-docmon.stat-card label="Pending" value="6" note="Waiting for signatures" />
--}}
@props(['label', 'value', 'note' => ''])

<div class="dm-card dm-stat-card">
    <p class="dm-stat-label">{{ $label }}</p>
    {{-- data-count lets docmon-dashboard.js count up to whole numbers; the real value is still in the HTML --}}
    <p class="dm-stat-value" @if (ctype_digit((string) $value)) data-count="{{ $value }}" @endif>{{ $value }}</p>
    <p class="dm-stat-note">{{ $note }}</p>
</div>
