<?php

use App\Http\Middleware\LimitMcpRequestBody;
use App\Mcp\Servers\AwardeeServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP servers
|--------------------------------------------------------------------------
|
| The awardee registry is exposed as a public, read-only web server: any MCP
| client may reach it over Streamable HTTP at POST /mcp/awarded without
| authentication. The "mcp" rate limiter (registered in AppServiceProvider)
| keeps the endpoint usable when it is hit by many clients.
|
*/

Mcp::web('/mcp/awarded', AwardeeServer::class)
    ->middleware([LimitMcpRequestBody::class, 'throttle:mcp']); // послідовність важлива (LimitMcpRequestBody::class має бути першим)

Mcp::local('awarded', AwardeeServer::class);
