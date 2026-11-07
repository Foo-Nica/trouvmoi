<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Étape de mise en production : migrations, puis compte administrateur
 * défini par ADMIN_EMAIL et ADMIN_PASSWORD (créé ou mis à jour, e-mail vérifié).
 */
#[Signature('app:deploy')]
#[Description('Exécute les migrations et prépare le compte administrateur')]
class DeployApplication extends Command
{
    public function handle(): int
    {
        $this->call('migrate', ['--force' => true]);

        $email = config('trouvmoi.admin.email');
        $password = config('trouvmoi.admin.password');

        if (blank($email) || blank($password)) {
            $this->warn('ADMIN_EMAIL ou ADMIN_PASSWORD absent : aucun compte administrateur créé.');

            return self::SUCCESS;
        }

        if (strlen($password) < 12) {
            $this->error('ADMIN_PASSWORD doit contenir au moins 12 caractères.');

            return self::FAILURE;
        }

        $admin = User::firstOrNew(['email' => $email]);
        $admin->forceFill([
            'name' => $admin->name ?? 'Administrateur',
            'password' => $password,
            'role' => UserRole::Admin,
            'plan' => 'studio',
            'is_active' => true,
            'email_verified_at' => $admin->email_verified_at ?? now(),
        ])->save();

        $this->info("Compte administrateur prêt : {$email}");

        return self::SUCCESS;
    }
}
