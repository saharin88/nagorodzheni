<?php

use App\Filament\Admin\Resources\Awardees\AwardeeResource;
use App\Filament\Widgets\AwardsStatsOverview;
use App\Models\Award;
use App\Models\Awardee;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Panel;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create());
});

it('counts the awardees of every award and the posthumous ones among them', function () {
    $award = Award::factory()->create(['name' => 'Герой України']);
    Awardee::factory()->count(2)->for($award, 'award')->create();
    Awardee::factory()->for($award, 'award')->create(['is_posthumous' => true]);

    Award::factory()->create(['name' => 'Орден Богдана Хмельницького']);

    livewire(AwardsStatsOverview::class)
        ->assertOk()
        ->assertSeeInOrder(['Герой України', '3', '(1 posthumous)'])
        ->assertSee('Орден Богдана Хмельницького');
});

it('shows a zero awardee count for an award nobody has received', function () {
    Award::factory()->create(['name' => 'Герой України']);

    livewire(AwardsStatsOverview::class)
        ->assertOk()
        ->assertSeeInOrder(['Герой України', '0'])
        ->assertSeeHtml('fi-wi-stats-overview-stat-value')
        ->assertDontSee('posthumous');
});

it('orders the awards by the number of awardees', function () {
    $biggestAward = Award::factory()->create(['name' => 'Герой України']);
    $smallestAward = Award::factory()->create(['name' => 'Орден Богдана Хмельницького']);

    Awardee::factory()->count(2)->for($biggestAward, 'award')->create();
    Awardee::factory()->for($smallestAward, 'award')->create();

    livewire(AwardsStatsOverview::class)
        ->assertOk()
        ->assertSeeInOrder(['Герой України', 'Орден Богдана Хмельницького']);
});

it('links every award to the awardees of that award', function () {
    $award = Award::factory()->create();
    Awardee::factory()->for($award, 'award')->create();

    $url = AwardeeResource::getFilteredIndexUrl(['award' => [$award->getKey()]]);

    livewire(AwardsStatsOverview::class)
        ->assertOk()
        ->assertSeeHtml('href="'.e($url).'"');
});

it('renders without links in a panel that has no awardee resource', function () {
    Filament::registerPanel(Panel::make()->id('guest')->path('guest'));
    Filament::setCurrentPanel('guest');

    $award = Award::factory()->create(['name' => 'Герой України']);
    Awardee::factory()->for($award, 'award')->create();

    livewire(AwardsStatsOverview::class)
        ->assertOk()
        ->assertSee('Герой України')
        ->assertDontSeeHtml('href=');
});

it('is registered on the dashboard of the panel', function () {
    expect(Filament::getWidgets())->toContain(AwardsStatsOverview::class);
});
