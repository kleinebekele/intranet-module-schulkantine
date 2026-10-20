<?php

namespace Intranet\Modules\Schulkantine\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Wochenbudget eines Schülers (siehe Migration). week_start NULL = allgemein.
 */
class Budget extends Model
{
    protected $table = 'kantine_budgets';

    protected $fillable = [
        'user_id',
        'week_start',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Das eingetragene allgemeine Wochenbudget (für Spontankäufe) – oder null, wenn
     * keins eingetragen ist. Was dann gilt (Kind: 0 €, sonst kein Limit), sagt stand().
     */
    public static function weeklyAmount(int $userId): ?float
    {
        $amount = static::where('user_id', $userId)->whereNull('week_start')->value('amount');

        return $amount !== null ? (float) $amount : null;
    }

    /**
     * Das Budget, das in der Woche ab $weekStart gilt: ein Budget nur für diese Woche
     * übersteuert das allgemeine. null = kein Limit.
     */
    public static function fuerWoche(int $userId, Carbon $weekStart): ?float
    {
        return self::stand($userId, $weekStart)['betrag'];
    }

    /**
     * Ist das ein Kind (mit Eltern im Intranet)? Kinder haben ohne Eintrag 0 € Budget,
     * Erwachsene ohne Eltern-Verknüpfung kein Limit.
     */
    public static function istKind(int $userId): bool
    {
        return User::whereKey($userId)->whereHas('parents')->exists();
    }

    /**
     * Gibt es in der aktiven Saison überhaupt Budgets? Aus = kein Limit für niemanden,
     * keine Prüfung an der Ausgabe – abgerechnet wird trotzdem alles.
     */
    public static function aktiv(): bool
    {
        return Season::budgetAktivForActive();
    }

    /**
     * @return array{betrag: ?float, nurWoche: bool, allgemein: ?float}
     */
    public static function stand(int $userId, Carbon $weekStart): array
    {
        if (! self::aktiv()) {
            return ['betrag' => null, 'nurWoche' => false, 'allgemein' => null];
        }
        $allgemein = self::weeklyAmount($userId) ?? (self::istKind($userId) ? 0.0 : null);
        $woche = static::where('user_id', $userId)->whereDate('week_start', $weekStart->toDateString())->value('amount');

        return [
            'betrag' => $woche !== null ? (float) $woche : $allgemein,
            'nurWoche' => $woche !== null,
            'allgemein' => $allgemein,
        ];
    }

    /**
     * Alles, was „Essen bestellen" und das Bestell-Terminal zum Budget zeigen.
     *
     * @return array{betrag: ?float, nurWoche: bool, allgemein: ?float, genutzt: float, offen: list<object>, amAusgabeterminal: bool}
     */
    public static function ansicht(int $userId, Carbon $weekStart): array
    {
        return self::stand($userId, $weekStart) + [
            'genutzt' => self::genutzt($userId, $weekStart),
            'offen' => self::offeneUeberschreitungen($userId)->all(),
            'amAusgabeterminal' => self::amAusgabeterminal($userId),
        ];
    }

    /** Spontan Gekauftes (Extras, Nachschlag) in der Woche ab $weekStart. */
    public static function genutzt(int $userId, Carbon $weekStart): float
    {
        return (float) Serving::where('user_id', $userId)->where('spontaneous', true)
            ->whereBetween('date', [$weekStart->toDateString(), $weekStart->copy()->addDays(6)->toDateString()])
            ->sum('price_snapshot');
    }

    /** Die noch nicht von den Eltern bestätigten Überschreitungen eines Kindes. */
    public static function offeneUeberschreitungen(int $userId): \Illuminate\Support\Collection
    {
        return \Illuminate\Support\Facades\DB::table('kantine_budget_ueberschreitungen')
            ->where('user_id', $userId)->whereNull('akzeptiert_at')->orderBy('tag')->get();
    }

    /** Wollen die Eltern, dass das Budget am Ausgabe-Terminal angezeigt wird? (Standard nein) */
    public static function amAusgabeterminal(int $userId): bool
    {
        return (bool) \Illuminate\Support\Facades\DB::table('kantine_budget_optionen')
            ->where('user_id', $userId)->value('am_ausgabeterminal');
    }

    public static function amAusgabeterminalSetzen(int $userId, bool $an): void
    {
        \Illuminate\Support\Facades\DB::table('kantine_budget_optionen')->updateOrInsert(
            ['user_id' => $userId],
            ['am_ausgabeterminal' => $an, 'updated_at' => now(), 'created_at' => now()],
        );
    }
}
