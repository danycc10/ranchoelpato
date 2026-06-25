<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Recibo;
use Illuminate\Support\Facades\Storage;

class ReciboFirmaController extends Controller
{
    public function show(Recibo $recibo)
    {
        abort_unless($recibo->firma_path, 404);

        $disk = $recibo->firma_disk ?: 'private';

        abort_unless(Storage::disk($disk)->exists($recibo->firma_path), 404);

        return response()->file(
            Storage::disk($disk)->path($recibo->firma_path),
            [
                'Content-Type' => $recibo->firma_mime ?: 'image/png',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }
}
