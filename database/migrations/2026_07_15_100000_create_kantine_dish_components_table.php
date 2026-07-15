<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bestandteile eines Bündel-Gerichts („Sparmenü"): ein Gericht kann auf mehrere
 * andere Gerichte zeigen und diese zu einem eigenen Fixpreis zusammenfassen.
 *
 * Bewusst KEIN `is_bundle`-Flag am Gericht: Ein Gericht IST ein Sparmenü, wenn es
 * Bestandteile hat (`Dish::isBundle()`). Ein Flag könnte gegen die Bestandteile
 * auseinanderlaufen – dieser Zustand ist hier gar nicht darstellbar.
 *
 * Der Preis bleibt am Bündel-Gericht selbst (`kantine_dishes.price`), damit die
 * gesamte Kette (Bestellung → `price_snapshot` → Ausgabe → Abrechnung) unverändert
 * weiterläuft: Ein Sparmenü ist für sie schlicht ein Gericht mit einem Preis.
 *
 * Verschachtelung ist NICHT vorgesehen (ein Bestandteil darf selbst kein Bündel
 * sein). Erzwungen wird das in der Validierung (`DishController`), nicht per DB –
 * SQLite/MySQL können das nicht prüfen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_dish_components', function (Blueprint $table) {
            // Das Sparmenü …
            $table->foreignId('bundle_dish_id')->constrained('kantine_dishes')->cascadeOnDelete();
            // … und ein darin enthaltenes Gericht.
            $table->foreignId('part_dish_id')->constrained('kantine_dishes')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);

            $table->primary(['bundle_dish_id', 'part_dish_id']);
            // Für die Rückrichtung „in welchen Sparmenüs steckt dieses Gericht?"
            // (Löschschutz im Katalog + Auflösen der Küchen-Mengen).
            $table->index('part_dish_id');
        });

        // Eigene Kategorie für Sparmenüs. Sie ist nötig, weil `orders.category_id`
        // NICHT NULL sein darf – NULL ist im ganzen Modul der Marker für „OGS
        // ja/nein“ (siehe kantine_orders-Migration). Ein Sparmenü ohne echte
        // Kategorie würde also als OGS-Zeile fehlgedeutet.
        //
        // `allows_walkin = false`: Ob ein Sparmenü spontan am Tresen gekauft werden
        // darf, ist eine Betriebsentscheidung – bewusst erst mal zu, änderbar im UI.
        if (! DB::table('kantine_categories')->where('name', 'Sparmenü')->exists()) {
            DB::table('kantine_categories')->insert([
                'name' => 'Sparmenü',
                'allows_walkin' => false,
                'sort_order' => (int) DB::table('kantine_categories')->max('sort_order') + 1,
                'color' => '#0d9488', // Teal – hebt sich von den bestehenden Kategorien ab
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_dish_components');
        // Die Kategorie bleibt bewusst stehen: An ihr können bereits Gerichte und
        // damit Bestellungen hängen; ein Drop würde per Fremdschlüssel Daten reißen.
    }
};
