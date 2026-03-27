<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContributionPlan;
use App\Models\ContributionSession;
use App\Services\ContributionSessionGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContributionSessionController extends Controller
{
    public function __construct(
        private readonly ContributionSessionGenerator $sessionGenerator,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        ContributionPlan::query()
            ->when($request->integer('tontine_id'), function ($query, $tontineId) {
                $query->where('tontine_id', $tontineId);
            })
            ->where('status', 'active')
            ->get()
            ->each(fn (ContributionPlan $plan) => $this->sessionGenerator->syncForPlan($plan));

        $sessions = ContributionSession::query()
            ->when($request->integer('tontine_id'), function ($query, $tontineId) {
                $query->where('tontine_id', $tontineId);
            })
            ->when($request->integer('contribution_plan_id'), function ($query, $planId) {
                $query->where('contribution_plan_id', $planId);
            })
            ->with(['tontine', 'contributionPlan'])
            ->withCount('payments')
            ->orderBy('due_date')
            ->get();

        return response()->json(['data' => $sessions]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tontine_id' => ['required', 'exists:tontines,id'],
            'contribution_plan_id' => ['required', 'exists:contribution_plans,id'],
            'title' => ['required', 'string', 'max:255'],
            'due_date' => ['required', 'date'],
            'meeting_date' => ['nullable', 'date'],
            'expected_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $session = ContributionSession::create($validated);

        return response()->json([
            'message' => 'Reunion de cotisation creee.',
            'data' => $session->load(['tontine', 'contributionPlan']),
        ], 201);
    }

    public function show(ContributionSession $contributionSession): JsonResponse
    {
        $contributionSession->load(['tontine', 'contributionPlan', 'payments.member']);

        return response()->json(['data' => $contributionSession]);
    }

    public function update(Request $request, ContributionSession $contributionSession): JsonResponse
    {
        $validated = $request->validate([
            'tontine_id' => ['sometimes', 'required', 'exists:tontines,id'],
            'contribution_plan_id' => ['sometimes', 'required', 'exists:contribution_plans,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'due_date' => ['sometimes', 'required', 'date'],
            'meeting_date' => ['nullable', 'date'],
            'expected_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $contributionSession->update($validated);

        return response()->json([
            'message' => 'Reunion de cotisation mise a jour.',
            'data' => $contributionSession->fresh(['tontine', 'contributionPlan']),
        ]);
    }

    public function destroy(ContributionSession $contributionSession): JsonResponse
    {
        $contributionSession->delete();

        return response()->json([
            'message' => 'Reunion de cotisation supprimee.',
        ]);
    }
}
