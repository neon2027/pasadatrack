<?php

namespace App\Http\Middleware;

use App\Enums\PlatformRole;
use App\Enums\TeamPermission;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'canAdminister' => $user?->isAdministrator() ?? false,
                'canManageCatalog' => $user?->isAdministrator() && $user->platform_role === PlatformRole::SuperAdmin,
                'requiresGoogleConfirmation' => now()->timestamp - $request->session()->get('auth.password_confirmed_at', 0) > 300,
            ],
            'onboarding' => [
                'needsTutorial' => $user !== null && $user->onboarding_dismissed_at === null && ! $request->is('admin/*'),
            ],
            'teamQuota' => fn () => $user?->teamQuota(),
            'teamPermissions' => function () use ($user): array {
                $role = $user?->currentTeam ? $user->teamAccessRole($user->currentTeam) : null;

                return $role?->is_owner ? TeamPermission::catalog() : ($role->permissions ?? []);
            },
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentTeam' => fn () => $user?->currentTeam ? $user->toUserTeam($user->currentTeam) : null,
            'teams' => fn () => $user?->toUserTeams(includeCurrent: true) ?? [],
        ];
    }
}
