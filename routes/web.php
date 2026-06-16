<?php

use Illuminate\Support\Facades\Route;



/*
|--------------------------------------------------------------------------
| Imports – Admin / Contratos
|--------------------------------------------------------------------------
*/
use App\Livewire\Admin\Contratos\Index as ContratosIndex;
use App\Livewire\Admin\Contratos\Create as ContratosCreate;
use App\Livewire\Admin\Contratos\Show as ContratosShow;
use App\Livewire\Admin\Contratos\Edit as ContratosEdit;

use App\Livewire\Admin\ContratosServicios\Index as ContratoServicioIndex;
use App\Livewire\Admin\ContratosServicios\Create as ContratoServicioCreate;
use App\Livewire\Admin\ContratosServicios\Show as ContratoServicioShow;
use App\Livewire\Admin\ContratosServicios\Edit as ContratosServiciosEdit;

use App\Livewire\Admin\Recibos\Index as RecibosIndex;
use App\Http\Controllers\Admin\ReciboFirmaController;
use App\Http\Controllers\Admin\RecibosPrintController;
use App\Http\Controllers\PrivateFileController;

use App\Livewire\Admin\Recibos\Edit;

use App\Livewire\Admin\TipoCobroPropietarioConfigs\Index as TipoCobroPropietarioConfigsIndex;

/*
|--------------------------------------------------------------------------
| Imports – Reports
|--------------------------------------------------------------------------
*/
use App\Livewire\Reports\ReportsIndex;
use App\Livewire\Reports\DailyReceiptsReport;
use App\Livewire\Reports\CustomerPaymentsReport;
use App\Livewire\Reports\MonthlyIncomeReport;
use App\Livewire\Reports\BankMovementsReport;

/*
|--------------------------------------------------------------------------
| Imports – Dashboard
|--------------------------------------------------------------------------
*/
use App\Livewire\Dashboard\CobranzaDashboard;

/*
|--------------------------------------------------------------------------
| Imports – Admin / Clientes Excelentes
|--------------------------------------------------------------------------
*/
use App\Livewire\Admin\ClientesExcelentes\Index as ClientesExcelentesIndex;
use App\Livewire\Admin\ClientesExcelentes\Contrato as ClientesExcelentesContrato;

/*
|--------------------------------------------------------------------------
| Imports – Admin / Sistema
|--------------------------------------------------------------------------
*/
use App\Livewire\Admin\Sistema\Index as SistemaIndex;
use App\Livewire\Admin\Permisos\Index as PermisosIndex;

/*
|--------------------------------------------------------------------------
| Página pública
|--------------------------------------------------------------------------
*/

Route::get('/', fn() => view('welcome'));

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'permission:dashboard.ver',
])->group(function () {
    Route::get('/dashboard', CobranzaDashboard::class)->name('dashboard');
});

