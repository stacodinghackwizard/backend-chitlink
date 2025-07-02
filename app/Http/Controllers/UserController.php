<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Merchant;
use Illuminate\Support\Facades\Log;

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

   

    // Get User Profile
    public function profile()
    {
    
        $user = Auth::user();
        $user = User::find($user->id);

        if(!$user){
            return response()->json(['message' => 'User not found.'], 404);
        }

        if($user->user_type === 'merchant'){
            return response()->json(['message' => 'Merchants cannot access user details.'], 403);
        }

        $userArr = $user->toArray();
        unset($userArr['id']);

        return response()->json([
            'message' => 'User profile retrieved successfully.',
            'user' => $userArr,
        ], 200);

       
    }

    // Update User Profile
    public function updateProfile(Request $request)
    {
       
        $user = Auth::user();
        $user = User::find($user->id);


       
        if (!$user) {
            return response()->json(['message' => 'User not authenticated.'], 401);
        }

       
        $request->validate([
            'email' => 'sometimes|email',
            'phone_number' => 'sometimes|string',
            'password' => 'sometimes|string|min:8',
            'name' => 'sometimes|string'
        ]);

        
        
        $user->update($request->only('name', 'email', 'phone_number'));

        
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
            $user->save();
        }

        return response()->json(['message' => 'Profile updated successfully.', 'user' => $user], 200);
    }

    public function updateProfileImage(Request $request)
    {
        Log::info('User profile image update request:', $request->all());
        
        $user = Auth::user();
    
        if (!$user) {
            return response()->json(['message' => 'User not authenticated.'], 401);
        }
    
        // Debug: Log all request data
        Log::info('Request data:', [
            'all_data' => $request->all(),
            'files' => $request->allFiles(),
            'has_profile_image_file' => $request->hasFile('profile_image'),
            'content_type' => $request->header('Content-Type'),
            'method' => $request->method()
        ]);
    
      
        if (!$request->hasFile('profile_image')) {
            return response()->json([
                'message' => 'No profile image file found in request.',
                'debug_info' => [
                    'files_received' => $request->allFiles(),
                    'all_input' => $request->all()
                ]
            ], 422);
        }
    
        // Validate the request
        $validator = Validator::make($request->all(), [
            'profile_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
                'debug_info' => [
                    'files_received' => $request->allFiles(),
                    'has_file' => $request->hasFile('profile_image')
                ]
            ], 422);
        }
    
        try {
            // Store the image
            $path = $request->file('profile_image')->store('profile_images');
    
         
            $user->profile_image = $path;
            $user->save();
    
            Log::info('Profile image updated successfully for user: ' . $user->id);
    
            return response()->json([
                'message' => 'Profile image updated successfully.',
                'user' => $user
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to update profile image: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update profile image: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all thrift package invites for the authenticated user
     */
    public function listThriftInvites(Request $request)
    {
        $user = Auth::user();
        $invites = $user->thriftInvites()->with('thriftPackage')->orderByDesc('created_at')->get()->map(function($invite) {
            $arr = $invite->toArray();
            unset($arr['invited_by_merchant_id']);
            return $arr;
        });
        return response()->json(['invites' => $invites]);
    }

    /**
     * List all thrift package applications for the authenticated user
     */
    public function listThriftApplications(Request $request)
    {
        $user = Auth::user();
        $merchant = Auth::guard('merchant')->user();
        if ($merchant) {
            return response()->json(['message' => 'This endpoint is only for users. Merchants cannot view thrift applications here.'], 403);
        }
        $applications = $user->thriftApplications()->with('thriftPackage')->orderByDesc('created_at')->get();
        return response()->json(['applications' => $applications]);
    }

    /**
     * List all rejected thrift packages for the authenticated user (from invites and applications)
     */
    public function listRejectedPackages(Request $request)
    {
        $user = Auth::user();
        $merchant = Auth::guard('merchant')->user();
        if ($merchant) {
            return response()->json(['message' => 'This endpoint is only for users. Merchants cannot view rejected thrift packages here.'], 403);
        }
        $rejectedInvites = $user->thriftInvites()->where('status', 'rejected')->with('thriftPackage')->get()->map(function($invite) {
            $arr = $invite->toArray();
            unset($arr['invited_by_merchant_id']);
            return $arr;
        });
        $rejectedApplications = $user->thriftApplications()->where('status', 'rejected')->with('thriftPackage')->get()->map(function($application) {
            $arr = $application->toArray();
            unset($arr['invited_by_merchant_id']);
            return $arr;
        });
        return response()->json([
            'rejected_invites' => $rejectedInvites,
            'rejected_applications' => $rejectedApplications
        ]);
    }
}
