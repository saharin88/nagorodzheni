<?php

use App\Models\Awardee;

/**
 * Build a JSON-RPC payload for the MCP web endpoint.
 *
 * @param  array<string, mixed>  $params
 * @return array<string, mixed>
 */
function mcpPayload(string $method, array $params = [], int|string $id = 1): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => $id,
        'method' => $method,
        'params' => $params,
    ];
}

/**
 * Build a non-legacy payload that includes required MCP protocol metadata.
 *
 * @param  array<string, mixed>  $params
 * @return array<string, mixed>
 */
function mcpModernPayload(string $method, array $params = [], int|string $id = 1): array
{
    return mcpPayload($method, [
        ...$params,
        '_meta' => [
            'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
            'io.modelcontextprotocol/clientCapabilities' => ['roots' => ['listChanged' => true]],
            'io.modelcontextprotocol/clientInfo' => ['name' => 'test-client', 'version' => '1.0.0'],
        ],
    ], $id);
}

it('lists the awardee tools over the public web endpoint', function () {
    $this->postJson('/mcp/awarded', mcpPayload('tools/list'))
        ->assertOk()
        ->assertJsonPath('jsonrpc', '2.0')
        ->assertJsonPath('result.tools.0.name', 'search_awardees')
        ->assertJsonPath('result.tools.1.name', 'list_decree_awardees');
});

it('answers the modern protocol discovery without authentication', function () {
    $payload = mcpModernPayload('server/discover');

    $this->postJson('/mcp/awarded', $payload, [
        'MCP-Protocol-Version' => '2026-07-28',
        'Mcp-Method' => 'server/discover',
    ])
        ->assertOk()
        ->assertJsonPath('result.supportedVersions', ['2026-07-28']);
});

it('returns public cache hints for modern metadata methods', function () {
    config([
        'mcp.cache_hints.tools_list_ttl_ms' => 45000,
        'mcp.cache_hints.server_discover_ttl_ms' => 90000,
    ]);

    $this->postJson('/mcp/awarded', mcpModernPayload('tools/list'), [
        'MCP-Protocol-Version' => '2026-07-28',
        'Mcp-Method' => 'tools/list',
    ])
        ->assertOk()
        ->assertJsonPath('result.ttlMs', 45000)
        ->assertJsonPath('result.cacheScope', 'public');

    $this->postJson('/mcp/awarded', mcpModernPayload('server/discover'), [
        'MCP-Protocol-Version' => '2026-07-28',
        'Mcp-Method' => 'server/discover',
    ])
        ->assertOk()
        ->assertJsonPath('result.ttlMs', 90000)
        ->assertJsonPath('result.cacheScope', 'public');
});

it('does not attach cache hints to tools/call responses', function () {
    Awardee::factory()->create([
        'full_name' => 'Антонюка Івана Олександровича',
        'full_name_nominative' => 'Антонюк Іван Олександрович',
    ]);

    $this->postJson('/mcp/awarded', mcpModernPayload('tools/call', [
        'name' => 'search_awardees',
        'arguments' => ['query' => 'Антонюк'],
    ]), [
        'MCP-Protocol-Version' => '2026-07-28',
        'Mcp-Method' => 'tools/call',
        'Mcp-Name' => 'search_awardees',
    ])
        ->assertOk()
        ->assertJsonMissingPath('result.ttlMs')
        ->assertJsonMissingPath('result.cacheScope');
});

it('searches the awardees over the public web endpoint', function () {
    Awardee::factory()->create([
        'full_name' => 'Антонюка Івана Олександровича',
        'full_name_nominative' => 'Антонюк Іван Олександрович',
        'rank' => 'молодшого лейтенанта',
    ]);

    $this->postJson('/mcp/awarded', mcpPayload('tools/call', [
        'name' => 'search_awardees',
        'arguments' => ['query' => 'Антонюк Іван', 'limit' => 5],
    ]))
        ->assertOk()
        ->assertJsonPath('result.isError', false)
        ->assertJsonPath('result.structuredContent.total', 1)
        ->assertJsonPath('result.structuredContent.awardees.0.full_name_nominative', 'Антонюк Іван Олександрович');
});

