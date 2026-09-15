<?php

use App\Contracts\AwardDecreeSynchronizer;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

it('reports the decrees and the awardees it has imported', function () {
    $this->mock(AwardDecreeSynchronizer::class)
        ->shouldReceive('sync')
        ->once()
        ->andReturn(['added' => 2, 'awardees' => 5, 'skipped' => 0]);

    $this->artisan('decrees:sync')
        ->expectsOutput(__('Looking for the new decrees about state awards...'))
        ->expectsOutput(__('New decrees added: :count', ['count' => 2]))
        ->expectsOutput(__('Imported awardees: :count', ['count' => 5]))
        ->assertSuccessful();
});

it('reports the decrees it has skipped because of errors', function () {
    $this->mock(AwardDecreeSynchronizer::class)
        ->shouldReceive('sync')
        ->once()
        ->andReturn(['added' => 0, 'awardees' => 0, 'skipped' => 3]);

    $this->artisan('decrees:sync')
        ->expectsOutput(__('Looking for the new decrees about state awards...'))
        ->expectsOutput(__('Decrees skipped because of errors: :total', ['total' => 3]))
        ->assertSuccessful();
});

it('is scheduled hourly in the daytime timezone window of the site', function () {
    $this->artisan('schedule:list')->assertSuccessful();

    $event = collect(app(Schedule::class)->events())
        ->sole(fn (Event $event): bool => str_contains((string) $event->command, 'decrees:sync'));

    expect($event->expression)->toBe('0 * * * *')
        ->and($event->timezone)->toBe('Europe/Kyiv')
        ->and($event->withoutOverlapping)->toBeTrue();
});
