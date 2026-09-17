<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Wipe Mongo demo/runtime collections (keeps migrations).
 */
final class DemoWipeCommand extends Command
{
    protected $signature = 'demo:wipe {--force : Skip confirmation}';

    protected $description = 'Delete all MongoDB app data (except migrations) for a clean local demo';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Wipe ALL data in MongoDB database (except migrations)?', true)) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        try {
            $database = DB::connection('mongodb')->getDatabase();
        } catch (Throwable $exception) {
            $this->error('MongoDB connection failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $kept = ['migrations'];
        $wiped = 0;

        foreach ($database->listCollectionNames() as $name) {
            if (in_array($name, $kept, true)) {
                continue;
            }

            $deleted = $database->selectCollection($name)->deleteMany([])->getDeletedCount();
            $this->line("  - {$name}: deleted {$deleted}");
            $wiped++;
        }

        @unlink(storage_path('app/demo-ids.json'));

        $this->info("Done. Wiped {$wiped} collections.");

        return self::SUCCESS;
    }
}
