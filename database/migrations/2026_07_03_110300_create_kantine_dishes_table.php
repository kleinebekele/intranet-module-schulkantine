<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Der Gerichte-Katalog. Jedes Gericht hat einen Fixpreis (unabhängig vom Käufer)
 * und gehört zu genau einer Kategorie. Allergene/Zusatzstoffe/Diäten hängen über
 * eigene Verknüpfungstabellen daran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_dishes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('kantine_categories')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 6, 2)->default(0); // Fixpreis in Euro
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_dishes');
    }
};
