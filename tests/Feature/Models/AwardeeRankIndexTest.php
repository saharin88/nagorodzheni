<?php

use Illuminate\Support\Facades\Schema;

it('indexes the awardees rank column', function () {
    $indexes = collect(Schema::getIndexes('awardees'));

    expect($indexes->contains(
        fn (array $index): bool => $index['name'] === 'awardees_rank_index'
            && $index['columns'] === ['rank'],
    ))->toBeTrue();
});
