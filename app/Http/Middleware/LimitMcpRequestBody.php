<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LimitMcpRequestBody
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $maxBodyBytes = max(1024, (int) config('mcp.max_body_bytes', 262144));

        $contentLength = $request->server('CONTENT_LENGTH') ?? $request->header('Content-Length');

        if (is_numeric($contentLength) && (int) $contentLength > $maxBodyBytes) {
            return $this->payloadTooLarge();
        }

        if (strlen((string) $request->getContent()) > $maxBodyBytes) {
            return $this->payloadTooLarge();
        }

        return $next($request);
    }

    private function payloadTooLarge(): Response
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => null,
            'error' => [
                'code' => -32000,
                'message' => 'Payload Too Large: Request body exceeds the MCP size limit.',
            ],
        ], 413);
    }
}
