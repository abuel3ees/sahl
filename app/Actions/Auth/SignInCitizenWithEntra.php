<?php

namespace App\Actions\Auth;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Turns a verified Entra External ID sign-in into a Sahl citizen.
 *
 * V1: the National ID is self-declared at sign-up (custom attribute `NationalId`, sent as the `national_id` claim).
 * Rules, until a national ID provider verifies it (V2):
 * - an account is linked by its Entra object ID; the National ID it first came with never changes;
 * - one National ID per account: if another Entra account already holds it, sign-in is refused;
 * - a citizen record that no Entra account holds yet (seeded demo citizens) is claimed by the first sign-in with its ID.
 */
class SignInCitizenWithEntra
{
    public const NATIONAL_ID_CLAIM = 'national_id';

    /**
     * @throws SignInRefused
     */
    public function handle(SocialiteUser $entraUser): User
    {
        $nationalId = (string) ($entraUser->getRaw()[self::NATIONAL_ID_CLAIM] ?? '');

        if (preg_match('/^\d{11}$/', $nationalId) !== 1) {
            throw new SignInRefused('no_national_id', "Entra user {$entraUser->getId()} has no valid national_id claim.");
        }

        return DB::transaction(function () use ($entraUser, $nationalId): User {
            $linked = User::where('entra_object_id', $entraUser->getId())->lockForUpdate()->first();

            if ($linked) {
                $linked->update(['name' => $entraUser->getName() ?? $linked->name]);

                return $linked;
            }

            $holder = User::where('national_id', $nationalId)->lockForUpdate()->first();

            if ($holder && ($holder->entra_object_id !== null || ! $holder->isCitizen())) {
                throw new SignInRefused('national_id_taken', "National ID ending {$this->lastFour($nationalId)} is already linked to another account.");
            }

            $citizen = $holder ?? new User(['type' => UserType::Citizen, 'national_id' => $nationalId]);
            $citizen->fill([
                'entra_object_id' => $entraUser->getId(),
                'name' => $entraUser->getName() ?? $citizen->name ?? 'Citizen',
                'email' => $this->usableEmail($entraUser->getEmail(), $citizen) ?? $citizen->email,
            ])->save();

            return $citizen;
        });
    }

    /**
     * The sign-in email, unless another account already uses it (emails are unique in Sahl).
     */
    private function usableEmail(?string $email, User $citizen): ?string
    {
        if ($email === null) {
            return null;
        }

        $takenByOther = User::where('email', $email)->when($citizen->exists, fn ($query) => $query->whereKeyNot($citizen->getKey()))->exists();

        return $takenByOther ? null : $email;
    }

    private function lastFour(string $nationalId): string
    {
        return substr($nationalId, -4);
    }
}
