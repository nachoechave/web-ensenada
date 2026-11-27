<?php

namespace Database\Factories;

use App\Auth\JavaBcrypt;
use App\Auth\Role;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Usuario> */
class UsuarioFactory extends Factory
{
    public function definition(): array
    {
        static $hash;

        return [
            'nombre' => 'Operador sintético',
            'email' => fake()->unique()->userName().'@example.test',
            'password' => $hash ??= app(JavaBcrypt::class)->hash('Synthetic-password-1234'),
            'rol' => Role::Prensa->value,
            'activo' => true,
            'token_version' => 0,
        ];
    }

    public function withRole(Role $role): static
    {
        return $this->state(fn (): array => ['rol' => $role->value]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (Usuario $usuario) => $usuario->assignedRoles()->create(['rol' => $usuario->rol]));
    }
}
