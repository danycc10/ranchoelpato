<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promoción de pago escalonado: un monto más bajo los primeros meses y el
 * normal lo que resta. Por ejemplo, $250 el primer año y $400 después.
 */
return new class extends Migration
{
    private const TIPOS = [
        'diferir_primer_pago',
        'cuotas_fijas',
        'descuento_saldo',
        'descuento_monto_pago',
    ];

    public function up(): void
    {
        Schema::table('promociones', function (Blueprint $table) {
            $table->enum('tipo', [...self::TIPOS, 'pago_escalonado'])->change();

            $table->decimal('monto_pago_inicial', 12, 2)->nullable()->after('monto_fijo');
            $table->unsignedSmallInteger('meses_pago_inicial')->nullable()->after('monto_pago_inicial');
            // Con el que se llena el monto de pago del contrato: es el que sigue
            // al terminar la promoción.
            $table->decimal('monto_pago_posterior', 12, 2)->nullable()->after('meses_pago_inicial');
        });
    }

    public function down(): void
    {
        Schema::table('promociones', function (Blueprint $table) {
            $table->dropColumn(['monto_pago_inicial', 'meses_pago_inicial', 'monto_pago_posterior']);
            $table->enum('tipo', self::TIPOS)->change();
        });
    }
};
