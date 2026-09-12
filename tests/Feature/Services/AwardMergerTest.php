<?php

use App\Contracts\Contracts\AwardMerger;
use App\Models\Award;
use App\Models\Awardee;

it('links the awardees of the merged awards to the award with the most awardees', function () {
    $primaryAward = Award::factory()->create(['name' => 'Герой України']);
    $duplicateAward = Award::factory()->create(['name' => 'Орден Богдана Хмельницького']);

    Awardee::factory()->count(2)->for($primaryAward, 'award')->create();
    $duplicateAwardee = Awardee::factory()->for($duplicateAward, 'award')->create();

    $mergedAward = app(AwardMerger::class)->merge(
        Award::query()->whereKey([$duplicateAward->getKey(), $primaryAward->getKey()])->get()
    );

    expect($mergedAward->is($primaryAward))->toBeTrue()
        ->and($duplicateAwardee->refresh()->award_id)->toBe($primaryAward->getKey());

    $this->assertDatabaseMissing('awards', ['id' => $duplicateAward->getKey()]);
    $this->assertDatabaseCount('awards', 1);
    $this->assertDatabaseCount('awardees', 3);
});

it('merges the awards in the order of the awardees count, not of the selection', function () {
    $smallAward = Award::factory()->create();
    $bigAward = Award::factory()->create();

    Awardee::factory()->for($smallAward, 'award')->create();
    Awardee::factory()->count(3)->for($bigAward, 'award')->create();

    $mergedAward = app(AwardMerger::class)->merge(
        Award::query()->whereKey([$smallAward->getKey(), $bigAward->getKey()])->orderBy('id')->get()
    );

    expect($mergedAward->is($bigAward))->toBeTrue()
        ->and(Award::query()->whereKey($smallAward->getKey())->exists())->toBeFalse();
});

it('merges awards with an equal awardees count into the oldest one', function () {
    $oldestAward = Award::factory()->create();
    $newestAward = Award::factory()->create();

    Awardee::factory()->for($oldestAward, 'award')->create();
    Awardee::factory()->for($newestAward, 'award')->create();

    $mergedAward = app(AwardMerger::class)->merge(
        Award::query()->whereKey([$oldestAward->getKey(), $newestAward->getKey()])->orderByDesc('id')->get()
    );

    expect($mergedAward->is($oldestAward))->toBeTrue()
        ->and(Award::query()->whereKey($newestAward->getKey())->exists())->toBeFalse();
});

it('keeps the awardees of the awards that were not selected', function () {
    $primaryAward = Award::factory()->create();
    $duplicateAward = Award::factory()->create();
    $untouchedAward = Award::factory()->create();

    Awardee::factory()->count(2)->for($primaryAward, 'award')->create();
    Awardee::factory()->for($duplicateAward, 'award')->create();
    $untouchedAwardee = Awardee::factory()->for($untouchedAward, 'award')->create();

    app(AwardMerger::class)->merge(
        Award::query()->whereKey([$primaryAward->getKey(), $duplicateAward->getKey()])->get()
    );

    expect($untouchedAwardee->refresh()->award_id)->toBe($untouchedAward->getKey())
        ->and(Award::query()->whereKey($untouchedAward->getKey())->exists())->toBeTrue();
});

it('refuses to merge fewer than two awards', function () {
    $award = Award::factory()->create();
    Awardee::factory()->for($award, 'award')->create();

    app(AwardMerger::class)->merge(Award::query()->whereKey($award->getKey())->get());
})->throws(InvalidArgumentException::class, 'Select at least two awards to merge.');

it('merges awards without any awardees into the oldest one', function () {
    $oldestAward = Award::factory()->create();
    $newestAward = Award::factory()->create();

    app(AwardMerger::class)->merge(
        Award::query()->whereKey([$newestAward->getKey(), $oldestAward->getKey()])->get()
    );

    expect(Award::query()->whereKey($oldestAward->getKey())->exists())->toBeTrue()
        ->and(Award::query()->whereKey($newestAward->getKey())->exists())->toBeFalse();
});
