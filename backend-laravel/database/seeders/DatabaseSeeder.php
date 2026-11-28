<?php

namespace Database\Seeders;

use App\Auth\Role;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Role::cases() as $role) {
            $email = mb_strtolower($role->value).'@example.test';
            if (! Usuario::where('email', $email)->exists()) {
                Usuario::factory()->withRole($role)->create(['email' => $email]);
            }
        }
    }
}
