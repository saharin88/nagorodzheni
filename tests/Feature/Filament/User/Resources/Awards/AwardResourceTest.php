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

it('sorts the awards by their sort position', function () {
    $thirdAward = Award::factory()->create(['name' => 'Третя нагорода', 'sort' => 3]);
    $secondAward = Award::factory()->create(['name' => 'Друга нагорода', 'sort' => 2]);
    $firstAward = Award::factory()->create(['name' => 'Перша нагорода', 'sort' => 1]);

    livewire(ListAwards::class)
        ->assertCanSeeTableRecords([$firstAward, $secondAward, $thirdAward], inOrder: true);
});

it('does not let the visitors reorder the awards', function () {
    $firstAward = Award::factory()->create(['sort' => 1]);
    $secondAward = Award::factory()->create(['sort' => 2]);

    livewire(ListAwards::class)
        ->call('reorderTable', [$secondAward->getKey(), $firstAward->getKey()])
        ->assertHasNoErrors();

    expect($firstAward->refresh()->sort)->toBe(1)
        ->and($secondAward->refresh()->sort)->toBe(2);
});
