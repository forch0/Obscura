<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Models\WorkspaceAccessCode;
use App\Services\AccessCode\CodeGeneratorService;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;

class AccessCodeController extends Controller
{
    public function __construct(private AuditLogger $audit, private CodeGeneratorService $codes) {}

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize('viewAny', [WorkspaceAccessCode::class, $workspace]);

        $codes = $workspace->accessCodes()->latest()->get();

        return view('access-codes.index', [
            'workspace' => $workspace,
            'codes' => $codes,
        ]);
    }

    public function create(Request $request, Workspace $workspace)
    {
        $this->authorize('create', [WorkspaceAccessCode::class, $workspace]);

        return view('access-codes.create', ['workspace' => $workspace]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->authorize('create', [WorkspaceAccessCode::class, $workspace]);

        $validated = $request->validate([
            'scope' => ['required', 'string', 'in:workspace,collection,gallery'],
            'scope_id' => ['nullable', 'uuid'],
            'permissions' => ['required', 'integer', 'min:1', 'max:7'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:43200'], // max 30 days
            'max_uses' => ['nullable', 'integer', 'min:0'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $generated = $this->codes->generate();

        $code = WorkspaceAccessCode::create([
            'workspace_id' => $workspace->id,
            'scope' => $validated['scope'],
            'scope_id' => $validated['scope_id'] ?? null,
            'permissions' => $validated['permissions'],
            'code_hash' => $generated['hash'],
            'code_salt' => $generated['salt'],
            'code_prefix' => $generated['prefix'],
            'wrapped_dek' => null, // will be set by browser after sealing
            'expires_at' => now()->addMinutes($validated['duration_minutes']),
            'max_uses' => $validated['max_uses'] ?? 0,
            'created_by' => $request->user()->id,
            'label' => $validated['label'] ?? null,
        ]);

        $this->audit->log($request->user(), $code, 'access_code.generated', [
            'scope' => $code->scope,
            'permissions' => $code->permissions,
            'expires_at' => $code->expires_at->toISOString(),
        ]);

        // Return the raw code ONCE — never stored on server
        if ($request->expectsJson()) {
            return response()->json([
                'code_id' => $code->id,
                'raw_code' => $generated['raw'],
                'code_salt' => $generated['salt'],
            ], 201);
        }

        return redirect()->route('access-codes.show', $code)->with('raw_code', $generated['raw']);
    }

    public function show(Request $request, WorkspaceAccessCode $code)
    {
        $this->authorize('viewAny', [WorkspaceAccessCode::class, $code->workspace]);

        $rawCode = session('raw_code'); // only available immediately after creation

        return view('access-codes.show', [
            'code' => $code,
            'rawCode' => $rawCode,
        ]);
    }

    public function storeWrappedDek(Request $request, Workspace $workspace, WorkspaceAccessCode $code)
    {
        $this->authorize('create', [WorkspaceAccessCode::class, $workspace]);

        if ($code->workspace_id !== $workspace->id) {
            abort(404);
        }

        $validated = $request->validate([
            'wrapped_dek' => ['required', 'string'],
        ]);

        $code->update(['wrapped_dek' => $validated['wrapped_dek']]);

        return response()->json(['status' => 'dek_stored']);
    }

    public function revoke(Request $request, Workspace $workspace, WorkspaceAccessCode $code)
    {
        $this->authorize('delete', $code);

        if ($code->workspace_id !== $workspace->id) {
            abort(404);
        }

        $code->revoke();

        $this->audit->log($request->user(), $code, 'access_code.revoked', [
            'scope' => $code->scope,
            'label' => $code->label,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['status' => 'revoked']);
        }

        return redirect()->route('access-codes.index', $workspace);
    }
}
