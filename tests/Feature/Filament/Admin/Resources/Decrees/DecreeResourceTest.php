<?php

use App\Contracts\AwardDecreeSynchronizer;
use App\Contracts\DecreeMetaParser;
use App\Exceptions\DecreeParseException;
use App\Filament\Admin\Resources\Decrees\Pages\ListDecrees;
use App\Models\Awardee;
use App\Models\Decree;
use App\Models\User;
use App\Services\DecreeAwardeeImporter;
use Carbon\CarbonImmutable;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create());
});

it('renders the decrees list page', function () {
    $firstDecree = Decree::factory()->create([
        'number' => '123/2026',
        'date' => '2026-09-08',
        'url' => 'https://example.com/123',
    ]);
    $secondDecree = Decree::factory()->create([
        'number' => '124/2026',
        'date' => '2026-09-09',
        'url' => 'https://example.com/124',
    ]);
    $decrees = Decree::query()->whereKey([$firstDecree->getKey(), $secondDecree->getKey()])->get();

    livewire(ListDecrees::class)
        ->assertOk()
        ->assertActionHasLabel(CreateAction::class, 'Додати указ')
        ->assertCanSeeTableRecords($decrees);
});

it('sorts the decrees by date', function () {
    $olderDecree = Decree::factory()->create([
        'number' => '121/2026',
        'date' => '2026-01-05',
        'url' => 'https://example.com/121',
    ]);
    $newerDecree = Decree::factory()->create([
        'number' => '122/2026',
        'date' => '2026-09-09',
        'url' => 'https://example.com/122',
    ]);

    livewire(ListDecrees::class)
        ->sortTable('date', 'asc')
        ->assertCanSeeTableRecords([$olderDecree, $newerDecree], inOrder: true);
});

it('creates a decree from the form', function () {
    $url = 'https://www.president.gov.ua/documents/8752026-61465';

    $decreeMetaParser = Mockery::mock(DecreeMetaParser::class);
    $decreeMetaParser->shouldReceive('getDecreeNumber')
        ->once()
        ->with($url)
        ->andReturn('875/2026');
    $decreeMetaParser->shouldReceive('getDecreeDate')
        ->once()
        ->with($url)
        ->andReturn(CarbonImmutable::parse('2026-09-04'));

    $this->app->instance(DecreeMetaParser::class, $decreeMetaParser);

    livewire(ListDecrees::class)
        ->mountAction(CreateAction::class)
        ->fillForm([
            'url' => $url,
        ])
        ->goToNextWizardStep()
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('decrees', [
        'number' => '875/2026',
        'url' => $url,
    ]);
    expect(Decree::query()->where('number', '875/2026')->firstOrFail()->date->toDateString())->toBe('2026-09-04');
});

it('validates decree form fields on create', function () {
    livewire(ListDecrees::class)
        ->mountAction(CreateAction::class)
        ->fillForm([
            'url' => 'not-an-url',
        ])
        ->goToNextWizardStep()
        ->assertHasFormErrors([
            'url' => 'url',
        ]);
});

it('does not expose decree editing actions', function () {
    $decree = Decree::factory()->create([
        'number' => '126/2026',
        'date' => '2026-09-11',
        'url' => 'https://example.com/126',
    ]);

    livewire(ListDecrees::class)
        ->assertActionDoesNotExist(TestAction::make('edit')->table($decree))
        ->assertTableActionDoesNotExist('edit', null, $decree);
});

it('counts the awardees of every decree and separates the posthumous ones', function () {
    $decree = Decree::factory()->create();
    Awardee::factory()->count(2)->for($decree, 'decree')->create();
    Awardee::factory()->for($decree, 'decree')->create(['is_posthumous' => true]);

    $decreeWithoutPosthumousAwardees = Decree::factory()->create();
    Awardee::factory()->for($decreeWithoutPosthumousAwardees, 'decree')->create();

    livewire(ListDecrees::class)
        ->assertTableColumnStateSet('awardees_count', 3, $decree)
        ->assertTableColumnFormattedStateSet('awardees_count', '3 (1 посмертно)', $decree)
        ->assertTableColumnFormattedStateSet('awardees_count', 1, $decreeWithoutPosthumousAwardees);
});

it('offers the awardee import action for every decree', function () {
    $decree = Decree::factory()->create();

    livewire(ListDecrees::class)
        ->assertActionExists(TestAction::make('importAwardees')->table($decree))
        ->assertActionHasLabel(TestAction::make('importAwardees')->table($decree), 'Імпортувати нагороджених і нагороди');
});

it('offers the manual decrees sync action', function () {
    livewire(ListDecrees::class)
        ->assertActionExists(TestAction::make('syncDecrees'))
        ->assertActionHasLabel(TestAction::make('syncDecrees'), __('Sync decrees'));
});

it('runs decree synchronization from the header action', function () {
    $this->mock(AwardDecreeSynchronizer::class)
        ->shouldReceive('sync')
        ->once()
        ->andReturn([
            'added' => 2,
            'awardees' => 174,
            'skipped' => 0,
        ]);

    livewire(ListDecrees::class)
        ->callAction(TestAction::make('syncDecrees'))
        ->assertNotified(
            Notification::make()
                ->success()
                ->title(__('Synchronization completed'))
                ->body(__('New decrees: :added, Imported awardees: :awardees, Skipped decrees: :skipped', [
                    'added' => 2,
                    'awardees' => 174,
                    'skipped' => 0,
                ])),
        );
});

it('notifies when manual decrees synchronization fails', function () {
    $this->mock(AwardDecreeSynchronizer::class)
        ->shouldReceive('sync')
        ->once()
        ->andThrow(new DecreeParseException('Decree list is empty.'));

    livewire(ListDecrees::class)
        ->callAction(TestAction::make('syncDecrees'))
        ->assertNotified(
            Notification::make()
                ->danger()
                ->title(__('Unable to synchronize decrees'))
                ->body('Decree list is empty.'),
        );
});

it('imports the awardees of the decree from the table action', function () {
    $decree = Decree::factory()->create();

    $this->mock(DecreeAwardeeImporter::class)
        ->shouldReceive('import')
        ->once()
        ->with(Mockery::on(fn (Decree $record): bool => $record->getKey() === $decree->getKey()))
        ->andReturn(174);

    livewire(ListDecrees::class)
        ->callAction(TestAction::make('importAwardees')->table($decree))
        ->assertNotified(
            Notification::make()
                ->success()
                ->title('Нагороджених і нагороди імпортовано')
                ->body('Імпортовано нагороджених: 174'),
        );
});

it('notifies when the awardees of the decree cannot be imported', function () {
    $decree = Decree::factory()->create();

    $this->mock(DecreeAwardeeImporter::class)
        ->shouldReceive('import')
        ->once()
        ->andThrow(new DecreeParseException('Decree is not about state awards.'));

    livewire(ListDecrees::class)
        ->callAction(TestAction::make('importAwardees')->table($decree))
        ->assertNotified(
            Notification::make()
                ->danger()
                ->title('Не вдалося імпортувати нагороджених і нагороди')
                ->body('Decree is not about state awards.'),
        );
});
