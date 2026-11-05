<?php

namespace Intranet\Modules\Schulkantine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Intranet\Modules\Schulkantine\Support\MensaAudit;

/**
 * Ein einzelner Schließtag innerhalb einer Saison.
 */
class ClosedDay extends Model
{
    protected $table = 'kantine_closed_days';

    protected $fillable = [
        'season_id',
        'date',
        'reason',
        'type',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * Ein neuer Schließtag storniert die verbindlichen Tagesmenü-Bestellungen dieses Tages –
     * sonst würden sie trotz geschlossener Mensa abgerechnet. Wie beim Abbestellen werden die
     * Zeilen gelöscht und je Esser im Audit-Log festgehalten (erscheint im Tagesprotokoll).
     * Keine Mail an die Eltern. OGS braucht nichts: dort zählen ohnehin nur Öffnungstage.
     */
    /** Zähler für die Erfolgsmeldung: bei wie vielen Essern wurde in diesem Request storniert. */
    public static int $storniertePersonen = 0;

    protected static function booted(): void
    {
        static::created(function (ClosedDay $tag) {
            $orders = Order::with(['user', 'dish', 'menuDay'])
                ->where('season_id', $tag->season_id)
                ->whereDate('date', $tag->date->toDateString())
                ->whereNotNull('category_id')
                ->where('status', Order::STATUS_ORDERED)
                ->get();

            foreach ($orders->groupBy('user_id') as $eigene) {
                $was = $eigene->groupBy(fn (Order $o) => $o->menu_day_id ? 'm'.$o->menu_day_id : 'o'.$o->id)
                    ->map(fn ($g) => $g->first()->menuDay?->name ?? $g->first()->dish?->name ?? 'Bestellung')
                    ->implode(', ');
                $eater = $eigene->first()->user;
                $eigene->each->delete();
                self::$storniertePersonen++;
                MensaAudit::schreiben('kantine.abbestellt',
                    'Storniert wegen Schließtag'.($tag->reason ? ' ('.$tag->reason.')' : '').': '.$was.($eater ? ' für '.$eater->name : ''),
                    $eater, ['Tag' => $tag->date->format('d.m.Y')]);
            }
        });
    }

    /** Die Saison, zu der dieser Schließtag gehört. */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class, 'season_id');
    }
}
