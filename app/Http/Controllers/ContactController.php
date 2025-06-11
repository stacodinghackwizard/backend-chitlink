<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\User;

class ContactController extends Controller
{
    // Middleware to ensure only merchants can access these routes
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // Create a new contact
    public function store(Request $request)
    {
        // Check if the authenticated user is a merchant
        $merchant = Auth::guard('merchant')->user();
        if (!$merchant) {
            return response()->json(['message' => 'Unauthorized, What are you doing bro!'], Response::HTTP_UNAUTHORIZED);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone_number' => 'nullable|string',
        ]);

        // Check if the contact already exists for this merchant
        $contact = Contact::where('email', $request->email)
                         ->where('merchant_id', $merchant->id)
                         ->first();

        if ($contact) {
            // If the contact exists for this merchant, update it
            $contact->name = $request->name;
            $contact->save();
        } else {
            // Create a new contact
            $contact = Contact::create([
                'merchant_id' => $merchant->id,
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
            ]);
        }

        return response()->json(['message' => 'Contact created successfully.', 'contact' => $contact], 201);
    }

    // Get all contacts and users in a single endpoint with filtering options
    public function index(Request $request)
    {
        $merchant = Auth::guard('merchant')->user();
        if (!$merchant) {
            return response()->json(['message' => 'Unauthorized, What are you doing bro!'], Response::HTTP_UNAUTHORIZED);
        }

        // Get filter parameter (contacts, users, or all)
        $filter = $request->get('filter', 'all'); // Default to 'all'

        $combinedData = collect();
        $sequentialId = 1;

        // Get merchant's existing contact emails for checking duplicates
        $existingContactEmails = Contact::where('merchant_id', $merchant->id)
                                      ->pluck('email')
                                      ->toArray();

        // Add contacts if requested
        if ($filter === 'all' || $filter === 'contacts') {
            $contacts = Contact::where('merchant_id', $merchant->id)
                              ->orderBy('created_at', 'asc')
                              ->get(['id', 'name', 'email', 'phone_number', 'created_at', 'updated_at']);

            foreach ($contacts as $contact) {
                $combinedData->push([
                    'id' => $sequentialId,
                    'original_id' => $contact->id,
                    'name' => $contact->name,
                    'email' => $contact->email,
                    'phone_number' => $contact->phone_number, 
                    'created_at' => $contact->created_at,
                    'updated_at' => $contact->updated_at,
                    'type' => 'contact',
                    'deletable' => true,
                    'already_added' => true 
                ]);
                $sequentialId++;
            }
        }

        // Add users if requested
        if ($filter === 'all' || $filter === 'users') {
            $users = User::orderBy('created_at', 'asc')
                        ->get(['id', 'name', 'email', 'phone_number', 'created_at', 'updated_at']);

            foreach ($users as $user) {
                // Skip users that are already in contacts when showing 'all'
                $isAlreadyAdded = in_array($user->email, $existingContactEmails);
                
                if ($filter === 'all' && $isAlreadyAdded) {
                    continue; // Skip users already added when showing combined view
                }

                $combinedData->push([
                    'id' => $sequentialId,
                    'original_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'type' => 'user',
                    'deletable' => false, // Users from user table cannot be deleted
                    'already_added' => $isAlreadyAdded
                ]);
                $sequentialId++;
            }
        }

        return response()->json([
            'data' => $combinedData->values(),
            'filter' => $filter,
            'total' => $combinedData->count()
        ]);
    }

    // Method to copy users to merchant's contact list
    public function addUserToContacts(Request $request)
    {
        $merchant = Auth::guard('merchant')->user();
        if (!$merchant) {
            return response()->json(['message' => 'Unauthorized, What are you doing bro!'], Response::HTTP_UNAUTHORIZED);
        }

        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'integer|exists:users,id'
        ]);

        $addedContacts = [];
        
        foreach ($request->user_ids as $userId) {
            $user = User::find($userId);
            
            if ($user) {
                // Check if this user is already in merchant's contacts
                $existingContact = Contact::where('merchant_id', $merchant->id)
                                        ->where('email', $user->email)
                                        ->first();
                
                if (!$existingContact) {
                    // Copy user to merchant's contact list
                    $contact = Contact::create([
                        'merchant_id' => $merchant->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone_number' => $user->phone_number,
                    ]);
                    
                    $addedContacts[] = $contact;
                }
            }
        }

        return response()->json([
            'message' => count($addedContacts) . ' users added to your contacts.',
            'contacts' => $addedContacts
        ]);
    }

    // Update a contact (only works for merchant's own contacts)
    public function update(Request $request, $id)
    {
        $merchant = Auth::guard('merchant')->user();
        if (!$merchant) {
            return response()->json(['message' => 'Unauthorized, What are you doing bro!'], Response::HTTP_UNAUTHORIZED);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone_number' => 'nullable|string',
        ]);

        try {
            // Find contact by original ID and merchant
            $contact = Contact::where('id', $id)
                             ->where('merchant_id', $merchant->id)
                             ->firstOrFail();

            $contact->update($request->only('name', 'email', 'phone_number'));

            return response()->json(['message' => 'Contact updated successfully.', 'contact' => $contact]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Contact not found or unauthorized.'], Response::HTTP_NOT_FOUND);
        }
    }

    // Universal delete method that handles both contacts and prevents user deletion
    public function destroy(Request $request)
    {
        $merchant = Auth::guard('merchant')->user();
        if (!$merchant) {
            return response()->json(['message' => 'Unauthorized, What are you doing bro!'], Response::HTTP_UNAUTHORIZED);
        }

        $request->validate([
            'id' => 'required|integer',
            'type' => 'required|string|in:contact,user'
        ]);

        try {
            if ($request->type === 'contact') {
                // Delete from contacts table
                $contact = Contact::where('id', $request->id)
                                 ->where('merchant_id', $merchant->id)
                                 ->firstOrFail();
                
                $contact->delete();
                
                return response()->json(['message' => 'Contact removed from your list successfully.']);
                
            } elseif ($request->type === 'user') {
                // For users, we need to find and delete the corresponding contact record
                $user = User::findOrFail($request->id);
                
                $contact = Contact::where('merchant_id', $merchant->id)
                                 ->where('email', $user->email)
                                 ->first();
                
                if ($contact) {
                    $contact->delete();
                    return response()->json(['message' => 'User removed from your contacts successfully.']);
                } else {
                    return response()->json(['message' => 'User is not in your contacts.'], Response::HTTP_NOT_FOUND);
                }
            }
            
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Record not found or unauthorized.'], Response::HTTP_NOT_FOUND);
        }
    }

    // Delete by sequential ID with type detection
    public function destroyBySequentialId(Request $request, $sequentialId)
    {
        $merchant = Auth::guard('merchant')->user();
        if (!$merchant) {
            return response()->json(['message' => 'Unauthorized, What are you doing bro!'], Response::HTTP_UNAUTHORIZED);
        }

        // Get the current filter to determine what data we're working with
        $filter = $request->get('filter', 'all');
        
        // Rebuild the same data structure as in index() to find the correct item
        $combinedData = collect();
        $currentSequentialId = 1;

        // Get merchant's existing contact emails
        $existingContactEmails = Contact::where('merchant_id', $merchant->id)
                                      ->pluck('email')
                                      ->toArray();

        // Add contacts if they're part of the current view
        if ($filter === 'all' || $filter === 'contacts') {
            $contacts = Contact::where('merchant_id', $merchant->id)
                              ->orderBy('created_at', 'asc')
                              ->get(['id', 'name', 'email', 'phone_number', 'created_at', 'updated_at']);

            foreach ($contacts as $contact) {
                if ($currentSequentialId == $sequentialId) {
                    // Found the target - delete the contact
                    $contact->delete();
                    return response()->json(['message' => 'Contact removed from your list successfully.']);
                }
                $currentSequentialId++;
            }
        }

        // Add users if they're part of the current view
        if ($filter === 'all' || $filter === 'users') {
            $users = User::orderBy('created_at', 'asc')
                        ->get(['id', 'name', 'email', 'phone_number', 'created_at', 'updated_at']);

            foreach ($users as $user) {
                $isAlreadyAdded = in_array($user->email, $existingContactEmails);
                
                // Skip users already added when showing combined view
                if ($filter === 'all' && $isAlreadyAdded) {
                    continue;
                }

                if ($currentSequentialId == $sequentialId) {
                    // Found the target user - remove from contacts if added
                    if ($isAlreadyAdded) {
                        $contact = Contact::where('merchant_id', $merchant->id)
                                         ->where('email', $user->email)
                                         ->first();
                        if ($contact) {
                            $contact->delete();
                            return response()->json(['message' => 'User removed from your contacts successfully.']);
                        }
                    }
                    return response()->json(['message' => 'User is not in your contacts.'], Response::HTTP_NOT_FOUND);
                }
                $currentSequentialId++;
            }
        }

        return response()->json(['message' => 'Record not found.'], Response::HTTP_NOT_FOUND);
    }
}