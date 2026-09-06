<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\CompanyProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function register(Request $request, CompanyProvisioner $provisioner)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:120',
            'email' => 'required|string|email|max:190|unique:users,email',
            'password' => 'required|string|min:6|max:100',
            'company' => 'required|string|min:2|max:190',
            'phone' => 'nullable|string|max:30',
            'country' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $this->ensureBaseRoles();

        try {
            $result = $provisioner->provision([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => $request->input('password'),
                'company' => $request->input('company'),
                'phone' => $request->input('phone'),
                'country' => $request->input('country'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Could not create company workspace',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }

        /** @var User $user */
        $user = $result['user'];
        /** @var Company $company */
        $company = $result['company'];

        $token = $user->createToken('erp-pos')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful. Your ERP POS workspace is ready.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user->fresh(['company', 'role'])),
            'company' => $this->companyPayload($company),
        ], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            if (!Auth::attempt($request->only('email', 'password'))) {
                return response([
                    'message' => 'Invalid credentials!',
                ], Response::HTTP_UNAUTHORIZED);
            }

            /** @var User $user */
            $user = Auth::user();
            $user->load(['company', 'role']);

            if (!$user->status) {
                Auth::logout();
                return response()->json(['message' => 'Account is inactive'], 403);
            }

            // Auto-verify legacy accounts that never completed email verify
            if (!$user->email_verified_at) {
                $user->email_verified_at = now();
                $user->save();
            }

            $token = $user->createToken('erp-pos')->plainTextToken;

            return response([
                'message' => 'Login successful',
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $this->userPayload($user),
                'company' => $user->company ? $this->companyPayload($user->company) : null,
            ]);
        } catch (\Exception $e) {
            return response([
                'message' => 'Something went wrong. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->currentAccessToken()?->delete();
        }

        return response()->json(['message' => 'Logout successful']);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load(['company', 'role']);

        return response()->json([
            'user' => $this->userPayload($user),
            'company' => $user->company ? $this->companyPayload($user->company) : null,
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'verification_code' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->email_verified_at) {
            return response()->json(['message' => 'User already verified'], 200);
        }

        if ($user->verification_code !== $request->verification_code) {
            return response()->json(['message' => 'Invalid verification code'], 422);
        }

        $user->email_verified_at = now();
        $user->verification_code = null;
        $user->save();

        $token = $user->createToken('erp-pos')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'message' => 'User verified successfully',
            'user' => $this->userPayload($user->fresh(['company', 'role'])),
            'company' => $user->company ? $this->companyPayload($user->company) : null,
        ]);
    }

    protected function ensureBaseRoles(): void
    {
        if (!Role::query()->exists()) {
            Role::insert([
                ['name' => 'Administrator', 'slug' => 'admin', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'User', 'slug' => 'user', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    protected function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'company_id' => $user->company_id,
            'is_owner' => (bool) $user->is_owner,
            'role' => $user->role?->name,
            'email_verified_at' => $user->email_verified_at,
        ];
    }

    protected function companyPayload(Company $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'code' => $company->code,
            'currency' => $company->currency,
            'timezone' => $company->timezone,
            'trial_ends_at' => optional($company->trial_ends_at)->toIso8601String(),
            'status' => (bool) $company->status,
        ];
    }
}
