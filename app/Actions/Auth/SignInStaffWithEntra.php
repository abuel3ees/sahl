<?php

namespace App\Actions\Auth;

use App\Enums\UserType;
use App\Models\GovEntity;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Turns a verified Entra ID sign-in into a Sahl employee.
 *
 * Entra is the source of truth for who is a reviewer: the app role `{ENTITY_CODE}.Reviewer`
 * (e.g. `MOI.Reviewer`) in the ID token decides which ministry the person works for.
 * The user row is created on first sign-in and refreshed on every sign-in after that.
 */
class SignInStaffWithEntra
{
    public const REVIEWER_ROLE_SUFFIX = '.Reviewer';

    /**
     * @return User|null The employee, or null when the token carries no reviewer role for a known ministry.
     */
    public function handle(SocialiteUser $entraUser): ?User
    {
        $govEntity = $this->govEntityFromRoles($entraUser->getRaw()['roles'] ?? []);

        if ($govEntity === null) {
            return null;
        }

        return User::updateOrCreate(
            ['entra_object_id' => $entraUser->getId()],
            [
                'type' => UserType::Employee,
                'gov_entity_id' => $govEntity->id,
                'name' => $entraUser->getName(),
                'email' => $entraUser->getEmail(),
            ],
        );
    }

    /**
     * @param  array<int, string>  $roles
     */
    private function govEntityFromRoles(array $roles): ?GovEntity
    {
        $codes = collect($roles)
            ->filter(fn (string $role): bool => Str::endsWith($role, self::REVIEWER_ROLE_SUFFIX))
            ->map(fn (string $role): string => Str::beforeLast($role, self::REVIEWER_ROLE_SUFFIX));

        return $codes->isEmpty() ? null : GovEntity::whereIn('code', $codes)->orderBy('id')->first();
    }
}
