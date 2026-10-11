{{-- OGS-Abgleich: Menü&Serve-Buchung gegen Abo + An-/Abmeldungen bei uns. Erwartet $ogs, $ab. --}}
    {{-- OGS-Abgleich: Menü&Serve-Buchung gegen Abo + An-/Abmeldungen bei uns --}}
    @if ($ogs && $ogs['kinder'])
        @php
            $abweichend = collect($ogs['kinder'])->filter(fn ($k) => $k['abweichungen']);
            $ogsSumme = $abweichend->sum(fn ($k) => count($k['abweichungen']));
        @endphp
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-2">
                <div class="text-sm font-semibold text-gray-700">
                    OGS-Abgleich – {{ count($ogs['kinder']) }} Kinder mit Menü&amp;Serve-Buchung,
                    {{ $abweichend->count() }} weichen an {{ $ogsSumme }} Tagen ab
                </div>
                @if ($ogsSumme > 0)
                    <form method="POST" action="{{ route('module.schulkantine.seasons.menueserve.ogs.angleichen') }}"
                          onsubmit="return confirm(@js('Alle '.$ogsSumme.' abweichenden OGS-Tage an Menü&Serve angleichen (An-/Abmeldung je Tag)?'))">
                        @csrf
                        <input type="hidden" name="ab" value="{{ $ab->format('Y-m-d') }}">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700">Alle angleichen</button>
                    </form>
                @endif
            </div>
            <p class="px-4 py-2 text-xs text-gray-500">
                <span class="font-semibold text-green-700">+</span> = in Menü&amp;Serve gebucht, isst bei uns nicht → wird angemeldet ·
                <span class="font-semibold text-red-700">−</span> = isst bei uns laut Abo, in Menü&amp;Serve nicht gebucht → wird abgemeldet ·
                <span class="text-gray-400">·</span> = stimmt überein.
            </p>
            @if ($ogsSumme > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                                <th class="px-4 py-2 font-medium">Kind</th>
                                <th class="px-3 py-2 font-medium">bei uns</th>
                                @foreach ($ogs['tage'] as $t)
                                    <th class="px-2 py-2 text-center font-medium">{{ \Illuminate\Support\Carbon::parse($t)->locale('de')->isoFormat('dd DD.MM.') }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($abweichend as $k)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-1.5 font-medium text-gray-900">{{ $k['user']->name }}</td>
                                    <td class="whitespace-nowrap px-3 py-1.5 text-xs text-gray-500">{{ $k['abo'] }}</td>
                                    @foreach ($ogs['tage'] as $t)
                                        @php $a = $k['abweichungen'][$t] ?? null; @endphp
                                        <td class="px-2 py-1.5 text-center font-semibold {{ $a === 'anmelden' ? 'text-green-700' : ($a ? 'text-red-700' : 'text-gray-300') }}"
                                            title="{{ $a === 'anmelden' ? 'wird angemeldet' : ($a ? 'wird abgemeldet' : 'stimmt') }}">{{ $a === 'anmelden' ? '+' : ($a ? '−' : '·') }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-gray-200 bg-gray-50">
                                <td colspan="2" class="px-4 py-1.5 text-xs text-gray-500">je Tag</td>
                                @foreach ($ogs['tage'] as $t)
                                    @php $nTag = $abweichend->filter(fn ($k) => isset($k['abweichungen'][$t]))->count(); @endphp
                                    <td class="px-2 py-1.5 text-center">
                                        @if ($nTag > 0)
                                            <form method="POST" action="{{ route('module.schulkantine.seasons.menueserve.ogs.angleichen') }}"
                                                  onsubmit="return confirm(@js($nTag.' OGS-Kinder am '.\Illuminate\Support\Carbon::parse($t)->format('d.m.Y').' angleichen?'))">
                                                @csrf
                                                <input type="hidden" name="ab" value="{{ $ab->format('Y-m-d') }}">
                                                <input type="hidden" name="tag" value="{{ $t }}">
                                                <button type="submit" class="rounded-md border border-sky-300 bg-white px-1.5 py-0.5 text-xs font-medium text-sky-800 hover:bg-sky-50" title="Diesen Tag angleichen">{{ $nTag }}</button>
                                            </form>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
            @if ($ogs['ohneBuchung'])
                <div class="border-t border-gray-100 px-4 py-2 text-xs text-gray-500">
                    Mit Abo bei uns, aber in Menü&amp;Serve im Zeitraum nichts gebucht (bleibt unverändert):
                    {{ collect($ogs['ohneBuchung'])->map(fn ($o) => $o['user']->name.' ('.$o['abo'].')')->implode(', ') }}
                </div>
            @endif
        </div>
    @endif

