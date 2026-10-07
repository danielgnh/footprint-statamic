<?php

use Illuminate\Support\Facades\Process;

it('only sends visitors on a local site', function () {
    Process::fake();

    $this->artisan('footprint:visitors', ['url' => 'https://site.test/'])->assertFailed();

    Process::assertNothingRan();
});

it('runs the visitor script from the app, where Playwright is installed', function () {
    Process::fake();
    app()->detectEnvironment(fn () => 'local');

    $this->artisan('footprint:visitors', ['url' => 'https://site.test/gravel', '--visits' => 5, '--device' => ['mobile']])->assertSuccessful();

    Process::assertRan(fn ($process) => $process->path === base_path()
        && $process->command[0] === 'node'
        && str_ends_with($process->command[1], '/bin/visitors.mjs')
        && array_slice($process->command, 2) === ['https://site.test/gravel', '--visits', '5', '--device', 'mobile']);
});
