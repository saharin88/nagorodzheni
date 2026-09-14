<?php

use App\Filament\User\Resources\Awardees\AwardeeResource;
use App\Filament\User\Resources\Awards\AwardResource;
use App\Filament\User\Resources\Decrees\DecreeResource;
use App\Models\Award;
use App\Models\Awardee;
use App\Models\Decree;
use Filament\Facades\Filament;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

it('serves the user panel dashboard from the project root', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(route('filament.user.resources.awards.index'));
});

it('opens every list page without authentication', function () {
    $award = Award::factory()->create(['name' => 'Герой України']);
    $decree = Decree::factory()->create(['number' => '875/2026']);
    $awardee = Awardee::factory()->for($decree, 'decree')->for($award, 'award')->create();

    $this->get('/awards')->assertOk()->assertSee('Герой України');
    $this->get('/decrees')->assertOk()->assertSee('875/2026');
    $this->get('/awardees')->assertOk()->assertSee($awardee->full_name);
});

it('registers no login, registration, password reset, email verification or profile pages', function () {
    $panel = Filament::getPanel('user');

    expect($panel->hasLogin())->toBeFalse()
        ->and($panel->hasRegistration())->toBeFalse()
        ->and($panel->hasPasswordReset())->toBeFalse()
        ->and($panel->hasEmailVerification())->toBeFalse()
        ->and($panel->hasProfile())->toBeFalse();

    $authRoutes = Collection::make(Route::getRoutes()->getRoutesByMethod()['GET'] ?? [])
        ->map(fn (IlluminateRoute $route): ?string => $route->getName())
        ->filter(fn (?string $name): bool => str_starts_with($name ?? '', 'filament.user.auth.'))
        ->values();

    expect($authRoutes->all())->toBe([]);
});

it('forbids every mutating ability on the user resources while allowing read access', function () {
    $award = Award::factory()->create();
    $awardee = Awardee::factory()->create();
    $decree = Decree::factory()->create();

    $resources = [
        [AwardResource::class, $award],
        [AwardeeResource::class, $awardee],
        [DecreeResource::class, $decree],
    ];

    foreach ($resources as [$resource, $record]) {
        expect($resource::canCreate())->toBeFalse()
            ->and($resource::canEdit($record))->toBeFalse()
            ->and($resource::canDelete($record))->toBeFalse()
            ->and($resource::canDeleteAny())->toBeFalse()
            ->and($resource::canViewAny())->toBeTrue()
            ->and($resource::canView($record))->toBeTrue();
    }
});

it('keeps the admin panel behind authentication', function () {
    $this->get('/admin/awards')->assertRedirect('/admin/login');
});
