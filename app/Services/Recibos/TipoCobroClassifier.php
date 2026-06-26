<?php

namespace App\Services\Recibos;

use App\Models\TipoCobro;

class TipoCobroClassifier
{
    protected array $tipos = [];

    public function esMensualidad(?int $tipoCobroId): bool
    {
        return str_contains($this->nombre($tipoCobroId), 'MENSUAL');
    }

    public function esAnualidad(?int $tipoCobroId): bool
    {
        return str_contains($this->nombre($tipoCobroId), 'ANUAL');
    }

    public function esRecargo(?int $tipoCobroId): bool
    {
        return str_contains($this->nombre($tipoCobroId), 'RECARGO');
    }

    public function esServicio(?int $tipoCobroId): bool
    {
        return $this->tipo($tipoCobroId)?->categoria === 'servicio';
    }

    public function requiereAsociarCuota(?int $tipoCobroId): bool
    {
        return $this->esMensualidad($tipoCobroId)
            || $this->esAnualidad($tipoCobroId)
            || $this->esServicio($tipoCobroId)
            || $this->esRecargo($tipoCobroId);
    }

    public function afectaSaldoContrato(?int $tipoCobroId): bool
    {
        $nombre = $this->nombre($tipoCobroId);

        return str_contains($nombre, 'ANUAL') || str_contains($nombre, 'ENGANCHE');
    }

    protected function tipo(?int $tipoCobroId): ?TipoCobro
    {
        if (! $tipoCobroId) {
            return null;
        }

        if (! array_key_exists($tipoCobroId, $this->tipos)) {
            $this->tipos[$tipoCobroId] = TipoCobro::find($tipoCobroId);
        }

        return $this->tipos[$tipoCobroId];
    }

    protected function nombre(?int $tipoCobroId): string
    {
        return mb_strtoupper(trim((string) ($this->tipo($tipoCobroId)?->nombre ?? '')));
    }
}
