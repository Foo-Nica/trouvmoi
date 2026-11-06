<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Comptes de démonstration (mot de passe : password). À ne pas lancer en production. */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Administrateur',
            'email' => 'admin@trouvmoi.test',
            'role' => UserRole::Admin,
            'business_name' => null,
        ]);

        User::factory()->create([
            'name' => 'Photographe Démo',
            'email' => 'photo@trouvmoi.test',
            'business_name' => 'Studio Lumière',
            'plan' => 'pro',
        ]);
    }
}
