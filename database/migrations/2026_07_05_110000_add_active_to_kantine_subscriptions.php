<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OGS-Abo an/aus. Bisher galt „Zeile vorhanden = abonniert" – dadurch legte die
 * Auto-Provisionierung ein abbestelltes Abo sofort wieder an. Mit `active` kann
 * das Abo bewusst deaktiviert bleiben (isst dann nur an angehakten Tagen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kantine_subscriptions', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('kantine_subscriptions', function (Blueprint $table) {
            $table->dropColumn('active');
        });
    }
};
