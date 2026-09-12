<?php

use App\Models\Award;
use App\Models\Awardee;
use App\Models\Decree;

it('stores the decree and the award on the awardee record', function () {
    $decree = Decree::factory()->create();
    $award = Award::factory()->create();

    $awardee = $decree->awardees()->create([
        'full_name' => 'Іваненко Іван Іванович',
        'rank' => 'капітан',
        'award_id' => $award->getKey(),
    ]);

    $this->assertDatabaseHas('awardees', [
        'id' => $awardee->getKey(),
        'decree_id' => $decree->getKey(),
        'award_id' => $award->getKey(),
        'full_name' => 'Іваненко Іван Іванович',
        'rank' => 'капітан',
        'is_posthumous' => false,
    ]);

    expect($awardee->decree->is($decree))->toBeTrue()
        ->and($awardee->award->is($award))->toBeTrue();
});

it('stores the posthumous flag on the awardee', function () {
    $decree = Decree::factory()->create();
    $award = Award::factory()->create();

    $awardee = $decree->awardees()->create([
        'full_name' => 'Іваненко Іван Іванович',
        'rank' => 'капітан',
        'award_id' => $award->getKey(),
        'is_posthumous' => true,
    ]);

    expect($awardee->is_posthumous)->toBeTrue()
        ->and($awardee->fresh()->is_posthumous)->toBeTrue();
});

it('keeps the full namesakes of one decree as separate awardees', function () {
    $decree = Decree::factory()->create();
    $award = Award::factory()->create();

    $attributes = [
        'full_name' => 'Іваненко Іван Іванович',
        'rank' => 'капітан',
        'award_id' => $award->getKey(),
    ];

    $firstAwardee = $decree->awardees()->create($attributes);
    $secondAwardee = $decree->awardees()->create($attributes);

    expect($decree->awardees()->count())->toBe(2)
        ->and($firstAwardee->isNot($secondAwardee))->toBeTrue();
});

it('stores the same person as a separate awardee per decree', function () {
    $firstDecree = Decree::factory()->create();
    $secondDecree = Decree::factory()->create();
    $firstAward = Award::factory()->create();
    $secondAward = Award::factory()->create();

    $firstAwardee = $firstDecree->awardees()->create([
        'full_name' => 'Іваненко Іван Іванович',
        'rank' => 'капітан',
        'award_id' => $firstAward->getKey(),
    ]);
    $secondAwardee = $secondDecree->awardees()->create([
        'full_name' => 'Іваненко Іван Іванович',
        'rank' => 'капітан',
        'award_id' => $secondAward->getKey(),
    ]);

    expect(Awardee::query()->where('full_name', 'Іваненко Іван Іванович')->count())->toBe(2)
        ->and($firstAwardee->decree->is($firstDecree))->toBeTrue()
        ->and($secondAwardee->decree->is($secondDecree))->toBeTrue();
});

it('deletes the awardees of a decree together with the decree', function () {
    $decree = Decree::factory()->create();
    $awardee = Awardee::factory()->create(['decree_id' => $decree->getKey()]);

    $decree->delete();

    expect(Awardee::query()->whereKey($awardee->getKey())->exists())->toBeFalse();
});
