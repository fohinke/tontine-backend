<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContributionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'tontine_id',
        'name',
        'frequency',
        'amount',
        'starts_on',
        'ends_on',
        'day_interval',
        'schedule_type',
        'day_of_month',
        'week_of_month',
        'weekday',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'day_interval' => 'integer',
            'day_of_month' => 'integer',
        ];
    }

    public function tontine(): BelongsTo
    {
        return $this->belongsTo(Tontine::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ContributionSession::class);
    }
}
