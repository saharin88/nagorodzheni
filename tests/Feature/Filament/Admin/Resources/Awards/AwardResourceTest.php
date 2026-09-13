<?php

use App\Filament\Admin\Resources\Awardees\AwardeeResource;
use App\Filament\Admin\Resources\Awards\Pages\ListAwards;
use App\Models\Award;
use App\Models\Awardee;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create());
});

it('renders the awards list page', function () {
    $firstAward = Award::factory()->create(['name' => 'Герой України']);
    $secondAward = Award::factory()->create(['name' => 'Орден Богдана Хмельницького']);
    $awards = Award::query()->whereKey([$firstAward->getKey(), $secondAward->getKey()])->get();

    livewire(ListAwards::class)
        ->assertOk()
        ->assertActionHasLabel(CreateAction::class, 'Додати нагороду')
        ->assertCanSeeTableRecords($awards);
});

it('creates an award from the modal action', function () {
    livewire(ListAwards::class)
        ->mountAction(CreateAction::class)
        ->fillForm([
            'name' => 'Герой України',
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('awards', [
        'name' => 'Герой України',
    ]);
});

it('validates the award form fields on create', function () {
    livewire(ListAwards::class)
        ->mountAction(CreateAction::class)
        ->fillForm([
            'name' => null,
        ])
        ->callMountedAction()
        ->assertHasFormErrors([
            'name' => 'required',
        ]);
});

it('rejects a duplicate award name', function () {
    Award::factory()->create(['name' => 'Герой України']);

    livewire(ListAwards::class)
        ->mountAction(CreateAction::class)
        ->fillForm([
            'name' => 'Герой України',
        ])
        ->callMountedAction()
        ->assertHasFormErrors([
            'name' => 'unique',
        ]);
});

it('edits an award from the modal table action', function () {
    $award = Award::factory()->create(['name' => 'Герой України']);

    livewire(ListAwards::class)
        ->callAction(
            TestAction::make('edit')->table($award),
            data: [
                'name' => 'Орден Богдана Хмельницького',
            ],
        )
        ->assertHasNoFormErrors();

    expect($award->refresh()->name)->toBe('Орден Богдана Хмельницького');
});

it('deletes an award from the table', function () {
    $award = Award::factory()->create();

    livewire(ListAwards::class)
        ->callAction(TestAction::make('delete')->table($award));

    expect(Award::query()->whereKey($award->getKey())->exists())->toBeFalse();
});

it('counts the awardees of every award', function () {
    $award = Award::factory()->create();
    Awardee::factory()->count(2)->for($award, 'award')->create();
    $awardWithoutAwardees = Award::factory()->create();

    livewire(ListAwards::class)
        ->assertCanSeeTableRecords([$award, $awardWithoutAwardees])
        ->assertTableColumnStateSet('awardees_count', 2, $award)
        ->assertTableColumnStateSet('awardees_count', 0, $awardWithoutAwardees);
});

it('separates the posthumous awardees from the total count', function () {
    $award = Award::factory()->create();
    Awardee::factory()->count(2)->for($award, 'award')->create();
    Awardee::factory()->for($award, 'award')->create(['is_posthumous' => true]);

    $awardWithoutPosthumousAwardees = Award::factory()->create();
    Awardee::factory()->for($awardWithoutPosthumousAwardees, 'award')->create();

    livewire(ListAwards::class)
        ->assertTableColumnFormattedStateSet('awardees_count', '3 (1 посмертно)', $award)
        ->assertTableColumnFormattedStateSet('awardees_count', 1, $awardWithoutPosthumousAwardees);
});

it('links the awardees count to the awardee list filtered by the award', function () {
    $award = Award::factory()->create();

    $url = urldecode(AwardeeResource::getFilteredIndexUrl(['award' => [$award->getKey()]]));

    expect($url)
        ->toContain('/admin/awardees')
        ->toContain('filters[award][values][0]='.$award->getKey());
});

it('does not register separate create and edit pages', function () {
    $award = Award::factory()->create();

    $this->get('/admin/awards/create')->assertNotFound();
    $this->get("/admin/awards/{$award->getKey()}/edit")->assertNotFound();
});

it('offers the award merging bulk action', function () {
    livewire(ListAwards::class)
        ->assertTableBulkActionExists('mergeAwards')
        ->assertTableBulkActionHasLabel('mergeAwards', 'Об\'єднати нагороди');
});

it('merges the selected awards into the one with the most awardees', function () {
    $primaryAward = Award::factory()->create(['name' => 'Герой України']);
    $duplicateAward = Award::factory()->create(['name' => 'Орден Богдана Хмельницького']);

    Awardee::factory()->count(2)->for($primaryAward, 'award')->create();
    $duplicateAwardee = Awardee::factory()->for($duplicateAward, 'award')->create();

    livewire(ListAwards::class)
        ->callTableBulkAction('mergeAwards', [$duplicateAward, $primaryAward])
        ->assertNotified(
            Notification::make()
                ->success()
                ->title('Нагороди об\'єднано')
                ->body('Нагороджених прив\'язано до «Герой України». Видалено нагород: 1'),
        );

    expect($duplicateAwardee->refresh()->award_id)->toBe($primaryAward->getKey())
        ->and(Award::query()->whereKey($duplicateAward->getKey())->exists())->toBeFalse()
        ->and(Award::query()->whereKey($primaryAward->getKey())->exists())->toBeTrue();
});

it('refuses to merge a single selected award', function () {
    $award = Award::factory()->create();

    livewire(ListAwards::class)
        ->callTableBulkAction('mergeAwards', [$award])
        ->assertNotified(
            Notification::make()
                ->danger()
                ->title('Не вдалося об\'єднати нагороди')
                ->body('Виберіть щонайменше дві нагороди для об\'єднання.'),
        );

    expect(Award::query()->whereKey($award->getKey())->exists())->toBeTrue();
});
