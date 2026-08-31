<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ein Sparmenü wird beim Bewerten in seine Bestandteile zerlegt: der Esser
 * bewertet jedes Bestandteil-Gericht einzeln, nicht „das Sparmenü". Damit gibt
 * es je Ausgabe (Serving) mehrere Bewertungen – die Eindeutigkeit wandert von
 * serving_id auf das Paar (serving_id, dish_id).
 *
 * Alte Ganz-Bündel-Bewertungen (dish_id = Sparmenü) werden entfernt, da sie
 * nicht mehr vergeben werden können und den Küchen-Report verfälschen würden.
 *
 * ⚠️ MySQL: serving_id trägt einen Fremdschlüssel und braucht durchgehend einen
 * Index. Der einspaltige Unique darf deshalb erst fallen, NACHDEM der neue
 * zusammengesetzte Unique existiert (dessen erste Spalte serving_id die
 * FK-Anforderung deckt). Reihenfolge daher: erst anlegen, dann droppen.
 */
return new class extends Migration
{
    private const SINGLE = 'kantine_meal_ratings_serving_id_unique';

    private const COMPOSITE = 'kantine_meal_ratings_serving_id_dish_id_unique';

    public function up(): void
    {
        // 1) Alte Bewertungen auf Sparmenüs selbst löschen (jetzt je Bestandteil).
        if (Schema::hasTable('kantine_meal_ratings') && Schema::hasTable('kantine_dish_components')) {
            DB::table('kantine_meal_ratings')
                ->whereIn('dish_id', fn ($q) => $q->from('kantine_dish_components')->select('bundle_dish_id'))
                ->delete();
        }

        // 2) Neuen zusammengesetzten Unique anlegen (deckt zugleich den FK-Index).
        if (! $this->indexExists(self::COMPOSITE)) {
            Schema::table('kantine_meal_ratings', function (Blueprint $table) {
                $table->unique(['serving_id', 'dish_id']);
            });
        }

        // 3) Erst jetzt den alten einspaltigen Unique entfernen.
        if ($this->indexExists(self::SINGLE)) {
            Schema::table('kantine_meal_ratings', function (Blueprint $table) {
                $table->dropUnique(self::SINGLE);
            });
        }
    }

    public function down(): void
    {
        // Rückweg spiegelbildlich: erst den einspaltigen Unique wiederherstellen
        // (FK-Index), dann den zusammengesetzten entfernen.
        if (! $this->indexExists(self::SINGLE)) {
            Schema::table('kantine_meal_ratings', function (Blueprint $table) {
                $table->unique('serving_id');
            });
        }

        if ($this->indexExists(self::COMPOSITE)) {
            Schema::table('kantine_meal_ratings', function (Blueprint $table) {
                $table->dropUnique(self::COMPOSITE);
            });
        }
    }

    /** Existiert der Index? Treiber-bewusst (MySQL/MariaDB, SQLite). */
    private function indexExists(string $name): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            return (bool) $connection->selectOne(
                "SELECT 1 FROM sqlite_master WHERE type = 'index' AND tbl_name = 'kantine_meal_ratings' AND name = ?",
                [$name]
            );
        }

        // MySQL / MariaDB
        return (bool) $connection->selectOne(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            ['kantine_meal_ratings', $name]
        );
    }
};
