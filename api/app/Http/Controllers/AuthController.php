<?php

namespace App\Http\Controllers;

use App\Models\StaffAccount;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function userLogin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone_e164' => ['nullable', 'string', 'max:24'],
            'phone_country' => ['nullable', 'string', 'max:8'],
            'phone_number' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $phone = $data['phone_e164'] ?? $data['phone_number'] ?? '';
        $user = User::query()->where('phone_e164', $phone)->orWhere('phone_number', $phone)->first();
        $this->ensureValidLogin($user, $data['password']);
        $user->forceFill(['last_login_at' => now()])->save();

        return $this->tokenResponse($user, 'user', $data['device_name'] ?? 'web');
    }

    public function signup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:140'],
            'phone_country' => ['required', 'string', 'max:8'],
            'phone_number' => ['required', 'string', 'max:32'],
            'phone_e164' => ['required', 'string', 'max:24', 'unique:users,phone_e164'],
            'promo_code' => ['required', 'string', 'max:80'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $agent = StaffAccount::query()
            ->where('role', 'agent')
            ->where('promo_code', strtoupper($data['promo_code']))
            ->where('active', true)
            ->firstOrFail();

        $user = User::query()->create([
            'agent_id' => $agent->id,
            'promo_code_used' => $agent->promo_code,
            'full_name' => $data['full_name'],
            'phone_country' => $data['phone_country'],
            'phone_number' => $data['phone_number'],
            'phone_e164' => $data['phone_e164'],
            'password_hash' => Hash::make($data['password']),
            'active' => true,
            'last_login_at' => now(),
        ]);

        return $this->tokenResponse($user, 'user', $data['device_name'] ?? 'web', 201);
    }

    public function staffLogin(Request $request, string $role): JsonResponse
    {
        abort_unless(in_array($role, ['agent', 'super'], true), 404);
        $data = $request->validate([
            'username' => ['required', 'string', 'max:80'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $staff = StaffAccount::query()->where('role', $role)->where('username', $data['username'])->first();
        $this->ensureValidLogin($staff, $data['password']);
        if ($staff->expires_at?->isPast()) {
            throw ValidationException::withMessages(['username' => 'This account has expired.']);
        }
        $staff->forceFill(['last_login_at' => now()])->save();

        return $this->tokenResponse($staff, $role, $data['device_name'] ?? 'web');
    }

    public function agentLogin(Request $request): JsonResponse
    {
        return $this->staffLogin($request, 'agent');
    }

    public function superLogin(Request $request): JsonResponse
    {
        return $this->staffLogin($request, 'super');
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    private function ensureValidLogin(User|StaffAccount|null $actor, string $password): void
    {
        if (!$actor || !$actor->active || !Hash::check($password, $actor->password_hash)) {
            throw ValidationException::withMessages(['credentials' => 'Invalid credentials.']);
        }
    }

    private function tokenResponse(User|StaffAccount $actor, string $role, string $device, int $status = 200): JsonResponse
    {
        $token = $actor->createToken($device, [$role])->plainTextToken;
        return response()->json(['token' => $token, 'role' => $role, 'data' => $actor], $status);
    }
}
