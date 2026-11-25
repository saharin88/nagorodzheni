<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Collator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();

        $connection = DB::connection();

        if ($connection->getDriverName() === 'sqlite') {
            $collator = new Collator('uk_UA');

            $connection->getPdo()->sqliteCreateCollation('UKRAINIAN_CI', static function ($string1, $string2) use ($collator): int {
                return (int) $collator->compare((string) $string1, (string) $string2);
            });

            $connection->getPdo()->sqliteCreateFunction('ukrainian_search', function (?string $value): ?string {
                if ($value === null) {
                    return null;
                }

                return str_replace(['’', 'ʼ', '`', '‘', '´', "'"], '', mb_strtolower($value, 'UTF-8'));
            });
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('mcp', function (Request $request): array {

            $actorKey = $request->user()?->getAuthIdentifier()
                ? 'user:'.$request->user()->getAuthIdentifier()
                : 'ip:'.($request->ip() ?: 'unknown');

            $perActorLimit = max(1, (int) config('mcp.rate_limit', 120));
            $globalLimit = max(1, (int) config('mcp.global_rate_limit', 1000));
            $toolCallLimit = max(1, (int) config('mcp.tool_call_rate_limit', 60));

            $payload = $this->parseJsonRpcPayload($request);
            $toolCallsCount = $this->countToolCalls($payload);

            $jsonRpcTooManyRequests = fn (Request $req, array $headers): SymfonyResponse => $this->mcpRateLimitResponse($payload, $headers);

            $limits = [
                Limit::perMinute($perActorLimit)
                    ->by("mcp:{$actorKey}")
                    ->response($jsonRpcTooManyRequests),
                Limit::perMinute($globalLimit)
                    ->by('mcp:global')
                    ->response($jsonRpcTooManyRequests),
            ];

            if ($toolCallsCount > 0) {
                $limits[] = Limit::perMinute($toolCallLimit)
                    ->by("mcp:{$actorKey}:tools/call")
                    ->response($jsonRpcTooManyRequests);
            }

            return $limits;
        });
    }

    /**
     * @return array<string, mixed>|list<array<string, mixed>>|null
     */
    protected function parseJsonRpcPayload(Request $request): ?array
    {
        $decoded = json_decode((string) $request->getContent(), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>|null  $payload
     */
    protected function countToolCalls(?array $payload): int
    {
        if ($payload === null) {
            return 0;
        }

        if (isset($payload['method'])) {
            return $payload['method'] === 'tools/call' ? 1 : 0;
        }

        if (array_is_list($payload)) {
            return count(array_filter(
                $payload,
                static fn (mixed $item): bool => is_array($item) && ($item['method'] ?? null) === 'tools/call',
            ));
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>|null  $payload
     * @param  array<string, string>  $headers
     */
    protected function mcpRateLimitResponse(?array $payload, array $headers): SymfonyResponse
    {
        $extractId = static function (mixed $item): int|string|null {
            if (! is_array($item)) {
                return null;
            }

            return (is_int($item['id'] ?? null) || is_string($item['id'] ?? null))
                ? $item['id']
                : null;
        };

        if (is_array($payload) && array_is_list($payload) && $payload !== []) {
            $errors = array_map(static fn (mixed $item): array => [
                'jsonrpc' => '2.0',
                'id' => $extractId($item),
                'error' => [
                    'code' => -32000,
                    'message' => 'Too Many Requests: Rate limit exceeded. Retry later.',
                ],
            ], $payload);

            return response()->json($errors, 429, $headers);
        }

        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $extractId($payload),
            'error' => [
                'code' => -32000,
                'message' => 'Too Many Requests: Rate limit exceeded. Retry later.',
            ],
        ], 429, $headers);
    }
}