/*
|--------------------------------------------------------------------------
| Reportes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'permission:reportes.ver'])->group(function () {

    Route::get('/reportes', ReportsIndex::class)->name('reportes.index');

    Route::prefix('reportes')->name('reportes.')->group(function () {
        Route::get('/diario-recibos', DailyReceiptsReport::class)
            ->name('diario.recibos')
            ->middleware('permission:reportes.diario_recibos');

        Route::get('/cliente-pagos', CustomerPaymentsReport::class)
            ->name('cliente.pagos')
            ->middleware('permission:reportes.cliente_pagos');

        Route::get('/ingresos-mensuales', MonthlyIncomeReport::class)
            ->name('ingresos.mensuales')
            ->middleware('permission:reportes.ingresos_mensuales');
            
Route::get('/print-demo-data', function () {
    return response()->json([
        'ok' => true,
        'text' => "HOLA DESDE LARAVEL\nPRUEBA DE IMPRESION\n",
    ]);
});

Route::get('/print-demo', function () {
    return view('print-demo');
});
        Route::get('/movimientos-bancarios', BankMovementsReport::class)
            ->name('movimientos.bancarios')
            ->middleware('permission:reportes.movimientos_bancarios');
    });
});

/*
|--------------------------------------------------------------------------
| Administración
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'permission:admin.ver'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        /*
        | Dashboard Admin
        */
        Route::view('/', 'admin.index')->name('index');

        /*
        |--------------------------------------------------------------------------
        | SISTEMA
        |--------------------------------------------------------------------------
        */
        Route::get('/sistema', SistemaIndex::class)
            ->name('sistema.index')
            ->middleware('permission:sistema.ver');

        /*
        | Catálogos
        */
        Route::get('/promociones', \App\Livewire\Admin\Promociones\Index::class)
            ->name('promociones')
            ->middleware('permission:catalogos.ver');

        Route::get('/tipos-cobro', \App\Livewire\Admin\TiposCobro\Index::class)
            ->name('tipos-cobro')
            ->middleware('permission:catalogos.ver');

        Route::get('/formas-pago', \App\Livewire\Admin\FormasPago\Index::class)
            ->name('formas-pago')
            ->middleware('permission:catalogos.ver');

        Route::get('/propietarios', \App\Livewire\Admin\Propietarios\Index::class)
            ->name('propietarios')
            ->middleware('permission:catalogos.ver');

        Route::get('/periodos', \App\Livewire\Admin\Periodos\Index::class)
            ->name('periodos')
            ->middleware('permission:catalogos.ver');

        Route::get('/fraccionamientos', \App\Livewire\Admin\Fraccionamientos\Index::class)
            ->name('fraccionamientos')
            ->middleware('permission:catalogos.ver');

        Route::get('/lotes', \App\Livewire\Admin\Lotes\Index::class)
            ->name('lotes')
            ->middleware('permission:catalogos.ver');

        Route::get('/cuentas-bancarias', \App\Livewire\Admin\CuentasBancarias\Index::class)
            ->name('cuentas-bancarias')
            ->middleware('permission:catalogos.ver');

        Route::get('/clientes', \App\Livewire\Admin\Clientes\Index::class)
            ->name('clientes')
            ->middleware('permission:clientes.ver');
            
        Route::get('/configuracion-propietarios-contables', TipoCobroPropietarioConfigsIndex::class)
        ->name('configuracion-propietarios-contables.index');

        /*
        | Contratos
        */
        Route::get('/contratos', ContratosIndex::class)
            ->name('contratos.index')
            ->middleware('permission:contratos.ver');

        Route::get('/contratos/crear', ContratosCreate::class)
            ->name('contratos.create')
            ->middleware('permission:contratos.editar');

        Route::get('/contratos/{contrato}', ContratosShow::class)
            ->name('contratos.show')
            ->middleware('permission:contratos.ver_detalle');

        Route::get('/contratos/{contrato}/pdf', [\App\Http\Controllers\Admin\ContratosPdfController::class, 'download'])
            ->name('contratos.pdf');

        Route::get('/contratos/{uuid}/editar', ContratosEdit::class)
            ->name('contratos.edit');

        Route::get('/private/contratos/{uuid}/pdf', [PrivateFileController::class, 'showContratoPdf'])
            ->name('private.contratos.pdf')
            ->middleware('permission:contratos.ver_detalle');

        Route::get('/private/contratos/{uuid}/credencial/{persona}/{lado}', [PrivateFileController::class, 'showContratoCredencial'])
            ->name('private.contratos.credencial');

        Route::get('/private/contratos/{uuid}/docx', [PrivateFileController::class, 'showContratoDocx'])
            ->name('private.contratos.docx');

        Route::get('/private/contratos/{uuid}/docx/download', [PrivateFileController::class, 'downloadContratoDocx'])
            ->name('private.contratos.docx.download');

        Route::get('/private/contratos/{uuid}/documentos/{tipo}/docx/download', [PrivateFileController::class, 'downloadContratoDocumentoDocx'])
            ->name('private.contratos.documentos.docx.download');

        Route::get('/private/contratos/{uuid}/documentos/{tipo}/scan', [PrivateFileController::class, 'showContratoDocumentoScan'])
            ->name('private.contratos.documentos.scan');
            
        Route::get('/private-files/show', [PrivateFileController::class, 'show'])
            ->name('private-files.show');


        /*
        | Contratos Servicios
        */
        Route::get('/contratos-servicios', ContratoServicioIndex::class)
            ->name('contratos-servicios.index')
            ->middleware('permission:contratos_servicios.ver');

        Route::get('/contratos-servicios/crear', ContratoServicioCreate::class)
            ->name('contratos-servicios.create')
            ->middleware('permission:contratos_servicios.editar');

        Route::get('/contratos-servicios/{contrato}', ContratoServicioShow::class)
            ->name('contratos-servicios.show')
            ->middleware('permission:contratos_servicios.ver_detalle');

        Route::get('/contratos-servicios/{uuid}/edit', ContratosServiciosEdit::class)
            ->name('contratos-servicios.edit');
            
            
        Route::get('/private/contratos/{uuid}/pdf', [PrivateFileController::class, 'showContratoPdf'])
            ->name('private.contratos.pdf')
            ->middleware('permission:contratos.ver_detalle');
            
            

        /*
        | Cobranza / Recibos
        */
         Route::get('/cuotas', \App\Livewire\Admin\Cuotas\Index::class)
            ->name('cuotas')
            ->middleware('permission:cuotas.ver');

        Route::get('/recibos', RecibosIndex::class)
            ->name('recibos.index')
            ->middleware('permission:recibos.crear');

        Route::get('/recibos/{uuid}/editar', Edit::class)
            ->name('recibos.edit');

        Route::get('/recibos/crear', \App\Livewire\Admin\Recibos\Crear::class)
            ->name('recibos.crear')
            ->middleware('permission:recibos.crear');

        Route::get('/recibos/{recibo}/imprimir', [RecibosPrintController::class, 'show'])
            ->name('recibos.imprimir')
            ->middleware('permission:recibos.imprimir');

        Route::get('/recibos/imprimir-lote/{token}', [RecibosPrintController::class, 'pdfLote'])
            ->name('recibos.pdf-lote')
            ->middleware('permission:recibos.imprimir');
            
            Route::get('/recibos/{recibo}/print-data', [RecibosPrintController::class, 'printData'])
    ->name('recibos.print-data')
    ->middleware('permission:recibos.imprimir');
    
    Route::get('/recibos/{recibo}/print-token', [RecibosPrintController::class, 'printToken'])
    ->name('recibos.print-token')
    ->middleware('permission:recibos.imprimir');

        Route::get('/recibos/lote/{token}/imprimir', [RecibosPrintController::class, 'showLote'])
            ->name('recibos.print-lote')
            ->middleware('permission:recibos.imprimir');

        Route::get('/recibos/{recibo}/pdf', [RecibosPrintController::class, 'pdf'])
            ->name('recibos.pdf')
            ->middleware('permission:recibos.imprimir');

        Route::get('/recibos/{uuid}/evidencia', [PrivateFileController::class, 'showReciboEvidencia'])
            ->name('recibos.evidencia.show')
            ->middleware('permission:recibos.imprimir');

        Route::get('/recibo-pagos/{reciboPagoId}/evidencia', [PrivateFileController::class, 'showReciboPagoEvidencia'])
            ->name('recibo-pagos.evidencia.show')
            ->middleware('permission:recibos.imprimir');

        Route::get('/recibos/{recibo:uuid}/firma', [ReciboFirmaController::class, 'show'])
            ->name('recibos.firma.show')
            ->middleware('permission:recibos.imprimir');

        /*
        | Clientes Excelentes
        */
        Route::get('/clientes-excelentes', ClientesExcelentesIndex::class)
            ->name('clientes-excelentes.index')
            ->middleware('permission:clientes_excelentes.ver');

        Route::get('/clientes-excelentes/contrato/{contrato}', ClientesExcelentesContrato::class)
            ->name('clientes-excelentes.contrato')
            ->middleware('permission:clientes_excelentes.condonar');

        /*
        |--------------------------------------------------------------------------
        | Usuarios / Roles / Logs
        |--------------------------------------------------------------------------
        */
        Route::get('/usuarios', \App\Livewire\Admin\Usuarios\Index::class)
            ->name('usuarios.index')
            ->middleware('permission:usuarios.ver');

        Route::get('/roles', \App\Livewire\Admin\Roles\Index::class)
            ->name('roles.index')
            ->middleware('permission:roles.ver');
            
        Route::get('/permisos', PermisosIndex::class)
            ->name('permisos.index')
            ->middleware('permission:permisos.ver');

        Route::get('/logs', \App\Livewire\Admin\Logs\Index::class)
            ->name('logs.index')
            ->middleware('permission:logs.ver');
    });
