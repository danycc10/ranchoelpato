<?php

namespace App\Livewire\Admin\Cuotas;

use App\Models\Contrato;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\Propietario;
use App\Models\Recibo;
use App\Models\TipoCobro;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    public string $q = '';

    public ?int $propietario_id = null;

    public string $estatus = 'todas';

    public ?string $mes = null;   // YYYY-MM

    public ?string $desde = null; // YYYY-MM-DD

    public ?string $hasta = null; // YYYY-MM-DD

    public string $tipo_contrato = 'todos'; // todos | terreno | servicio

    public ?int $tipo_servicio_id = null;

    public bool $modalImprimir = false;

    public ?string $urlImprimirPrincipal = null;

    public ?string $urlImprimirRecargo = null;

    protected $queryString = [
        'q' => ['except' => ''],
        'propietario_id' => ['except' => null],
        'estatus' => ['except' => 'todas'],
        'mes' => ['except' => null],
        'desde' => ['except' => null],
        'hasta' => ['except' => null],
        'tipo_contrato' => ['except' => 'todos'],
        'tipo_servicio_id' => ['except' => null],
    ];

    public function mount(): void
    {
        if (! $this->mes) {
            $this->mes = now()->format('Y-m');
        }

        $this->aplicarRangoPorMes(true);
    }

    public function updating($name, $value): void
    {
        if (in_array($name, [
            'q', 'propietario_id', 'estatus', 'mes', 'desde', 'hasta', 'tipo_contrato', 'tipo_servicio_id',
        ], true)) {
            $this->resetPage();
        }
    }

    public function updatedMes(): void
    {
        $this->aplicarRangoPorMes(true);

        if ($this->tipo_contrato !== 'servicio') {
            $this->tipo_servicio_id = null;
        }

        $this->resetPage();
    }

    public function updatedTipoContrato(): void
    {
        if ($this->tipo_contrato !== 'servicio') {
            $this->tipo_servicio_id = null;
        }

        $this->resetPage();
    }

    private function aplicarRangoPorMes(bool $forzar = false): void
    {
        $mes = trim((string) $this->mes);

        if ($mes === '') {
            return;
        }

        try {
            $inicio = Carbon::createFromFormat('!Y-m', $mes)->startOfMonth();
            $fin = $inicio->copy()->endOfMonth();

            if ($forzar || ! $this->desde || ! $this->hasta) {
                $this->desde = $inicio->format('Y-m-d');
                $this->hasta = $fin->format('Y-m-d');
            }
        } catch (\Throwable $e) {
            // no romper
        }
    }

    // =========================================================
    // ✅ TERRENO
    // =========================================================
    public function crearReciboDesdeCuota(int $cuotaId): void
    {
        $cuota = Cuota::query()->findOrFail($cuotaId);

        $esAnualidad = $this->cuotaEsAnualidad($cuota);
        $tc = $esAnualidad ? 'anualidad' : 'mensualidad';

        $this->redirectRoute('admin.recibos.crear', [
            'cuota' => $cuota->uuid,
            'tc' => $tc,
        ]);
    }

    protected function cuotaEsAnualidad(Cuota $cuota): bool
    {
        if ((bool) ($cuota->es_anualidad ?? false)) {
            return true;
        }

        $concepto = $this->normTxt($cuota->concepto ?? '');
        if ($concepto !== '' && str_contains($concepto, 'ANUAL')) {
            return true;
        }

        if (isset($cuota->tipo) && is_string($cuota->tipo)) {
            $tipo = $this->normTxt($cuota->tipo);
            if ($tipo !== '' && str_contains($tipo, 'ANUAL')) {
                return true;
            }
        }

        if (isset($cuota->tipos_cobro_id) && $cuota->tipos_cobro_id) {
            $tc = TipoCobro::find((int) $cuota->tipos_cobro_id);
            $n = $this->normTxt($tc?->nombre ?? '');
            if ($n !== '' && str_contains($n, 'ANUAL')) {
                return true;
            }
        }

        return false;
    }

    protected function normTxt($value): string
    {
        $s = trim((string) $value);
        if ($s === '') {
            return '';
        }

        $s = mb_strtoupper($s);

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if (is_string($ascii) && $ascii !== '') {
            $s = mb_strtoupper($ascii);
        }

        $s = preg_replace('/[^A-Z0-9\s]/', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);

        return trim($s);
    }

    // =========================================================
    // ✅ SERVICIO
    // =========================================================
    public function crearReciboServicioDesdeCuota(int $cuotaId): void
    {
        $cuota = Cuota::with('contrato')->findOrFail($cuotaId);
        $tc = $this->resolverTcServicioDesdeContrato($cuota->contrato);

        $this->redirectRoute('admin.recibos.crear', [
            'cuota' => $cuota->uuid,
            'tc' => $tc,
        ]);
    }

    protected function resolverTcServicioDesdeContrato(?Contrato $contrato): string
    {
        if (! $contrato) {
            return 'agua';
        }

        // 1) Campo directo más confiable en tu sistema
        if (isset($contrato->servicio_tipo) && is_string($contrato->servicio_tipo) && trim($contrato->servicio_tipo) !== '') {
            $slug = $this->normalizarTcSlug($contrato->servicio_tipo);
            if ($slug) {
                return $slug;
            }
        }

        // 2) Compatibilidad si en algún registro viejo usaste otro nombre
        if (isset($contrato->tipo_servicio) && is_string($contrato->tipo_servicio) && trim($contrato->tipo_servicio) !== '') {
            $slug = $this->normalizarTcSlug($contrato->tipo_servicio);
            if ($slug) {
                return $slug;
            }
        }

        // 3) Si guardas un tipo_cobro relacionado por id específico
        if (isset($contrato->tipo_servicio_id) && $contrato->tipo_servicio_id) {
            $tc = TipoCobro::find((int) $contrato->tipo_servicio_id);
            $slug = $this->mapTipoCobroNombreATcSlug($tc?->nombre);
            if ($slug) {
                return $slug;
            }
        }

        // 4) Compatibilidad con tipos_cobro_id
        if (isset($contrato->tipos_cobro_id) && $contrato->tipos_cobro_id) {
            $tc = TipoCobro::find((int) $contrato->tipos_cobro_id);
            $slug = $this->mapTipoCobroNombreATcSlug($tc?->nombre);
            if ($slug) {
                return $slug;
            }
        }

        return 'agua';
    }

    protected function normalizarTcSlug(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $v = mb_strtolower(trim($value));

        return match ($v) {
            'agua' => 'agua',
            'electricidad', 'electrico', 'electrica' => 'electricidad',
            'fosa' => 'fosa',
            'limpieza' => 'limpieza',
            default => null,
        };
    }

    protected function mapTipoCobroNombreATcSlug(?string $nombre): ?string
    {
        if (! $nombre) {
            return null;
        }

        $n = mb_strtoupper(trim($nombre));

        if (str_contains($n, 'AGUA')) {
            return 'agua';
        }

        if (
            str_contains($n, 'ELECTRIC') ||
            str_contains($n, 'LUZ') ||
            str_contains($n, 'ENERG')
        ) {
            return 'electricidad';
        }

        if (str_contains($n, 'FOSA')) {
            return 'fosa';
        }

        if (str_contains($n, 'LIMPIEZA')) {
            return 'limpieza';
        }

        return null;
    }

    // =========================================================
    // ✅ IMPRIMIR
    // =========================================================
    public function imprimirReciboDeCuota(int $cuotaId): void
    {
        $principalUuid = $this->buscarReciboPrincipalUuid($cuotaId);

        if (! $principalUuid) {
            $this->dispatch('toast', type: 'warning', message: 'Esa cuota aún no tiene recibo.');

            return;
        }

        $recargoUuid = $this->buscarReciboRecargoUuid($cuotaId);

        $urlPrincipal = route('admin.recibos.imprimir', ['recibo' => $principalUuid]);

        if (! $recargoUuid) {
            $this->dispatch('open-print-tab', url: $urlPrincipal);

            return;
        }

        $this->urlImprimirPrincipal = $urlPrincipal;
        $this->urlImprimirRecargo = route('admin.recibos.imprimir', ['recibo' => $recargoUuid]);
        $this->modalImprimir = true;
    }

    public function cerrarModalImprimir(): void
    {
        $this->modalImprimir = false;
        $this->urlImprimirPrincipal = null;
        $this->urlImprimirRecargo = null;
    }

    public function confirmarImpresion(string $tipo): void
    {
        $principal = $this->urlImprimirPrincipal;
        $recargo = $this->urlImprimirRecargo;

        if (! $principal) {
            $this->cerrarModalImprimir();
            $this->dispatch('toast', type: 'warning', message: 'No se encontró el recibo principal.');

            return;
        }

        if ($tipo === 'principal') {
            $this->dispatch('open-print-tab', url: $principal);
            $this->cerrarModalImprimir();

            return;
        }

        if ($tipo === 'recargo') {
            if (! $recargo) {
                $this->dispatch('toast', type: 'warning', message: 'No se encontró el recibo de recargo.');
                $this->cerrarModalImprimir();

                return;
            }

            $this->dispatch('open-print-tab', url: $recargo);
            $this->cerrarModalImprimir();

            return;
        }

        if ($tipo === 'ambos') {
            $this->dispatch('open-print-tab', url: $principal);

            if ($recargo) {
                $this->dispatch('open-print-tab', url: $recargo);
            }

            $this->cerrarModalImprimir();

            return;
        }

        $this->cerrarModalImprimir();
    }

    protected function buscarReciboPrincipalUuid(int $cuotaId): ?string
    {
        $recargoTipoCobroId = $this->obtenerTipoCobroRecargoId();

        // 1) Flujo normal por pagos
        $pago = Pago::query()
            ->where('cuota_id', $cuotaId)
            ->where('estatus', 'confirmado')
            ->whereNotNull('recibo_id')
            ->with(['recibo:id,uuid,tipos_cobro_id,observaciones,es_historico'])
            ->whereHas('recibo', function ($r) use ($recargoTipoCobroId) {
                if ($recargoTipoCobroId) {
                    $r->where('tipos_cobro_id', '!=', $recargoTipoCobroId);
                }

                $r->where(function ($x) {
                    $x->whereNull('observaciones')
                        ->orWhereRaw('UPPER(observaciones) NOT LIKE ?', ['%RECARGO%']);
                });
            })
            ->latest('id')
            ->first();

        if ($pago?->recibo?->uuid) {
            return $pago->recibo->uuid;
        }

        // 2) Flujo histórico directo por recibos
        $recibo = Recibo::query()
            ->where('cuota_id', $cuotaId)
            ->when($recargoTipoCobroId, fn ($q) => $q->where('tipos_cobro_id', '!=', $recargoTipoCobroId))
            ->where(function ($x) {
                $x->whereNull('observaciones')
                    ->orWhereRaw('UPPER(observaciones) NOT LIKE ?', ['%RECARGO%']);
            })
            ->latest('id')
            ->first(['uuid']);

        return $recibo?->uuid ?: null;
    }

    protected function buscarReciboRecargoUuid(int $cuotaId): ?string
    {
        $recargoTipoCobroId = $this->obtenerTipoCobroRecargoId();

        // 1) Flujo normal por pagos
        $pago = Pago::query()
            ->where('cuota_id', $cuotaId)
            ->where('estatus', 'confirmado')
            ->whereNotNull('recibo_id')
            ->with(['recibo:id,uuid,tipos_cobro_id,observaciones'])
            ->whereHas('recibo', function ($r) use ($recargoTipoCobroId) {
                $r->where(function ($x) use ($recargoTipoCobroId) {
                    if ($recargoTipoCobroId) {
                        $x->where('tipos_cobro_id', $recargoTipoCobroId);
                    }

                    $x->orWhereRaw('UPPER(observaciones) LIKE ?', ['%RECARGO%']);
                });
            })
            ->latest('id')
            ->first();

        if ($pago?->recibo?->uuid) {
            return $pago->recibo->uuid;
        }

        // 2) Flujo directo por recibos
        $recibo = Recibo::query()
            ->where('cuota_id', $cuotaId)
            ->where(function ($x) use ($recargoTipoCobroId) {
                if ($recargoTipoCobroId) {
                    $x->where('tipos_cobro_id', $recargoTipoCobroId);
                }

                $x->orWhereRaw('UPPER(observaciones) LIKE ?', ['%RECARGO%']);
            })
            ->latest('id')
            ->first(['uuid']);

        return $recibo?->uuid ?: null;
    }

    protected function obtenerTipoCobroRecargoId(): ?int
    {
        $id = TipoCobro::query()
            ->whereRaw('UPPER(nombre) LIKE ?', ['%RECARGO%'])
            ->value('id');

        return $id ? (int) $id : null;
    }

    public function limpiarFiltros(): void
    {
        $this->reset([
            'q',
            'propietario_id',
            'estatus',
            'mes',
            'desde',
            'hasta',
            'tipo_contrato',
            'tipo_servicio_id',
        ]);

        $this->estatus = 'todas';
        $this->tipo_contrato = 'todos';

        $this->mes = now()->format('Y-m');
        $this->aplicarRangoPorMes(true);

        $this->resetPage();
    }

    public function render()
    {
        $term = trim($this->q);
        $recargoTipoCobroId = $this->obtenerTipoCobroRecargoId();

        $tokens = $term !== '' ? preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) : [];

        $cuotas = Cuota::query()
            ->with(['contrato.cliente', 'contrato.lote.fraccionamiento'])
            ->withExists([
                'pagos as tiene_pago_recibo' => fn ($q) => $q->whereNotNull('recibo_id'),
                'recibos as tiene_recibo_directo' => fn ($q) => $q,
            ])

            ->when($this->propietario_id, function ($qq) {
                $qq->whereHas('contrato.lote.fraccionamiento', function ($f) {
                    $f->where('propietario_id', $this->propietario_id);
                });
            })

            ->when($this->tipo_contrato !== 'todos', function ($qq) {
                $qq->whereHas('contrato', function ($c) {
                    $c->where('tipo', $this->tipo_contrato);
                });
            })

            ->when($this->tipo_contrato === 'servicio' && $this->tipo_servicio_id, function ($qq) use ($recargoTipoCobroId) {
                $qq->whereHas('pagos', function ($p) use ($recargoTipoCobroId) {
                    $p->where('estatus', 'confirmado')
                        ->whereNotNull('recibo_id')
                        ->whereHas('recibo', function ($r) use ($recargoTipoCobroId) {
                            if ($recargoTipoCobroId) {
                                $r->where('tipos_cobro_id', '!=', $recargoTipoCobroId);
                            }

                            $r->where('tipos_cobro_id', $this->tipo_servicio_id);
                        });
                })->orWhereHas('recibos', function ($r) use ($recargoTipoCobroId) {
                    if ($recargoTipoCobroId) {
                        $r->where('tipos_cobro_id', '!=', $recargoTipoCobroId);
                    }

                    $r->where('tipos_cobro_id', $this->tipo_servicio_id);
                });
            })

            ->when($this->estatus !== 'todas', function ($qq) {
                if ($this->estatus === 'pendientes') {
                    $qq->where('estatus', '!=', 'pagada');
                }

                if ($this->estatus === 'pagadas') {
                    $qq->where('estatus', '=', 'pagada');
                }
            })

            ->when($this->desde, fn ($qq) => $qq->whereDate('fecha_vencimiento', '>=', $this->desde))
            ->when($this->hasta, fn ($qq) => $qq->whereDate('fecha_vencimiento', '<=', $this->hasta))

            ->when($term !== '', function ($qq) use ($term, $tokens) {
                $qq->where(function ($q) use ($term, $tokens) {
                    $q->where('numero', 'like', "%{$term}%")
                        ->orWhereHas('contrato', function ($c) use ($term, $tokens) {
                            $c->where('folio_contrato', 'like', "%{$term}%")
                                ->orWhereHas('lote', fn ($l) => $l->where('clave', 'like', "%{$term}%"))
                                ->orWhereHas('cliente', function ($cl) use ($term, $tokens) {
                                    $cl->where(function ($nameQ) use ($term, $tokens) {
                                        $nameQ->whereRaw("CONCAT(nombres,' ',apellidos) LIKE ?", ["%{$term}%"])
                                            ->orWhereRaw("CONCAT(apellidos,' ',nombres) LIKE ?", ["%{$term}%"]);

                                        if (count($tokens) > 1) {
                                            $nameQ->orWhere(function ($and) use ($tokens) {
                                                foreach ($tokens as $t) {
                                                    $and->where(function ($w) use ($t) {
                                                        $w->where('nombres', 'like', "%{$t}%")
                                                            ->orWhere('apellidos', 'like', "%{$t}%");
                                                    });
                                                }
                                            });
                                        } else {
                                            $nameQ->orWhere('nombres', 'like', "%{$term}%")
                                                ->orWhere('apellidos', 'like', "%{$term}%");
                                        }
                                    });
                                });
                        });
                });
            })

            ->orderBy('fecha_vencimiento', 'asc')
            ->orderBy('numero', 'asc')
            ->paginate(15);

        $propietarios = Propietario::orderBy('nombre')->get();

        $tiposServicio = TipoCobro::query()
            ->where('activa', 1)
            ->where('categoria', 'servicio')
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return view('livewire.admin.cuotas.index', compact('cuotas', 'propietarios', 'tiposServicio'))
            ->layout('layouts.app');
    }
}
