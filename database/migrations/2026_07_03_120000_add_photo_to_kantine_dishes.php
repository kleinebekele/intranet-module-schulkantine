<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fügt den Gerichten ein optionales Foto hinzu (Pfad auf der public-Disk).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kantine_dishes', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('kantine_dishes', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
