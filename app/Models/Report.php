<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportInterval;
use App\Enums\ReportRange;
use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id', 'currency_id', 'range', 'interval',
        'status', 'data_source', 'error_message', 'completed_at',
    ];

    protected $appends = ['range_label'];

    protected function casts(): array
    {
        return [
            'range'        => ReportRange::class,
            'interval'     => ReportInterval::class,
            'status'       => ReportStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function getRangeLabelAttribute(): string
    {
        return $this->range->label();
    }

    public function isCompleted(): bool
    {
        return $this->status === ReportStatus::Completed;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(ReportResult::class)->orderBy('date');
    }
}
