<?php

namespace App\Models;

use App\Auth\Role;
use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable
{
    /** @use HasFactory<UsuarioFactory> */
    use HasFactory;

    protected $table = 'usuarios';

    public $timestamps = false;

    protected $fillable = ['nombre', 'email'];

    protected $hidden = ['password', 'token_version'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'token_version' => 'integer'];
    }

    public function assignedRoles(): HasMany
    {
        return $this->hasMany(UsuarioRol::class, 'usuario_id');
    }

    /** @return list<string> */
    public function currentRoles(bool $lockForUpdate = false): array
    {
        $values = $this->roleValues($lockForUpdate);
        if ($values === []) {
            $values = [$this->rol];
        }

        return array_values(array_unique(array_filter($values, fn ($value): bool => is_string($value) && Role::tryFrom($value) !== null)));
    }

    /** @return list<string> */
    public function roleValues(bool $lockForUpdate = false): array
    {
        if ($lockForUpdate) {
            $this->setRelation('assignedRoles', $this->assignedRoles()->lockForUpdate()->get());
        }

        return $this->relationLoaded('assignedRoles')
            ? $this->assignedRoles->pluck('rol')->all()
            : $this->assignedRoles()->pluck('rol')->all();
    }

    /** @param list<string> $roles */
    public function hasAnyRole(array $roles): bool
    {
        $current = $this->currentRoles();

        return in_array(Role::SuperAdmin->value, $current, true) || array_intersect($current, $roles) !== [];
    }

    /** @return array{nombre:string,email:string,rol:string,roles:list<string>} */
    public function profile(): array
    {
        $roles = $this->currentRoles();
        sort($roles);

        return ['nombre' => $this->nombre, 'email' => $this->email, 'rol' => $roles[0] ?? '', 'roles' => $roles];
    }
}
