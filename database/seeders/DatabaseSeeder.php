<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        if ($email = env('ADMIN_EMAIL')) {
            $user = User::where('email', $email)->first();
            $password = env('ADMIN_PASSWORD');

            if (! $user && ! $password) {
                throw new \RuntimeException('ADMIN_PASSWORD è obbligatoria per creare l’amministratore.');
            }

            $attributes = [
                'name' => env('ADMIN_NAME', 'Amministratore Produce a Value'),
                'is_active' => true,
            ];

            if ($password) {
                $attributes['password'] = $password;
            }

            User::updateOrCreate(['email' => $email], $attributes);
        }
    }
}
