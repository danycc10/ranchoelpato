<?php

namespace App\Services\Impresion;

use App\Models\PrintAgent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Empareja la computadora de un usuario con el sistema. La clave se muestra una
 * sola vez: en la base solo queda su hash.
 */
class PrintAgentPairingService
{
    /** @return array{agent: PrintAgent, token: string} */
    public function pair(User $user, User $createdBy, ?string $name = null): array
    {
        $token = bin2hex(random_bytes(32));

        $agent = DB::transaction(function () use ($user, $createdBy, $name, $token): PrintAgent {
            return PrintAgent::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'created_by_id' => $createdBy->id,
                    'name' => trim($name ?: "Equipo de {$user->name}"),
                    'token_hash' => hash('sha256', $token),
                    'token_last_four' => substr($token, -4),
                    'is_active' => true,
                    'version' => null,
                    'ticket_printer' => null,
                    'quote_printer' => null,
                    'available_printers' => null,
                    'last_seen_at' => null,
                ],
            );
        });

        return ['agent' => $agent, 'token' => $token];
    }

    public function revoke(PrintAgent $agent): void
    {
        $agent->forceFill(['is_active' => false])->save();
    }
}
