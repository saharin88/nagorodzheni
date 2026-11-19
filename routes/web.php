<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/_maintenance/artisan', function (Request $request) {
    if (! (bool) config('maintenance.artisan_route.enabled', false)) {
        abort(404);
    }

    $expectedToken = (string) config('maintenance.artisan_route.token', '');
    $providedToken = (string) $request->query('token', '');

    if (($expectedToken === '') || ! hash_equals($expectedToken, $providedToken)) {
        abort(403);
    }

    $availableCommands = Artisan::all();

    $configuredCommands = config('maintenance.artisan_route.commands', []);

    if (! is_array($configuredCommands)) {
        $configuredCommands = [];
    }

    $commands = array_values(array_filter(
        $configuredCommands,
        fn (mixed $command): bool => is_string($command)
            && ($command !== '')
            && array_key_exists($command, $availableCommands),
    ));

    $results = [];

    foreach ($commands as $command) {
        $exitCode = Artisan::call($command);
        $rawOutput = trim(Artisan::output());

        if ($rawOutput === '') {
            $lines = [];
        } else {
            $splitOutput = preg_split('/\r\n|\r|\n/', $rawOutput);

            $lines = $splitOutput === false
                ? []
                : array_values(array_filter(
                    $splitOutput,
                    fn (string $line): bool => trim($line) !== '',
                ));
        }

        $results[] = [
            'command' => $command,
            'exit_code' => $exitCode,
            'output' => $lines,
        ];
    }

    $hasFailedCommand = collect($results)->contains(
        fn (array $result): bool => $result['exit_code'] !== 0,
    );

    return response()->json([
        'ok' => ! $hasFailedCommand,
        'executed_at' => now()->toIso8601String(),
        'results' => $results,
        'note' => 'Temporary maintenance route. Disable and remove after use.',
    ], $hasFailedCommand ? 500 : 200);
})->middleware('throttle:'.config('maintenance.artisan_route.throttle', '6,1'));
