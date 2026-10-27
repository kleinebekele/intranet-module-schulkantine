<?php

namespace Intranet\Modules\Schulkantine\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein NFC-Chip eines Essers. Ein Esser kann mehrere haben (eigene + Schul-Chip).
 * Die UID wird normalisiert gespeichert (Kleinbuchstaben, nur Buchstaben/Ziffern).
 *
 * Herkunft:
 *  - source 'eltern'  eigener Chip, von Eltern verwaltbar.
 *  - source 'schule'  Schul-Chip; Ausgabe/Rückgabe nur durch die Verwaltung.
 *                     In der Elternansicht nur lesbar.
 *
 * Chip-Pfand rechnet die Buchhaltung außerhalb des Intranets ab. Die alte Spalte
 * `deposit` bleibt in der Tabelle, wird aber nicht mehr beschrieben oder gelesen.
 *
 * Lebenszyklus:
 *  - lent_at      Ausgabe-Datum.
 *  - returned_at  Rückgabe-Datum; danach INAKTIV (zählt nicht mehr bei der Essensausgabe).
 *  - lost_at      Chip verloren: ebenfalls inaktiv (returned_at gesetzt).
 *
 * OGS-Kinder bekommen bewusst KEINEN Chip.
 */
class NfcChip extends Model
{
    protected $table = 'kantine_nfc_chips';

    public const SOURCE_ELTERN = 'eltern';

    public const SOURCE_SCHULE = 'schule';

    protected $fillable = [
        'uid',
        'user_id',
        'source',
        'lent_at',
        'returned_at',
        'lost_at',
    ];

    protected function casts(): array
    {
        return [
            'lent_at' => 'date',
            'returned_at' => 'date',
            'lost_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Aktiv = noch nicht zurückgegeben. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('returned_at');
    }

    public function isSchool(): bool
    {
        return $this->source === self::SOURCE_SCHULE;
    }

    public function isReturned(): bool
    {
        return $this->returned_at !== null;
    }

    /** Normalisiert eine rohe Chip-Kennung zu einem stabilen Vergleichswert. */
    public static function normalize(?string $raw): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim((string) $raw)));
    }

    /** Der aktive Chip zu einer (rohen) UID – oder null. */
    public static function activeForUid(?string $raw): ?self
    {
        $uid = self::normalize($raw);
        if ($uid === '') {
            return null;
        }

        return self::active()->where('uid', $uid)->first();
    }

    /** Der Esser hinter einer aktiven UID – oder null, wenn unbekannt/zurückgegeben. */
    public static function userForUid(?string $raw): ?User
    {
        return self::activeForUid($raw)?->user;
    }

    /**
     * Wie userForUid(), versteht zusätzlich Tastaturleser, die statt Hex die
     * DEZIMALZAHL der letzten 4 Byte tippen (HDWR: 10 Ziffern bis 4294967295).
     * Gesucht wird dann der aktive 125-kHz-Chip (10 Hex), der auf diese 8 Hex
     * endet – nur bei genau einem Treffer, sonst lieber „unbekannt".
     */
    public static function userForLeser(?string $raw): ?User
    {
        if ($user = self::userForUid($raw)) {
            return $user;
        }

        $s = trim((string) $raw);
        if (! preg_match('/^\d{10}$/', $s) || (int) $s > 0xFFFFFFFF) {
            return null;
        }

        $treffer = self::active()
            ->where('uid', 'like', '%'.sprintf('%08x', (int) $s))
            ->whereRaw('LENGTH(uid) = 10')
            ->limit(2)->get();

        return $treffer->count() === 1 ? $treffer->first()->user : null;
    }
}
