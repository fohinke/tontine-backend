<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContributionPlan;
use App\Services\ContributionSessionGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContributionPlanController extends Controller
{
    public function __construct(
        private readonly ContributionSessionGenerator $sessionGenerator,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $plans = ContributionPlan::query()
            ->when($request->integer('tontine_id'), function ($query, $tontineId) {
                $query->where('tontine_id', $tontineId);
            })
            ->with('tontine')
            ->withCount('sessions')
            ->latest()
            ->get();

        return response()->json(['data' => $plans]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePlan($request);

        $plan = DB::transaction(function () use ($validated) {
            $plan = ContributionPlan::create($validated);
            $this->sessionGenerator->syncForPlan($plan);

            return $plan;
        });

        return response()->json([
            'message' => 'Parametrage de cotisation cree.',
            'data' => $plan->load('tontine')->loadCount('sessions'),
        ], 201);
    }

    public function show(ContributionPlan $contributionPlan): JsonResponse
    {
        $contributionPlan->load(['tontine', 'sessions']);

        return response()->json(['data' => $contributionPlan]);
    }

    public function update(Request $request, ContributionPlan $contributionPlan): JsonResponse
    {
        $validated = $this->validatePlan($request, $contributionPlan, true);

        DB::transaction(function () use ($contributionPlan, $validated) {
            $contributionPlan->update($validated);
            $this->sessionGenerator->syncForPlan($contributionPlan->fresh());
        });

        return response()->json([
            'message' => 'Parametrage de cotisation mis a jour.',
            'data' => $contributionPlan->fresh()->load('tontine')->loadCount('sessions'),
        ]);
    }

    public function destroy(ContributionPlan $contributionPlan): JsonResponse
    {
        $contributionPlan->delete();

        return response()->json([
            'message' => 'Parametrage de cotisation supprime.',
        ]);
    }

    private function validatePlan(
        Request $request,
        ?ContributionPlan $existingPlan = null,
        bool $isUpdate = false,
    ): array {
        $required = $isUpdate ? ['sometimes', 'required'] : ['required'];

        $validated = $request->validate([
            'tontine_id' => [...$required, 'exists:tontines,id'],
            'name' => [...$required, 'string', 'max:255'],
            'frequency' => [...$required, 'in:weekly,monthly,interval_days'],
            'amount' => [...$required, 'numeric', 'min:0'],
            'starts_on' => [...$required, 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'day_interval' => ['nullable', 'integer', 'min:1'],
            'schedule_type' => ['nullable', 'in:day_of_month,weekday_position'],
            'day_of_month' => ['nullable', 'integer', 'between:1,31'],
            'week_of_month' => ['nullable', 'in:first,second,third,fourth,last'],
            'weekday' => ['nullable', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $payload = [
            'frequency' => $validated['frequency'] ?? $existingPlan?->frequency,
            'schedule_type' => $validated['schedule_type'] ?? $existingPlan?->schedule_type,
            'day_interval' => $validated['day_interval'] ?? $existingPlan?->day_interval,
            'day_of_month' => $validated['day_of_month'] ?? $existingPlan?->day_of_month,
            'week_of_month' => $validated['week_of_month'] ?? $existingPlan?->week_of_month,
            'weekday' => $validated['weekday'] ?? $existingPlan?->weekday,
        ];

        if ($payload['frequency'] === 'interval_days' && empty($payload['day_interval'])) {
            throw ValidationException::withMessages([
                'day_interval' => 'Le nombre de jours est requis pour une recurrence par intervalle.',
            ]);
        }

        if ($payload['frequency'] === 'weekly' && empty($payload['weekday'])) {
            throw ValidationException::withMessages([
                'weekday' => 'Le jour de semaine est requis pour une recurrence hebdomadaire.',
            ]);
        }

        if ($payload['frequency'] === 'monthly') {
            if (!in_array($payload['schedule_type'], ['day_of_month', 'weekday_position'], true)) {
                throw ValidationException::withMessages([
                    'schedule_type' => 'Le mode de planification mensuelle est requis.',
                ]);
            }

            if ($payload['schedule_type'] === 'day_of_month' && empty($payload['day_of_month'])) {
                throw ValidationException::withMessages([
                    'day_of_month' => 'Le jour du mois est requis.',
                ]);
            }

            if (
                $payload['schedule_type'] === 'weekday_position'
                && (empty($payload['week_of_month']) || empty($payload['weekday']))
            ) {
                throw ValidationException::withMessages([
                    'week_of_month' => 'La position dans le mois et le jour sont requis.',
                    'weekday' => 'La position dans le mois et le jour sont requis.',
                ]);
            }
        }

        return $validated;
    }
}
