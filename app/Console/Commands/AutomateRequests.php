<?php

namespace App\Console\Commands;

use App\Services\RequestAutomation;
use Illuminate\Console\Command;

class AutomateRequests extends Command
{
    protected $signature = 'smartdoc:automate-requests';

    protected $description = 'Run one SmartDoc AI pass: verify new requests and advance in-progress ones.';

    public function handle(RequestAutomation $automation): int
    {
        $changed = $automation->run();

        $this->info("Automation pass complete. {$changed} request(s) updated.");

        return self::SUCCESS;
    }
}
