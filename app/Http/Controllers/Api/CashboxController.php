<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashTransaction;
use App\Models\Member;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashboxController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $tontineId = $request->integer('tontine_id');

        $query = CashTransaction::query();
        if ($tontineId) {
            $query->where('tontine_id', $tontineId);
        }

        $totals = (clone $query)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'inflow' THEN amount ELSE 0 END), 0) as inflows")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'outflow' THEN amount ELSE 0 END), 0) as outflows")
            ->first();

        $inflows = (float) ($totals?->inflows ?? 0);
        $outflows = (float) ($totals?->outflows ?? 0);

        return response()->json([
            'data' => [
                'inflows' => $inflows,
                'outflows' => $outflows,
                'balance' => $inflows - $outflows,
                'transactions_count' => (clone $query)->count(),
            ],
        ]);
    }

    public function unpaidMembers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'exists:contribution_sessions,id'],
        ]);

        $paidMemberIds = Payment::query()
            ->where('contribution_session_id', $validated['session_id'])
            ->where('status', 'paid')
            ->pluck('member_id');

        $members = Member::query()
            ->whereHas('tontine.contributionSessions', function ($query) use ($validated): void {
                $query->where('contribution_sessions.id', $validated['session_id']);
            })
            ->whereNotIn('id', $paidMemberIds)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return response()->json(['data' => $members]);
    }
}
