<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Comptes et contenus de démonstration (mot de passe : password). À ne pas lancer en production. */
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Direction TRECHART',
            'email' => 'admin@trechart.test',
            'role' => UserRole::Admin,
            'business_name' => null,
            'plan' => 'studio',
        ]);

        User::factory()->create([
            'name' => 'Équipe TRECHART',
            'email' => 'equipe@trechart.test',
            'business_name' => null,
            'plan' => 'studio',
        ]);

        $this->callWith(DemoSeeder::class, ['owner' => $admin]);
    }
}
