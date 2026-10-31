<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Ticket;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * An officer's tickets: the list (My Tickets), the New Ticket form, and submitting it.
 * The organization always comes from the logged-in officer, never from the form.
 */
class TicketController extends Controller
{
    /** The status tabs on My Tickets, in order. */
    private const TABS = ['All', 'Pending', 'Needs Revision', 'Approved', 'Partly Approved', 'Rejected'];

    public function index(Request $request): View
    {
        // with(): load documents and the approval route in a few queries,
        // instead of one extra query per ticket (the "N+1" problem)
        $all = $request->user()->tickets()
            ->with(['documents.latestAction', 'organization.signatories'])
            ->latest()
            ->get();

        $counts = collect(self::TABS)->mapWithKeys(fn ($tab) => [
            $tab => $tab === 'All' ? $all->count() : $all->filter(fn ($t) => $t->status() === $tab)->count(),
        ]);

        $tab = in_array($request->query('status'), self::TABS, true) ? $request->query('status') : 'All';
        $tickets = $tab === 'All' ? $all : $all->filter(fn ($t) => $t->status() === $tab);

        return view('student-officer.my-tickets', compact('tickets', 'counts', 'tab'));
    }

    public function create(Request $request): View
    {
        return view('student-officer.new-ticket', [
            'organization' => $request->user()->organization?->load('signatories'),
            'documentTypes' => Document::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
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
                Rule::unique('tickets')->where('organization_id', $organization->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'date_needed' => ['nullable', 'date', 'after_or_equal:today'],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'documents' => ['required', 'array', 'min:1', 'max:20'],
            'documents.*.type' => ['required', Rule::in(Document::TYPES)],
            'documents.*.title' => ['required', 'string', 'max:255'],
            'documents.*.ref_no' => [
                'nullable', 'string', 'max:20', 'distinct',
                // A document's own Ref No. must not repeat within the organization (AUD-032)
                function (string $attribute, mixed $value, \Closure $fail) use ($organization) {
                    $used = Document::where('ref_no', $value)
                        ->whereHas('ticket', fn ($q) => $q->where('organization_id', $organization->id))
                        ->exists();
                    if ($used) {
                        $fail('Document :position: your organization already used this Ref No.');
                    }
                },
            ],
        ], [
            'ref_no.unique' => 'Your organization already used this Ref No.',
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

                return $ticket;
            }), 0, fn ($e) => $e instanceof UniqueConstraintViolationException
                && $organization->tickets()->where('ref_no', $data['ref_no'])->doesntExist());
        } catch (UniqueConstraintViolationException) {
            return back()->withInput()->withErrors(['ref_no' => 'Your organization already used this Ref No.']);
        }

        $first = $organization->signatories->first();

        return redirect('/officer/tickets')->with('status',
            "Ticket {$ticket->code} submitted. Hand the papers to {$first->displayName()} ({$first->pivot->title}).");
    }
}
