<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tontine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TontineController extends Controller
{
    public function index(): JsonResponse
    {
        $tontines = Tontine::query()
            ->withCount(['members', 'payments', 'cashOutflows'])
            ->latest()
            ->get();

        return response()->json(['data' => $tontines]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'currency' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'string', 'max:50'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $tontine = Tontine::create($validated);

        return response()->json([
            'message' => 'Tontine creee avec succes.',
            'data' => $tontine->loadCount(['members', 'payments', 'cashOutflows']),
        ], 201);
    }

    public function show(Tontine $tontine): JsonResponse
    {
        $tontine->load([
            'owner',
            'members',
            'contributionPlans',
            'contributionSessions',
        ]);

        return response()->json(['data' => $tontine]);
    }

    public function update(Request $request, Tontine $tontine): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'currency' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'string', 'max:50'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $tontine->update($validated);

        return response()->json([
            'message' => 'Tontine mise a jour.',
            'data' => $tontine->fresh(),
        ]);
    }

    public function destroy(Tontine $tontine): JsonResponse
    {
        $tontine->delete();

        return response()->json([
            'message' => 'Tontine supprimee.',
        ]);
    }
}
