<?php

use App\Filament\User\Resources\Decrees\Pages\ListDecrees;
use App\Models\Awardee;
use App\Models\Decree;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('user');
});

it('lists the decrees without any mutation actions', function () {
    $decree = Decree::factory()->create(['number' => '875/2026']);

    livewire(ListDecrees::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$decree])
        ->assertActionDoesNotExist('create')
        ->assertTableActionDoesNotExist('importAwardees', null, $decree)
        ->assertTableActionDoesNotExist('delete', null, $decree)
        ->assertTableBulkActionDoesNotExist('delete');
});

it('counts the awardees of every decree', function () {
    $decree = Decree::factory()->create();
    Awardee::factory()->count(2)->for($decree, 'decree')->create();
    Awardee::factory()->for($decree, 'decree')->create(['is_posthumous' => true]);

    livewire(ListDecrees::class)
        ->assertTableColumnStateSet('awardees_count', 3, $decree);
});
