<?php

/*
|--------------------------------------------------------------------------
| MCP
|--------------------------------------------------------------------------
|
| Only the values this application overrides are listed here. Every other
| option keeps the default from the package configuration, because Laravel
| merges this file over the package defaults.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | The MCP server is public, so non-browser clients are served without any
    | restriction: a request without an Origin header is always accepted.
    | Browser based clients send an Origin header, and the package rejects the
    | request unless that origin is the application URL, a localhost address,
    | or one of the origins listed below.
    |
    | Comma separated, for example:
    | MCP_ALLOWED_ORIGINS="https://inspector.example.com,https://playground.example.com"
    |
    */

    'allowed_origins' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('MCP_ALLOWED_ORIGINS', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Rate Limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of MCP requests allowed per minute from a single IP
    | address. The limit protects the public endpoint, which reads the awardee
    | registry without authentication.
    |
    */

    'rate_limit' => (int) env('MCP_RATE_LIMIT', 120),

    /*
    |--------------------------------------------------------------------------
    | Global Rate Limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of MCP requests allowed per minute across all clients.
    | This is a safety net for burst traffic spread across many source IPs.
    |
    */

    'global_rate_limit' => (int) env('MCP_GLOBAL_RATE_LIMIT', 1000),

    /*
    |--------------------------------------------------------------------------
    | Tool Call Rate Limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of tools/call requests allowed per minute from one IP.
    | Tool calls are more expensive than discovery methods such as tools/list.
    |
    */

    'tool_call_rate_limit' => (int) env('MCP_TOOL_CALL_RATE_LIMIT', 60),

    /*
    |--------------------------------------------------------------------------
    | Maximum Request Body Size
    |--------------------------------------------------------------------------
    |
    | Maximum allowed size (in bytes) of one MCP HTTP request body. The limit
    | protects the public endpoint from oversized JSON-RPC payloads.
    |
    */

    'max_body_bytes' => (int) env('MCP_MAX_BODY_BYTES', 262144),

    /*
    |--------------------------------------------------------------------------
    | Cache Hints
    |--------------------------------------------------------------------------
    |
    | Advisory cache hints for stable MCP metadata responses. Clients decide
    | whether to cache, while these TTLs suggest safe reuse windows.
    |
    */

    'cache_hints' => [
        'tools_list_ttl_ms' => (int) env('MCP_TOOLS_LIST_CACHE_TTL_MS', 30000),
        'server_discover_ttl_ms' => (int) env('MCP_SERVER_DISCOVER_CACHE_TTL_MS', 60000),
    ],

];
