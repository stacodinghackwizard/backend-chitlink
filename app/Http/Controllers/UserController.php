<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Merchant;

class UserController extends Controller
{
    // User Registration
    public function register(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Create the user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json(['user' => $user], 201);
    }

    // User Login
    // public function login(Request $request)
    // {
    //     // Validate the request
    //     $validator = Validator::make($request->all(), [
    //         'email' => 'required|string|email',
    //         'password' => 'required|string',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json($validator->errors(), 422);
    //     }

    //     // Attempt to log the user in
    //     if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
    //         $user = Auth::user();
    //         return response()->json(['user' => $user], 200);
    //     }

    //     return response()->json(['message' => 'Invalid credentials'], 401);
    // }

    // Get User Profile
    public function profile()
    {
        // Get the authenticated user
        $user = Auth::user();
        $user = User::find($user->id);

        if(!$user){
            return response()->json(['message' => 'User not found.'], 404);
        }

        if($user->user_type === 'merchant'){
            return response()->json(['message' => 'Merchants cannot access user details.'], 403);
        }

        // Return user details
        return response()->json([
            'message' => 'User profile retrieved successfully.',
            'user' => $user,
        ], 200);

        // Check if the user is authenticated
        // if (!$user) {
        //     return response()->json(['message' => 'User not authenticated.'], 401);
        // }

        // // Ensure that only users can access this endpoint
        // if ($user->user_type === 'merchant') {
        //     return response()->json(['message' => 'Merchants cannot access user details.'], 403);
        // }

        // // Return user details
        // return response()->json([
        //     'message' => 'User profile retrieved successfully.',
        //     'user' => $user,
        // ], 200);
    }

    // Update User Profile
    public function updateProfile(Request $request)
    {
        // Get the authenticated user
        $user = Auth::user();
        $user = User::find($user->id);


        // Check if the user is authenticated
        if (!$user) {
            return response()->json(['message' => 'User not authenticated.'], 401);
        }

        // Validate the request
        $request->validate([
            'email' => 'sometimes|email',
            'phone_number' => 'sometimes|string',
            'password' => 'sometimes|string|min:8'
        ]);

        
        
        $user->update($request->only('email', 'phone_number'));

        
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
            $user->save();
        }

        return response()->json(['message' => 'Profile updated successfully.', 'user' => $user], 200);
    }
}
