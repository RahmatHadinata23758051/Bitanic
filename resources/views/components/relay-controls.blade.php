@props([
    'deviceId' => null,
    'relays' => [
        'irrigationPump' => false,
        'fertigationValve' => false,
        'mistingFan' => false,
        'growLight' => false,
    ],
    'canControl' => true,
    'isOnline' => false,
])

<div
    class="p-5 rounded-2xl bg-white border border-slate-200 shadow-2xs space-y-4 flex flex-col justify-between"
    x-data="{
        relays: {{ json_encode($relays) }},
        loadingKey: null,
        errorMessage: '',
        toggleRelay(key) {
            if (!{{ $canControl ? 'true' : 'false' }} || this.loadingKey) return;
            this.loadingKey = key;
            this.errorMessage = '';
            const targetState = !this.relays[key];

            fetch('/api/v1/devices/relay/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]') ? document.querySelector('meta[name=csrf-token]').content : ''
                },
                body: JSON.stringify({
                    device_id: '{{ $deviceId }}',
                    relay_key: key,
                    state: targetState
                })
            })
            .then(async (res) => {
                const data = await res.json();
                if (!res.ok || !data.success || data.hardware_ack !== true) {
                    throw new Error(data.message || 'Belum ada konfirmasi dari hardware.');
                }
                this.relays[key] = data.state === true;
            })
            .catch((error) => {
                this.errorMessage = error.message || 'Perintah relay gagal. Status tidak berubah.';
            })
            .finally(() => {
                this.loadingKey = null;
            });
        }
    }"
