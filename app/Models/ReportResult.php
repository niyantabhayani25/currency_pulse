<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportResult extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = ['report_id', 'date', 'rate'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'rate' => 'decimal:6',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
