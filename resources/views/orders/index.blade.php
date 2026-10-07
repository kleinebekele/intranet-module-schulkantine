<x-app-layout>
    @php
        $money = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
    @endphp

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <x-module-icon name="restaurant" class="text-2xl text-indigo-600" />
                <h1 class="text-xl font-semibold text-gray-800">Essen bestellen</h1>
            </div>
            @if ($season)
                <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-1.5 text-right">
                    <div class="text-[11px] uppercase tracking-wide text-indigo-400">Kosten im {{ $monthStart->isoFormat('MMMM YYYY') }}</div>
                    <div class="text-lg font-bold text-indigo-700" id="month-total">{{ $money($monthTotal) }}</div>
                </div>
            @endif
        </div>
    </x-slot>

    @include('schulkantine::orders._woche', [
        'routen' => [
            'woche' => 'module.schulkantine.orders.index',
            'bestellen' => 'module.schulkantine.orders.store',
            'abo' => 'module.schulkantine.orders.subscription',
        ],
        'ichId' => auth()->id(),
    ])
</x-app-layout>
