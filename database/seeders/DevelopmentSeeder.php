<?php

namespace Database\Seeders;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DevelopmentSeeder só pode executar em local ou testing.');
        }

        $password = config('development.user.password');

        if (! is_string($password) || $password === '') {
            $this->command?->warn('DEV_USER_PASSWORD ausente; usuário de desenvolvimento não foi criado.');

            return;
        }

        $user = User::query()->updateOrCreate(
            ['email' => config('development.user.email')],
            [
                'name' => config('development.user.name'),
                'email_verified_at' => now(),
                'password' => Hash::make($password),
            ],
        );

        foreach ([
            CategoryType::Income->value => ['Salário', 'Freelance', 'Investimentos', 'Outras receitas'],
            CategoryType::Expense->value => ['Alimentação', 'Moradia', 'Transporte', 'Moto', 'Saúde', 'Lazer', 'Assinaturas', 'Outras despesas'],
        ] as $type => $names) {
            foreach ($names as $name) {
                Category::query()->firstOrCreate(['user_id' => $user->id, 'type' => $type, 'name' => $name]);
            }
        }

        if (config('development.seed_demo_data')) {
            $this->call(DevelopmentFinancialSeeder::class);
        }
    }
}
