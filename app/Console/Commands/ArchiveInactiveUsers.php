<?php

namespace App\Console\Commands;

use App\Enums\RequestStatus;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Console\Command;

class ArchiveInactiveUsers extends Command
{
    protected $signature = 'smartdoc:archive-inactive-users';

    protected $description = 'Archive user accounts that have been deactivated for at least three years.';

    public function handle(): int
    {
        $archived = 0;
        User::query()
            ->unarchived()
            ->where('status', UserStatus::Deactivated->value)
            ->whereNotNull('inactive_since')
            ->where('inactive_since', '<=', now()->subYears(3))
            ->whereDoesntHave('documentRequests', fn ($query) => $query->whereNotIn('status', [
                RequestStatus::Completed->value,
                RequestStatus::Rejected->value,
            ]))
            ->chunkById(100, function ($users) use (&$archived) {
                foreach ($users as $user) {
                    $user->archive();
                    $archived++;
                }
            });

        $this->info("Archived {$archived} inactive account(s).");

        return self::SUCCESS;
    }
}
