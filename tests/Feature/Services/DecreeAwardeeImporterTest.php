<?php

use App\Contracts\Contracts\DecreeAwardeeParser;
use App\Exceptions\DecreeParseException;
use App\Models\Award;
use App\Models\Awardee;
use App\Models\Decree;
use App\Services\DecreeAwardeeImporter;
use Illuminate\Support\Facades\Event;

/**
 * The parser hands the names over in the genitive case, exactly as the decree writes them.
 *
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

    expect(app(DecreeAwardeeImporter::class)->import($decree))->toBe(2);

    $posthumousAward = Award::query()->where('name', 'орденом Богдана Хмельницького ІІ ступеня')->firstOrFail();
    $award = Award::query()->where('name', 'відзнакою Президента України “Хрест бойових заслуг”')->firstOrFail();

    $this->assertDatabaseHas('awardees', [
        'decree_id' => $decree->getKey(),
        'award_id' => $posthumousAward->getKey(),
        'full_name' => 'СИДОРА Юрія Васильовича',
        'rank' => 'капітана',
        'is_posthumous' => true,
    ]);
    $this->assertDatabaseHas('awardees', [
        'decree_id' => $decree->getKey(),
        'award_id' => $award->getKey(),
        'full_name' => 'ВОВЧЕНКА Олександра Євгеновича',
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
        ->with($decree->url)
        ->andReturn([]);

    expect(app(DecreeAwardeeImporter::class)->import($decree))->toBe(0);

    $this->assertDatabaseCount('awardees', 0);
    $this->assertDatabaseCount('awards', 0);
});

it('reuses the award that another decree already introduced', function () {
    $firstDecree = Decree::factory()->create();
    $secondDecree = Decree::factory()->create();

    $this->mock(DecreeAwardeeParser::class)
        ->shouldReceive('getAwardees')
        ->twice()
        ->andReturn(parsedAwardees());

    $importer = app(DecreeAwardeeImporter::class);

    $importer->import($firstDecree);
    $importer->import($secondDecree);

    $sharedAward = Award::query()->where('name', 'орденом Богдана Хмельницького ІІ ступеня')->firstOrFail();

    expect(Award::query()->count())->toBe(2)
        ->and($sharedAward->awardees()->count())->toBe(2);

    $this->assertDatabaseCount('awardees', 4);
});

it('stores nothing when one of the awardees cannot be imported', function () {
    $decree = Decree::factory()->create();

    $this->mock(DecreeAwardeeParser::class)
        ->shouldReceive('getAwardees')
        ->once()
        ->andReturn(parsedAwardees());

    $storedAwardees = 0;

    Event::listen('eloquent.creating: '.Awardee::class, function () use (&$storedAwardees): void {
        if (++$storedAwardees === 2) {
            throw new RuntimeException('The awardee could not be stored.');
        }
    });

    expect(fn () => app(DecreeAwardeeImporter::class)->import($decree))
        ->toThrow(RuntimeException::class, 'The awardee could not be stored.');

    $this->assertDatabaseCount('awardees', 0);
    $this->assertDatabaseCount('awards', 0);
});

it('lets a parser failure bubble up so the caller can report it', function () {
    $decree = Decree::factory()->create();

    $this->mock(DecreeAwardeeParser::class)
        ->shouldReceive('getAwardees')
        ->once()
        ->andThrow(new DecreeParseException('Unable to parse decree awardees'));

    expect(fn () => app(DecreeAwardeeImporter::class)->import($decree))
        ->toThrow(DecreeParseException::class, 'Unable to parse decree awardees');

    $this->assertDatabaseCount('awardees', 0);
});
