<?php

namespace App\Livewire\Admin\Recibos;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

use App\Models\Recibo;
use App\Models\ReciboPago;
use App\Models\Propietario;
use App\Models\TipoCobro;
use App\Models\FormaPago;
use App\Models\CuentaBancaria;
use App\Services\ImageUploadService;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Index extends Component
{
    use WithPagination, WithFileUploads;

    protected string $paginationTheme = 'tailwind';

    public string $q = '';
    public ?int $propietario_id = null;
    public ?string $mes = null;
    public ?string $desde = null;
    public ?string $hasta = null;
    public ?int $tipo_cobro_id = null;
    public ?int $forma_pago_id = null;
    public ?int $cuenta_id = null;

    // activos | eliminados | todos
    public string $verEliminados = 'activos';

    // ===== Evidencia por recibo_pago =====
    public bool $modalEvidenciaOpen = false;
    public ?int $reciboPagoEvidenciaId = null;
    public ?int $reciboEvidenciaId = null;
    public ?string $reciboEvidenciaFolio = null;
    public ?string $reciboEvidenciaPath = null;
    public ?string $reciboEvidenciaUuid = null;
    public ?string $reciboEvidenciaFormaPago = null;
    public ?string $reciboEvidenciaCuenta = null;
    public bool $reciboEvidenciaEditable = false;

    public ?TemporaryUploadedFile $nuevaEvidencia = null;
    public ?string $nuevaEvidenciaPreviewUrl = null;

    // ===== Firma =====
    public bool $modalFirmaOpen = false;
    public ?int $reciboFirmaId = null;
    public ?string $reciboFirmaFolio = null;
    public ?string $reciboFirmaPath = null;
    public ?string $reciboFirmaUuid = null;
    public ?string $firmaData = null;

    protected $queryString = [
        'q' => ['except' => ''],
        'propietario_id' => ['except' => null],
        'mes' => ['except' => null],
        'desde' => ['except' => null],
        'hasta' => ['except' => null],
        'tipo_cobro_id' => ['except' => null],
        'forma_pago_id' => ['except' => null],
        'cuenta_id' => ['except' => null],
        'verEliminados' => ['except' => 'activos'],
    ];

    public function mount(): void
    {
        if (! $this->mes) {
            $this->mes = now()->format('Y-m');
        }

        $this->aplicarRangoPorMes();
    }

    public function updating($name, $value): void
    {
        if ($name !== 'page') {
            $this->resetPage();
        }
    }

    public function updatedMes(): void
    {
        $this->aplicarRangoPorMes();
        $this->resetPage();
    }

    public function updatedPropietarioId(): void
    {
        $this->cuenta_id = null;
        $this->resetPage();
    }

    public function updatedNuevaEvidencia(): void
    {
        $this->validateOnly('nuevaEvidencia', [
            'nuevaEvidencia' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        if (! $this->nuevaEvidencia) {
            $this->nuevaEvidenciaPreviewUrl = null;
            return;
        }

        $ext = strtolower($this->nuevaEvidencia->getClientOriginalExtension());

        if ($ext === 'pdf') {
            $this->nuevaEvidenciaPreviewUrl = null;
            return;
        }

        $this->nuevaEvidenciaPreviewUrl = $this->nuevaEvidencia->temporaryUrl();
    }

    protected function reciboPagoPermiteEvidencia(ReciboPago $reciboPago): bool
    {
        return (bool) ($reciboPago->formaPago?->requiere_cuenta);
    }

private function aplicarRangoPorMes(): void
{
    if (! $this->mes) {
        return;
    }

    try {
        $inicio = Carbon::createFromFormat('!Y-m', $this->mes)->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();

        $this->desde = $inicio->format('Y-m-d');
        $this->hasta = $fin->format('Y-m-d');
    } catch (\Throwable $e) {
        // silencioso
    }
}

    public function limpiarFiltros(): void
    {
        $this->reset([
            'q',
            'propietario_id',
            'mes',
            'desde',
            'hasta',
            'tipo_cobro_id',
            'forma_pago_id',
            'cuenta_id',
            'verEliminados',
        ]);

        $this->mes = now()->format('Y-m');
        $this->verEliminados = 'activos';

        $this->aplicarRangoPorMes();
        $this->resetPage();
    }

    // =========================
    // Evidencia por recibo_pago
    // =========================

    public function abrirModalEvidencia(int $reciboPagoId): void
    {
        $reciboPago = ReciboPago::query()
            ->with([
                'recibo' => fn($q) => $q->withTrashed(),
                'formaPago',
                'cuentaBancaria',
            ])
            ->findOrFail($reciboPagoId);

        $recibo = $reciboPago->recibo;

        $this->reciboPagoEvidenciaId = $reciboPago->id;
        $this->reciboEvidenciaId = $recibo?->id;
        $this->reciboEvidenciaFolio = $recibo?->folio;
        $this->reciboEvidenciaPath = $reciboPago->evidencia_path;
        $this->reciboEvidenciaUuid = $recibo?->uuid;
        $this->reciboEvidenciaFormaPago = $reciboPago->formaPago?->nombre;
        $this->reciboEvidenciaCuenta = $reciboPago->cuentaBancaria?->alias;
        $this->reciboEvidenciaEditable = $this->reciboPagoPermiteEvidencia($reciboPago);

        $this->nuevaEvidencia = null;
        $this->nuevaEvidenciaPreviewUrl = null;

        $this->modalEvidenciaOpen = true;
    }

    public function cerrarModalEvidencia(): void
    {
        $this->reset([
            'modalEvidenciaOpen',
            'reciboPagoEvidenciaId',
            'reciboEvidenciaId',
            'reciboEvidenciaFolio',
            'reciboEvidenciaPath',
            'reciboEvidenciaUuid',
            'reciboEvidenciaFormaPago',
            'reciboEvidenciaCuenta',
            'reciboEvidenciaEditable',
            'nuevaEvidencia',
            'nuevaEvidenciaPreviewUrl',
        ]);
    }

    public function eliminarEvidencia(ImageUploadService $imageUploadService): void
    {
        if (! $this->reciboPagoEvidenciaId) {
            return;
        }

        $reciboPago = ReciboPago::query()
            ->with('formaPago')
            ->findOrFail($this->reciboPagoEvidenciaId);

        if (! $this->reciboPagoPermiteEvidencia($reciboPago)) {
            $this->dispatch('toast', type: 'warning', message: 'Este pago no permite manejar evidencia.');
            return;
        }

        if ($reciboPago->evidencia_path) {
            $imageUploadService->deleteIfExists($reciboPago->evidencia_path);
        }

        $reciboPago->update([
            'evidencia_path' => null,
            'evidencia_disk' => null,
            'evidencia_mime' => null,
            'evidencia_size' => null,
        ]);

        $this->reciboEvidenciaPath = null;
        $this->nuevaEvidencia = null;
        $this->nuevaEvidenciaPreviewUrl = null;

        $this->dispatch('toast', type: 'success', message: 'Evidencia eliminada correctamente.');
    }

    public function reemplazarEvidencia(ImageUploadService $imageUploadService): void
    {
        $this->validate([
            'nuevaEvidencia' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        if (! $this->reciboPagoEvidenciaId) {
            return;
        }

        $reciboPago = ReciboPago::query()
            ->with(['formaPago', 'recibo'])
            ->findOrFail($this->reciboPagoEvidenciaId);

        if (! $this->reciboPagoPermiteEvidencia($reciboPago)) {
            $this->dispatch('toast', type: 'warning', message: 'Este pago no permite subir evidencia.');
            return;
        }

        DB::transaction(function () use ($reciboPago, $imageUploadService) {
            if ($reciboPago->evidencia_path) {
                $imageUploadService->deleteIfExists($reciboPago->evidencia_path);
            }

            $folioBase = $reciboPago->recibo?->folio ?? 'recibo';
            $ordenPago = $reciboPago->orden ?: $reciboPago->id;

            $data = $imageUploadService->saveOptimized(
                file: $this->nuevaEvidencia,
                folder: 'recibos/evidencias',
                maxWidth: 1600,
                maxHeight: 1600,
                quality: 72,
                referenceFolder: $folioBase . '-pago-' . $ordenPago
            );

            $reciboPago->update([
                'evidencia_path' => $data['path'],
                'evidencia_disk' => $data['disk'] ?? 'private',
                'evidencia_mime' => $data['mime'] ?? null,
                'evidencia_size' => $data['size'] ?? null,
            ]);
        });

        $reciboPago->refresh();

        $this->reciboEvidenciaPath = $reciboPago->evidencia_path;
        $this->nuevaEvidencia = null;
        $this->nuevaEvidenciaPreviewUrl = null;

        $this->dispatch('toast', type: 'success', message: 'Evidencia reemplazada correctamente.');
    }

    // =========================
    // Firma
    // =========================

    public function abrirModalFirma(int $reciboId): void
    {
        $recibo = Recibo::withTrashed()->findOrFail($reciboId);

        if ($recibo->trashed()) {
            $this->dispatch('toast', type: 'warning', message: 'No puedes firmar un recibo eliminado.');
            return;
        }

        $this->reciboFirmaId = $recibo->id;
        $this->reciboFirmaFolio = $recibo->folio;
        $this->reciboFirmaPath = $recibo->firma_path;
        $this->reciboFirmaUuid = $recibo->uuid;
        $this->firmaData = null;

        $this->modalFirmaOpen = true;

        if (! $recibo->firma_path) {
            $this->js(<<<'JS'
                setTimeout(() => {
                    if (window.initReciboSignaturePad) {
                        window.initReciboSignaturePad();
                    }
                }, 250);
            JS);
        }
    }

    public function cerrarModalFirma(): void
    {
        $this->reset([
            'modalFirmaOpen',
            'reciboFirmaId',
            'reciboFirmaFolio',
            'reciboFirmaPath',
            'reciboFirmaUuid',
            'firmaData',
        ]);
    }

    public function guardarFirma(): void
    {
        $this->validate([
            'reciboFirmaId' => ['required', 'integer', 'exists:recibos,id'],
            'firmaData' => ['required', 'string'],
        ], [
            'firmaData.required' => 'La firma es obligatoria.',
        ]);

        $recibo = Recibo::findOrFail($this->reciboFirmaId);

        if ($recibo->trashed()) {
            $this->dispatch('toast', type: 'warning', message: 'No puedes firmar un recibo eliminado.');
            return;
        }

        if ($recibo->firma_path) {
            $this->reciboFirmaPath = $recibo->firma_path;
            $this->reciboFirmaUuid = $recibo->uuid;
            $this->dispatch('toast', type: 'info', message: 'Este recibo ya está firmado.');
            return;
        }

        if (! str_starts_with($this->firmaData, 'data:image/png;base64,')) {
            $this->dispatch('toast', type: 'error', message: 'Formato de firma inválido.');
            return;
        }

        $base64 = str_replace('data:image/png;base64,', '', $this->firmaData);
        $base64 = str_replace(' ', '+', $base64);
        $binary = base64_decode($base64);

        if ($binary === false) {
            $this->dispatch('toast', type: 'error', message: 'No se pudo procesar la firma.');
            return;
        }

        $folioSeguro = str_replace(['/', '\\', ' '], '-', $recibo->folio);

        $path = sprintf(
            'recibos/firmas/%s/%s/%s/%s-%s.png',
            now()->format('Y'),
            now()->format('m'),
            now()->format('d'),
            $folioSeguro,
            Str::uuid()
        );

        $optimizedBinary = $this->cropWhitePngBinary($binary);

        Storage::disk('private')->put($path, $optimizedBinary);

        $recibo->update([
            'firma_path' => $path,
            'firma_disk' => 'private',
            'firma_mime' => 'image/png',
            'firma_size' => strlen($optimizedBinary),
            'firmado_en' => now(),
            'firmado_por' => auth()->user()?->name ?? 'Sistema',
        ]);

        $this->reciboFirmaPath = $path;
        $this->reciboFirmaUuid = $recibo->uuid;
        $this->firmaData = null;

        $this->dispatch('toast', type: 'success', message: 'Firma guardada correctamente.');
    }

    protected function cropWhitePngBinary(string $binary): string
    {
        $source = imagecreatefromstring($binary);

        if (! $source) {
            return $binary;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($source, $x, $y);

                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $isWhite = $r >= 245 && $g >= 245 && $b >= 245;

                if (! $isWhite) {
                    if ($x < $minX) $minX = $x;
                    if ($y < $minY) $minY = $y;
                    if ($x > $maxX) $maxX = $x;
                    if ($y > $maxY) $maxY = $y;
                }
            }
        }

        if ($maxX === -1 || $maxY === -1) {
            ob_start();
            imagepng($source);
            $out = ob_get_clean();
            imagedestroy($source);
            return $out ?: $binary;
        }

        $padding = 10;

        $cropX = max(0, $minX - $padding);
        $cropY = max(0, $minY - $padding);
        $cropWidth = min($width - $cropX, ($maxX - $minX + 1) + ($padding * 2));
        $cropHeight = min($height - $cropY, ($maxY - $minY + 1) + ($padding * 2));

        $cropped = imagecreatetruecolor($cropWidth, $cropHeight);

        $white = imagecolorallocate($cropped, 255, 255, 255);
        imagefill($cropped, 0, 0, $white);

        imagecopy(
            $cropped,
            $source,
            0,
            0,
            $cropX,
            $cropY,
            $cropWidth,
            $cropHeight
        );

        ob_start();
        imagepng($cropped);
        $result = ob_get_clean();

        imagedestroy($source);
        imagedestroy($cropped);

        return $result ?: $binary;
    }

    public function render()
    {
        $term = trim($this->q);
        $tokens = $term !== '' ? preg_split('/\s+/', $term) : [];

        $query = Recibo::query();

        if ($this->verEliminados === 'eliminados') {
            $query->onlyTrashed();
        } elseif ($this->verEliminados === 'todos') {
            $query->withTrashed();
        }

        $recibos = $query
            ->where(function ($q) {
                $q->whereNull('es_historico')
                    ->orWhere('es_historico', false);
            })
            ->where(function ($q) {
                $q->whereNull('folio')
                    ->orWhere('folio', 'not like', 'REC%');
            })
            ->with([
                'cliente',
                'cuota',
                'lote.fraccionamiento',
                'tipoCobro',
                'pagosDetalle.formaPago',
                'pagosDetalle.cuentaBancaria',
            ])
            ->when($this->propietario_id, function ($q) {
                $q->whereHas('lote.fraccionamiento', function ($f) {
                    $f->where('propietario_id', $this->propietario_id);
                });
            })
            ->when(
                $this->tipo_cobro_id,
                fn($q) => $q->where('tipos_cobro_id', $this->tipo_cobro_id)
            )
            ->when(
                $this->forma_pago_id,
                fn($q) => $q->whereHas('pagosDetalle', fn($rp) => $rp->where('forma_pago_id', $this->forma_pago_id))
            )
            ->when(
                $this->cuenta_id,
                fn($q) => $q->whereHas('pagosDetalle', fn($rp) => $rp->where('cuentas_bancarias_id', $this->cuenta_id))
            )
            ->when(
                $this->desde,
                fn($q) => $q->whereDate('fecha', '>=', $this->desde)
            )
            ->when(
                $this->hasta,
                fn($q) => $q->whereDate('fecha', '<=', $this->hasta)
            )
            ->when($term !== '', function ($q) use ($term, $tokens) {
                $q->where(function ($qq) use ($term, $tokens) {
                    $qq->where('folio', 'like', "%{$term}%")
                        ->orWhereHas('lote', function ($l) use ($term) {
                            $l->where('clave', 'like', "%{$term}%")
                                ->orWhere('lote', 'like', "%{$term}%");
                        })
                        ->orWhereHas('cliente', function ($cl) use ($term, $tokens) {
                            $cl->where(function ($nameQ) use ($term, $tokens) {
                                $nameQ->whereRaw("CONCAT(nombres,' ',apellidos) LIKE ?", ["%{$term}%"]);

                                if (count($tokens) > 1) {
                                    $nameQ->orWhere(function ($and) use ($tokens) {
                                        foreach ($tokens as $t) {
                                            $and->where(function ($w) use ($t) {
                                                $w->where('nombres', 'like', "%{$t}%")
                                                    ->orWhere('apellidos', 'like', "%{$t}%");
                                            });
                                        }
                                    });
                                }
                            });
                        })
                        ->orWhereHas('pagosDetalle.cuentaBancaria', function ($cb) use ($term) {
                            $cb->where('alias', 'like', "%{$term}%")
                                ->orWhere('banco', 'like', "%{$term}%");
                        });
                });
            })
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(15);

        $propietarios = Propietario::orderBy('nombre')->get();
        $tiposCobro = TipoCobro::orderBy('nombre')->get();
        $formasPago = FormaPago::orderBy('nombre')->get();

        $cuentas = CuentaBancaria::query()
            ->when($this->propietario_id, fn($q) => $q->where('propietario_id', $this->propietario_id))
            ->orderBy('alias')
            ->get();

        return view('livewire.admin.recibos.index', compact(
            'recibos',
            'propietarios',
            'tiposCobro',
            'formasPago',
            'cuentas'
        ))->layout('layouts.app');
    }
}
