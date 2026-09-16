<?php

namespace App\Http\Controllers;

use App\Models\WorkspaceAccessCode;
use App\Services\AccessCode\CodeGeneratorService;
use App\Services\AccessCode\CodeSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class AccessCodeEntryController extends Controller
{
    public function __construct(
        private CodeGeneratorService $codes,
        private CodeSessionService $sessions,
    ) {}

    public function create()
    {
        return view('access-codes.enter');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20'],
        ]);

        $rawCode = $validated['code'];

        // Find candidate codes by prefix (lookup optimization)
        $candidates = WorkspaceAccessCode::where('revoked_at', null)
            ->where('expires_at', '>', now())
            ->get();

        $matchedCode = null;

        foreach ($candidates as $candidate) {
            if ($this->codes->verifyCode($rawCode, $candidate->code_salt, $candidate->code_hash)) {
                $matchedCode = $candidate;
                break;
            }
        }

        if (!$matchedCode) {
            return back()->withErrors(['code' => 'Invalid or expired access code.']);
        }

        if (!$matchedCode->isActive()) {
            return back()->withErrors(['code' => 'This access code is no longer active.']);
        }

        // Increment use count
        $matchedCode->incrementUseCount();

        // Issue scoped session token
        $payload = $this->sessions->issue($matchedCode);
        $cookie = cookie(
            $this->sessions->cookieName(),
            $this->sessions->encode($payload),
            $this->sessions->cookieMinutes(),
            null, null, true, true, false, 'Strict'
        );

        // Redirect to the workspace view with the DEK unsealing data
        return redirect()->route('workspaces.show', $matchedCode->workspace_id)
            ->with('access_code', $rawCode)
            ->cookie($cookie);
    }
}
