<?php

use App\Filament\Admin\Resources\Awardees\Pages\ListAwardees;
use App\Jobs\InflectAwardeeNamesJob;
use App\Models\Award;
use App\Models\Awardee;
use App\Models\Decree;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Queue;

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
        ->assertActionHasLabel(CreateAction::class, 'Додати нагородженого')
        ->assertCanSeeTableRecords($awardees);
});

it('offers the nominative names filling action', function () {
    livewire(ListAwardees::class)
        ->assertActionExists(TestAction::make('fillNominativeNames')->table()->bulk())
        ->assertActionHasLabel(TestAction::make('fillNominativeNames')->table()->bulk(), __('Fill nominative names'));
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
    $award = Award::factory()->create(['name' => 'Герой України']);
    $otherAward = Award::factory()->create(['name' => 'Орден Богдана Хмельницького']);
    $olderDecree = Decree::factory()->create(['number' => '123/2026', 'date' => '2026-01-15']);
    $newerDecree = Decree::factory()->create(['number' => '456/2026', 'date' => '2026-02-15']);

    $awardeeInOlderDecree = Awardee::factory()->for($award, 'award')->for($olderDecree, 'decree')->create();
    $awardeeInNewerDecree = Awardee::factory()->for($otherAward, 'award')->for($newerDecree, 'decree')->create();

    livewire(ListAwardees::class)
        ->assertTableFilterExists('award')
        ->assertTableFilterExists('decree')
        ->filterTable('award', $award->getKey())
        ->assertCanSeeTableRecords([$awardeeInOlderDecree])
        ->assertCanNotSeeTableRecords([$awardeeInNewerDecree])
        ->resetTableFilters()
        ->filterTable('decree', $newerDecree->getKey())
        ->assertCanSeeTableRecords([$awardeeInNewerDecree])
        ->assertCanNotSeeTableRecords([$awardeeInOlderDecree]);
});

it('creates an awardee from the form', function () {
    $decree = Decree::factory()->create(['number' => '123/2026']);
    $award = Award::factory()->create(['name' => 'Герой України']);

    livewire(ListAwardees::class)
        ->mountAction(CreateAction::class)
        ->set('mountedActions.0.data.full_name', 'Сидоренко Сидір Сидорович')
        ->set('mountedActions.0.data.full_name_nominative', 'Сидоренко Сидір Сидорович')
        ->set('mountedActions.0.data.rank', 'полковник')
        ->set('mountedActions.0.data.decree_id', $decree->getKey())
        ->set('mountedActions.0.data.award_id', $award->getKey())
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('awardees', [
        'full_name' => 'Сидоренко Сидір Сидорович',
        'full_name_nominative' => 'Сидоренко Сидір Сидорович',
        'rank' => 'полковник',
        'decree_id' => $decree->getKey(),
        'award_id' => $award->getKey(),
    ]);
});

it('validates awardee form fields on create', function () {
    livewire(ListAwardees::class)
        ->mountAction(CreateAction::class)
        ->set('mountedActions.0.data.full_name', null)
        ->set('mountedActions.0.data.rank', null)
        ->set('mountedActions.0.data.decree_id', null)
        ->set('mountedActions.0.data.award_id', null)
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
    $originalDecree = Decree::factory()->create();
    $originalAward = Award::factory()->create(['name' => 'Герой України']);
    $newAward = Award::factory()->create(['name' => 'Орден Богдана Хмельницького']);

    $awardee = Awardee::factory()->create([
        'full_name' => 'Іваненко Іван Іванович',
        'full_name_nominative' => 'Іваненко Іван Іванович',
        'rank' => 'капітан',
        'decree_id' => $originalDecree->getKey(),
        'award_id' => $originalAward->getKey(),
        'is_posthumous' => false,
    ]);

    livewire(ListAwardees::class)
        ->mountAction(TestAction::make('edit')->table($awardee))
        ->set('mountedActions.0.data.full_name', 'Іваненко Іван Петрович')
        ->set('mountedActions.0.data.full_name_nominative', 'Іваненко Іван Петрович')
        ->set('mountedActions.0.data.rank', 'майор')
        ->set('mountedActions.0.data.award_id', $newAward->getKey())
        ->set('mountedActions.0.data.is_posthumous', true)
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $awardee->refresh();

    expect($awardee->full_name)->toBe('Іваненко Іван Петрович')
        ->and($awardee->full_name_nominative)->toBe('Іваненко Іван Петрович')
        ->and($awardee->rank)->toBe('майор')
        ->and($awardee->award_id)->toBe($newAward->getKey())
        ->and($awardee->decree_id)->toBe($originalDecree->getKey())
        ->and($awardee->is_posthumous)->toBeTrue();
});

