<?php

namespace Database\Seeders;

use App\Models\Decree;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;

class DecreeSeeder extends Seeder
{
    public function run(): void
    {
        $filePath = database_path('seeders/data/decrees.csv');

        if (! file_exists($filePath)) {
            $this->command->error(__('File not found at path: :path', ['path' => $filePath]));

            return;
        }

        $this->command->info(__('Import of decrees started...'));
        $totalImported = 0;

        DB::beginTransaction();

        try {
            $decrees = LazyCollection::make(function () use ($filePath) {
                $handle = fopen($filePath, 'r');

                if ($handle === false) {
                    throw new \RuntimeException(__('Failed to open file: :path', ['path' => $filePath]));
                }

                fgetcsv($handle, 1000, ',');

                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    yield $data;
                }

                fclose($handle);
            })
                ->filter(function ($data) {
                    if (count($data) !== 3) {
                        return false;
                    }

                    for ($i = 0; $i < 3; $i++) {
                        if (blank($data[$i])) {
                            return false;
                        }
                    }

                    return true;
                })
                ->map(function ($data) {
                    $number = $data[0] ?? '';
                    $dateValue = $data[1] ?? '';
                    $url = $data[2] ?? '';

                    try {
                        $formattedDate = Carbon::parse($dateValue)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $formattedDate = now()->format('Y-m-d');
                    }

                    return [
                        'number' => trim($number),
                        'date' => $formattedDate,
                        'url' => trim($url),
                    ];
                });

            $decrees->chunk(100)->each(function ($chunk) use (&$totalImported) {
                $data = $chunk->values()->toArray();

                Decree::upsert($data, ['number'], ['date', 'url']);

                $totalImported += count($data);
            });

            DB::commit();
            $this->command->info(__('Import completed successfully! Decrees added/updated: :total', ['total' => $totalImported]));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error during decree import: '.$e->getMessage(), ['exception' => $e]);
            $this->command->error(__('Critical error during import: :message', ['message' => $e->getMessage()]));
        }
    }
}
