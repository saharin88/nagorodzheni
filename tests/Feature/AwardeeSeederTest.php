<?php

use App\Contracts\Contracts\DecreeAwardeeParser;
use App\Exceptions\DecreeParseException;
use App\Models\Award;
use App\Models\Decree;
use Database\Seeders\AwardeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('imports the awardees of every stored decree', function () {
    $firstDecree = Decree::factory()->create(['url' => 'https://www.president.gov.ua/documents/8752026-61465']);
    $secondDecree = Decree::factory()->create(['url' => 'https://www.president.gov.ua/documents/8742026-61457']);

    $this->mock(DecreeAwardeeParser::class)
        ->shouldReceive('getAwardees')
        ->twice()
        ->andReturnUsing(fn (string $url): array => $url === $firstDecree->url
            ? [
                [
                    'full_name' => 'СИДОРА Юрія Васильовича',
                    'rank' => 'капітана',
                    'award' => 'орденом Богдана Хмельницького ІІ ступеня',
                    'is_posthumous' => true,
                ],
            ]
            : [
                [
                    'full_name' => 'ВОВЧЕНКА Олександра Євгеновича',
                    'rank' => 'молодшого лейтенанта',
                    'award' => 'орденом Богдана Хмельницького ІІ ступеня',
                    'is_posthumous' => false,
                ],
            ]);

    $this->seed(AwardeeSeeder::class);

    $award = Award::query()->sole();

    expect($award->name)->toBe('орденом Богдана Хмельницького ІІ ступеня');

    $this->assertDatabaseCount('awardees', 2);

    $this->assertDatabaseHas('awardees', [
        'decree_id' => $firstDecree->getKey(),
        'award_id' => $award->getKey(),
        'full_name' => 'СИДОРА Юрія Васильовича',
        'rank' => 'капітана',
        'is_posthumous' => true,
    ]);

    $this->assertDatabaseHas('awardees', [
        'decree_id' => $secondDecree->getKey(),
        'award_id' => $award->getKey(),
        'full_name' => 'ВОВЧЕНКА Олександра Євгеновича',
        'rank' => 'молодшого лейтенанта',
        'is_posthumous' => false,
    ]);
});

it('imports nothing when no decree is stored yet', function () {
    $this->mock(DecreeAwardeeParser::class)
        ->shouldNotReceive('getAwardees');

    $this->seed(AwardeeSeeder::class);

    $this->assertDatabaseCount('awardees', 0);
    $this->assertDatabaseCount('awards', 0);
});

it('keeps importing the remaining decrees when one of them cannot be parsed', function () {
    $brokenDecree = Decree::factory()->create(['url' => 'https://www.president.gov.ua/documents/8752026-61465']);
    $workingDecree = Decree::factory()->create(['url' => 'https://www.president.gov.ua/documents/8742026-61457']);

    $this->mock(DecreeAwardeeParser::class)
        ->shouldReceive('getAwardees')
        ->twice()
        ->andReturnUsing(fn (string $url): array => $url === $brokenDecree->url
            ? throw new DecreeParseException("Unable to parse decree awardees [{$url}].")
            : [
                [
                    'full_name' => 'ВОВЧЕНКА Олександра Євгеновича',
                    'rank' => 'молодшого лейтенанта',
                    'award' => 'відзнакою Президента України “Хрест бойових заслуг”',
                    'is_posthumous' => false,
                ],
            ]);

    $this->seed(AwardeeSeeder::class);

    $this->assertDatabaseCount('awardees', 1);

    $this->assertDatabaseHas('awardees', [
        'decree_id' => $workingDecree->getKey(),
        'full_name' => 'ВОВЧЕНКА Олександра Євгеновича',
        'rank' => 'молодшого лейтенанта',
    ]);
});
