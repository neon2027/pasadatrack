<?php

use App\Enums\TeamPermission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privilege_levels', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('team_limit');
            $table->timestamps();
        });
        foreach (['Basic' => 1, 'Plus' => 3, 'Pro' => 10] as $name => $limit) {
            DB::table('privilege_levels')->insert(['name' => $name, 'team_limit' => $limit, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::create('team_access_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name')->unique();
            $table->json('permissions');
            $table->boolean('is_owner')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });
        foreach (['owner' => TeamPermission::catalog(), 'admin' => ['team:update', 'invitation:create', 'invitation:cancel'], 'member' => []] as $key => $permissions) {
            DB::table('team_access_roles')->insert(['key' => $key, 'name' => ucfirst($key), 'permissions' => json_encode($permissions), 'is_owner' => $key === 'owner', 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->string('platform_role')->default('user');
            $table->string('account_status')->default('active');
            $table->foreignId('privilege_level_id')->default(1)->constrained('privilege_levels')->restrictOnDelete();
            $table->unsignedInteger('auth_version')->default(0);
        });
        foreach (['team_members', 'team_invitations'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('team_access_role_id')->nullable()->constrained('team_access_roles')->restrictOnDelete();
            });
            foreach (DB::table('team_access_roles')->get() as $role) {
                DB::table($name)->where('role', $role->key)->update(['team_access_role_id' => $role->id]);
            }
        }
        foreach (DB::table('teams')->where('slug', 'admin')->get() as $team) {
            $slug = 'admin-team';
            $suffix = 1;
            while (DB::table('teams')->where('slug', $slug)->exists()) {
                $slug = 'admin-team-'.($suffix++);
            }
            DB::table('teams')->where('id', $team->id)->update(['slug' => $slug]);
        }
        Schema::create('platform_security_locks', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->unsignedInteger('version')->default(0);
        });
        DB::table('platform_security_locks')->insert(['id' => 1]);
        Schema::create('platform_audit_entries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('source')->default('web');
            $table->string('action');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason');
            $table->timestamp('created_at');
            $table->index(['subject_type', 'subject_id', 'id']);
        });
        Schema::create('google_link_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('google_id');
            $table->string('token_hash', 64)->unique();
            $table->string('browser_hash', 64);
            $table->unsignedInteger('auth_version');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_link_challenges');
        Schema::dropIfExists('platform_audit_entries');
        Schema::dropIfExists('platform_security_locks');
        foreach (['team_members', 'team_invitations'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('team_access_role_id');
            });
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('privilege_level_id');
            $table->dropColumn(['platform_role', 'account_status', 'auth_version']);
        });
        Schema::dropIfExists('team_access_roles');
        Schema::dropIfExists('privilege_levels');
    }
};
