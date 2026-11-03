<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OGS-Anmeldung „an der Ausgabe" angelegt (Terminal-Suche, Nachtragen in der Übersicht)
 * statt vorbestellt. Nur diese zählen als spontan – was Eltern vorab gebucht haben, ist
 * vorbestellt, egal ob innerhalb oder außerhalb des Abos.
 *
 * Bestand: angelegt im selben Moment wie die OGS-Ausgabe (≤ 10 s davor, Terminal) oder
 * mit Audit-Eintrag „OGS-Essen nachgetragen" (Übersicht).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kantine_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('kantine_orders', 'an_ausgabe')) {
                $table->boolean('an_ausgabe')->default(false)->after('status');
            }
        });

        $orders = DB::table('kantine_orders')->whereNull('category_id')->where('status', 'bestellt')
            ->where('an_ausgabe', false)->get(['id', 'user_id', 'date', 'created_at']);
        if ($orders->isEmpty()) {
            return;
        }

        $servings = DB::table('kantine_servings')->whereNull('category_id')->where('spontaneous', false)
            ->whereIn('order_id', $orders->pluck('id'))->get(['order_id', 'created_at'])->groupBy('order_id');

        $nachgetragen = Schema::hasTable('audit_log')
            ? DB::table('audit_log')->where('aktion', 'kantine.bestellt')->where('beschreibung', 'like', 'OGS-Essen nachgetragen:%')
                ->get(['betroffener_id', 'daten'])
                ->map(fn ($a) => $a->betroffener_id.'|'.(json_decode((string) $a->daten, true)['Tag'] ?? ''))
                ->flip()
            : collect();

        $ids = [];
        foreach ($orders as $o) {
            $angelegt = $o->created_at ? Carbon::parse($o->created_at) : null;
            $imMoment = $angelegt && ($servings[$o->id] ?? collect())->contains(function ($s) use ($angelegt) {
                $ausgabe = $s->created_at ? Carbon::parse($s->created_at) : null;

                return $ausgabe && $ausgabe->gte($angelegt) && $angelegt->diffInSeconds($ausgabe) <= 10;
            });
            $tag = Carbon::parse($o->date)->format('d.m.Y');
            if ($imMoment || $nachgetragen->has($o->user_id.'|'.$tag)) {
                $ids[] = $o->id;
            }
        }
        foreach (array_chunk($ids, 500) as $teil) {
            DB::table('kantine_orders')->whereIn('id', $teil)->update(['an_ausgabe' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('kantine_orders', function (Blueprint $table) {
            if (Schema::hasColumn('kantine_orders', 'an_ausgabe')) {
                $table->dropColumn('an_ausgabe');
            }
        });
    }
};
