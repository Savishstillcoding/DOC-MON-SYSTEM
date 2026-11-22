<?php

namespace App\Http\Controllers\Officer;

/*
| READING GUIDE 11/15: TicketController
|
| WHAT: a controller: the officer's My Tickets page and New Ticket form.
| USED FOR: listing tickets and filing a new one (store()).
| KEY IDEA: the ticket and all its papers are saved together or not at all,
|           and the organization comes from the logged-in officer, never from the form.
*/

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\Ticket;
use App\Services\TicketAlerts;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * TicketController = the officer's My Tickets page, the New Ticket form, and submitting it.
 * The organization always comes from the logged-in officer, never from the form.
 */
class TicketController extends Controller
{
    /**
     * index() = My Tickets: every ticket of the officer's organization, newest first,
     * with the status tabs and the search box.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        // with(): load documents and the approval route in a few queries,
        // instead of one extra query per ticket (the "N+1" problem)
        $all = Ticket::visibleTo($request->user())
            ->when($search !== '', fn ($q) => $q->search($search))
            // Every action with who did it, oldest first, for each paper's History
            ->with(['documents.latestAction', 'documents.actions' => fn ($q) => $q->with('user')->oldest()->oldest('id'),
                'organization.signatories', 'officer'])
            ->latest()
            ->get();

        $counts = collect(Ticket::STATUS_TABS)->mapWithKeys(fn ($tab) => [
            $tab => $tab === 'All' ? $all->count() : $all->filter(fn ($t) => $t->status() === $tab)->count(),
        ]);

        $tab = in_array($request->query('status'), Ticket::STATUS_TABS, true) ? $request->query('status') : 'All';
        $tickets = $tab === 'All' ? $all : $all->filter(fn ($t) => $t->status() === $tab);

        return view('student-officer.my-tickets', compact('tickets', 'counts', 'tab', 'search'));
    }

    /** create() = shows the New Ticket form (with the org's route and the paper types). */
    public function create(Request $request): View
    {
        return view('student-officer.new-ticket', [
            'organization' => $request->user()->organization?->load('signatories'),
            'documentTypes' => Document::TYPES,
        ]);
    }

    /**
     * store() = what happens when the officer presses Submit.
     * How it works:
     *   1. No approval route yet? Stop (the papers would have nowhere to go).
     *   2. Check the form: Ref No. not used by another open ticket, 1 to 20 papers,
     *      each with a type and a title.
     *   3. Save the ticket AND all its papers together (all or nothing), with a new
     *      code (DM-2026-00001) and the officer's own organization.
     *   4. Send Telegram to the first signatory.
     *   5. Show "Ticket ... submitted. Hand the papers to ..."
     */
    public function store(Request $request, TicketAlerts $alerts): RedirectResponse
    {
        $officer = $request->user();
        $organization = $officer->organization;

        // Without an approval route the papers would have nowhere to go
        if (! $organization || $organization->signatories()->doesntExist()) {
            return back()->withInput()->withErrors([
                'ticket' => 'Your organization has no approval route yet. Ask the admin to add its signatories.',
            ]);
        }

        $data = $request->validate([
            'ref_no' => [
                'required', 'string', 'max:20',
                // unique: no other OPEN ticket of this organization may use this Ref No.
                // A fully rejected ticket gives its number back
                Rule::unique('tickets', 'active_ref_no')->where('organization_id', $organization->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'date_needed' => ['nullable', 'date', 'after_or_equal:today'],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'documents' => ['required', 'array', 'min:1', 'max:20'],
            'documents.*.type' => ['required', Rule::in(Document::TYPES)],
            'documents.*.title' => ['required', 'string', 'max:255'],
            'documents.*.ref_no' => [
                'nullable', 'string', 'max:20', 'distinct',
                // 'distinct' = no two papers in THIS ticket may share a Ref No.
                // The function below = no OPEN paper of the whole organization may have it
                // either. $fail(...) shows the error message.
                function (string $attribute, mixed $value, \Closure $fail) use ($organization) {
                    // Rejected papers don't count: they give their Ref No. back
                    $used = Document::where('ref_no', $value)
                        ->where('status', '!=', Document::REJECTED)
                        ->whereHas('ticket', fn ($q) => $q->where('organization_id', $organization->id))
                        ->exists();
                    if ($used) {
                        $fail('Document :position: your organization already has an open paper with this Ref No.');
                    }
                },
            ],
        ], [
            'ref_no.unique' => 'Your organization already has an open ticket with this Ref No.',
            'date_needed.after_or_equal' => 'The date needed can\'t be in the past.',
            'documents.required' => 'Add at least one document.',
            'documents.*.type.in' => 'Document :position: choose a type from the list.',
            'documents.*.title.required' => 'Document :position needs a title.',
            'documents.*.ref_no.distinct' => 'Document :position: the same Ref No. is used twice in this ticket.',
        ]);

        try {
            // retry(): if two officers got the same new code at the same moment,
            // the unique rule on tickets.code stops the second one; try again with
            // a fresh code. Any other duplicate (the Ref No.) is not retried.
            $ticket = retry(3, fn () => DB::transaction(function () use ($data, $officer, $organization) {
                $ticket = new Ticket(Arr::except($data, 'documents'));
                $ticket->code = Ticket::nextCode();
                $ticket->organization()->associate($organization);
                $ticket->officer()->associate($officer);
                $ticket->save();

                // Same transaction: the ticket and its documents are saved together or not at all
                $ticket->documents()->createMany($data['documents']);

                $count = count($data['documents']);
                ActivityLog::record($officer, 'tickets', 'Submitted ticket',
                    "{$ticket->code} · {$ticket->title} · ".($count > 1 ? "Batch ({$count})" : 'Single'));

                return $ticket;
            }), 0, fn ($e) => $e instanceof UniqueConstraintViolationException
                && $organization->tickets()->where('active_ref_no', $data['ref_no'])->doesntExist());
        } catch (UniqueConstraintViolationException) {
            return back()->withInput()->withErrors(['ref_no' => 'Your organization already has an open ticket with this Ref No.']);
        }

        $alerts->submitted($ticket);   // Telegram to the first signatory

        $first = $organization->signatories->first();

        return redirect('/officer/tickets')->with('status',
            "Ticket {$ticket->code} submitted. Hand the papers to {$first->displayName()} ({$first->pivot->title}).");
    }
}