>
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i data-lucide="power" class="w-4 h-4 text-slate-700"></i>
            <h4 class="text-xs font-semibold text-slate-900">Aktuator trainer kit</h4>
        </div>
        <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-slate-100 text-slate-600 font-semibold uppercase">
            {{ $canControl ? ($isOnline ? 'Tersambung' : 'Standby') : 'Mode Monitor' }}
        </span>
    </div>

    <!-- Hardware Feedback / Unconfirmed Alert -->
    <div
        x-cloak
        x-show="errorMessage"
        class="p-2.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-xs flex items-center justify-between gap-2"
    >
        <div class="flex items-center gap-1.5">
            <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 shrink-0"></i>
            <span x-text="errorMessage"></span>
        </div>
        <button type="button" @click="errorMessage = ''" class="text-amber-500 hover:text-amber-800 text-xs font-bold">✕</button>
    </div>

    <!-- Actuators Stack (4 Rows) -->
    <div class="space-y-2">
        <!-- Row 1: Pompa Irigasi -->
        <div
            @if($canControl) data-relay-clickable="true" @endif
            @click="toggleRelay('irrigationPump')"
            class="flex items-center justify-between p-2.5 rounded-xl border border-slate-100 bg-slate-50/60 {{ $canControl ? 'cursor-pointer hover:bg-slate-100/80 transition-colors' : 'cursor-not-allowed opacity-75' }}"
        >
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-white border border-slate-200/80 flex items-center justify-center text-slate-600">
                    <i data-lucide="droplets" class="w-3.5 h-3.5"></i>
                </div>
                <span class="text-xs font-medium text-slate-800">Pompa irigasi</span>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="text-[10px] font-mono font-bold"
                    :class="relays.irrigationPump ? 'text-emerald-700' : 'text-slate-400'"
                    x-text="relays.irrigationPump ? 'ON' : 'OFF'"
                >OFF</span>
                <div
                    class="w-9 h-5 rounded-full transition-colors relative flex items-center p-0.5"
                    :class="relays.irrigationPump ? 'bg-emerald-600' : 'bg-slate-200'"
                >
                    <div
                        class="w-4 h-4 rounded-full bg-white shadow-2xs transition-transform transform"
                        :class="relays.irrigationPump ? 'translate-x-4' : 'translate-x-0'"
                    ></div>
                </div>
            </div>
        </div>

        <!-- Row 2: Valve Fertigasi -->
        <div
            @if($canControl) data-relay-clickable="true" @endif
            @click="toggleRelay('fertigationValve')"
            class="flex items-center justify-between p-2.5 rounded-xl border border-slate-100 bg-slate-50/60 {{ $canControl ? 'cursor-pointer hover:bg-slate-100/80 transition-colors' : 'cursor-not-allowed opacity-75' }}"
        >
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-white border border-slate-200/80 flex items-center justify-center text-slate-600">
                    <i data-lucide="flask-conical" class="w-3.5 h-3.5"></i>
                </div>
                <span class="text-xs font-medium text-slate-800">Valve fertigasi</span>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="text-[10px] font-mono font-bold"
                    :class="relays.fertigationValve ? 'text-emerald-700' : 'text-slate-400'"
                    x-text="relays.fertigationValve ? 'ON' : 'OFF'"
                >OFF</span>
                <div
                    class="w-9 h-5 rounded-full transition-colors relative flex items-center p-0.5"
                    :class="relays.fertigationValve ? 'bg-emerald-600' : 'bg-slate-200'"
                >
                    <div
                        class="w-4 h-4 rounded-full bg-white shadow-2xs transition-transform transform"
                        :class="relays.fertigationValve ? 'translate-x-4' : 'translate-x-0'"
                    ></div>
                </div>
            </div>
        </div>

        <!-- Row 3: Kipas Misting -->
        <div
            @if($canControl) data-relay-clickable="true" @endif
            @click="toggleRelay('mistingFan')"
            class="flex items-center justify-between p-2.5 rounded-xl border border-slate-100 bg-slate-50/60 {{ $canControl ? 'cursor-pointer hover:bg-slate-100/80 transition-colors' : 'cursor-not-allowed opacity-75' }}"
        >
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-white border border-slate-200/80 flex items-center justify-center text-slate-600">
                    <i data-lucide="wind" class="w-3.5 h-3.5"></i>
                </div>
                <span class="text-xs font-medium text-slate-800">Kipas misting</span>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="text-[10px] font-mono font-bold"
                    :class="relays.mistingFan ? 'text-emerald-700' : 'text-slate-400'"
                    x-text="relays.mistingFan ? 'ON' : 'OFF'"
                >OFF</span>
                <div
                    class="w-9 h-5 rounded-full transition-colors relative flex items-center p-0.5"
                    :class="relays.mistingFan ? 'bg-emerald-600' : 'bg-slate-200'"
                >
                    <div
                        class="w-4 h-4 rounded-full bg-white shadow-2xs transition-transform transform"
                        :class="relays.mistingFan ? 'translate-x-4' : 'translate-x-0'"
                    ></div>
                </div>
            </div>
        </div>

        <!-- Row 4: Grow Light -->
        <div
            @if($canControl) data-relay-clickable="true" @endif
            @click="toggleRelay('growLight')"
            class="flex items-center justify-between p-2.5 rounded-xl border border-slate-100 bg-slate-50/60 {{ $canControl ? 'cursor-pointer hover:bg-slate-100/80 transition-colors' : 'cursor-not-allowed opacity-75' }}"
        >
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-white border border-slate-200/80 flex items-center justify-center text-slate-600">
                    <i data-lucide="sun" class="w-3.5 h-3.5"></i>
                </div>
                <span class="text-xs font-medium text-slate-800">Grow light</span>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="text-[10px] font-mono font-bold"
                    :class="relays.growLight ? 'text-emerald-700' : 'text-slate-400'"
                    x-text="relays.growLight ? 'ON' : 'OFF'"
                >OFF</span>
                <div
                    class="w-9 h-5 rounded-full transition-colors relative flex items-center p-0.5"
                    :class="relays.growLight ? 'bg-emerald-600' : 'bg-slate-200'"
                >
                    <div
                        class="w-4 h-4 rounded-full bg-white shadow-2xs transition-transform transform"
                        :class="relays.growLight ? 'translate-x-4' : 'translate-x-0'"
                    ></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footnote / Offline Note -->
    <div class="pt-2 border-t border-slate-100 text-[11px] text-slate-400">
        @if(!$isOnline || !$canControl)
            <span>Kit offline. Kontrol aktif setelah kit terhubung.</span>
        @else
            <span>Mode kontrol aman. Memerlukan konfirmasi ACK hardware.</span>
        @endif
    </div>
</div>
