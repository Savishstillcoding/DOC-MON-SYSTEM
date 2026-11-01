<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Activity Logs: everything that happened in DOC-MON, newest first, with tabs per
 * category, a date range (default: last 7 days) and a search box. Read-only.
 */
class ActivityLogController extends Controller
{
    /** Date range => [label, days back] (null = all time, 0 = today). */
    public const RANGES = [
        'today' => ['Today', 0],
        '7' => ['Last 7 days', 7],
        '30' => ['Last 30 days', 30],
        'all' => ['All time', null],
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $range = array_key_exists((string) $request->query('range'), self::RANGES) ? (string) $request->query('range') : '7';
        $days = self::RANGES[$range][1];
        $tab = array_key_exists((string) $request->query('tab'), ActivityLog::CATEGORIES) ? (string) $request->query('tab') : 'all';

        $logs = ActivityLog::query()
            ->when($days !== null, fn (Builder $q) => $q->where('created_at', '>=', $days === 0 ? today() : now()->subDays($days)))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('actor_name', 'like', "%{$search}%")
                ->orWhere('action', 'like', "%{$search}%")
                ->orWhere('details', 'like', "%{$search}%")))
            ->when($tab !== 'all', fn (Builder $q) => $q->where('category', $tab))
            ->latest()->latest('id')
            ->paginate(8)
            ->withQueryString();

        return view('admin.activity-logs', compact('logs', 'search', 'range', 'tab'));
    }
}
