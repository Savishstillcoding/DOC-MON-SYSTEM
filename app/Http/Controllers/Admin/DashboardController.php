<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The admin's dashboard: accounts waiting for verification (with their ID),
 * the numbers, each organization's approval route, and recent decisions.
 */
class DashboardController extends Controller
{
    /** Tab label => role shown (null = both). */
    private const TABS = ['All' => null, 'Student Officers' => 'officer', 'Signatories' => 'signatory'];

    public function index(Request $request): View
    {
        // Only the ID's size and type are loaded here, never the 2 MB file itself
        $pending = User::where('status', 'pending')
            ->whereIn('role', ['officer', 'signatory'])
            ->with(['organization', 'idDocument:id,user_id,mime,size'])
            ->oldest()
            ->get();

        $counts = collect(self::TABS)->map(fn ($role) => $role ? $pending->where('role', $role)->count() : $pending->count());
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'All';
        $shown = self::TABS[$tab] ? $pending->where('role', self::TABS[$tab]) : $pending;

        return view('admin.dashboard', [
            'pending' => $shown,
            'counts' => $counts,
            'tab' => $tab,
            'stats' => [
                'pending' => $pending->count(),
                'verified' => User::where('status', 'verified')->whereIn('role', ['officer', 'signatory'])->count(),
                'organizations' => Organization::count(),
                // Tickets with at least one paper still moving (pending or back with the officer)
                'activeTickets' => Ticket::whereHas('documents', fn ($q) => $q->whereIn('status', [Document::PENDING, Document::RETURNED]))->count(),
            ],
            'organizations' => Organization::with('signatories')->orderBy('code')->get(),
            'recent' => User::whereNotNull('reviewed_at')->with('reviewer')->latest('reviewed_at')->limit(5)->get(),
        ]);
    }
}
