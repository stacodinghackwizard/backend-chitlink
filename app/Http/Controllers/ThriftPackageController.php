<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ThriftPackage;
use App\Models\ThriftContributor;
use App\Models\ThriftSlot;
use App\Models\ThriftTransaction;
use App\Models\Contact;
use Illuminate\Validation\Rule;
use App\Models\ThriftPackageInvite;
use App\Models\ThriftPackageApplication;
use App\Notifications\ThriftPackageInviteNotification;
use App\Notifications\ThriftPackageApplicationNotification;
use App\Models\User;
use App\Models\Merchant;
use Illuminate\Support\Carbon;

class ThriftPackageController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum']); // Add merchant guard/middleware as needed
    }

    // List all thrift packages for merchant or user admin
    public function index(Request $request)
    {
        $user = Auth::user();
        $merchant = Auth::guard('merchant')->user();

        if ($merchant) {
            $packages = ThriftPackage::where('merchant_id', $merchant->id)->get();
            // Prepare merchant details
            $merchantDetails = [
                'id' => $merchant->id,
                'mer_id' => $merchant->mer_id,
                'created_at' => $merchant->created_at,
                'updated_at' => $merchant->updated_at,
                'name' => $merchant->name,
                'business_name' => $merchant->business_name,
                'email' => $merchant->email,
                'phone_number' => $merchant->phone_number,
                'profile_image' => $merchant->profile_image,
            ];
            // Prepare packages array (remove embedded merchant object)
            $packagesArr = $packages->map(function($package) use ($merchant) {
                $arr = $package->toArray();
                $arr['merchant_id'] = $merchant->mer_id;
                unset($arr['merchant']);
                return $arr;
            });
            return response()->json([
                'merchant' => $merchantDetails,
                'packages' => $packagesArr,
            ]);
        } elseif ($user) {
            $packages = ThriftPackage::where(function($q) use ($user) {
                $q->where(function($q2) use ($user) {
                    $q2->where('created_by_type', 'user')->where('created_by_id', $user->id);
                })->orWhereHas('admins', function($q3) use ($user) {
                    $q3->where('users.id', $user->id);
                });
            })->get();
            // Map merchant_id to mer_id in the response
            $packages = $packages->map(function($package) {
                $arr = $package->toArray();
                if ($package->merchant && isset($package->merchant->mer_id)) {
                    $arr['merchant_id'] = $package->merchant->mer_id;
                    $arr['merchant'] = [
                        'id' => $package->merchant->id,
                        'mer_id' => $package->merchant->mer_id,
                        'created_at' => $package->merchant->created_at,
                        'updated_at' => $package->merchant->updated_at,
                        'name' => $package->merchant->name,
                        'business_name' => $package->merchant->business_name,
                        'email' => $package->merchant->email,
                        'phone_number' => $package->merchant->phone_number,
                        'profile_image' => $package->merchant->profile_image,
                    ];
                }
                return $arr;
            });
            return response()->json($packages);
        } else {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
    }

    // Create thrift package (step 1)
    public function store(Request $request)
    {
        $user = Auth::user();
        $merchant = Auth::guard('merchant')->user();

        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('thrift_packages')->where(function ($query) use ($user, $merchant) {
                    if ($merchant) {
                        return $query->where('created_by_type', 'merchant')
                                     ->where('created_by_id', $merchant->id);
                    } elseif ($user) {
                        return $query->where('created_by_type', 'user')
                                     ->where('created_by_id', $user->id);
                    }
                    return $query;
                }),
            ],
            'total_amount' => 'required|numeric|min:1',
            'duration_days' => 'required|integer|min:1',
            'slots' => 'required|integer|min:1',
        ];
        $validated = $request->validate($rules);

        if ($merchant) {
            $validated['merchant_id'] = $merchant->id;
            $validated['created_by_type'] = 'merchant';
            $validated['created_by_id'] = $merchant->id;
            $package = ThriftPackage::create($validated);
            // Do NOT add merchant as admin
        } elseif ($user) {
            $validated['merchant_id'] = null; // Explicitly set to null for user
            $validated['created_by_type'] = 'user';
            $validated['created_by_id'] = $user->id;
            $package = ThriftPackage::create($validated);
            // Only add as admin if user (not merchant)
            $package->admins()->syncWithoutDetaching([$user->id]);
        } else {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $arr = $package->toArray();
        if ($package->merchant && isset($package->merchant->mer_id)) {
            $arr['merchant_id'] = $package->merchant->mer_id;
        }
        return response()->json($arr, 201);
    }

    // Show thrift package details
    public function show($id)
    {
        try {
            $user = Auth::user();
            $merchant = Auth::guard('merchant')->user();
            if ($merchant) {
                $package = ThriftPackage::where('id', $id)->where('merchant_id', $merchant->id)->with(['contributors.contact', 'slots', 'transactions', 'merchant'])->firstOrFail();
            } elseif ($user) {
                $package = ThriftPackage::where('id', $id)
                    ->where(function($q) use ($user) {
                        $q->where(function($q2) use ($user) {
                            $q2->where('created_by_type', 'user')->where('created_by_id', $user->id);
                        })->orWhereHas('admins', function($q3) use ($user) {
                            $q3->where('users.id', $user->id);
                        });
                    })
                    ->with(['contributors.contact', 'slots', 'transactions', 'merchant'])
                    ->firstOrFail();
            } else {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
            $arr = $package->toArray();
            if ($package->merchant && isset($package->merchant->mer_id)) {
                $arr['merchant_id'] = $package->merchant->mer_id;
            
                $arr['merchant'] = [
                    'id' => $package->merchant->id,
                    'mer_id' => $package->merchant->mer_id,
                    'created_at' => $package->merchant->created_at,
                    'updated_at' => $package->merchant->updated_at,
                    'name' => $package->merchant->name,
                    'business_name' => $package->merchant->business_name,
                    'email' => $package->merchant->email,
                    'phone_number' => $package->merchant->phone_number,
                    'profile_image' => $package->merchant->profile_image,
                ];
            }

            // Map contributors by their id for quick lookup
            $contributorMap = [];
            if (isset($arr['contributors'])) {
                foreach ($arr['contributors'] as $contributor) {
                    $contributorMap[$contributor['id']] = $contributor;
                }
            }
            // Adjust slots to include user_id/contact_id
            if (isset($arr['slots'])) {
                foreach ($arr['slots'] as &$slot) {
                    $contributorId = $slot['contributor_id'];
                    if (isset($contributorMap[$contributorId])) {
                        $contributor = $contributorMap[$contributorId];
                        if (!empty($contributor['user_id'])) {
                            $slot['user_id'] = $contributor['user_id'];
                        }
                        if (!empty($contributor['contact_id'])) {
                            $slot['contact_id'] = $contributor['contact_id'];
                        }
                    }
                }
                unset($slot); // break reference
            }
            // Remove merchant_id from contact in contributors
            if (isset($arr['contributors'])) {
                foreach ($arr['contributors'] as &$contributor) {
                    if (isset($contributor['contact']) && is_array($contributor['contact'])) {
                        unset($contributor['contact']['merchant_id']);
                    }
                }
                unset($contributor); // break reference
            }

            return response()->json($arr);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Thrift package not found.'], 404);
        }
    }

    // Update T&C
    public function updateTerms(Request $request, $id)
    {
        $user = Auth::user();
        $merchant = Auth::guard('merchant')->user();
        if ($merchant) {
            $package = ThriftPackage::where('id', $id)->where('merchant_id', $merchant->id)->first();
            if (!$package) {
                return response()->json(['message' => 'You do not have permission to update this package.'], 403);
            }
        } elseif ($user) {
            $package = ThriftPackage::where('id', $id)
                ->where(function($q) use ($user) {
                    $q->where(function($q2) use ($user) {
                        $q2->where('created_by_type', 'user')->where('created_by_id', $user->id);
                    })->orWhereHas('admins', function($q3) use ($user) {
                        $q3->where('users.id', $user->id);
                    });
                })
                ->first();
            if (!$package) {
                return response()->json(['message' => 'You do not have permission to update this package.'], 403);
            }
        } else {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $request->validate(['terms_accepted' => 'required|boolean']);
        $package->terms_accepted = $request->terms_accepted;
        $package->save();
        return response()->json($package);
    }

    // Add contributors (merchant only for now)
    public function addContributors(Request $request, $id)
    {
        $user = Auth::user();
        $merchant = Auth::guard('merchant')->user();
        if ($merchant) {
            $package = ThriftPackage::where('id', $id)
                ->where('merchant_id', $merchant->id)
                ->first();
            if (!$package) {
                return response()->json(['message' => 'Thrift package not found or not accessible.'], 404);
            }
            $request->validate([
                'contributor_ids' => 'required|array|min:1',
                'contributor_ids.*' => 'integer',
            ]);
        } elseif ($user) {
            $package = ThriftPackage::where('id', $id)
                ->where(function($q) use ($user) {
                    $q->where(function($q2) use ($user) {
                        $q2->where('created_by_type', 'user')->where('created_by_id', $user->id);
                    })->orWhereHas('admins', function($q3) use ($user) {
                        $q3->where('users.id', $user->id);
                    });
                })
                ->first();
            if (!$package) {
                return response()->json(['message' => 'Thrift package not found or not accessible.'], 404);
            }
            $request->validate([
                'contributor_ids' => 'required|array|min:1',
                'contributor_ids.*' => 'integer|exists:users,id',
            ]);
        } else {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        // Block if T&C not accepted
        if (!$package->terms_accepted) {
            return response()->json([
                'message' => 'You must accept the Terms & Conditions before adding contributors.'
            ], 403);
        }
        $added = [];
        $errors = [];
        if ($merchant) {
            foreach ($request->contributor_ids as $id) {
                $userModel = \App\Models\User::find($id);
                $contactModel = \App\Models\Contact::find($id);
                if ($userModel) {
                    $added[] = ThriftContributor::firstOrCreate([
                        'thrift_package_id' => $package->id,
                        'user_id' => $id,
                    ]);
                } elseif ($contactModel) {
                    $added[] = ThriftContributor::firstOrCreate([
                        'thrift_package_id' => $package->id,
                        'contact_id' => $id,
                    ]);
                } else {
                    $errors[] = $id;
                }
            }
        } elseif ($user) {
            foreach ($request->contributor_ids as $userId) {
                $userModel = \App\Models\User::find($userId);
                if ($userModel) {
                    $added[] = ThriftContributor::firstOrCreate([
                        'thrift_package_id' => $package->id,
                        'user_id' => $userId,
                    ]);
                } else {
                    $errors[] = $userId;
                }
            }
        }
        $publicUser = function ($user) {
            if (!$user) return null;
            return [
                'user_id' => $user->user_id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'profile_image_url' => $user->profile_image_url ?? null,
            ];
        };
        $publicContact = function ($contact) {
            if (!$contact) return null;
            return [
                'user_id' => $contact->user_id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone_number' => $contact->phone_number,
                'profile_image_url' => $contact->profile_image_url ?? null,
            ];
        };
        // Transform the response to always show user_id/contact_id and user/contact public details
        $contributors = collect($added)->map(function ($contributor) use ($publicUser, $publicContact) {
            $arr = $contributor->toArray();
            // user_id and merchant_id already handled in toArray
            $arr['user'] = $publicUser($contributor->user);
            $arr['contact'] = $publicContact($contributor->contact);
            return $arr;
        });
        $response = [
            'message' => 'Contributors added successfully.',
            'contributors' => $contributors,
        ];
        if (!empty($errors)) {
            $response['invalid_contributor_ids'] = $errors;
            $response['error_message'] = 'Some contributor IDs were invalid and not added.';
        }
        return response()->json($response);
    }

    // Get contributors
    public function getContributors($id)
    {
        $user = Auth::user();
        $merchant = Auth::guard('merchant')->user();
        $package = ThriftPackage::find($id);
        if (!$package) {
            return response()->json(['message' => 'Thrift package not found.'], 404);
        }

        $hasAccess = false;
        if ($merchant && $package->merchant_id === $merchant->id) {
            $hasAccess = true;
        } elseif ($user && (
            ($package->created_by_type === 'user' && $package->created_by_id === $user->id) ||
            $package->admins()->where('users.id', $user->id)->exists()
        )) {
            $hasAccess = true;
        }

        if (!$hasAccess) {
            return response()->json(['message' => 'Forbidden: You do not have access to this thrift package.'], 403);
        }

        $contributors = $package->contributors()->with(['contact', 'user'])->get();
        $publicUser = function ($user) {
            if (!$user) return null;
            return [
                'user_id' => $user->user_id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'profile_image_url' => $user->profile_image_url ?? null,
            ];
        };
        $publicContact = function ($contact) {
            if (!$contact) return null;
            return [
                'user_id' => $contact->user_id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone_number' => $contact->phone_number,
                'profile_image_url' => $contact->profile_image_url ?? null,
            ];
        };
        $contributors = $contributors->map(function ($contributor) use ($publicUser, $publicContact) {
            $arr = $contributor->toArray();
            // user_id and merchant_id already handled in toArray
            $arr['user'] = $publicUser($contributor->user);
            $arr['contact'] = $publicContact($contributor->contact);
            return $arr;
        });
        return response()->json(['contributors' => $contributors]);
    }

    // Confirm contributors
    public function confirmContributors(Request $request, $id)
    {
        $user = Auth::user();
        $merchant = Auth::guard('merchant')->user();
        if ($merchant) {
            $package = ThriftPackage::where('id', $id)
                ->where('merchant_id', $merchant->id)
                ->first();
            if (!$package) {
                return response()->json(['message' => 'Thrift package not found or not accessible.'], 404);
            }
        } elseif ($user) {
            $package = ThriftPackage::where('id', $id)
                ->where(function($q) use ($user) {
                    $q->where(function($q2) use ($user) {
                        $q2->where('created_by_type', 'user')->where('created_by_id', $user->id);
                    })->orWhereHas('admins', function($q3) use ($user) {
                        $q3->where('users.id', $user->id);
                    });
                })
                ->first();
            if (!$package) {
                return response()->json(['message' => 'Thrift package not found or not accessible.'], 404);
            }
        } else {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $contributorIds = $request->input('contributor_ids');
        if ($contributorIds && is_array($contributorIds)) {
            // Confirm only specified contributors
            $confirmed = ThriftContributor::where('thrift_package_id', $package->id)
                ->whereIn('id', $contributorIds)
                ->update(['status' => 'confirmed']);
        } else {
            // Confirm all contributors for this package
            $confirmed = ThriftContributor::where('thrift_package_id', $package->id)
                ->update(['status' => 'confirmed']);
        }
        return response()->json([
            'message' => 'Contributors confirmed successfully.',
            'confirmed_count' => $confirmed
        ]);
    }

    // Generate slots (FIFO)
    public function generateSlots($id)
    {
        $user = Auth::user();
        $merchant = Auth::guard('merchant')->user();
        if ($merchant) {
            $package = ThriftPackage::where('id', $id)
                ->where('merchant_id', $merchant->id)
                ->with(['contributors' => function($q) {
                    $q->where('status', 'confirmed')->orderBy('created_at', 'asc');
                }])
                ->first();
            if (!$package) {
                return response()->json(['message' => 'Thrift package not found or not accessible.'], 404);
            }
        } elseif ($user) {
            $package = ThriftPackage::where('id', $id)
                ->where(function($q) use ($user) {
                    $q->where(function($q2) use ($user) {
                        $q2->where('created_by_type', 'user')->where('created_by_id', $user->id);
                    })->orWhereHas('admins', function($q3) use ($user) {
                        $q3->where('users.id', $user->id);
                    });
                })
                ->with(['contributors' => function($q) {
                    $q->where('status', 'confirmed')->orderBy('created_at', 'asc');
                }])
                ->first();
            if (!$package) {
                return response()->json(['message' => 'Thrift package not found or not accessible.'], 404);
            }
        } else {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $contributors = $package->contributors;
        $slotCount = $package->slots;
        if ($contributors->isEmpty()) {
            return response()->json(['message' => 'No confirmed contributors to assign slots.'], 400);
        }
        // Only generate as many slots as there are contributors (FIFO)
        $slotsToGenerate = min($slotCount, $contributors->count());
        // Remove existing slots to avoid duplicates
        $package->slots()->delete();
        $slots = [];
        for ($i = 0; $i < $slotsToGenerate; $i++) {
            $contributor = $contributors[$i];
            $slots[] = ThriftSlot::create([
                'thrift_package_id' => $package->id,
                'contributor_id' => $contributor->id,
                'slot_no' => str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'status' => 'pending',
            ]);
        }
        return response()->json([
            'message' => 'Slots generated successfully (FIFO, one per contributor).',
            'slots' => $slots
        ]);
    }

    // Get transactions
    public function transactions($id)
    {
        $user = Auth::user();
        $merchant = Auth::guard('merchant')->user();
        if ($merchant) {
            $package = ThriftPackage::where('id', $id)->where('merchant_id', $merchant->id)->firstOrFail();
        } elseif ($user) {
            $package = ThriftPackage::where('id', $id)
                ->where(function($q) use ($user) {
                    $q->where(function($q2) use ($user) {
                        $q2->where('created_by_type', 'user')->where('created_by_id', $user->id);
                    })->orWhereHas('admins', function($q3) use ($user) {
                        $q3->where('users.id', $user->id);
                    });
                })
                ->firstOrFail();
        } else {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $transactions = $package->transactions;
        return response()->json(['transactions' => $transactions]);
    }

    // Payout (stub)
    public function payout(Request $request, $id)
    {
        // Implement payout logic
        return response()->json(['message' => 'Payout processed (stub)']);
    }

    // Request slot (stub)
    public function requestSlot(Request $request, $id)
    {
        // Implement slot request logic
        return response()->json(['message' => 'Slot request submitted (stub)']);
    }

    // Accept slot request (stub)
    public function acceptSlotRequest($id, $slotNo)
    {
        // Implement accept logic
        return response()->json(['message' => 'Slot request accepted (stub)']);
    }

    // Decline slot request (stub)
    public function declineSlotRequest($id, $slotNo)
    {
        // Implement decline logic
        return response()->json(['message' => 'Slot request declined (stub)']);
    }

    public function addAdmin(Request $request, $id)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $thriftPackage = \App\Models\ThriftPackage::findOrFail($id);

        $merchant = Auth::guard('merchant')->user();
        $user = Auth::user();

        $isMerchantOwner = $merchant && $thriftPackage->merchant_id === $merchant->id;
        $isAdmin = $user && $thriftPackage->admins()->where('users.id', $user->id)->exists();

        if (!$isMerchantOwner && !$isAdmin) {
            return response()->json(['message' => 'Forbidden: Only the merchant or an existing admin can add another admin.'], 403);
        }

        $newAdmin = \App\Models\User::findOrFail($request->user_id);
        $thriftPackage->admins()->syncWithoutDetaching([$newAdmin->id]);

        return response()->json([
            'status' => 'success',
            'message' => 'User added as admin to this thrift package.',
            'admin_user_id' => $newAdmin->user_id,
        ]);
    }

    /**
     * Invite a user to a thrift package (merchant or admin only)
     */
    public function inviteUser(Request $request, $id)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);
        $user = Auth::user();
        $merchant = Auth::guard('merchant')->user();
        $package = ThriftPackage::findOrFail($id);

        // Only merchant owner or admin can invite
        $isOwner = $merchant && $package->merchant_id === $merchant->id;
        $isAdmin = $user && $package->admins()->where('users.id', $user->id)->exists();
        if (!$isOwner && !$isAdmin) {
            return response()->json(['message' => 'Forbidden: Only the merchant or an admin can invite.'], 403);
        }

        $invite = ThriftPackageInvite::firstOrCreate([
            'thrift_package_id' => $package->id,
            'invited_user_id' => $request->user_id,
        ], [
            'invited_by_id' => $isOwner ? $merchant->id : $user->id,
            'status' => 'pending',
        ]);

        // Notify the user
        $invitee = User::find($request->user_id);
        $invitee->notify(new ThriftPackageInviteNotification($invite));

        return response()->json(['message' => 'User invited successfully.', 'invite' => $invite]);
    }

    /**
     * User responds to an invite (accept/reject)
     */
    public function respondToInvite(Request $request, $invite_id)
    {
        $request->validate(['status' => 'required|in:accepted,rejected']);
        $user = Auth::user();
        $invite = ThriftPackageInvite::findOrFail($invite_id);
        if ($invite->invited_user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden: Not your invite.'], 403);
        }
        $invite->status = $request->status;
        $invite->responded_at = now();
        $invite->save();
        if ($request->status === 'accepted') {
            // Add as contributor if not already
            \App\Models\ThriftContributor::firstOrCreate([
                'thrift_package_id' => $invite->thrift_package_id,
                'user_id' => $user->id,
            ]);
        }
        $inviteArr = $invite->toArray();
        if ($invite->invitedUser) {
            $inviteArr['user_id'] = $invite->invitedUser->user_id;
        }
        return response()->json(['message' => 'Invite response recorded.', 'invite' => $inviteArr]);
    }

    /**
     * List all public thrift packages (for users to apply)
     */
    public function listPublicPackages(Request $request)
    {
        $packages = ThriftPackage::where('status', 'public')->with('merchant')->get();
        $packages = $packages->map(function($package) {
            $arr = $package->toArray();
            if ($package->merchant && isset($package->merchant->mer_id)) {
                $arr['merchant_id'] = $package->merchant->mer_id;
            
                $arr['merchant'] = [
                    'id' => $package->merchant->id,
                    'mer_id' => $package->merchant->mer_id,
                    'created_at' => $package->merchant->created_at,
                    'updated_at' => $package->merchant->updated_at,
                    'name' => $package->merchant->name,
                    'business_name' => $package->merchant->business_name,
                    'email' => $package->merchant->email,
                    'phone_number' => $package->merchant->phone_number,
                    'profile_image' => $package->merchant->profile_image,
                ];
            }
            return $arr;
        });
        return response()->json(['packages' => $packages]);
    }

    /**
     * User applies to join a thrift package
     */
    public function applyToPackage(Request $request, $id)
    {
        $user = Auth::user();
        $package = ThriftPackage::findOrFail($id);
        // Only allow if not already a contributor or invited
        $alreadyContributor = $package->contributors()->where('user_id', $user->id)->exists();
        $alreadyInvited = $package->invites()->where('invited_user_id', $user->id)->where('status', 'pending')->exists();
        $alreadyApplied = $package->applications()->where('user_id', $user->id)->where('status', 'pending')->exists();
        if ($alreadyContributor || $alreadyInvited || $alreadyApplied) {
            return response()->json(['message' => 'You have already joined, been invited, or applied.'], 409);
        }
        $application = ThriftPackageApplication::create([
            'thrift_package_id' => $package->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
        // Notify merchant/admin
        if ($package->merchant_id) {
            $merchant = Merchant::find($package->merchant_id);
            if ($merchant) $merchant->notify(new ThriftPackageApplicationNotification($application));
        }
        foreach ($package->admins as $admin) {
            $admin->notify(new ThriftPackageApplicationNotification($application));
        }
        return response()->json(['message' => 'Application submitted.', 'application' => $application]);
    }

    /**
     * List all applications for a package (merchant/admin only)
     */
    public function listApplications($id)
    {
        $user = Auth::user();
        $merchant = Auth::guard('merchant')->user();
        $package = ThriftPackage::findOrFail($id);
        $isOwner = $merchant && $package->merchant_id === $merchant->id;
        $isAdmin = $user && $package->admins()->where('users.id', $user->id)->exists();
        if (!$isOwner && !$isAdmin) {
            return response()->json(['message' => 'Forbidden: Only the merchant or an admin can view applications.'], 403);
        }
        $applications = $package->applications()->with('user')->get()->map(function($application) {
            $arr = $application->toArray();
            if ($application->user) {
                $arr['user_id'] = $application->user->user_id;
            }
            return $arr;
        });
        return response()->json(['applications' => $applications]);
    }

    /**
     * Merchant/admin responds to an application (accept/reject)
     */
    public function respondToApplication(Request $request, $application_id)
    {
        $request->validate(['status' => 'required|in:accepted,rejected']);
        $user = Auth::user();
        $merchant = Auth::guard('merchant')->user();
        $application = ThriftPackageApplication::findOrFail($application_id);
        $package = $application->thriftPackage;
        $isOwner = $merchant && $package->merchant_id === $merchant->id;
        $isAdmin = $user && $package->admins()->where('users.id', $user->id)->exists();
        if (!$isOwner && !$isAdmin) {
            return response()->json(['message' => 'Forbidden: Only the merchant or an admin can respond.'], 403);
        }
        $application->status = $request->status;
        $application->responded_at = now();
        $application->save();
        if ($request->status === 'accepted') {
            \App\Models\ThriftContributor::firstOrCreate([
                'thrift_package_id' => $package->id,
                'user_id' => $application->user_id,
            ]);
        }
        // Notify user
        $application->user->notify(new ThriftPackageApplicationNotification($application));
        return response()->json(['message' => 'Application response recorded.', 'application' => $application]);
    }
} 