<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrintAgentHeartbeatRequest;
use App\Models\PrintAgent;
use Illuminate\Http\JsonResponse;

/**
 * Latido del agente: reporta su versión y las impresoras que ve en esa
 * computadora. Sin latido reciente el equipo se considera desconectado.
 */
class PrintAgentHeartbeatController extends Controller
{
    public function __invoke(PrintAgentHeartbeatRequest $request): JsonResponse
    {
        /** @var PrintAgent $agent */
        $agent = $request->attributes->get('print_agent');
        $data = $request->validated();

        $agent->forceFill([
            'version' => $data['version'],
            'ticket_printer' => $data['ticket_printer'] ?? null,
            'quote_printer' => $data['quote_printer'] ?? null,
            'available_printers' => $data['available_printers'],
            'last_seen_at' => now(),
        ])->save();

        return response()->json([
            'data' => [
                'agent_id' => $agent->id,
                'agent_name' => $agent->name,
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }
}
