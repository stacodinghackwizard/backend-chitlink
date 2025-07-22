<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ContactsImport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response as FacadeResponse;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\HeadingRowImport;
use Illuminate\Support\Facades\Log;

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
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $profileImagePath = null;
        if ($request->hasFile('profile_image')) {
            $profileImagePath = $request->file('profile_image')->store('contact_images');
        }

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
                'profile_image' => $profileImagePath
            ]);
        }

        $contactArr = $contact->toArray();
        $contactArr['merchant_id'] = $merchant->mer_id;

        return response()->json(['message' => 'Contact created successfully.', 'contact' => $contactArr], 201);
    }

    // Get all contacts for the merchant (no users)
    public function index(Request $request)
    {
        $merchant = Auth::guard('merchant')->user();
        if (!$merchant) {
            return response()->json(['message' => 'Unauthorized, What are you doing bro!'], Response::HTTP_UNAUTHORIZED);
        }

        $perPage = 10;
        $page = $request->get('page', 1);

        $contacts = Contact::where('merchant_id', $merchant->id)
                          ->with('groups:id,name,color')
                          ->orderBy('created_at', 'asc')
                          ->get(['id', 'merchant_id', 'name', 'email', 'phone_number', 'profile_image', 'created_at', 'updated_at']);

        $combinedData = collect();
        $sequentialId = 1;
        foreach ($contacts as $contact) {
            $combinedData->push([
                'id' => $sequentialId,
                'contact_id' => $contact->id,
                'merchant_id' => $contact->merchant ? $contact->merchant->mer_id : null,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone_number' => $contact->phone_number,
                'profile_image' => $contact->profile_image_url,
                'created_at' => $contact->created_at,
                'updated_at' => $contact->updated_at,
                'type' => 'contact',
                'deletable' => true,
                'already_added' => true,
                'groups' => $contact->groups->map(function($group) {
                    return [
                        'id' => $group->id,
                        'name' => $group->name,
                        'color' => $group->color
                    ];
                })
            ]);
            $sequentialId++;
        }

        $paginated = $combinedData->forPage($page, $perPage)->values();
        $total = $combinedData->count();
        $lastPage = (int) ceil($total / $perPage);

        return response()->json([
            'data' => $paginated,
            'filter' => 'contacts',
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => (int) $page,
            'last_page' => $lastPage
        ]);
    }

    // New endpoint: Get all users (public data only)
    public function publicUsers(Request $request)
    {
        $perPage = 10;
        $page = $request->get('page', 1);
        $users = User::orderBy('created_at', 'asc')
            ->get(['id', 'user_id', 'name', 'email', 'phone_number', 'profile_image', 'created_at', 'updated_at']);
        $publicUsers = $users->map(function($user) {
            return [
                'id' => $user->id,
                'user_id' => $user->user_id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'profile_image' => $user->profile_image_url ?? null,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];
        });
        $paginated = $publicUsers->forPage($page, $perPage)->values();
        $total = $publicUsers->count();
        $lastPage = (int) ceil($total / $perPage);
        return response()->json([
            'data' => $paginated,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => (int) $page,
            'last_page' => $lastPage
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
                        'profile_image' => $user->profile_image_url,
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

            $contactArr = $contact->toArray();
            $contactArr['merchant_id'] = $merchant->mer_id;

            return response()->json(['message' => 'Contact updated successfully.', 'contact' => $contactArr]);
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

    // Bulk delete method for multiple contacts
    public function bulkDestroy(Request $request)
    {
        $merchant = Auth::guard('merchant')->user();
        if (!$merchant) {
            return response()->json(['message' => 'Unauthorized, What are you doing bro!'], Response::HTTP_UNAUTHORIZED);
        }

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.type' => 'required|string|in:contact,user'
        ]);

        $deletedCount = 0;
        $errors = [];

        DB::beginTransaction();
        
        try {
            foreach ($request->items as $item) {
                try {
                    if ($item['type'] === 'contact') {
                        // Delete from contacts table
                        $contact = Contact::where('id', $item['id'])
                                         ->where('merchant_id', $merchant->id)
                                         ->firstOrFail();
                        
                        $contact->delete();
                        $deletedCount++;
                        
                    } elseif ($item['type'] === 'user') {
                        // For users, find and delete the corresponding contact record
                        $user = User::findOrFail($item['id']);
                        
                        $contact = Contact::where('merchant_id', $merchant->id)
                                         ->where('email', $user->email)
                                         ->first();
                        
                        if ($contact) {
                            $contact->delete();
                            $deletedCount++;
                        } else {
                            $errors[] = "User {$user->name} is not in your contacts.";
                        }
                    }
                } catch (ModelNotFoundException $e) {
                    $errors[] = "Record with ID {$item['id']} not found.";
                }
            }
            
            DB::commit();
            
            $message = "$deletedCount items deleted successfully.";
            if (!empty($errors)) {
                $message .= " Errors: " . implode(', ', $errors);
            }
            
            return response()->json([
                'message' => $message,
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'An error occurred during bulk deletion.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GROUP MANAGEMENT METHODS

    // Get all groups for merchant
    public function getGroups(Request $request)
    {
        $merchant = Auth::guard('merchant')->user();
        if (!$merchant) {
            return response()->json(['message' => 'Unauthorized, What are you doing bro!'], Response::HTTP_UNAUTHORIZED);
        }

        $perPage = 10;
        $groups = ContactGroup::where('merchant_id', $merchant->id)
                             ->withCount('contacts')
                             ->with(['contacts' => function($query) {
                                 $query->select('contacts.id', 'name', 'email', 'phone_number', 'profile_image');
                             }])
                             ->orderBy('created_at', 'desc')
                             ->paginate($perPage);

        // Map merchant_id to mer_id for each group
        $groupsData = collect($groups->items())->map(function($group) {
            $groupArr = $group->toArray();
            if (isset($group->merchant) && isset($group->merchant->mer_id)) {
                $groupArr['merchant_id'] = $group->merchant->mer_id;
            }
            return $groupArr;
        });

        return response()->json([
            'data' => $groupsData,
            'total' => $groups->total(),
            'per_page' => $groups->perPage(),
            'current_page' => $groups->currentPage(),
            'last_page' => $groups->lastPage()
        ]);
    }

    // Create a new group
    public function createGroup(Request $request)
    {
        $merchant = Auth::guard('merchant')->user();
        if (!$merchant) {
            return response()->json(['message' => 'Unauthorized, What are you doing bro!'], Response::HTTP_UNAUTHORIZED);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'contact_ids' => 'nullable|array',
            'contact_ids.*' => 'integer|exists:contacts,id'
        ]);

        // Check if group name already exists for this merchant
        $existingGroup = ContactGroup::where('merchant_id', $merchant->id)
                                   ->where('name', $request->name)
                                   ->first();

        if ($existingGroup) {
            return response()->json(['message' => 'Group name already exists.'], Response::HTTP_CONFLICT);
        }

        DB::beginTransaction();
        
        try {
            // Create the group
            $group = ContactGroup::create([
                'merchant_id' => $merchant->id,
                'name' => $request->name,
                'description' => $request->description,
                'color' => $request->color ?? '#3B82F6'
            ]);

            // Add contacts to group if provided
            if ($request->has('contact_ids') && !empty($request->contact_ids)) {
                // Verify all contacts belong to this merchant
                $validContacts = Contact::where('merchant_id', $merchant->id)
                                      ->whereIn('id', $request->contact_ids)
                                      ->pluck('id')
                                      ->toArray();

                if (!empty($validContacts)) {
                    $group->contacts()->attach($validContacts);
                }
            }

            DB::commit();

            // Load the group with contacts
            $group->load(['contacts:id,name,email,phone_number,profile_image']);
            $group->loadCount('contacts');

            $groupArr = $group->toArray();
            $groupArr['merchant_id'] = $merchant->mer_id;

            return response()->json([
                'message' => 'Group created successfully.',
                'group' => $groupArr
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'An error occurred while creating the group.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Update a group
    public function updateGroup(Request $request, $id)
    {
        $merchant = Auth::guard('merchant')->user();
        if (!$merchant) {
            return response()->json(['message' => 'Unauthorized, What are you doing bro!'], Response::HTTP_UNAUTHORIZED);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/'
        ]);

        try {
            $group = ContactGroup::where('id', $id)
                                ->where('merchant_id', $merchant->id)
                                ->firstOrFail();

            // Check if new name conflicts with existing groups (excluding current group)
            $existingGroup = ContactGroup::where('merchant_id', $merchant->id)
                                       ->where('name', $request->name)
                                       ->where('id', '!=', $id)
                                       ->first();

            if ($existingGroup) {
                return response()->json(['message' => 'Group name already exists.'], Response::HTTP_CONFLICT);
            }

            $group->update($request->only('name', 'description', 'color'));

            $groupArr = $group->toArray();
            $groupArr['merchant_id'] = $merchant->mer_id;

            return response()->json([
                'message' => 'Group updated successfully.',
                'group' => $groupArr
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Group not found.'], Response::HTTP_NOT_FOUND);
        }
    }

    // Delete a group
    public function deleteGroup($id)
    {
        $merchant = Auth::guard('merchant')->user();
        if (!$merchant) {
            return response()->json(['message' => 'Unauthorized, What are you doing bro!'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $group = ContactGroup::where('id', $id)
                                ->where('merchant_id', $merchant->id)
                                ->firstOrFail();

            $group->delete(); // This will also delete the pivot table entries due to cascade

            return response()->json(['message' => 'Group deleted successfully.']);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Group not found.'], Response::HTTP_NOT_FOUND);
        }
    }

    // Add contacts to a group
    public function addContactsToGroup(Request $request, $groupId)
    {
        $merchant = Auth::guard('merchant')->user();
        if (!$merchant) {
            return response()->json(['message' => 'Unauthorized, What are you doing bro!'], Response::HTTP_UNAUTHORIZED);
        }

        $request->validate([
            'contact_ids' => 'required|array|min:1',
            'contact_ids.*' => 'integer|exists:contacts,id'
        ]);

        try {
            $group = ContactGroup::where('id', $groupId)
                                ->where('merchant_id', $merchant->id)
                                ->firstOrFail();

            // Verify all contacts belong to this merchant
            $validContacts = Contact::where('merchant_id', $merchant->id)
                                  ->whereIn('id', $request->contact_ids)
                                  ->pluck('id')
                                  ->toArray();

            if (empty($validContacts)) {
                return response()->json(['message' => 'No valid contacts found.'], Response::HTTP_BAD_REQUEST);
            }

            // Attach contacts (sync will prevent duplicates)
            $group->contacts()->syncWithoutDetaching($validContacts);

            $addedCount = count($validContacts);

            return response()->json([
                'message' => "$addedCount contacts added to group successfully.",
                'added_count' => $addedCount
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Group not found.'], Response::HTTP_NOT_FOUND);
        }
    }

    // Remove contacts from a group
    public function removeContactsFromGroup(Request $request, $groupId)
    {
        $merchant = Auth::guard('merchant')->user();
        if (!$merchant) {
            return response()->json(['message' => 'Unauthorized, What are you doing bro!'], Response::HTTP_UNAUTHORIZED);
        }

        $request->validate([
            'contact_ids' => 'required|array|min:1',
            'contact_ids.*' => 'integer'
        ]);

        try {
            $group = ContactGroup::where('id', $groupId)
                                ->where('merchant_id', $merchant->id)
                                ->firstOrFail();

            // Remove contacts from group
            $removedCount = $group->contacts()->detach($request->contact_ids);

            return response()->json([
                'message' => "$removedCount contacts removed from group successfully.",
                'removed_count' => $removedCount
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Group not found.'], Response::HTTP_NOT_FOUND);
        }
    }

    // Get contacts in a specific group
    public function getGroupContacts($groupId)
    {
        $merchant = Auth::guard('merchant')->user();
        if (!$merchant) {
            return response()->json(['message' => 'Unauthorized, What are you doing bro!'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $group = ContactGroup::where('id', $groupId)
                                ->where('merchant_id', $merchant->id)
                                ->with(['contacts' => function($query) {
                                    $query->select(
                                        'contacts.id',
                                        'contacts.name',
                                        'contacts.email',
                                        'contacts.phone_number',
                                        'contacts.profile_image',
                                        'contacts.created_at',
                                        'contacts.updated_at'
                                    );
                                }])
                                ->firstOrFail();

            $groupArr = $group->toArray();
            $groupArr['merchant_id'] = $merchant->mer_id;

            // Add contact_id or user_id to each contact in the response
            $contacts = $group->contacts->map(function($contact) {
                $contactArr = $contact->toArray();
                $contactArr['contact_id'] = $contact->id;
                // If you ever support user contacts, add user_id here as well
                return $contactArr;
            });

            return response()->json([
                'group' => $groupArr,
                'contacts' => $contacts,
                'total' => $contacts->count()
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Group not found.'], Response::HTTP_NOT_FOUND);
        }
    }

    public function importExcel(Request $request)
    {
        $merchant = Auth::guard('merchant')->user();
        if (!$merchant) {
            return response()->json(['message' => 'Unauthorized, What are you doing bro!'], 401);
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        // Validate headers
        $requiredHeaders = ['name', 'email', 'phone_number', 'profile_image'];
        $headings = (new HeadingRowImport)->toArray($request->file('file'))[0][0];
        Log::info('Excel Headings:', $headings);

        $normalizedHeadings = array_map(function($header) {
            return strtolower(trim($header));
        }, $headings);

        $missingHeaders = array_diff($requiredHeaders, $normalizedHeadings);
        if (!empty($missingHeaders)) {
            return response()->json([
                'message' => 'Invalid or missing headers.',
                'missing_headers' => $missingHeaders,
                'received_headers' => $normalizedHeadings
            ], 422);
        }

        $import = new ContactsImport;
        try {
            Excel::import($import, $request->file('file'));

            $failures = $import->failures();
            if (count($failures) > 0) {
                return response()->json([
                    'message' => 'Some rows failed to import.',
                    'failures' => $failures
                ], 422);
            }

            return response()->json(['message' => 'Contacts imported successfully.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Download a sample Excel file for contact import
     */
    public function downloadSampleExcel()
    {
        $merchant = Auth::guard('merchant')->user();
        if (!$merchant) {
            return response()->json(['message' => 'Unauthorized, What are you doing bro!'], 401);
        }

        $sampleData = [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'phone_number' => '08012345678', 'profile_image' => 'https://example.com/images/john.jpg'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'phone_number' => '08087654321', 'profile_image' => 'https://example.com/images/jane.png'],
        ];

        $fileName = 'sample_contacts_import.xlsx';
        return Excel::download(new \App\Exports\SampleContactsExport($sampleData), $fileName);
    }

    /**
     * Search contacts by name or email
     */
    public function searchContacts(Request $request)
    {
        $merchant = Auth::guard('merchant')->user();
        if (!$merchant) {
            return response()->json(['message' => 'Unauthorized, What are you doing bro!'], 401);
        }

        $request->validate([
            'query' => 'required|string|min:1',
        ]);

        $query = $request->input('query');
        $contacts = Contact::where('merchant_id', $merchant->id)
            ->where(function($q) use ($query) {
                $q->where('name', 'like', "%$query%")
                  ->orWhere('email', 'like', "%$query%")
                  ;
            })
            ->get();

        if ($contacts->isEmpty()) {
            return response()->json([
                'message' => 'No record found.',
                'data' => $contacts
            ], 404);
        }
        return response()->json([
            'message' => 'Records found.',
            'data' => $contacts
        ]);
    }

    /**
     * Search contact groups by name
     */
    public function searchContactGroups(Request $request)
    {
        $merchant = Auth::guard('merchant')->user();
        if (!$merchant) {
            return response()->json(['message' => 'Unauthorized, What are you doing bro!'], 401);
        }

        $request->validate([
            'query' => 'required|string|min:1',
        ]);

        $query = $request->input('query');
        $groups = ContactGroup::where('merchant_id', $merchant->id)
            ->where('name', 'like', "%$query%")
            ->get();

        $groupsData = $groups->map(function($group) use ($merchant) {
            $groupArr = $group->toArray();
            $groupArr['merchant_id'] = $merchant->mer_id;
            return $groupArr;
        });

        if ($groups->isEmpty()) {
            return response()->json([
                'message' => 'No record found.',
                'data' => $groupsData
            ], 404);
        }
        return response()->json([
            'message' => 'Records found.',
            'data' => $groupsData
        ]);
    }
}