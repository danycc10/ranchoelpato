<?php

namespace App\Http\Middleware;

use App\Models\PrintAgent;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePrintAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || strlen($token) !== 64) {
            return $this->unauthorized();
        }

        $agent = PrintAgent::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('is_active', true)
            ->first();

        if (! $agent || ! hash_equals($agent->token_hash, hash('sha256', $token))) {
            return $this->unauthorized();
        }

        $request->attributes->set('print_agent', $agent);

        return $next($request);
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'message' => 'Las credenciales del agente de impresión no son válidas.',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
