<?php

namespace App\Services;

use App\Models\ContributionPlan;
use App\Models\ContributionSession;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ContributionSessionGenerator
{
    private const DEFAULT_HORIZON_MONTHS = 12;

    public function syncForPlan(ContributionPlan $plan): void
    {
        if ($plan->status === 'inactive') {
            return;
        }

        $plan->loadMissing('sessions');

        $this->deleteRegenerableSessions($plan);

        foreach ($this->buildDueDates($plan) as $dueDate) {
            ContributionSession::firstOrCreate(
                [
                    'contribution_plan_id' => $plan->id,
                    'due_date' => $dueDate->toDateString(),
                ],
                [
                    'tontine_id' => $plan->tontine_id,
                    'title' => $this->buildSessionTitle($plan, $dueDate),
                    'meeting_date' => $dueDate->copy()->startOfDay(),
                    'expected_amount' => $plan->amount,
                    'status' => 'scheduled',
                ],
            );
        }
    }

    private function deleteRegenerableSessions(ContributionPlan $plan): void
    {
        $plan->sessions()
            ->where('status', '!=', 'closed')
            ->doesntHave('payments')
            ->delete();
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function buildDueDates(ContributionPlan $plan): Collection
    {
        $startDate = Carbon::parse($plan->starts_on)->startOfDay();
        $endDate = $plan->ends_on
            ? Carbon::parse($plan->ends_on)->startOfDay()
            : Carbon::now()->startOfDay()->addMonthsNoOverflow(self::DEFAULT_HORIZON_MONTHS);

        if ($endDate->lt($startDate)) {
            return collect();
        }

        return match ($plan->frequency) {
            'weekly' => $this->buildWeeklyDueDates($plan, $startDate, $endDate),
            'monthly' => $this->buildMonthlyDueDates($plan, $startDate, $endDate),
            'interval_days' => $this->buildIntervalDueDates($plan, $startDate, $endDate),
            default => collect([$startDate]),
        };
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function buildWeeklyDueDates(
        ContributionPlan $plan,
        Carbon $startDate,
        Carbon $endDate,
    ): Collection {
        $weekday = strtolower($plan->weekday ?: $startDate->englishDayOfWeek);
        $cursor = $startDate->copy();

        while (strtolower($cursor->englishDayOfWeek) !== $weekday) {
            $cursor->addDay();
        }

        $dates = collect();
        while ($cursor->lte($endDate)) {
            $dates->push($cursor->copy());
            $cursor->addWeek();
        }

        return $dates;
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function buildMonthlyDueDates(
        ContributionPlan $plan,
        Carbon $startDate,
        Carbon $endDate,
    ): Collection {
        $dates = collect();
        $monthCursor = $startDate->copy()->startOfMonth();

        while ($monthCursor->lte($endDate)) {
            $dueDate = $plan->schedule_type === 'weekday_position'
                ? $this->resolveMonthlyWeekdayPositionDate($plan, $monthCursor)
                : $this->resolveMonthlyDayOfMonthDate($plan, $monthCursor, $startDate);

            if ($dueDate->betweenIncluded($startDate, $endDate)) {
                $dates->push($dueDate);
            }

            $monthCursor->addMonthNoOverflow()->startOfMonth();
        }

        return $dates;
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function buildIntervalDueDates(
        ContributionPlan $plan,
        Carbon $startDate,
        Carbon $endDate,
    ): Collection {
        $interval = max(1, (int) ($plan->day_interval ?? 1));
        $dates = collect();
        $cursor = $startDate->copy();

        while ($cursor->lte($endDate)) {
            $dates->push($cursor->copy());
            $cursor->addDays($interval);
        }

        return $dates;
    }

    private function resolveMonthlyDayOfMonthDate(
        ContributionPlan $plan,
        Carbon $monthCursor,
        Carbon $startDate,
    ): Carbon {
        $day = max(1, min(31, (int) ($plan->day_of_month ?? $startDate->day)));
        $maxDay = $monthCursor->copy()->endOfMonth()->day;

        return $monthCursor->copy()->day(min($day, $maxDay))->startOfDay();
    }

    private function resolveMonthlyWeekdayPositionDate(
        ContributionPlan $plan,
        Carbon $monthCursor,
    ): Carbon {
        $weekday = strtolower($plan->weekday ?: 'monday');
        $weekOfMonth = strtolower($plan->week_of_month ?: 'first');

        if ($weekOfMonth === 'last') {
            $cursor = $monthCursor->copy()->endOfMonth()->startOfDay();

            while (strtolower($cursor->englishDayOfWeek) !== $weekday) {
                $cursor->subDay();
            }

            return $cursor;
        }

        $occurrence = match ($weekOfMonth) {
            'second' => 2,
            'third' => 3,
            'fourth' => 4,
            default => 1,
        };

        $cursor = $monthCursor->copy()->startOfDay();
        while (strtolower($cursor->englishDayOfWeek) !== $weekday) {
            $cursor->addDay();
        }

        return $cursor->addWeeks($occurrence - 1);
    }

    private function buildSessionTitle(ContributionPlan $plan, CarbonInterface $dueDate): string
    {
        return sprintf('%s - %s', $plan->name, $dueDate->format('d/m/Y'));
    }
}
