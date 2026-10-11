<?php

namespace App\Console\Commands;

use App\Actions\Admin\ManageUsers;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProvisionAdministrator extends Command
{
    protected $signature = 'app:admin {email} {--name=} {--role=super-admin} {--promote : Permit promotion of an existing user} {--force : Explicitly confirm promotion without a prompt}';

    protected $description = 'Provision an administrator or explicitly promote an existing account; Google sign-in and two-factor setup remain required';

    public function handle(ManageUsers $users): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $role = (string) $this->option('role');
        $validator = Validator::make(['email' => $email, 'role' => $role, 'name' => $this->option('name')], ['email' => ['required', 'email', 'max:255'], 'role' => ['required', 'in:admin,super-admin'], 'name' => ['nullable', 'string', 'max:255']]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $existing = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        try {
            if ($existing) {
                if (! $this->option('promote')) {
                    $this->error('This account exists. Use --promote to explicitly change its platform role.');

                    return self::FAILURE;
                }
                if (! $this->option('force') && (! $this->input->isInteractive() || ! $this->confirm('Promote '.$email.' to '.$role.'?'))) {
                    $this->error('Promotion was not confirmed.');

                    return self::FAILURE;
                }
                $user = $users->update(null, $existing, ['platform_role' => $role, 'reason' => 'Explicit administrator promotion from server console.'], 'console');
            } else {
                $name = $this->option('name');
                if (! is_string($name) || trim($name) === '') {
                    $this->error('Provide --name when creating a new administrator.');

                    return self::FAILURE;
                }
                $user = $users->provision(null, ['name' => $name, 'email' => $email, 'platform_role' => $role, 'privilege_level_id' => 1, 'reason' => 'Administrator provisioned from server console.'], 'console');
            }
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $errors) {
                foreach ($errors as $error) {
                    $this->error($error);
                }
            }

            return self::FAILURE;
        }
        $this->info('Administrator '.$user->email.' is '.$user->platform_role->value.'. Google sign-in and confirmed two-factor authentication are required.');
        if (! $user->isActive()) {
            $this->warn('This account is suspended. A Super Admin must reactivate it before sign-in.');
        }

        return self::SUCCESS;
    }
}
