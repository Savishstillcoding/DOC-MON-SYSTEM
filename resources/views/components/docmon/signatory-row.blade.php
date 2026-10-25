{{--
    One signatory in a list (approval route, availability, admin signatory list).
    Usage:
      <x-docmon.signatory-row badge="AD" name="Dela Cruz, Ana" role="Step 1 · Adviser" status="In a Meeting" note="2 PM" />
      <x-docmon.signatory-row badge="1" name="Reyes, Paolo" role="SSC President" status="Verified" />

    badge  = initials or step number shown in the dark circle
    status = an availability (Available, In a Meeting, ...) or "Verified"
--}}
@props(['badge', 'name', 'role', 'status', 'note' => null])

<div class="dm-signatory-row">
    <span class="dm-avatar">{{ $badge }}</span>
    <div class="dm-signatory-info">
        <p class="dm-signatory-name">{{ $name }}</p>
        <p class="dm-signatory-role">{{ $role }}</p>
    </div>
    @if ($status === 'Verified')
        <span class="dm-availability dm-availability-available"><span class="dm-dot"></span>Verified</span>
    @else
        <x-docmon.availability :status="$status" :note="$note" />
    @endif
</div>
