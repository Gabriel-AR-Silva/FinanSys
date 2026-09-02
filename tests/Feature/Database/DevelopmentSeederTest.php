<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Database\Seeders\DevelopmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DevelopmentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_idempotent_and_hashes_the_environment_password(): void
    {
        Config::set('development.user.email', 'dev@finansys.test');
        Config::set('development.user.password', 'segredo-local');
        Config::set('development.user.name', 'Dev FinanSys');

        $this->seed(DevelopmentSeeder::class);
        $this->seed(DevelopmentSeeder::class);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('categories', 12);
        $this->assertDatabaseHas('categories', ['name' => 'Moto', 'type' => 'expense']);
        $this->assertDatabaseHas('categories', ['name' => 'Salário', 'type' => 'income']);
        $user = User::query()->sole();
        $this->assertTrue(Hash::check('segredo-local', $user->password));
        $this->assertNotSame('segredo-local', $user->password);
    }
}
