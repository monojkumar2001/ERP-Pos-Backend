<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Notifications\PasswordResetCode;
use App\Notifications\VerifyEmailCode;
use App\Services\CompanyProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    protected int $codeTtlMinutes = 5;

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

        $code = $this->generateCode();
        $expiresAt = now()->addMinutes($this->codeTtlMinutes);

        try {
            $result = $provisioner->provision([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => $request->input('password'),
                'company' => $request->input('company'),
                'phone' => $request->input('phone'),
                'country' => $request->input('country'),
                'verification_code' => $code,
                'verification_code_expires_at' => $expiresAt,
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

        $mailSent = $this->sendVerificationMail($user, $code);

        return response()->json([
            'message' => $mailSent
                ? 'Registration successful. Check your email for the 6-digit verification code.'
                : 'Registration successful, but the verification email could not be sent. Use Resend code.',
            'requires_verification' => true,
            'email' => $user->email,
            'code_expires_in' => $this->codeTtlMinutes * 60,
            'company' => $this->companyPayload($company),
            'mail_sent' => $mailSent,
        ], 201);
    }

    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'verification_code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->email_verified_at) {
            $token = $user->createToken('erp-pos')->plainTextToken;
            $user->load(['company', 'role']);

            return response()->json([
                'message' => 'User already verified',
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $this->userPayload($user),
                'company' => $user->company ? $this->companyPayload($user->company) : null,
            ]);
        }

        if (!$user->verification_code || $user->verification_code !== $request->verification_code) {
            return response()->json(['message' => 'Invalid verification code'], 422);
        }

        if (!$user->verification_code_expires_at || now()->greaterThan($user->verification_code_expires_at)) {
            return response()->json([
                'message' => 'Verification code has expired. Please request a new code.',
                'expired' => true,
            ], 422);
        }

        $user->email_verified_at = now();
        $user->verification_code = null;
        $user->verification_code_expires_at = null;
        $user->save();

        $user->load(['company', 'role']);
        $token = $user->createToken('erp-pos')->plainTextToken;

        return response()->json([
            'message' => 'Email verified successfully. Your ERP POS workspace is ready.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
            'company' => $user->company ? $this->companyPayload($user->company) : null,
        ]);
    }

    public function resendVerification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email is already verified'], 200);
        }

        $code = $this->generateCode();
        $user->verification_code = $code;
        $user->verification_code_expires_at = now()->addMinutes($this->codeTtlMinutes);
        $user->save();

        $mailSent = $this->sendVerificationMail($user, $code);

        return response()->json([
            'message' => $mailSent
                ? 'A new verification code has been sent to your email.'
                : 'Could not send verification email. Please try again.',
            'email' => $user->email,
            'code_expires_in' => $this->codeTtlMinutes * 60,
            'mail_sent' => $mailSent,
        ], $mailSent ? 200 : 500);
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

            if (!$user->email_verified_at) {
                Auth::logout();

                return response()->json([
                    'message' => 'Please verify your email with the code we sent you.',
                    'requires_verification' => true,
                    'email' => $user->email,
                ], 403);
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

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        // Same response whether user exists or not (avoid email enumeration)
        if (!$user) {
            return response()->json([
                'message' => 'If an account exists for this email, a reset code has been sent.',
                'email' => $request->email,
                'code_expires_in' => $this->codeTtlMinutes * 60,
            ]);
        }

        $code = $this->generateCode();
        $user->password_reset_code = $code;
        $user->password_reset_expires_at = now()->addMinutes($this->codeTtlMinutes);
        $user->save();

        $mailSent = false;
        try {
            $user->notify(new PasswordResetCode($code, $this->codeTtlMinutes));
            $mailSent = true;
        } catch (\Throwable $e) {
            Log::error('Password reset mail failed: ' . $e->getMessage());
        }

        return response()->json([
            'message' => $mailSent
                ? 'If an account exists for this email, a reset code has been sent.'
                : 'Could not send reset email. Please try again.',
            'email' => $user->email,
            'code_expires_in' => $this->codeTtlMinutes * 60,
            'mail_sent' => $mailSent,
            'requires_code' => true,
        ], $mailSent || !$user ? 200 : 500);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'reset_code' => 'required|string|size:6',
            'password' => 'required|string|min:6|max:100|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if (!$user->password_reset_code || $user->password_reset_code !== $request->reset_code) {
            return response()->json(['message' => 'Invalid reset code'], 422);
        }

        if (!$user->password_reset_expires_at || now()->greaterThan($user->password_reset_expires_at)) {
            return response()->json([
                'message' => 'Reset code has expired. Please request a new code.',
                'expired' => true,
            ], 422);
        }

        $user->password = Hash::make($request->password);
        $user->password_reset_code = null;
        $user->password_reset_expires_at = null;
        // Also clear stale email verification codes
        if (!$user->email_verified_at) {
            // keep unverified; they still need email verify to login
        }
        $user->save();

        return response()->json([
            'message' => 'Password updated successfully. You can now sign in.',
        ]);
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

    protected function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    protected function sendVerificationMail(User $user, string $code): bool
    {
        try {
            $user->notify(new VerifyEmailCode($code, $this->codeTtlMinutes));
            return true;
        } catch (\Throwable $e) {
            Log::error('Verification mail failed: ' . $e->getMessage());
            return false;
        }
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
