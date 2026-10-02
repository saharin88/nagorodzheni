<?php

use App\Contracts\DecreeMetaParser;
use App\Exceptions\DecreeParseException;
use App\Models\Decree;

it('marks the stored decrees that confer the Hero of Ukraine title', function () {
    $heroDecree = Decree::factory()->create([
        'number' => '264/2022',
        'url' => 'https://www.president.gov.ua/documents/2642022-42217',
    ]);
    $awardDecree = Decree::factory()->create([
        'number' => '875/2026',
        'url' => 'https://www.president.gov.ua/documents/8752026-61465',
    ]);

    $this->mock(DecreeMetaParser::class)
        ->shouldReceive('isHeroDecree')
        ->andReturnUsing(fn (string $url): bool => $url === $heroDecree->url);

    $this->artisan('decrees:mark-hero')
        ->expectsOutput(__('Hero of Ukraine decrees marked: :count', ['count' => 1]))
        ->assertSuccessful();

    expect($heroDecree->refresh()->is_hero)->toBeTrue()
        ->and($awardDecree->refresh()->is_hero)->toBeFalse();
});

it('skips the decrees whose page cannot be read and keeps marking the rest', function () {
    $failedDecree = Decree::factory()->create();
    $heroDecree = Decree::factory()->create();

    $this->mock(DecreeMetaParser::class)
        ->shouldReceive('isHeroDecree')
        ->andReturnUsing(function (string $url) use ($failedDecree, $heroDecree): bool {
            if ($url === $failedDecree->url) {
                throw new DecreeParseException('The site protection answered instead of the decree page.');
            }

            return $url === $heroDecree->url;
        });

    $this->artisan('decrees:mark-hero')
        ->expectsOutput(__('Hero of Ukraine decrees marked: :count', ['count' => 1]))
        ->expectsOutput(__('Decrees skipped because of errors: :total', ['total' => 1]))
        ->assertSuccessful();

    expect($heroDecree->refresh()->is_hero)->toBeTrue()
        ->and($failedDecree->refresh()->is_hero)->toBeFalse();
});

it('does not ask again for the decrees that are marked already', function () {
    Decree::factory()->create(['is_hero' => true]);
    $awardDecree = Decree::factory()->create(['is_hero' => false]);

    $this->mock(DecreeMetaParser::class)
        ->shouldReceive('isHeroDecree')
        ->once()
        ->with($awardDecree->url)
        ->andReturn(false);

    $this->artisan('decrees:mark-hero')
        ->expectsOutput(__('Hero of Ukraine decrees marked: :count', ['count' => 0]))
        ->assertSuccessful();
});