it('rejects the non-POST methods used for server pushed messages', function (string $method) {
    $this->call($method, '/mcp/awarded')
        ->assertStatus(405)
        ->assertHeader('Allow', 'POST');
})->with(['GET', 'DELETE']);

it('accepts browser clients from the application origin only', function () {
    $server = config('app.url');

    $this->postJson('/mcp/awarded', mcpPayload('tools/list'), ['Origin' => $server])
        ->assertOk();

    $this->postJson('/mcp/awarded', mcpPayload('tools/list'), ['Origin' => 'https://evil.example'])
        ->assertForbidden()
        ->assertJsonPath('error.message', 'Forbidden: The request origin is not allowed.');
});

it('allows the configured origins for browser clients', function () {
    config(['mcp.allowed_origins' => ['https://inspector.example']]);

    $this->postJson('/mcp/awarded', mcpPayload('tools/list'), ['Origin' => 'https://inspector.example'])
        ->assertOk();
});

it('accepts MCP requests below the configured body size limit', function () {
    config(['mcp.max_body_bytes' => 2048]);

    $this->postJson('/mcp/awarded', mcpPayload('tools/list'))
        ->assertOk();
});

it('rejects MCP requests that exceed the configured body size limit', function () {
    config(['mcp.max_body_bytes' => 128]);

    $this->postJson('/mcp/awarded', mcpPayload('tools/call', [
        'name' => 'search_awardees',
        'arguments' => ['query' => str_repeat('А', 200)],
    ]))
        ->assertStatus(413)
        ->assertJsonPath('jsonrpc', '2.0')
        ->assertJsonPath('id', null)
        ->assertJsonPath('error.code', -32000)
        ->assertJsonPath('error.message', 'Payload Too Large: Request body exceeds the MCP size limit.');
});

it('rate limits the requests of a single IP address', function () {
    config(['mcp.rate_limit' => 2]);

    $payload = mcpPayload('tools/list');

    $this->postJson('/mcp/awarded', $payload)->assertOk();
    $this->postJson('/mcp/awarded', $payload)->assertOk();
    $this->postJson('/mcp/awarded', $payload)
        ->assertTooManyRequests()
        ->assertJsonPath('jsonrpc', '2.0')
        ->assertJsonPath('id', 1)
        ->assertJsonPath('error.code', -32000);
});

it('rate limits globally even when per IP budget is still available', function () {
    config([
        'mcp.rate_limit' => 100,
        'mcp.global_rate_limit' => 2,
    ]);

    $payload = mcpPayload('tools/list');

    $this->postJson('/mcp/awarded', $payload)->assertOk();
    $this->postJson('/mcp/awarded', $payload)->assertOk();
    $this->postJson('/mcp/awarded', $payload)
        ->assertTooManyRequests()
        ->assertJsonPath('error.code', -32000);
});

it('applies a stricter limit to tools/call than tools/list', function () {
    config([
        'mcp.rate_limit' => 100,
        'mcp.global_rate_limit' => 100,
        'mcp.tool_call_rate_limit' => 2,
    ]);

    $this->postJson('/mcp/awarded', mcpPayload('tools/list'))->assertOk();
    $this->postJson('/mcp/awarded', mcpPayload('tools/list'))->assertOk();
    $this->postJson('/mcp/awarded', mcpPayload('tools/list'))->assertOk();

    $toolCallPayload = mcpPayload('tools/call', [
        'name' => 'search_awardees',
        'arguments' => ['is_posthumous' => true],
    ]);

    $this->postJson('/mcp/awarded', $toolCallPayload)->assertOk();
    $this->postJson('/mcp/awarded', $toolCallPayload)->assertOk();
    $this->postJson('/mcp/awarded', $toolCallPayload)
        ->assertTooManyRequests()
        ->assertJsonPath('jsonrpc', '2.0')
        ->assertJsonPath('id', 1)
        ->assertJsonPath('error.code', -32000);
});
