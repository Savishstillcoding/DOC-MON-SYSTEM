<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Signatory Lists: each organization's approval route (who signs at which step).
 * The last step always has the final say.
 *
 * Safe rules while papers are in progress (decided 2026-11-01): papers remember only
 * their STEP NUMBER, so
 * - replacing the person at a step is fine: their waiting papers go to the new person;
 * - adding, removing or reordering steps is blocked, because papers would land with
 *   the wrong signatory or get final approval too early.
 */
class SignatoryListController extends Controller
{
    public function index(Request $request): View
    {
        $organizations = Organization::withCount('signatories')->orderBy('code')->get();
        $selected = $organizations->firstWhere('id', (int) $request->query('org')) ?? $organizations->first();

        return view('admin.signatory-lists', [
            'organizations' => $organizations,
            'selected' => $selected?->load('signatories'),
            'inProgress' => $selected ? $this->papersInProgress($selected) : 0,
            // Only verified signatories can be put on a route
            'signatories' => User::where('role', 'signatory')->where('status', 'verified')
                ->orderBy('last_name')->orderBy('first_name')->get(),
        ]);
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'steps' => ['required', 'array', 'min:1', 'max:10'],
            'steps.*.user_id' => ['required', 'integer', 'distinct',
                Rule::exists('users', 'id')->where('role', 'signatory')->where('status', 'verified')],
            'steps.*.title' => ['required', 'string', 'max:50'],
        ], [
            'steps.required' => 'The route needs at least one step.',
            'steps.*.user_id.required' => 'Step :position: choose a signatory.',
            'steps.*.user_id.distinct' => 'Step :position: this person is already on another step.',
            'steps.*.user_id.exists' => 'Step :position: choose a verified signatory.',
            'steps.*.title.required' => 'Step :position needs a title (e.g. Adviser).',
        ]);

        // The order of the rows IS the order of the steps: step 1, 2, 3...
        $new = collect(array_values($data['steps']))->map(fn ($s, $i) => [
            'step' => $i + 1, 'user_id' => (int) $s['user_id'], 'title' => trim($s['title']),
        ]);

        $result = DB::transaction(function () use ($organization, $new, $request) {
            // Locked so a ticket action can't run halfway through a route change
            $old = DB::table('organization_signatory')->where('organization_id', $organization->id)
                ->orderBy('step')->lockForUpdate()->get(['step', 'user_id', 'title']);

            $inProgress = $this->papersInProgress($organization);

            if ($inProgress > 0 && ($problem = $this->unsafeChange($old, $new, $inProgress))) {
                return ['error' => $problem];
            }

            DB::table('organization_signatory')->where('organization_id', $organization->id)->delete();
            DB::table('organization_signatory')->insert($new->map(fn ($s) => $s + [
                'organization_id' => $organization->id, 'created_at' => now(), 'updated_at' => now(),
            ])->all());

            ActivityLog::record($request->user(), 'signatory_lists', 'Edited signatory list',
                "{$organization->code} · ".$this->describeChange($old, $new));

            return ['moved' => $this->movedPapers($organization, $old, $new)];
        });

        if (isset($result['error'])) {
            return back()->withInput()->withErrors(['steps' => $result['error']]);
        }

        $message = "Saved the {$organization->code} signatory list.";
        foreach ($result['moved'] as $step => $count) {
            $message .= " {$count} waiting ".str('paper')->plural($count)." at step {$step} now ".($count === 1 ? 'goes' : 'go').' to the new signatory.';
        }

        return redirect('/admin/signatory-lists?org='.$organization->id)->with('status', $message);
    }

    /** Papers of this organization still moving (waiting for a signatory, or back with the officer). */
    private function papersInProgress(Organization $organization): int
    {
        return Document::whereIn('status', [Document::PENDING, Document::RETURNED])
            ->whereHas('ticket', fn ($q) => $q->where('organization_id', $organization->id))
            ->count();
    }

    /** Why a change is not allowed while papers are in progress, or null when it is safe. */
    private function unsafeChange($old, $new, int $inProgress): ?string
    {
        $papers = "{$inProgress} ".str('paper')->plural($inProgress).' of this organization '.($inProgress === 1 ? 'is' : 'are').' still in progress';

        if ($old->count() !== $new->count()) {
            return "{$papers}, so steps can't be added or removed right now (papers would go to the wrong step). "
                .'You can still replace the person at a step or rename a title.';
        }

        $onRoute = $old->pluck('step', 'user_id');
        foreach ($new as $i => $step) {
            $before = $old[$i];
            // A different person at this step must be someone NEW to the route:
            // moving someone from another step is a reorder, which would misroute papers
            if ((int) $before->user_id !== $step['user_id'] && $onRoute->has($step['user_id'])) {
                return "{$papers}, so people can't change places in the order right now. "
                    .'You can still replace someone with a signatory who is not on this route yet.';
            }
        }

        return null;
    }

    /**
     * A short, readable summary for the Activity Logs, e.g.
     * "step 1: Dela Cruz, Ana → Cruz, Nora" or "new route: Adviser (Dela Cruz, Ana) → VPSD (Santos, Liza)".
     */
    private function describeChange($old, $new): string
    {
        $names = User::whereIn('id', $old->pluck('user_id')->merge($new->pluck('user_id')))->get()->keyBy('id');
        $who = fn ($id) => $names[$id]?->displayName() ?? 'Unknown';

        // Same number of steps: list what changed at each step
        if ($old->count() === $new->count()) {
            $changes = $new->map(function ($step, $i) use ($old, $who) {
                $before = $old[$i];
                $parts = [];
                if ((int) $before->user_id !== $step['user_id']) {
                    $parts[] = $who((int) $before->user_id).' → '.$who($step['user_id']);
                }
                if ($before->title !== $step['title']) {
                    $parts[] = "title \"{$before->title}\" → \"{$step['title']}\"";
                }

                return $parts ? "step {$step['step']}: ".implode(', ', $parts) : null;
            })->filter();

            if ($changes->isNotEmpty()) {
                return $changes->implode('; ');
            }
            if ($old->isNotEmpty()) {
                return 'no changes';
            }
        }

        // Steps added, removed or reordered: show the whole new route
        return 'new route: '.$new->map(fn ($s) => "{$s['title']} (".$who($s['user_id']).')')->implode(' → ');
    }

    /** [step => number of waiting papers that now go to a different person]. */
    private function movedPapers(Organization $organization, $old, $new): array
    {
        $moved = [];
        foreach ($old as $i => $before) {
            if (isset($new[$i]) && (int) $before->user_id !== $new[$i]['user_id']) {
                $count = Document::where('status', Document::PENDING)->where('current_step', $before->step)
                    ->whereHas('ticket', fn ($q) => $q->where('organization_id', $organization->id))->count();
                if ($count > 0) {
                    $moved[$before->step] = $count;
                }
            }
        }

        return $moved;
    }
}
