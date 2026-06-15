<div
    x-cloak
    x-data="{
        show: false,
        title: '',
        message: '',
        type: 'success',
        timeout: null,
        progress: 100,

        open(payload) {
            this.title = payload.title ?? (
                payload.type === 'error' ? 'Ocurrió un error' :
                payload.type === 'warning' ? 'Atención' :
                'Operación exitosa'
            );

            this.message = payload.message ?? '';
            this.type = payload.type ?? 'success';
            this.show = true;
            this.progress = 100;

            clearInterval(this.timeout);

            this.timeout = setInterval(() => {
                this.progress -= 2.5;
                if (this.progress <= 0) {
                    this.show = false;
                    clearInterval(this.timeout);
                }
            }, 100);
        }
    }"
    x-on:toast.window="open($event.detail)"
    class="fixed top-6 right-6 z-[9999] w-full max-w-sm"
>
    <div
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="rounded-3xl shadow-2xl border overflow-hidden bg-white"
    >
        <div class="flex gap-4 p-5">
            {{-- Ícono --}}
            <div
                class="flex items-center justify-center w-12 h-12 rounded-full shrink-0"
                :class="type === 'success'
                    ? 'bg-green-100 text-green-600'
                    : (type === 'error'
                        ? 'bg-red-100 text-red-600'
                        : 'bg-yellow-100 text-yellow-600')"
            >
                <template x-if="type === 'success'">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M5 13l4 4L19 7"/>
                    </svg>
                </template>

                <template x-if="type === 'error'">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </template>

                <template x-if="type === 'warning'">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 9v2m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3z"/>
                    </svg>
                </template>
            </div>

            {{-- Texto --}}
            <div class="flex-1">
                <div class="text-base font-black text-gray-900" x-text="title"></div>
                <div class="text-sm text-gray-600 mt-1" x-text="message"></div>
            </div>

            {{-- Cerrar --}}
            <button @click="show = false"
                    class="text-gray-400 hover:text-gray-600">
                ✕
            </button>
        </div>

        {{-- Barra de progreso --}}
        <div class="h-1 bg-gray-100">
            <div
                class="h-full transition-all"
                :class="type === 'success'
                    ? 'bg-green-500'
                    : (type === 'error'
                        ? 'bg-red-500'
                        : 'bg-yellow-500')"
                :style="`width: ${progress}%`"
            ></div>
        </div>
    </div>
</div>
