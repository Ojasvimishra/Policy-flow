<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ReconciliationService;
use Illuminate\Console\Command;

class ReconcileStalePolicies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policyflow:reconcile {--limit=100 : Maximum number of policies to evaluate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile uncertain, in-flight, and timed-out policy issuance attempts against insurer records';

    /**
     * Execute the console command.
     */
    public function handle(ReconciliationService $service): int
    {
        $limit = (int) $this->option('limit');
        $this->info("Starting PolicyFlow reconciliation pass (limit: {$limit})...");

        $result = $service->reconcile($limit);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Policies Examined', $result['examined']],
                ['Reconciled to ISSUED', $result['reconciled']],
                ['Still Pending Provider', $result['still_pending']],
                ['Safe to Retry (Unissued)', $result['safe_to_retry']],
                ['Failed / Errors', $result['failed']],
            ]
        );

        $this->info('Reconciliation run completed successfully.');

        return Command::SUCCESS;
    }
}
