<?php

use App\Filament\Admin\Resources\Awardees\AwardeeResource;
use App\Filament\Widgets\AwardsStatsOverview;
use App\Models\Award;
use App\Models\Awardee;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

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
        ->assertSeeInOrder(['Герой України', '3', '(1 посмертно)'])
        ->assertSee('Орден Богдана Хмельницького');
});

it('shows a zero awardee count for an award nobody has received', function () {
    Award::factory()->create(['name' => 'Герой України']);

    livewire(AwardsStatsOverview::class)
        ->assertOk()
        ->assertSeeInOrder(['Герой України', '0'])
        ->assertSeeHtml('fi-wi-stats-overview-stat-value')
        ->assertDontSee('посмертно');
});

it('renders the uploaded image for an award', function () {
    Storage::fake('public');
    Storage::disk('public')->put('award-images/medal.png', 'image');

    $award = Award::factory()->create([
        'name' => 'Орден За мужність',
        'image_path' => 'award-images/medal.png',
    ]);

    $expectedUrl = Storage::disk('public')->url($award->image_path).'?v='.$award->updated_at->timestamp;

    livewire(AwardsStatsOverview::class)
        ->assertOk()
        ->assertSeeHtml('src="'.e($expectedUrl).'"')
        ->assertSeeHtml('class="size-20 object-contain"')
        ->assertSeeHtml('$refs.awardImageDialog.showModal()');
});

it('renders a fallback trophy icon when an award image is missing', function () {
    Award::factory()->create([
        'name' => 'Орден За мужність',
        'image_path' => null,
    ]);

    livewire(AwardsStatsOverview::class)
        ->assertOk()
        ->assertDontSeeHtml('<img')
        ->assertSeeHtml('size-12');
});

it('caches award statistics as plain arrays instead of serialized models', function () {
    $award = Award::factory()->create(['name' => 'Герой України']);
    Awardee::factory()->for($award, 'award')->create();

    livewire(AwardsStatsOverview::class)
        ->assertOk();

    expect(Cache::get(AwardsStatsOverview::CACHE_KEY))->toBeArray();

    livewire(AwardsStatsOverview::class)
        ->assertOk()
        ->assertSee('Герой України')
        ->assertSee('1');
});

it('orders the awards by their sort position', function () {
    $lastAward = Award::factory()->create(['name' => 'Герой України', 'sort' => 3]);
    $firstAward = Award::factory()->create(['name' => 'Орден Богдана Хмельницького', 'sort' => 1]);
    $middleAward = Award::factory()->create(['name' => 'Хрест бойових заслуг', 'sort' => 2]);

    Awardee::factory()->count(5)->for($lastAward, 'award')->create();

    livewire(AwardsStatsOverview::class)
        ->assertOk()
        ->assertSeeInOrder(['Орден Богдана Хмельницького', 'Хрест бойових заслуг', 'Герой України']);
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
