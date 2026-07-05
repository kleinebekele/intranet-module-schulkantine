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
 * Herkunft & Pfand:
 *  - source 'eltern'  eigener Chip, kein Pfand, von Eltern verwaltbar.
 *  - source 'schule'  Schul-Chip → Pfand (Standard 5,00 €); Ausgabe/Rückgabe nur
 *                     durch die Verwaltung. In der Elternansicht nur lesbar.
 *
 * Lebenszyklus (für die Abrechnung):
 *  - lent_at      Ausgabe-Datum (Pfand fällt in diesem Monat an).
 *  - returned_at  Rückgabe-Datum (Rückgabe in diesem Monat); danach INAKTIV
 *                 (zählt nicht mehr bei der Essensausgabe).
 *
 * OGS-Kinder bekommen bewusst KEINEN Chip.
 */
class NfcChip extends Model
{
    protected $table = 'kantine_nfc_chips';

    public const SOURCE_ELTERN = 'eltern';

    public const SOURCE_SCHULE = 'schule';

    /** Standard-Pfand für einen Schul-Chip. */
    public const SCHULE_DEPOSIT = 5.00;

    protected $fillable = [
        'uid',
        'user_id',
        'source',
        'deposit',
        'lent_at',
        'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'deposit' => 'decimal:2',
            'lent_at' => 'date',
            'returned_at' => 'date',
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
}
