<?php

namespace App\Services\Whatsapp;

use App\Models\AjusteSistema;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class WhatsappProviderSelector
{
    public const OPENWA = 'openwa';

    public const META = 'meta';

    public function activo(): string
    {
        $configurado = null;

        if (Schema::hasTable('ajustes_sistema')) {
            $configurado = AjusteSistema::query()
                ->where('clave', 'whatsapp_provider')
                ->value('valor');
        }

        $proveedor = mb_strtolower(trim((string) ($configurado ?: config('services.whatsapp.provider', self::OPENWA))));

        return in_array($proveedor, $this->disponibles(), true)
            ? $proveedor
            : self::OPENWA;
    }

    public function seleccionar(string $proveedor): void
    {
        $proveedor = mb_strtolower(trim($proveedor));

        if (! in_array($proveedor, $this->disponibles(), true)) {
            throw ValidationException::withMessages([
                'whatsappProveedor' => 'Selecciona OpenWA o WhatsApp oficial de Meta.',
            ]);
        }

        AjusteSistema::query()->updateOrCreate(
            ['clave' => 'whatsapp_provider'],
            ['valor' => $proveedor],
        );
    }

    /** @return array<int, string> */
    public function disponibles(): array
    {
        return config('services.meta_whatsapp.enabled', false)
            ? [self::OPENWA, self::META]
            : [self::OPENWA];
    }
}
