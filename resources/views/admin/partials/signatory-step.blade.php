{{-- One step of a route on Signatory Lists. $i = row index, $row = ['user_id' => ..., 'title' => ...] --}}
<li class="sg-step" data-step draggable="true">
    <button type="button" class="sg-handle" data-handle aria-label="Move step (drag, or use the arrow keys)">
        <x-docmon.icon name="list" size="16" />
    </button>
    <span class="sg-step-no" data-step-no>{{ is_int($i) ? $i + 1 : '' }}</span>

    <div class="dm-field">
        <label>Signatory
            <select name="steps[{{ $i }}][user_id]" class="dm-select" required>
                <option value="" @selected(! $row['user_id'])>Choose a signatory</option>
                @foreach ($signatories as $s)
                    <option value="{{ $s->id }}" @selected($row['user_id'] === $s->id)>{{ $s->displayName() }}{{ $s->position ? ' · ' . $s->position : '' }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="dm-field">
        <label>Title / Office
            <input type="text" name="steps[{{ $i }}][title]" maxlength="50" value="{{ $row['title'] }}" placeholder="e.x. Adviser" required>
        </label>
    </div>

    <span class="sg-final" data-final hidden>Final say</span>

    <button type="button" class="dm-icon-btn" data-remove aria-label="Remove this step">
        <x-docmon.icon name="x" size="18" />
    </button>
</li>
