@props([
    'alerts' => []
])

@if(!empty($alerts))
    <div class="space-y-3" id="anomalies">
        @foreach($alerts as $alert)
            <div
                x-data="{ acknowledged: false }"
                x-show="!acknowledged"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="p-4 rounded-xl border flex flex-col md:flex-row items-start md:items-center justify-between gap-4 {{ ($alert['severity'] ?? 'warning') === 'critical' ? 'bg-rose-50 border-rose-200 text-rose-900' : 'bg-amber-50 border-amber-200 text-amber-900' }}"
            >
                <div class="flex items-start gap-3">
                    <div class="p-2 rounded-lg {{ ($alert['severity'] ?? 'warning') === 'critical' ? 'bg-rose-600 text-white' : 'bg-amber-600 text-white' }} shrink-0 mt-0.5">
                        <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h4 class="font-bold text-xs">{{ $alert['title'] }}</h4>
                            <span class="text-[10px] uppercase font-mono px-2 py-0.5 rounded font-bold {{ ($alert['severity'] ?? 'warning') === 'critical' ? 'bg-rose-200 text-rose-800' : 'bg-amber-200 text-amber-800' }}">
                                Nilai: {{ $alert['value'] }} (Ambang: {{ $alert['threshold'] }})
                            </span>
                        </div>
                        <p class="text-xs text-slate-700 leading-relaxed">{{ $alert['message'] }}</p>
                        <div class="text-xs font-semibold text-emerald-800 flex items-center gap-1.5 pt-1">
                            <i data-lucide="sprout" class="w-3.5 h-3.5 text-emerald-600"></i>
                            <span>Tindakan Agronomi: {{ $alert['agronomic_action'] }}</span>
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    @click="acknowledged = true"
                    class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shrink-0 transition-colors shadow-sm"
                >
                    Tangani / Selesai
                </button>
            </div>
        @endforeach
    </div>
@endif
