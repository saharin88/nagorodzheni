<?php

use App\Filament\User\Resources\Awardees\AwardeeResource;
use App\Filament\User\Resources\Awards\Pages\ListAwards;
use App\Models\Award;
use App\Models\Awardee;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('user');
});

it('lists the awards without any mutation actions', function () {
    $award = Award::factory()->create(['name' => 'Герой України']);

    livewire(ListAwards::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$award])
        ->assertActionDoesNotExist('create')
        ->assertTableActionDoesNotExist('edit', null, $award)
        ->assertTableActionDoesNotExist('delete', null, $award)
        ->assertTableBulkActionDoesNotExist('mergeAwards')
        ->assertTableBulkActionDoesNotExist('delete');
});

it('links the awardee count to the awardee list of the user panel', function () {
    $award = Award::factory()->create();
    Awardee::factory()->for($award, 'award')->create();

    livewire(ListAwards::class)
        ->assertTableColumnStateSet('awardees_count', 1, $award);

    expect(urldecode(AwardeeResource::getFilteredIndexUrl(['award' => [$award->getKey()]])))
        ->toContain('/awardees')
        ->not->toContain('/admin');
});
