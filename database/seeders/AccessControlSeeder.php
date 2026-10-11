<?php

namespace Database\Seeders;

use App\Enums\TeamPermission;
use App\Models\PrivilegeLevel;
use App\Models\TeamAccessRole;
use Illuminate\Database\Seeder;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([1 => ['Basic', 1], 2 => ['Plus', 3], 3 => ['Pro', 10]] as $id => [$name, $limit]) {
            PrivilegeLevel::query()->firstOrCreate(['id' => $id], ['name' => $name, 'team_limit' => $limit]);
        }
        foreach (['owner' => TeamPermission::catalog(), 'admin' => ['team:update', 'invitation:create', 'invitation:cancel'], 'member' => []] as $key => $permissions) {
            TeamAccessRole::query()->firstOrCreate(['key' => $key], ['name' => ucfirst($key), 'permissions' => $permissions, 'is_owner' => $key === 'owner']);
        }
    }
}
