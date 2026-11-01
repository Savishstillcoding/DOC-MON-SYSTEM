<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Support\LiveStamp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /officer/live: the current fingerprint of the officer's pages (AUD-050).
 * Tiny on purpose: it is asked every 20 s by every open officer tab.
 */
class LiveController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()
            ->json(['stamp' => LiveStamp::officer($request->user())])
            ->header('Cache-Control', 'no-store, private');
    }
}
