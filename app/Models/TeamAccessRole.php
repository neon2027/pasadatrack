<?php

namespace App\Models;

use App\Enums\TeamPermission;
use Database\Factories\TeamAccessRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property array<string> $permissions
 * @property bool $is_archived
 * @property bool $is_owner
 */
#[Fillable(['key', 'name', 'permissions', 'is_archived', 'is_owner'])]
class TeamAccessRole extends Model
{
    /** @use HasFactory<TeamAccessRoleFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['permissions' => 'array', 'is_archived' => 'boolean', 'is_owner' => 'boolean'];
    }

    public function allows(TeamPermission $permission): bool
    {
        return $this->is_owner || in_array($permission->value, $this->permissions, true);
    }
}
