<?php

namespace App\Http\Controllers\Signatory;

use App\Http\Controllers\Controller;
use App\Models\DocumentAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * History: every approve, return, and reject this signatory did, newest first,
 * with tabs per action, a search box, and pages of 10.
 */
class HistoryController extends Controller
{
    /** Tab label => the action it shows (null = all). */
    private const TABS = [
        'All' => null,
        'Approved' => DocumentAction::APPROVED,
        'Needs Revision' => DocumentAction::RETURNED,
        'Rejected' => DocumentAction::REJECTED,
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        // Base query: my signatory actions (an officer's "resubmitted" is never mine),
        // narrowed by the search box: ticket code, Ref No., document title, or officer name
        $mine = fn () => DocumentAction::where('user_id', $request->user()->id)
            ->whereIn('action', array_filter(self::TABS))
            ->when($search !== '', fn (Builder $q) => $q->whereHas('document', fn (Builder $doc) => $doc->where(fn (Builder $w) => $w
                ->where('title', 'like', "%{$search}%")
                ->orWhereHas('ticket', fn (Builder $t) => $t->where(fn (Builder $tw) => $tw
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('ref_no', 'like', "%{$search}%")
                    ->orWhereHas('officer', fn (Builder $o) => $o->where(fn (Builder $ow) => $ow
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"))))))));

        $counts = collect(self::TABS)->map(fn ($action) => $mine()->when($action, fn ($q) => $q->where('action', $action))->count());

        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'All';

        $actions = $mine()
            ->when(self::TABS[$tab], fn ($q, $action) => $q->where('action', $action))
            ->with(['document.ticket' => fn ($q) => $q->withCount('documents')->with('officer')])
            ->latest()->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('signatory.history', compact('actions', 'counts', 'tab', 'search'));
    }
}
