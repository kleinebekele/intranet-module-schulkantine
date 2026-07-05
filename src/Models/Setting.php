<?php

namespace Intranet\Modules\Schulkantine\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Globale Kantinen-Einstellungen (Singleton). Über Setting::current() abrufen –
 * legt die Zeile beim ersten Zugriff mit den Standardwerten an.
 */
class Setting extends Model
{
    protected $table = 'kantine_settings';

    protected $fillable = [
        'order_deadline_time',
        'cancel_deadline_time',
        'release_lead_weeks',
    ];

    protected function casts(): array
    {
        return [
            'release_lead_weeks' => 'integer',
        ];
    }

    /** Die eine Einstellungs-Zeile (bei Bedarf mit Standardwerten angelegt). */
    public static function current(): self
    {
        // Defaults explizit angeben, damit die Instanz auch beim erstmaligen
        // Anlegen sofort die Standardwerte trägt (nicht nur in der DB).
        return static::firstOrCreate([], [
            'order_deadline_time' => '14:00',
            'cancel_deadline_time' => '09:00',
            'release_lead_weeks' => 2,
        ]);
    }
}
