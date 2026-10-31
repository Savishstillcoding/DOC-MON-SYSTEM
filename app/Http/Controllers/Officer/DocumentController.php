<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The officer hands a revised paper back after a signatory returned it.
 * It goes back to the SAME signatory (the step never changed); earlier
 * approvals stay valid.
 */
class DocumentController extends Controller
{
    public function resubmit(Request $request, Document $document): RedirectResponse
    {
        $officer = $request->user();

        $document = DB::transaction(function () use ($document, $officer) {
            $document = Document::with('ticket.organization.signatories')->lockForUpdate()->findOrFail($document->id);

            // Only the officer who filed the ticket, and only while it is returned
            if ((int) $document->ticket->officer_id !== (int) $officer->id || $document->status !== Document::RETURNED) {
                return null;
            }

            $document->status = Document::PENDING;
            $document->save();
            $document->actions()->make(['step' => $document->current_step, 'action' => DocumentAction::RESUBMITTED])
                ->user()->associate($officer)->save();

            return $document;
        });

        if ($document === null) {
            return back()->withErrors(['resubmit' => 'This document can\'t be resubmitted.']);
        }

        $signatory = $document->ticket->organization->signatories->firstWhere('pivot.step', $document->current_step);

        return back()->with('status',
            "Resubmitted {$document->title}. Hand the revised paper to {$signatory->displayName()} ({$signatory->pivot->title}).");
    }
}
