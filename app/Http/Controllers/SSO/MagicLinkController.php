<?php

namespace App\Http\Controllers\SSO;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MagicLinkController extends Controller
{
    /**
     * Generate magic link
     */
    public function generate(Request $request): Response
    {
        // TODO: Implement magic link generation
        return response('Not implemented', 501);
    }

    /**
     * Verify magic link
     */
    public function verify(Request $request): Response
    {
        // TODO: Implement magic link verification
        return response('Not implemented', 501);
    }
}
