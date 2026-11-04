@props([
    'value' => 0,
    'min' => 0,
    'max' => 100,
    'idealMin' => 40,
    'idealMax' => 70,
    'unit' => '',
    'status' => 'optimal'
])

@php
    $hasValue = $value !== null;
    $val = $hasValue ? (float) $value : 0.0;
    $minVal = (float) $min;
    $maxVal = (float) $max;
    $range = $maxVal > $minVal ? ($maxVal - $minVal) : 1;
    $clampedVal = max($minVal, min($maxVal, $val));
    $percent = round((($clampedVal - $minVal) / $range) * 100);

    // Hitung posisi zona hijau ideal
    $idealLeftPercent = round((($idealMin - $minVal) / $range) * 100);
    $idealWidthPercent = round((($idealMax - $idealMin) / $range) * 100);

    $dotColor = match($status) {
        'critical' => 'bg-rose-500 border-rose-200 ring-rose-100',
        'warning' => 'bg-amber-500 border-amber-200 ring-amber-100',
        default => 'bg-emerald-600 border-emerald-200 ring-emerald-100',
    };
@endphp

<div class="space-y-1.5 w-full">
    <!-- Horizontal Range Track -->
    <div class="relative w-full h-2 rounded-full bg-slate-100 overflow-visible border border-slate-200">
        <!-- Target Band (Zona Ideal Hijau Segar) -->
        <div
            class="absolute top-0 bottom-0 bg-emerald-100 rounded-full"
            style="left: {{ max(0, $idealLeftPercent) }}%; width: {{ min(100, $idealWidthPercent) }}%;"
            title="Zona Ideal: {{ $idealMin }} - {{ $idealMax }} {{ $unit }}"
        ></div>

        @if($hasValue)
            <!-- Indicator Dot Posisi Nilai Riil -->
            <div
                class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 w-3.5 h-3.5 rounded-full {{ $dotColor }} border-2 shadow-sm ring-2 transition-all duration-500 z-10"
                style="left: {{ $percent }}%;"
                title="Nilai Saat Ini: {{ $val }} {{ $unit }}"
            ></div>
        @endif
    </div>

    <!-- Scale Min - Target - Max Labels -->
    <div class="flex items-center justify-between text-[10px] font-mono text-slate-400">
        <span>{{ $min }}</span>
        <span class="text-emerald-700 font-semibold text-[10px]">{{ $idealMin }} – {{ $idealMax }} {{ $unit }}</span>
        <span>{{ $max }}</span>
    </div>
</div>
