<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if ($this->container->environment(['local', 'testing'])) {
            $this->call(DevelopmentSeeder::class);
        }
    }
}
