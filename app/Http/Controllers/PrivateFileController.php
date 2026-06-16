<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\Recibo;
use App\Models\ReciboPago;
use App\Services\Contratos\ContratoWordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PrivateFileController extends Controller
{
    public function showReciboEvidencia(string $uuid)
    {
        $recibo = Recibo::withTrashed()
            ->where('uuid', $uuid)
            ->firstOrFail();

        abort_unless($recibo->evidencia_path, 404);

        $disk = $recibo->evidencia_disk ?: 'private';

        abort_unless(Storage::disk($disk)->exists($recibo->evidencia_path), 404);

        return response()->file(
            Storage::disk($disk)->path($recibo->evidencia_path),
            [
                'Content-Type' => $recibo->evidencia_mime ?: 'image/webp',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }

    public function showReciboPagoEvidencia(int $reciboPagoId)
    {
        $reciboPago = ReciboPago::query()
            ->with(['recibo' => fn ($q) => $q->withTrashed()])
            ->findOrFail($reciboPagoId);

        abort_unless($reciboPago->evidencia_path, 404);

        $disk = $reciboPago->evidencia_disk ?: 'private';

        abort_unless(Storage::disk($disk)->exists($reciboPago->evidencia_path), 404);

        return response()->file(
            Storage::disk($disk)->path($reciboPago->evidencia_path),
            [
                'Content-Type' => $reciboPago->evidencia_mime ?: 'image/webp',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }

    public function showContratoPdf(string $uuid)
    {
        abort_unless(auth()->check(), 403);
        abort_unless(auth()->user()?->can('sistema.ver'), 403);

        $contrato = Contrato::query()
            ->where('uuid', $uuid)
            ->firstOrFail();

        abort_unless($contrato->archivo_contrato, 404);

        $disk = $contrato->archivo_contrato_disk ?: 'private';

        abort_unless(Storage::disk($disk)->exists($contrato->archivo_contrato), 404);

        return response()->file(
            Storage::disk($disk)->path($contrato->archivo_contrato),
            [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }

    public function showContratoDocumentoScan(string $uuid, string $tipo)
    {
        abort_unless(auth()->check(), 403);
        abort_unless(auth()->user()?->can('sistema.ver'), 403);

        $documento = ContratoWordService::documentType($tipo);
        abort_unless($documento, 404);

        $contrato = Contrato::query()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $relativePath = $contrato->{$documento['scan_field']};

        abort_unless($relativePath, 404);

        $disk = $contrato->archivo_contrato_disk ?: 'private';

        abort_unless(Storage::disk($disk)->exists($relativePath), 404);

        return response()->file(
            Storage::disk($disk)->path($relativePath),
            [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }

    public function showContratoDocx(string $uuid)
    {
        abort_unless(auth()->check(), 403);
        abort_unless(auth()->user()?->can('sistema.ver'), 403);

        $contrato = Contrato::query()
            ->where('uuid', $uuid)
            ->firstOrFail();

        abort_unless($contrato->archivo_contrato_docx, 404);

        $disk = $contrato->archivo_contrato_disk ?: 'private';
        $relativePath = $contrato->archivo_contrato_docx;

        abort_unless(Storage::disk($disk)->exists($relativePath), 404);

        return response()->file(
            Storage::disk($disk)->path($relativePath),
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }

    public function downloadContratoDocx(string $uuid, ContratoWordService $contratoWordService)
    {
        return $this->downloadContratoDocumentoDocx($uuid, 'contrato', $contratoWordService);
    }

    public function downloadContratoDocumentoDocx(
        string $uuid,
        string $tipo,
        ContratoWordService $contratoWordService
    ) {
        abort_unless(auth()->check(), 403);
        abort_unless(auth()->user()?->can('sistema.ver'), 403);

        $documento = ContratoWordService::documentType($tipo);
        abort_unless($documento, 404);

        $contrato = Contrato::query()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $disk = $contrato->archivo_contrato_disk ?: 'private';
        $field = $documento['docx_field'];
        $relativePath = $contrato->{$field};

        if (! $relativePath || ! Storage::disk($disk)->exists($relativePath)) {
            $relativePath = $contratoWordService->generarDocx($contrato, $tipo);

            $contrato->update([
                $field => $relativePath,
                'archivo_contrato_disk' => $disk,
            ]);
        }

        $absolutePath = Storage::disk($disk)->path($relativePath);
        $nombre = $documento['filename_slug']
            .'-'
            .Str::slug((string) ($contrato->folio_contrato ?: $contrato->uuid))
            .'.docx';

        return response()->download(
            $absolutePath,
            $nombre,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }

    public function show(Request $request)
    {
        $disk = $request->get('disk', 'private');
        $path = decrypt($request->get('path'));

        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        $file = Storage::disk($disk)->path($path);

        return response()->file($file, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    public function showContratoCredencial(string $uuid, string $lado, string $persona)
    {
        abort_unless(auth()->check(), 403);
        abort_unless(auth()->user()?->can('sistema.ver'), 403);

        $contrato = Contrato::query()->where('uuid', $uuid)->firstOrFail();

        $campo = match ("{$persona}_{$lado}") {
            'comprador_frente' => 'comprador_ine_frente',
            'comprador_reverso' => 'comprador_ine_reverso',
            'vendedor_frente' => 'vendedor_ine_frente',
            'vendedor_reverso' => 'vendedor_ine_reverso',
            default => null,
        };

        abort_unless($campo, 404);

        $path = $contrato->{$campo};
        abort_unless($path, 404);

        $disk = $contrato->credenciales_disk ?: 'private';

        abort_unless(Storage::disk($disk)->exists($path), 404);

        return response()->file(
            Storage::disk($disk)->path($path),
            [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }
}
