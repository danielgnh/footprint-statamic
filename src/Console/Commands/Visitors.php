<?php

namespace Danielgnh\FootprintStatamic\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class Visitors extends Command
{
    protected $signature = 'footprint:visitors
        {url : A page of this site, e.g. https://site.test/}
        {--visits=20 : How many visits to send}
        {--device=* : desktop, tablet or mobile; all three by default}';

    protected $description = 'Send headless visitors through a page of the local site, so the Interactions tab has something to show';

    public function handle(): int
    {
        if (! app()->isLocal()) {
            $this->components->error('Only in the local environment: it writes made-up visits into the footprint tables.');

            return self::FAILURE;
        }

        $command = collect([
            'node', dirname(__DIR__, 3).'/bin/visitors.mjs', $this->argument('url'),
            '--visits', $this->option('visits'),
            ...collect((array) $this->option('device'))->flatMap(fn (mixed $device): array => ['--device', $device]),
        ])->map(strval(...))->all();

        $result = Process::path(base_path())
            ->forever()
            ->run($command, fn (string $type, string $output) => $this->output->write($output));

        return $result->successful() ? self::SUCCESS : self::FAILURE;
    }
}
