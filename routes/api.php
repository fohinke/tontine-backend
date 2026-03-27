<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CashOutflowController;
use App\Http\Controllers\Api\CashboxController;
use App\Http\Controllers\Api\ContributionPlanController;
use App\Http\Controllers\Api\ContributionSessionController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TontineController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::get('members/{member}/photo', [MemberController::class, 'photo']);
Route::apiResource('tontines', TontineController::class);
Route::apiResource('members', MemberController::class);
Route::apiResource('contribution-plans', ContributionPlanController::class);
Route::apiResource('contribution-sessions', ContributionSessionController::class);
Route::apiResource('payments', PaymentController::class);
Route::apiResource('cash-outflows', CashOutflowController::class);

Route::get('cashbox/summary', [CashboxController::class, 'summary']);
Route::get('reports/unpaid-members', [CashboxController::class, 'unpaidMembers']);
Route::get('payments/member/{member}', [PaymentController::class, 'historyByMember']);
Route::get('payments/session/{session}', [PaymentController::class, 'historyBySession']);
