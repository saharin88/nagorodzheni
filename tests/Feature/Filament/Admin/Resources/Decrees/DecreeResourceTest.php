<?php

use App\Contracts\DecreeMetaParser;
use App\Filament\Admin\Resources\Decrees\Pages\ListDecrees;
use App\Models\Decree;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
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
        ->assertActionHasLabel(CreateAction::class, 'Add decree')
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
