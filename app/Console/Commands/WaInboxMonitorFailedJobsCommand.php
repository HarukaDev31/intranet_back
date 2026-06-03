<?php

namespace App\Console\Commands;

use App\Services\WhatsappInbox\WhatsappInboxAlertService;
use Illuminate\Console\Command;

class WaInboxMonitorFailedJobsCommand extends Command
{
    protected $signature = 'wa-inbox:monitor-failed-jobs';

    protected $description = 'Alerta por WhatsApp si hay jobs fallidos del módulo inbox';

    /** @var WhatsappInboxAlertService */
    protected $alertService;

    public function __construct(WhatsappInboxAlertService $alertService)
    {
        parent::__construct();
        $this->alertService = $alertService;
    }

    public function handle()
    {
        $count = $this->alertService->notifyIfFailedJobs();
        $this->info('Revisión failed_jobs inbox: ' . $count . ' notificadas.');

        return 0;
    }
}
