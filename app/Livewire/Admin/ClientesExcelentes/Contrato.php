<?php

namespace App\Livewire\Admin\ClientesExcelentes;

use App\Models\Condonacion;
use App\Models\Contrato as ContratoModel;
use App\Models\Cuota;
use App\Models\Recibo; // ✅ alias
use App\Models\TipoCobro;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Contrato extends Component
{
    public ContratoModel $contrato; // ✅ ahora usa el alias

    public int $ultimasCuotas = 3;

    public bool $bajarSaldoContrato = true;

    public bool $generarReciboCondonacion = false;

    public bool $modalCondonar = false;

    public array $cuotasElegibles = [];

    public array $seleccionadas = [];

    public string $motivo = 'Beneficio por excelente historial de pagos';

    public function mount(ContratoModel $contrato): void // ✅ alias
    {
        $this->contrato = $contrato->load(['cliente', 'lote.fraccionamiento']);
        $this->cargarCuotasElegibles();
    }

    protected function cargarCuotasElegibles(): void
    {
        $this->cuotasElegibles = [];
        $this->seleccionadas = [];

        $cuotas = Cuota::query()
            ->where('contrato_id', $this->contrato->id)
            ->where('estatus', '!=', 'pagada')
            ->where('condonada', false)
            ->orderByDesc('numero')
            ->limit($this->ultimasCuotas)
            ->get();

        foreach ($cuotas as $c) {
            $pendiente = max(
                0,
                (float) $c->monto - (float) ($c->pagado_total ?? 0) - (float) ($c->condonado_total ?? 0)
            );

            if ($pendiente <= 0) {
                continue;
            }

            $this->cuotasElegibles[$c->id] = [
                'label' => 'Cuota #'.$c->numero.' | Vence: '.Carbon::parse($c->fecha_vencimiento)->format('Y-m-d'),
                'monto' => $pendiente,
            ];
        }
    }

    public function abrirModalCondonar(): void
    {
        $this->cargarCuotasElegibles();
        $this->modalCondonar = true;
    }

    public function cerrarModalCondonar(): void
    {
        $this->modalCondonar = false;
        $this->seleccionadas = [];
    }

    protected function obtenerTipoCobroCondonacionId(): ?int
    {
        $id = TipoCobro::query()
            ->whereRaw('UPPER(nombre) LIKE ?', ['%CONDON%'])
            ->value('id');

        return $id ? (int) $id : null;
    }

    protected function generarFolioRecibo(): string
    {
        $anio = now()->year;
        $ultimo = Recibo::where('anio', $anio)->orderByDesc('id')->first();

        $n = 1;
        if ($ultimo && is_string($ultimo->folio) && str_contains($ultimo->folio, '-')) {
            $lastPart = (int) last(explode('-', $ultimo->folio));
            $n = $lastPart + 1;
        }

        return 'R-'.$anio.'-'.str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }

    protected function restarSaldoContrato(float $monto): void
    {
        $c = ContratoModel::find($this->contrato->id);
        if (! $c) {
            return;
        }

        $campo = isset($c->saldo_actual) ? 'saldo_actual' : (isset($c->saldo) ? 'saldo' : null);
        if (! $campo) {
            return;
        }

        $c->{$campo} = max(0, (float) $c->{$campo} - (float) $monto);
        $c->save();

        $this->contrato->refresh();
    }

    public function confirmarCondonacion(): void
    {
        if (empty($this->seleccionadas)) {
            $this->dispatch('toast', type: 'warning', message: 'Selecciona al menos una cuota para condonar.');

            return;
        }

        DB::transaction(function () {
            foreach ($this->seleccionadas as $cuotaId) {
                $cuotaId = (int) $cuotaId;

                $cuota = Cuota::lockForUpdate()
                    ->where('contrato_id', $this->contrato->id)
                    ->findOrFail($cuotaId);

                if ($cuota->condonada) {
                    continue;
                }

                $pendiente = max(
                    0,
                    (float) $cuota->monto - (float) ($cuota->pagado_total ?? 0) - (float) ($cuota->condonado_total ?? 0)
                );

                if ($pendiente <= 0) {
                    continue;
                }

                $reciboId = null;

                if ($this->generarReciboCondonacion) {
                    $tipoCondonacionId = $this->obtenerTipoCobroCondonacionId();

                    $recibo = Recibo::create([
                        'folio' => $this->generarFolioRecibo(),
                        'fecha' => now()->toDateString(),
                        'anio' => (int) now()->format('Y'),
                        'semana_pago' => (int) now()->format('W'),
                        'semana_del_anio' => (int) now()->format('W'),
                        'mes_del_anio' => (int) now()->format('m'),

                        'cliente_id' => $this->contrato->cliente_id,
                        'contrato_id' => $this->contrato->id,
                        'lote_id' => $this->contrato->lote_id,

                        'tipos_cobro_id' => $tipoCondonacionId,
                        'forma_pago_id' => null,
                        'cuentas_bancarias_id' => null,
                        'periodo_id' => null,

                        'monto' => 0,
                        'observaciones' => 'CONDONACIÓN cuota #'.$cuota->numero
                            .' | Monto condonado: $'.number_format($pendiente, 2)
                            .' | '.$this->motivo,
                        'capturado_por_user_id' => auth()->id(),
                    ]);

                    $reciboId = $recibo->id;
                }

                Condonacion::create([
                    'cliente_id' => $this->contrato->cliente_id,
                    'contrato_id' => $this->contrato->id,
                    'cuota_id' => $cuota->id,
                    'monto' => $pendiente,
                    'motivo' => $this->motivo,
                    'recibo_id' => $reciboId,
                    'created_by_user_id' => auth()->id(),
                ]);

                activity('condonaciones')
                    ->performedOn($condonacion)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'cliente_id' => $condonacion->cliente_id,
                        'contrato_id' => $condonacion->contrato_id,
                        'cuota_id' => $condonacion->cuota_id,
                        'recibo_id' => $condonacion->recibo_id,
                        'monto' => $condonacion->monto,
                        'motivo' => $condonacion->motivo,
                    ])
                    ->log('Condonación aplicada');

                $cuota->condonado_total = (float) ($cuota->condonado_total ?? 0) + $pendiente;
                $cuota->condonada = true;
                $cuota->condonada_at = now();

                // coherencia UI
                $cuota->estatus = 'pagada';
                $cuota->save();

                if ($this->bajarSaldoContrato) {
                    $this->restarSaldoContrato($pendiente);
                }
            }
        });

        $this->cerrarModalCondonar();
        $this->cargarCuotasElegibles();

        $this->dispatch('toast', type: 'success', message: 'Condonación aplicada correctamente.');
    }

    public function render()
    {
        $cuotas = Cuota::query()
            ->where('contrato_id', $this->contrato->id)
            ->orderBy('numero')
            ->get();

        return view('livewire.admin.clientes-excelentes.contrato', [
            'cuotas' => $cuotas,
        ])->layout('layouts.app');
    }
}
