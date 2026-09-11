<?php

namespace App\Services\Impresion;

use App\Enums\PrintingMode;
use App\Models\AjusteSistema;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Modo de impresión de los tickets, guardado en ajustes_sistema igual que el
 * proveedor de WhatsApp.
 */
class ModoImpresionSelector
{
    public const CLAVE = 'modo_impresion';

    public function activo(): PrintingMode
    {
        $configurado = null;

        if (Schema::hasTable('ajustes_sistema')) {
            $configurado = AjusteSistema::query()
                ->where('clave', self::CLAVE)
                ->value('valor');
        }

        $modo = mb_strtolower(trim((string) ($configurado ?: config('impresion.modo', PrintingMode::Browser->value))));

        return PrintingMode::tryFrom($modo) ?? PrintingMode::Browser;
    }

    public function esAgenteLocal(): bool
    {
        return $this->activo() === PrintingMode::LocalAgent;
    }

    public function seleccionar(string $modo): void
    {
        $elegido = PrintingMode::tryFrom(mb_strtolower(trim($modo)));

        if (! $elegido) {
            throw ValidationException::withMessages([
                'modoImpresion' => 'Selecciona navegador o agente local.',
            ]);
        }

        AjusteSistema::query()->updateOrCreate(
            ['clave' => self::CLAVE],
            ['valor' => $elegido->value],
        );
    }
}
