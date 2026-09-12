<?php

use App\Filament\Admin\Resources\Awardees\Pages\ListAwardees;
use App\Models\Award;
use App\Models\Awardee;
use App\Models\Decree;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Tables\Filters\SelectFilter;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create());
});

it('renders the awardees list page', function () {
    $firstAwardee = Awardee::factory()->create([
        'full_name' => 'Іваненко Іван Іванович',
        'rank' => 'капітан',
    ]);
    $secondAwardee = Awardee::factory()->create([
        'full_name' => 'Петренко Петро Петрович',
        'rank' => 'майор',
    ]);
    $awardees = Awardee::query()->whereKey([$firstAwardee->getKey(), $secondAwardee->getKey()])->get();

    livewire(ListAwardees::class)
        ->assertOk()
        ->assertActionHasLabel(CreateAction::class, 'Add awardee')
        ->assertCanSeeTableRecords($awardees);
});

it('lists awards with their decree numbers in the table', function () {
    $decree = Decree::factory()->create(['number' => '123/2026']);
    $award = Award::factory()->create(['name' => 'Герой України']);

    $decree->awardees()->create([
        'full_name' => 'Іваненко Іван Іванович',
        'rank' => 'капітан',
        'award_id' => $award->getKey(),
    ]);

    livewire(ListAwardees::class)
        ->assertOk()
        ->assertSee('Герой України')
        ->assertSee('123/2026');
});

it('filters awardees by rank', function () {
    $captain = Awardee::factory()->create(['rank' => 'капітан']);
    $major = Awardee::factory()->create(['rank' => 'майор']);

    livewire(ListAwardees::class)
        ->filterTable('rank', 'капітан')
        ->assertCanSeeTableRecords([$captain])
        ->assertCanNotSeeTableRecords([$major]);
});

it('offers the distinct ranks of the awardees as rank filter options', function () {
    Awardee::factory()->create(['rank' => 'майор']);
    Awardee::factory()->create(['rank' => 'капітан']);
    Awardee::factory()->create(['rank' => 'капітан']);

    livewire(ListAwardees::class)
        ->assertTableFilterExists('rank', fn (SelectFilter $filter): bool => $filter->getOptions() === [
            'капітан' => 'капітан',
            'майор' => 'майор',
        ]);
});

it('filters awardees by award', function () {
    $heroAward = Award::factory()->create(['name' => 'Герой України']);
    $orderAward = Award::factory()->create(['name' => 'Орден Богдана Хмельницького']);

    $heroAwardee = Awardee::factory()->for($heroAward, 'award')->create();
    $orderAwardee = Awardee::factory()->for($orderAward, 'award')->create();

    livewire(ListAwardees::class)
        ->filterTable('award', $heroAward->getKey())
        ->assertCanSeeTableRecords([$heroAwardee])
        ->assertCanNotSeeTableRecords([$orderAwardee]);
});

it('filters awardees by decree number', function () {
    $firstDecree = Decree::factory()->create(['number' => '123/2026']);
    $secondDecree = Decree::factory()->create(['number' => '456/2026']);

    $firstAwardee = Awardee::factory()->for($firstDecree, 'decree')->create();
    $secondAwardee = Awardee::factory()->for($secondDecree, 'decree')->create();

    livewire(ListAwardees::class)
        ->filterTable('decree', $firstDecree->getKey())
        ->assertCanSeeTableRecords([$firstAwardee])
        ->assertCanNotSeeTableRecords([$secondAwardee]);
});

it('lists award names and decree numbers in the filter options', function () {
    Award::factory()->create(['name' => 'Герой України']);
    Decree::factory()->create(['number' => '123/2026', 'date' => '2026-01-15']);
    Decree::factory()->create(['number' => '456/2026', 'date' => '2026-02-15']);

    $html = str_replace('\\', '', livewire(ListAwardees::class)->assertOk()->html());

    expect($html)->toContain('Герой України')
        ->and($html)->toContain('123/2026')
        ->and($html)->toContain('456/2026')
        ->and(strpos($html, '456/2026'))->toBeLessThan(strpos($html, '123/2026'));
});

it('creates an awardee from the form', function () {
    $decree = Decree::factory()->create(['number' => '123/2026']);
    $award = Award::factory()->create(['name' => 'Герой України']);

    livewire(ListAwardees::class)
        ->mountAction(CreateAction::class)
        ->fillForm([
            'full_name' => 'Сидоренко Сидір Сидорович',
            'rank' => 'полковник',
            'decree_id' => $decree->getKey(),
            'award_id' => $award->getKey(),
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('awardees', [
        'full_name' => 'Сидоренко Сидір Сидорович',
        'rank' => 'полковник',
        'decree_id' => $decree->getKey(),
        'award_id' => $award->getKey(),
    ]);
});

it('validates awardee form fields on create', function () {
    livewire(ListAwardees::class)
        ->mountAction(CreateAction::class)
        ->fillForm([
            'full_name' => null,
            'rank' => null,
            'decree_id' => null,
            'award_id' => null,
        ])
        ->callMountedAction()
        ->assertHasFormErrors([
            'full_name' => 'required',
            'rank' => 'required',
            'decree_id' => 'required',
            'award_id' => 'required',
        ]);
});

it('does not register separate create and edit pages', function () {
    $awardee = Awardee::factory()->create();

    $this->get('/admin/awardees/create')->assertNotFound();
    $this->get("/admin/awardees/{$awardee->getKey()}/edit")->assertNotFound();
});

it('edits an awardee from the modal table action', function () {
    $awardee = Awardee::factory()->create([
        'full_name' => 'Іваненко Іван Іванович',
        'rank' => 'капітан',
    ]);

    livewire(ListAwardees::class)
        ->callAction(
            TestAction::make('edit')->table($awardee),
            data: [
                'full_name' => 'Іваненко Іван Петрович',
                'rank' => 'майор',
            ],
        )
        ->assertHasNoFormErrors();

    $awardee->refresh();

    expect($awardee->full_name)->toBe('Іваненко Іван Петрович')
        ->and($awardee->rank)->toBe('майор');
});

it('deletes an awardee from the table', function () {
    $awardee = Awardee::factory()->create();

    livewire(ListAwardees::class)
        ->callAction(TestAction::make('delete')->table($awardee));

    expect(Awardee::query()->whereKey($awardee->getKey())->exists())->toBeFalse();
});
