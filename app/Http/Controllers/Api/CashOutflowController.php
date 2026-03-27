<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashOutflow;
use App\Models\CashTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashOutflowController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $outflows = CashOutflow::query()
            ->when($request->integer('tontine_id'), function ($query, $tontineId) {
                $query->where('tontine_id', $tontineId);
            })
            ->with(['tontine', 'approvedBy'])
            ->latest('outflow_date')
            ->latest()
            ->get();

        return response()->json(['data' => $outflows]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tontine_id' => ['required', 'exists:tontines,id'],
            'approved_by_user_id' => ['nullable', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
            'outflow_date' => ['required', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'beneficiary_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $outflow = DB::transaction(function () use ($validated) {
            $outflow = CashOutflow::create($validated);
            $this->syncCashTransaction($outflow);

            return $outflow;
        });

        return response()->json([
            'message' => 'Sortie de caisse enregistree.',
            'data' => $outflow->load(['tontine', 'approvedBy']),
        ], 201);
    }

    public function show(CashOutflow $cashOutflow): JsonResponse
    {
        $cashOutflow->load(['tontine', 'approvedBy']);

        return response()->json(['data' => $cashOutflow]);
    }

    public function update(Request $request, CashOutflow $cashOutflow): JsonResponse
    {
        $validated = $request->validate([
            'tontine_id' => ['sometimes', 'required', 'exists:tontines,id'],
            'approved_by_user_id' => ['nullable', 'exists:users,id'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'reason' => ['sometimes', 'required', 'string', 'max:255'],
            'outflow_date' => ['sometimes', 'required', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'beneficiary_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($cashOutflow, $validated): void {
            $cashOutflow->update($validated);
            $this->syncCashTransaction($cashOutflow->fresh());
        });

        return response()->json([
            'message' => 'Sortie de caisse mise a jour.',
            'data' => $cashOutflow->fresh(['tontine', 'approvedBy']),
        ]);
    }

    public function destroy(CashOutflow $cashOutflow): JsonResponse
    {
        DB::transaction(function () use ($cashOutflow): void {
            CashTransaction::query()->where('cash_outflow_id', $cashOutflow->id)->delete();
            $cashOutflow->delete();
        });

        return response()->json([
            'message' => 'Sortie de caisse supprimee.',
        ]);
    }

    private function syncCashTransaction(CashOutflow $cashOutflow): void
    {
        CashTransaction::query()->updateOrCreate(
            ['cash_outflow_id' => $cashOutflow->id],
            [
                'tontine_id' => $cashOutflow->tontine_id,
                'payment_id' => null,
                'recorded_by_user_id' => $cashOutflow->approved_by_user_id,
                'type' => 'outflow',
                'amount' => $cashOutflow->amount,
                'transaction_date' => $cashOutflow->outflow_date,
                'label' => $cashOutflow->reason,
                'reference' => null,
                'notes' => $cashOutflow->notes,
            ],
        );
    }
}
