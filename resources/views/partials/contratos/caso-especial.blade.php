{{--
    Panel de caso especial. Se incluye dentro de un componente Livewire que
    expone: confirmar_caso_especial, confirmar_resolver_especial,
    nota_caso_especial, marcarCasoEspecial() y resolverCasoEspecial().
--}}
@php
    $esEspecial = ($contrato->estatus ?? null) === \App\Models\Contrato::ESTATUS_ESPECIAL;
    $estaCancelado = ($contrato->estatus ?? null) === 'cancelado' || $contrato->trashed();
@endphp

<div class="bg-indigo-50 border border-indigo-200 rounded-2xl p-4">
    <div class="font-bold text-indigo-900">
        Caso especial
        @if ($esEspecial)
            <span class="ml-2 align-middle text-xs px-2 py-0.5 rounded-full bg-indigo-600 text-white">Activo</span>
        @endif
    </div>

    @if ($esEspecial)
        <div class="text-sm text-indigo-800 mt-1">
            Este contrato está en caso especial: sigue existiendo y admite pagos, pero sus cuotas
            no cuentan para cobranza, atrasos, notificaciones ni reportes.
            Al resolverlo regresará a
            <strong>{{ app(\App\Services\Contratos\CasoEspecialService::class)->estatusPrevio($contrato) }}</strong>.
        </div>

        <div class="mt-3">
            <label class="text-xs text-indigo-900 font-semibold">Nota (opcional)</label>
            <input type="text" wire:model.live="nota_caso_especial"
                placeholder="Cómo se resolvió la situación"
                class="mt-1 w-full border border-indigo-200 rounded-xl px-3 py-2 text-sm">
        </div>

        <label class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-indigo-900">
            <input type="checkbox" class="rounded" wire:model.live="confirmar_resolver_especial">
            Confirmo que la situación quedó resuelta
        </label>

        @error('confirmar_resolver_especial')
            <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
        @enderror

        <div class="mt-4">
            <button wire:click="resolverCasoEspecial"
                wire:loading.attr="disabled"
                wire:target="resolverCasoEspecial"
                class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 disabled:opacity-50">
                <span wire:loading.remove wire:target="resolverCasoEspecial">Resolver caso especial</span>
                <span wire:loading wire:target="resolverCasoEspecial">Resolviendo...</span>
            </button>
        </div>
    @else
        <div class="text-sm text-indigo-800 mt-1">
            Saca el contrato de cobranza y reportes mientras se resuelve su situación.
            El contrato sigue existiendo y se le puede seguir cobrando; al resolverlo
            regresa al estatus que tiene hoy
            (<strong>{{ $contrato->estatus ?? 'activo' }}</strong>).
        </div>

        <div class="mt-3">
            <label class="text-xs text-indigo-900 font-semibold">Motivo (opcional)</label>
            <input type="text" wire:model.live="nota_caso_especial"
                placeholder="Por qué entra a caso especial"
                class="mt-1 w-full border border-indigo-200 rounded-xl px-3 py-2 text-sm">
        </div>

        <label class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-indigo-900">
            <input type="checkbox" class="rounded" wire:model.live="confirmar_caso_especial">
            Confirmo marcar este contrato como caso especial
        </label>

        @error('confirmar_caso_especial')
            <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
        @enderror

        <div class="mt-4">
            <button wire:click="marcarCasoEspecial"
                wire:loading.attr="disabled"
                wire:target="marcarCasoEspecial"
                @disabled($estaCancelado)
                class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="marcarCasoEspecial">Marcar como caso especial</span>
                <span wire:loading wire:target="marcarCasoEspecial">Marcando...</span>
            </button>
        </div>
    @endif
</div>
