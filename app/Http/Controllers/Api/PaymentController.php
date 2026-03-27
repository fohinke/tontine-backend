<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashTransaction;
use App\Models\ContributionSession;
use App\Models\Member;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $payments = Payment::query()
            ->when($request->integer('tontine_id'), function ($query, $tontineId) {
                $query->where('tontine_id', $tontineId);
            })
            ->with(['member', 'contributionSession', 'recordedBy'])
            ->latest('paid_at')
            ->latest()
            ->get();

        return response()->json(['data' => $payments]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tontine_id' => ['required', 'exists:tontines,id'],
            'member_id' => ['required', 'exists:members,id'],
            'contribution_session_id' => ['required', 'exists:contribution_sessions,id'],
            'recorded_by_user_id' => ['nullable', 'exists:users,id'],
            'amount_due' => ['required', 'numeric', 'min:0'],
            'amount_paid' => ['required', 'numeric', 'min:0'],
            'paid_at' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = DB::transaction(function () use ($validated) {
            $payment = Payment::create($validated);
            $this->syncCashTransaction($payment);

            return $payment;
        });

        return response()->json([
            'message' => 'Paiement enregistre avec succes.',
            'data' => $payment->load(['member', 'contributionSession', 'recordedBy']),
        ], 201);
    }

    public function show(Payment $payment): JsonResponse
    {
        $payment->load(['member', 'contributionSession', 'recordedBy']);

        return response()->json(['data' => $payment]);
    }

    public function update(Request $request, Payment $payment): JsonResponse
    {
        $validated = $request->validate([
            'tontine_id' => ['sometimes', 'required', 'exists:tontines,id'],
            'member_id' => ['sometimes', 'required', 'exists:members,id'],
            'contribution_session_id' => ['sometimes', 'required', 'exists:contribution_sessions,id'],
            'recorded_by_user_id' => ['nullable', 'exists:users,id'],
            'amount_due' => ['sometimes', 'required', 'numeric', 'min:0'],
            'amount_paid' => ['sometimes', 'required', 'numeric', 'min:0'],
            'paid_at' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($payment, $validated): void {
            $payment->update($validated);
            $this->syncCashTransaction($payment->fresh());
        });

        return response()->json([
            'message' => 'Paiement mis a jour.',
            'data' => $payment->fresh(['member', 'contributionSession', 'recordedBy']),
        ]);
    }

    public function destroy(Payment $payment): JsonResponse
    {
        DB::transaction(function () use ($payment): void {
            CashTransaction::query()->where('payment_id', $payment->id)->delete();
            $payment->delete();
        });

        return response()->json([
            'message' => 'Paiement supprime.',
        ]);
    }

    public function historyByMember(Member $member): JsonResponse
    {
        $payments = $member->payments()
            ->with(['contributionSession', 'recordedBy'])
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => [
                'member' => $member,
                'payments' => $payments,
            ],
        ]);
    }

    public function historyBySession(ContributionSession $session): JsonResponse
    {
        $payments = $session->payments()
            ->with(['member', 'recordedBy'])
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => [
                'session' => $session->load(['tontine', 'contributionPlan']),
                'payments' => $payments,
            ],
        ]);
    }

    private function syncCashTransaction(Payment $payment): void
    {
        CashTransaction::query()->updateOrCreate(
            ['payment_id' => $payment->id],
            [
                'tontine_id' => $payment->tontine_id,
                'cash_outflow_id' => null,
                'recorded_by_user_id' => $payment->recorded_by_user_id,
                'type' => 'inflow',
                'amount' => $payment->amount_paid,
                'transaction_date' => $payment->paid_at ?? now(),
                'label' => 'Paiement cotisation',
                'reference' => $payment->reference,
                'notes' => $payment->notes,
            ],
        );
    }
}
