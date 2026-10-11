<?php

namespace App\Models;

use Database\Factories\PrivilegeLevelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property int $team_limit
 */
#[Fillable(['name', 'team_limit'])]
class PrivilegeLevel extends Model
{
    /** @use HasFactory<PrivilegeLevelFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['team_limit' => 'integer'];
    }
}
