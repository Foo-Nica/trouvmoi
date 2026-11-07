<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeployCommandTest extends TestCase
{
    use RefreshDatabase;

    private function setAdminEnv(string $email, string $password): void
    {
        config(['trouvmoi.admin' => ['email' => $email, 'password' => $password]]);
    }

    public function test_creates_a_verified_admin_and_updates_its_password(): void
    {
        $this->setAdminEnv('nicanor@example.com', 'mot-de-passe-solide');
        $this->artisan('app:deploy')->assertSuccessful();

        $admin = User::sole();
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check('mot-de-passe-solide', $admin->password));

        $this->setAdminEnv('nicanor@example.com', 'nouveau-mot-de-passe');
        $this->artisan('app:deploy')->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertTrue(Hash::check('nouveau-mot-de-passe', $admin->fresh()->password));
    }

    public function test_refuses_a_short_password(): void
    {
        $this->setAdminEnv('nicanor@example.com', 'court');

        $this->artisan('app:deploy')->assertFailed();
        $this->assertSame(0, User::count());
    }

    public function test_without_admin_variables_only_migrates(): void
    {
        $this->artisan('app:deploy')
            ->expectsOutputToContain('aucun compte administrateur')
            ->assertSuccessful();
    }
}
