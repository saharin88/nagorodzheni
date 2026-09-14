<?php

use App\Filament\User\Resources\Awardees\Pages\ListAwardees;
use App\Models\Awardee;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('user');
});

it('lists the awardees without any mutation actions', function () {
    $awardee = Awardee::factory()->create(['full_name' => 'Шевченко Тарас Григорович']);

    livewire(ListAwardees::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$awardee])
        ->assertActionDoesNotExist('create')
        ->assertTableActionDoesNotExist('edit', null, $awardee)
        ->assertTableActionDoesNotExist('delete', null, $awardee)
        ->assertTableBulkActionDoesNotExist('changeAward')
        ->assertTableBulkActionDoesNotExist('delete');
});

it('filters the awardees by rank', function () {
    $awardee = Awardee::factory()->create(['rank' => 'капітана']);
    $otherAwardee = Awardee::factory()->create(['rank' => 'солдата']);

    livewire(ListAwardees::class)
        ->filterTable('rank', ['капітана'])
        ->assertCanSeeTableRecords([$awardee])
        ->assertCanNotSeeTableRecords([$otherAwardee]);
});
