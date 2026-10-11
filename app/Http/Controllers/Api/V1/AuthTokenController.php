<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IssueTokenRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

#[Group('Authentication', 'Issue and revoke API tokens.', weight: 0)]
class AuthTokenController extends Controller
{
    /**
     * Issue an access token.
     *
     * Sign in with a national ID (citizens) or work email (employees). Send the returned token as
     * `Authorization: Bearer <token>`. Local/PoC login only. On Azure this is replaced by Entra ID.
     *
     * @unauthenticated
     */
    public function store(IssueTokenRequest $request): JsonResponse
    {
        $login = $request->string('login')->trim()->toString();

        $user = User::query()
            ->where(fn ($query) => $query->where('email', $login)->orWhere('national_id', $login))
            ->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

        $token = $user->createToken($request->string('device_name')->toString());

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            /** @var string|null */
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => UserResource::make($user->load('govEntity')),
        ], 201);
    }

    /**
     * Revoke the current token (sign out).
     */
    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
