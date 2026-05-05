<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\ReportResult;
use App\Services\HistoricalRateResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateReportJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    /** @var int[] */
    public array $backoff = [30, 120];

    public function __construct(
        public readonly Report $report,
    ) {}

    public function handle(HistoricalRateResolver $resolver): void
    {
        // Guard: another worker may have already picked this up.
        if ($this->report->status !== ReportStatus::Pending) {
            return;
        }

        $this->report->update(['status' => ReportStatus::Processing]);

        $result = $resolver->resolve($this->report);

        DB::transaction(function () use ($result) {
            $rows = [];
            foreach ($result->rates as $date => $rate) {
                $rows[] = [
                    'report_id' => $this->report->id,
                    'date'      => $date,
                    'rate'      => $rate,
                ];
            }

            if (!empty($rows)) {
                ReportResult::insert($rows);
            }

            $this->report->update([
                'status'       => ReportStatus::Completed,
                'data_source'  => $result->dataSource,
                'completed_at' => now(),
            ]);
        });
    }

    public function failed(Throwable $e): void
    {
        $this->report->update([
            'status'        => ReportStatus::Failed,
            'error_message' => $e->getMessage(),
        ]);
    }
}
