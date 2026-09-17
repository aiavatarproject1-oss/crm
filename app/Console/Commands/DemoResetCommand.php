<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Wipe + seed fixed demo data in one step.
 */
final class DemoResetCommand extends Command
{
    protected $signature = 'demo:reset {--force : Skip wipe confirmation}';

    protected $description = 'Wipe Mongo data and seed fixed demo tenant/influencer/knowledge';

    public function handle(): int
    {
        $wipeArgs = $this->option('force') ? ['--force' => true] : [];
        $code = $this->call('demo:wipe', $wipeArgs);
        if ($code !== self::SUCCESS) {
            return $code;
        }

        return $this->call('demo:seed');
    }
}
