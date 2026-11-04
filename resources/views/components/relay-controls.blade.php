@props([
    'deviceId' => null,
    'relays' => [
        'irrigationPump' => false,
        'fertigationValve' => false,
        'mistingFan' => true,
        'growLight' => true,
    ],
    'canControl' => true
])

<div
    class="p-5 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4"
    x-data="{
        relays: {{ json_encode($relays) }},
        loadingKey: null,
        toggleRelay(key) {
            if (!{{ $canControl ? 'true' : 'false' }}) return;
            this.loadingKey = key;
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
            .then(res => res.json())
            .then(data => {
                this.loadingKey = null;
                this.relays[key] = targetState;
            })
            .catch(() => {
                this.loadingKey = null;
                // Optimistic fallback for training simulation
                this.relays[key] = targetState;
            });
        }
    }"
>
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center">
                <i data-lucide="power" class="w-4 h-4"></i>
            </div>
            <div>
                <h4 class="text-xs font-bold text-slate-900">Aktuator Trainer Kit 4-Kanal</h4>
                <p class="text-[10px] text-slate-500">Kontrol Relai Otomasi Irigasi & Fertigasi Meja Praktik</p>
            </div>
        </div>
        <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-slate-100 text-slate-600 font-semibold uppercase">
            {{ $canControl ? 'Aktif Terhubung' : 'Mode Monitor' }}
        </span>
    </div>

    <!-- Actuators Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <!-- Relay 1: Pompa Irigasi Tetes -->
        <div
            @click="toggleRelay('irrigationPump')"
            :class="relays.irrigationPump ? 'bg-emerald-50 border-emerald-300' : 'bg-slate-50 border-slate-200'"
            class="p-3.5 rounded-lg border cursor-pointer hover:border-emerald-400 transition-all flex flex-col justify-between space-y-3"
        >
            <div class="flex items-center justify-between">
                <i data-lucide="droplets" :class="relays.irrigationPump ? 'text-emerald-600' : 'text-slate-400'" class="w-4 h-4"></i>
                <span
                    :class="relays.irrigationPump ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-600'"
                    class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded font-mono"
                    x-text="relays.irrigationPump ? 'ON' : 'OFF'"
                ></span>
            </div>
            <div>
                <span class="text-xs font-bold text-slate-800 block">Pompa Irigasi</span>
                <span class="text-[10px] text-slate-500">Drip Substrat</span>
            </div>
        </div>

        <!-- Relay 2: Valve Solenoid Fertigasi -->
        <div
            @click="toggleRelay('fertigationValve')"
            :class="relays.fertigationValve ? 'bg-emerald-50 border-emerald-300' : 'bg-slate-50 border-slate-200'"
            class="p-3.5 rounded-lg border cursor-pointer hover:border-emerald-400 transition-all flex flex-col justify-between space-y-3"
        >
            <div class="flex items-center justify-between">
                <i data-lucide="flask-conical" :class="relays.fertigationValve ? 'text-emerald-600' : 'text-slate-400'" class="w-4 h-4"></i>
                <span
                    :class="relays.fertigationValve ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-600'"
                    class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded font-mono"
                    x-text="relays.fertigationValve ? 'ON' : 'OFF'"
                ></span>
            </div>
            <div>
                <span class="text-xs font-bold text-slate-800 block">Valve Fertigasi</span>
                <span class="text-[10px] text-slate-500">AB-Mix Injeksi</span>
            </div>
        </div>

        <!-- Relay 3: Kipas Misting -->
        <div
            @click="toggleRelay('mistingFan')"
            :class="relays.mistingFan ? 'bg-emerald-50 border-emerald-300' : 'bg-slate-50 border-slate-200'"
            class="p-3.5 rounded-lg border cursor-pointer hover:border-emerald-400 transition-all flex flex-col justify-between space-y-3"
        >
            <div class="flex items-center justify-between">
                <i data-lucide="fan" :class="relays.mistingFan ? 'text-emerald-600' : 'text-slate-400'" class="w-4 h-4"></i>
                <span
                    :class="relays.mistingFan ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-600'"
                    class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded font-mono"
                    x-text="relays.mistingFan ? 'ON' : 'OFF'"
                ></span>
            </div>
            <div>
                <span class="text-xs font-bold text-slate-800 block">Kipas Misting</span>
                <span class="text-[10px] text-slate-500">Iklim Mikro</span>
            </div>
        </div>

        <!-- Relay 4: Grow Light Spektrum -->
        <div
            @click="toggleRelay('growLight')"
            :class="relays.growLight ? 'bg-emerald-50 border-emerald-300' : 'bg-slate-50 border-slate-200'"
            class="p-3.5 rounded-lg border cursor-pointer hover:border-emerald-400 transition-all flex flex-col justify-between space-y-3"
        >
            <div class="flex items-center justify-between">
                <i data-lucide="sun" :class="relays.growLight ? 'text-emerald-600' : 'text-slate-400'" class="w-4 h-4"></i>
                <span
                    :class="relays.growLight ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-600'"
                    class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded font-mono"
                    x-text="relays.growLight ? 'ON' : 'OFF'"
                ></span>
            </div>
            <div>
                <span class="text-xs font-bold text-slate-800 block">Grow Light</span>
                <span class="text-[10px] text-slate-500">Fotosintesis</span>
            </div>
        </div>
    </div>
</div>
