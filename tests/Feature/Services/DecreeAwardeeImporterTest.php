<?php

use App\Contracts\Contracts\AwardeeNameInflector;
use App\Contracts\Contracts\DecreeAwardeeParser;
use App\Models\Award;
use App\Models\Decree;
use App\Services\DecreeAwardeeImporter;

/**
 * @return list<array{full_name: string, rank: string, award: string, is_posthumous: bool}>
 */
function parsedAwardees(): array
{
    return [
        [
            'full_name' => 'СИДОРА Юрія Васильовича',
            'rank' => 'капітана',
            'award' => 'орденом Богдана Хмельницького ІІ ступеня',
            'is_posthumous' => true,
        ],
        [
            'full_name' => 'ВОВЧЕНКА Олександра Євгеновича',
            'rank' => 'молодшого лейтенанта',
            'award' => 'відзнакою Президента України “Хрест бойових заслуг”',
            'is_posthumous' => false,
        ],
    ];
}

it('imports the awardees of the decree with their awards', function () {
    $decree = Decree::factory()->create(['url' => 'https://www.president.gov.ua/documents/8752026-61465']);

    $this->mock(DecreeAwardeeParser::class)
        ->shouldReceive('getAwardees')
        ->once()
        ->with($decree->url)
        ->andReturn(parsedAwardees());

    $this->mock(AwardeeNameInflector::class)
        ->shouldReceive('fromGenitiveMany')
        ->once()
        ->with(['СИДОРА Юрія Васильовича', 'ВОВЧЕНКА Олександра Євгеновича'])
        ->andReturn(['СИДІР Юрій Васильович', 'ВОВЧЕНКО Олександр Євгенович']);

    expect(app(DecreeAwardeeImporter::class)->import($decree))->toBe(2);

    $posthumousAward = Award::query()->where('name', 'орденом Богдана Хмельницького ІІ ступеня')->firstOrFail();
    $award = Award::query()->where('name', 'відзнакою Президента України “Хрест бойових заслуг”')->firstOrFail();

    $this->assertDatabaseHas('awardees', [
        'decree_id' => $decree->getKey(),
        'award_id' => $posthumousAward->getKey(),
        'rank' => 'капітана',
        'is_posthumous' => true,
    ]);
    $this->assertDatabaseHas('awardees', [
        'decree_id' => $decree->getKey(),
        'award_id' => $award->getKey(),
        'rank' => 'молодшого лейтенанта',
        'is_posthumous' => false,
    ]);
    $this->assertDatabaseCount('awardees', 2);
    $this->assertDatabaseCount('awards', 2);
});

it('keeps the decree awardees when the import runs twice', function () {
    $decree = Decree::factory()->create();

    $this->mock(DecreeAwardeeParser::class)
        ->shouldReceive('getAwardees')
        ->twice()
        ->andReturn(parsedAwardees());

    $this->mock(AwardeeNameInflector::class)
        ->shouldReceive('fromGenitiveMany')
        ->twice()
        ->andReturn(['СИДІР Юрій Васильович', 'ВОВЧЕНКО Олександр Євгенович']);

    $importer = app(DecreeAwardeeImporter::class);

    expect($importer->import($decree))->toBe(2)
        ->and($importer->import($decree))->toBe(2);

    $this->assertDatabaseCount('awardees', 2);
    $this->assertDatabaseCount('awards', 2);
});

it('imports nothing when the decree mentions no awardees', function () {
    $decree = Decree::factory()->create();

    $this->mock(DecreeAwardeeParser::class)
        ->shouldReceive('getAwardees')
        ->once()
        ->andReturn([]);

    $this->mock(AwardeeNameInflector::class)
        ->shouldNotReceive('fromGenitiveMany');

    expect(app(DecreeAwardeeImporter::class)->import($decree))->toBe(0);

    $this->assertDatabaseCount('awardees', 0);
    $this->assertDatabaseCount('awards', 0);
});
