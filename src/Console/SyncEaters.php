<?php

namespace Intranet\Modules\Schulkantine\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Intranet\Modules\Schulkantine\Models\Eater;

/**
 * Legt für alle bestehenden Intranet-Benutzer die passenden Teilnehmer an
 * (einmaliges Nachziehen bzw. jederzeit wiederholbar – idempotent).
 */
class SyncEaters extends Command
{
    protected $signature = 'schulkantine:sync-eaters';

    protected $description = 'Legt für jeden Intranet-Benutzer automatisch einen Teilnehmer an (falls noch keiner existiert).';

    public function handle(): int
    {
        $created = 0;

        User::query()->orderBy('id')->each(function (User $user) use (&$created) {
            $eater = Eater::firstOrCreate(
                ['user_id' => $user->id],
                ['name' => $user->name, 'is_active' => true],
            );

            if ($eater->wasRecentlyCreated) {
                $created++;
            }
        });

        $this->info("Fertig: {$created} neue Teilnehmer aus Benutzern angelegt (insgesamt {$this->eaterCount()} mit Login).");

        return self::SUCCESS;
    }

    private function eaterCount(): int
    {
        return Eater::whereNotNull('user_id')->count();
    }
}
