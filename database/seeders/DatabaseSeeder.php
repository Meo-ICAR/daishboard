<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $admin = User::firstOrCreate(
            ['email' => 'hassistosrl@gmail.com'],
            [
                'name' => 'Amministratore',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $this->call([
            CompanySeeder::class,
            MenuCategorySeeder::class,
            DashboardSeeder::class,
            DashboardWidgetSeeder::class,
            //  ChatHistorySeeder::class,
        ]);
    }
}
