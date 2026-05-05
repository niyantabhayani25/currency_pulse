<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ReportStatus;
use App\Jobs\GenerateReportJob;
use App\Models\Report;
use Illuminate\Console\Command;

class ProcessReportsCommand extends Command
{
    protected $signature   = 'reports:process';
    protected $description = 'Dispatch a job for every pending report';

    public function handle(): int
    {
        $reports = Report::where('status', ReportStatus::Pending)->get();

        if ($reports->isEmpty()) {
            $this->info('No pending reports.');

            return self::SUCCESS;
        }

        foreach ($reports as $report) {
            GenerateReportJob::dispatch($report);
        }

        $this->info("Dispatched {$reports->count()} report job(s).");

        return self::SUCCESS;
    }
}
