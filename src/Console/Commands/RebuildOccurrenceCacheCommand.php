<?php

declare(strict_types=1);

namespace ElSchneider\StatamicCalendar\Console\Commands;

use ElSchneider\StatamicCalendar\Facades\Occurrences;
use Illuminate\Console\Command;

class RebuildOccurrenceCacheCommand extends Command
{
    protected $signature = 'occurrences:rebuild';

    protected $description = 'Rebuild the occurrence cache from event entries';

    public function handle(): int
    {
        $this->info('Rebuilding occurrence cache...');

        $startTime = microtime(true);

        Occurrences::rebuild();

        $elapsed = round(microtime(true) - $startTime, 2);
        $count = Occurrences::all()->count();

        $this->info("Done! Cached {$count} occurrences in {$elapsed}s");

        return Command::SUCCESS;
    }
}
