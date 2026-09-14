<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function updateUser(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            'about' => 'nullable|string',
            'city' => 'nullable|string|max:120',
            'region' => 'nullable|string|max:120',
            'Region' => 'nullable|string|max:120',
            'country' => 'nullable|string|max:120',
            'ip' => 'nullable|string|max:64',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->address = $validated['address'] ?? $user->address;
        $user->phone = $validated['phone'] ?? $user->phone;
        $user->about = $validated['about'] ?? $user->about;
        $user->city = $validated['city'] ?? $user->city;
        $user->region = $validated['region'] ?? ($validated['Region'] ?? $user->region);
        $user->country = $validated['country'] ?? $user->country;
        $user->ip = $validated['ip'] ?? $user->ip;
        $user->save();

        if ($request->file('image')) {
            $image = $request->file('image');
            $userImage = uniqid('user_', true) . '.' . $image->extension();
            $dir = public_path('assets/images/user');

            if (!File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            if ($user->image && File::exists(public_path($user->image))) {
                File::delete(public_path($user->image));
            }

            $image->move($dir, $userImage);
            $user->update(['image' => 'assets/images/user/' . $userImage]);
        }

        $user->load(['company', 'role']);

        return ApiResponse::success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'city' => $user->city,
                'region' => $user->region,
                'country' => $user->country,
                'image' => $user->image,
                'company_id' => $user->company_id,
                'is_owner' => (bool) $user->is_owner,
                'role' => $user->role?->name,
            ],
        ], 'User profile updated successfully');
    }
}
