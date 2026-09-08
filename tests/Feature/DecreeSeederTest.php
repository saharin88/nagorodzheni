<?php

use Database\Seeders\DecreeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->testDirectory = database_path('seeders/data');
    $this->testFilePath = database_path('seeders/data/decrees.csv');
    $this->backupFilePath = database_path('seeders/data/decrees.csv.bak');

    if (! is_dir($this->testDirectory)) {
        mkdir($this->testDirectory, 0755, true);
    }

    if (file_exists($this->testFilePath)) {
        rename($this->testFilePath, $this->backupFilePath);
    }
});

afterEach(function () {
    if (file_exists($this->testFilePath)) {
        unlink($this->testFilePath);
    }

    if (file_exists($this->backupFilePath)) {
        rename($this->backupFilePath, $this->testFilePath);
    }
});

describe('DecreeSeeder Import', function () {

    describe('successful import operations', function () {

        it('imports decrees from csv successfully', function () {
            $csvContent = "Number,Date,URL\n".
                          "123/2026,08.09.2026,https://example.com/123\n".
                          "456/2026,09.09.2026,https://example.com/456\n";

            file_put_contents($this->testFilePath, $csvContent);

            $this->seed(DecreeSeeder::class);

            $this->assertDatabaseCount('decrees', 2);

            $this->assertDatabaseHas('decrees', [
                'number' => '123/2026',
                'date' => '2026-09-08',
                'url' => 'https://example.com/123',
            ]);

            $this->assertDatabaseHas('decrees', [
                'number' => '456/2026',
                'date' => '2026-09-09',
                'url' => 'https://example.com/456',
            ]);
        });

        it('imports decrees with various date formats successfully', function () {
            $csvContent = "Number,Date,URL\n".
                          "101/2026,2026-09-08,https://example.com/101\n".
                          "102/2026,08-Sep-2026,https://example.com/102\n";

            file_put_contents($this->testFilePath, $csvContent);

            $this->seed(DecreeSeeder::class);

            $this->assertDatabaseCount('decrees', 2);

            $this->assertDatabaseHas('decrees', [
                'number' => '101/2026',
                'date' => '2026-09-08',
            ]);

            $this->assertDatabaseHas('decrees', [
                'number' => '102/2026',
                'date' => '2026-09-08',
            ]);
        });

        it('upserts duplicate decrees by number instead of duplicating them', function () {
            $csvContent = "Number,Date,URL\n".
                          "123/2026,08.09.2026,https://example.com/123\n";

            file_put_contents($this->testFilePath, $csvContent);
            $this->seed(DecreeSeeder::class);

            $csvContentWithUpdate = "Number,Date,URL\n".
                                    "123/2026,08.09.2026,https://example.com/updated-url\n";

            file_put_contents($this->testFilePath, $csvContentWithUpdate);
            $this->seed(DecreeSeeder::class);

            $this->assertDatabaseCount('decrees', 1);

            $this->assertDatabaseHas('decrees', [
                'number' => '123/2026',
                'url' => 'https://example.com/updated-url',
            ]);
        });

    });

    describe('data validation and fallbacks', function () {

        it('falls back to current date on invalid date format', function () {
            $csvContent = "Number,Date,URL\n".
                          "789/2026,invalid-date-format,https://example.com/789\n";

            file_put_contents($this->testFilePath, $csvContent);

            $this->seed(DecreeSeeder::class);

            $this->assertDatabaseHas('decrees', [
                'number' => '789/2026',
                'date' => now()->format('Y-m-d'),
            ]);
        });

        it('skips rows with missing or blank values', function () {
            $csvContent = "Number,Date,URL\n".
                          "123/2026,08.09.2026\n". // Бракує URL (кількість колонок = 2)
                          "456/2026,,https://example.com/456\n". // Порожня дата (blank)
                          ",09.09.2026,https://example.com/789\n". // Порожній номер (blank)
                          ",,\n"; // Повністю порожній рядок

            file_put_contents($this->testFilePath, $csvContent);

            $this->seed(DecreeSeeder::class);

            $this->assertDatabaseEmpty('decrees');
        });

    });

});
