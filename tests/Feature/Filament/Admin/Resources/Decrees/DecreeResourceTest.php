<?php

use App\Filament\Admin\Resources\Decrees\Pages\CreateDecree;
use App\Filament\Admin\Resources\Decrees\Pages\EditDecree;
use App\Filament\Admin\Resources\Decrees\Pages\ListDecrees;
use App\Models\Decree;
use App\Models\User;
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
    livewire(CreateDecree::class)
        ->fillForm([
            'number' => '125/2026',
            'date' => '2026-09-10',
            'url' => 'https://example.com/125',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('decrees', [
        'number' => '125/2026',
        'url' => 'https://example.com/125',
    ]);
    expect(Decree::query()->where('number', '125/2026')->firstOrFail()->date->toDateString())->toBe('2026-09-10');
});

it('validates decree form fields on create', function () {
    livewire(CreateDecree::class)
        ->fillForm([
            'number' => '',
            'date' => null,
            'url' => 'not-an-url',
        ])
        ->call('create')
        ->assertHasFormErrors([
            'number' => 'required',
            'date' => 'required',
            'url' => 'url',
        ]);
});

it('updates an existing decree', function () {
    $decree = Decree::factory()->create([
        'number' => '126/2026',
        'date' => '2026-09-11',
        'url' => 'https://example.com/126',
    ]);

    livewire(EditDecree::class, [
        'record' => $decree->getKey(),
    ])
        ->fillForm([
            'number' => '126/2026-updated',
            'date' => '2026-09-12',
            'url' => 'https://example.com/126-updated',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('decrees', [
        'id' => $decree->id,
        'number' => '126/2026-updated',
        'url' => 'https://example.com/126-updated',
    ]);
    expect($decree->fresh()->date->toDateString())->toBe('2026-09-12');
});
