<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Abono extraordinario a capital con recibo, y la promoción «Adelanta y gana»:
 * por cada bloque completo de $2,000 en efectivo se bonifican $500 al saldo.
 */
return new class extends Migration
{
    private const TIPOS = [
        'diferir_primer_pago',
        'cuotas_fijas',
        'descuento_saldo',
        'descuento_monto_pago',
        'pago_escalonado',
    ];

    public function up(): void
    {
        Schema::table('promociones', function (Blueprint $table) {
            $table->enum('tipo', [...self::TIPOS, 'adelanta_y_gana'])->change();

            $table->decimal('abono_por_bloque', 12, 2)->nullable()->after('monto_pago_posterior');
            $table->decimal('bonificacion_por_bloque', 12, 2)->nullable()->after('abono_por_bloque');
        });

        // Los fraccionamientos donde se ofrece la promoción.
        Schema::create('fraccionamiento_promocion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promocion_id')->constrained('promociones')->cascadeOnDelete();
            $table->foreignId('fraccionamiento_id')->constrained('fraccionamientos')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['promocion_id', 'fraccionamiento_id']);
        });

        Schema::create('abonos_capital', function (Blueprint $table) {
            $table->id();

            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            // El recibo del efectivo; los abonos hechos desde el contrato no traen.
            $table->foreignId('recibo_id')->nullable()->constrained('recibos')->nullOnDelete();
            $table->foreignId('promocion_id')->nullable()->constrained('promociones')->nullOnDelete();

            $table->decimal('monto_abono', 12, 2);
            // No es ingreso: se descuenta del saldo pero no entra a caja.
            $table->decimal('monto_bonificacion', 12, 2)->default(0);

            $table->decimal('saldo_anterior', 12, 2);
            $table->decimal('saldo_nuevo', 12, 2);

            // Lo que se quitó del final del calendario, para poder regresarlo
            // si se anula el abono.
            $table->json('cuotas_recortadas')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_at')->nullable();
            $table->foreignId('anulado_por_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        if (! DB::table('tipos_cobro')->where('nombre', 'ABONO A CAPITAL')->exists()) {
            DB::table('tipos_cobro')->insert([
                'nombre' => 'ABONO A CAPITAL',
                'categoria' => 'contrato',
                'requiere_periodo' => false,
                'activa' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('abonos_capital');
        Schema::dropIfExists('fraccionamiento_promocion');

        Schema::table('promociones', function (Blueprint $table) {
            $table->dropColumn(['abono_por_bloque', 'bonificacion_por_bloque']);
            $table->enum('tipo', self::TIPOS)->change();
        });

        // El tipo de cobro se queda: borrarlo dejaría recibos sin concepto.
    }
};