it('queues the nominative names filling for the selected awardees', function () {
    Queue::fake([InflectAwardeeNamesJob::class]);

    $firstAwardee = Awardee::factory()->create([
        'full_name' => 'Іваненка Івана Івановича',
        'full_name_nominative' => null,
    ]);
    $secondAwardee = Awardee::factory()->create([
        'full_name' => 'Петренка Петра Петровича',
        'full_name_nominative' => null,
    ]);
    $alreadyFilledAwardee = Awardee::factory()->create([
        'full_name' => 'Шевченка Тараса Григоровича',
        'full_name_nominative' => 'Шевченко Тарас Григорович',
    ]);

    livewire(ListAwardees::class)
        ->selectTableRecords([$firstAwardee, $secondAwardee, $alreadyFilledAwardee])
        ->callAction(TestAction::make('fillNominativeNames')->table()->bulk())
        ->assertNotified(
            Notification::make()
                ->success()
                ->title('Обробку розпочато')
                ->body('ПІБ у називному відмінку буде згенеровано у фоновому режимі.'),
        );

    Queue::assertPushed(InflectAwardeeNamesJob::class, function (InflectAwardeeNamesJob $job) use ($firstAwardee, $secondAwardee, $alreadyFilledAwardee): bool {
        $dispatchedAwardeeIds = $job->awardeeIds;
        $selectedAwardeeIds = [$firstAwardee->getKey(), $secondAwardee->getKey(), $alreadyFilledAwardee->getKey()];

        sort($dispatchedAwardeeIds);
        sort($selectedAwardeeIds);

        return $dispatchedAwardeeIds === $selectedAwardeeIds;
    });
});

it('deletes an awardee from the table', function () {
    $awardee = Awardee::factory()->create();

    livewire(ListAwardees::class)
        ->callAction(TestAction::make('delete')->table($awardee));

    expect(Awardee::query()->whereKey($awardee->getKey())->exists())->toBeFalse();
});

it('changes the award of the selected awardees in bulk', function () {
    $heroAward = Award::factory()->create(['name' => 'Герой України']);
    $orderAward = Award::factory()->create(['name' => 'Орден Богдана Хмельницького']);

    $firstAwardee = Awardee::factory()->for($heroAward, 'award')->create();
    $secondAwardee = Awardee::factory()->for($heroAward, 'award')->create();
    $unselectedAwardee = Awardee::factory()->for($heroAward, 'award')->create();

    livewire(ListAwardees::class)
        ->selectTableRecords([$firstAwardee, $secondAwardee])
        ->callAction(
            TestAction::make('changeAward')->table()->bulk(),
            data: ['award_id' => $orderAward->getKey()],
        )
        ->assertHasNoFormErrors()
        ->assertNotified(
            Notification::make()
                ->success()
                ->title('Нагороду змінено')
                ->body('Нагороджених прив\'язано до «Орден Богдана Хмельницького». Оновлено нагороджених: 2'),
        );

    expect($firstAwardee->refresh()->award_id)->toBe($orderAward->getKey())
        ->and($secondAwardee->refresh()->award_id)->toBe($orderAward->getKey())
        ->and($unselectedAwardee->refresh()->award_id)->toBe($heroAward->getKey());
});

it('offers the award choice when changing the award of the selected awardees', function () {
    $heroAward = Award::factory()->create(['name' => 'Герой України']);
    $orderAward = Award::factory()->create(['name' => 'Орден Богдана Хмельницького']);
    $awardee = Awardee::factory()->for($heroAward, 'award')->create();

    livewire(ListAwardees::class)
        ->selectTableRecords([$awardee])
        ->callAction(
            TestAction::make('changeAward')->table()->bulk(),
            data: ['award_id' => $heroAward->getKey()],
        )
        ->assertHasNoFormErrors()
        ->callAction(
            TestAction::make('changeAward')->table()->bulk(),
            data: ['award_id' => $orderAward->getKey()],
        )
        ->assertHasNoFormErrors();

    expect($awardee->refresh()->award_id)->toBe($orderAward->getKey());
});

it('requires an award to change the award of the selected awardees', function () {
    $heroAward = Award::factory()->create(['name' => 'Герой України']);
    $awardee = Awardee::factory()->for($heroAward, 'award')->create();

    livewire(ListAwardees::class)
        ->selectTableRecords([$awardee])
        ->callAction(TestAction::make('changeAward')->table()->bulk())
        ->assertHasFormErrors([
            'award_id' => 'required',
        ]);

    expect($awardee->refresh()->award_id)->toBe($heroAward->getKey());
});
