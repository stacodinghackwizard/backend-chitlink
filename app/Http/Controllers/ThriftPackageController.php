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
            'status' => 'in:public,private', // allow status to be public or private
        ];
        $validated = $request->validate($rules);

        // Default to private if not provided
        $validated['status'] = $request->input('status', 'private');

        if ($merchant) {
            $validated['merchant_id'] = $merchant->id;
            $validated['created_by_type'] = 'merchant';
            $validated['created_by_id'] = $merchant->id;
            $package = ThriftPackage::create($validated);
            // Add merchant as admin
            // Only add merchant as admin if merchant exists in merchants table
            if ($merchant && Merchant::find($merchant->id)) {
                $package->merchantAdmins()->syncWithoutDetaching([$merchant->id]);
            }
        } elseif ($user) {
            $validated['merchant_id'] = null; // Explicitly set to null for user
            $validated['created_by_type'] = 'user';
            $validated['created_by_id'] = $user->id;
            $package = ThriftPackage::create($validated);
            // Only add user as admin if user exists in users table
            if ($user && User::find($user->id)) {
                $package->userAdmins()->syncWithoutDetaching([$user->id]);
            }
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
        if ($merchant && (int)$package->merchant_id === (int)$merchant->id) {
            $hasAccess = true;
        } elseif ($user && (
            ($package->created_by_type === 'user' && $package->created_by_id === $user->id) ||
            $package->userAdmins()->where('users.id', $user->id)->exists() || $package->merchantAdmins()->where('merchants.id', $merchant->id)->exists()
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

    /**
     * Payout: verify bank and transfer from wallet to account using Paystack
     */
    public function payout(Request $request, $id)
    {
        $user = Auth::user();
        $merchant = Auth::guard('merchant')->user();
        $wallet = null;
        if ($user) {
            $wallet = \App\Models\Wallet::where('user_id', $user->id)->first();
        } elseif ($merchant) {
            $wallet = \App\Models\Wallet::where('merchant_id', $merchant->id)->first();
        }
        if (!$wallet || $wallet->balance < 1) {
            return response()->json(['message' => 'Insufficient wallet balance.'], 400);
        }
        $amount = $request->input('amount');
        $bankCode = $request->input('bank_code');
        $accountNumber = $request->input('account_number');
        $accountName = $request->input('account_name');
        if (!$amount || !$bankCode || !$accountNumber || !$accountName) {
            return response()->json(['message' => 'All fields (amount, bank_code, account_number, account_name) are required.'], 422);
        }
        if ($amount > $wallet->balance) {
            return response()->json(['message' => 'Withdrawal amount exceeds wallet balance.'], 400);
        }
        $paystackConfig = config('paystack');
        // Step 1: Create transfer recipient
        $recipientRes = \Http::withHeaders([
            'Authorization' => 'Bearer ' . $paystackConfig['secret_key'],
            'Content-Type' => 'application/json',
        ])->post($paystackConfig['base_url'] . '/transferrecipient', [
            'type' => 'nuban',
            'name' => $accountName,
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
            'currency' => 'NGN',
        ]);
        $recipientData = $recipientRes->json();
        if (!$recipientData['status'] || empty($recipientData['data']['recipient_code'])) {
            return response()->json(['message' => 'Bank verification failed.', 'result' => $recipientData], 400);
        }
        $recipientCode = $recipientData['data']['recipient_code'];
        // Step 2: Initiate transfer
        $transferRes = \Http::withHeaders([
            'Authorization' => 'Bearer ' . $paystackConfig['secret_key'],
            'Content-Type' => 'application/json',
        ])->post($paystackConfig['base_url'] . '/transfer', [
            'source' => 'balance',
            'amount' => (int)$amount * 100, // Paystack expects kobo
            'recipient' => $recipientCode,
            'reason' => 'Thrift withdrawal',
        ]);
        $transferData = $transferRes->json();
        if (!$transferData['status']) {
            return response()->json(['message' => 'Transfer failed.', 'result' => $transferData], 400);
        }
        // Step 3: Deduct from wallet and log transaction
        $wallet->balance -= $amount;
        $wallet->save();
        $transaction = \App\Models\WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'withdrawal',
            'amount' => $amount,
            'reference' => $transferData['data']['reference'] ?? null,
            'status' => $transferData['data']['status'] ?? 'pending',
            'meta' => $transferData['data'],
        ]);
        return response()->json([
            'message' => 'Withdrawal initiated.',
            'wallet' => $wallet,
            'transaction' => $transaction,
            'paystack' => $transferData['data'],
        ]);
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

        $isMerchantOwner = $merchant && $thriftPackage->merchantAdmins()->where('merchants.id', $merchant->id)->exists();
        $isAdmin = $user && $thriftPackage->userAdmins()->where('users.id', $user->id)->exists();

        if (!$isMerchantOwner && !$isAdmin) {
            return response()->json(['message' => 'Forbidden: Only the merchant or an existing admin can add another admin.'], 403);
        }

        $newAdmin = \App\Models\User::findOrFail($request->user_id);
        // Only add as admin if user exists
        if ($newAdmin && User::find($newAdmin->id)) {
            $thriftPackage->userAdmins()->syncWithoutDetaching([$newAdmin->id]);
        }

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
        // Validate user_id as a string that exists in users.user_id
        $request->validate(['user_id' => 'required|exists:users,user_id']);
        $user = Auth::user();
        $merchant = Auth::guard('merchant')->user();
        $package = ThriftPackage::findOrFail($id);

        // Only merchant owner or admin can invite
        $isOwner = $merchant && $package->merchant_id === $merchant->id;
        $isAdmin = $user && $package->userAdmins()->where('users.id', $user->id)->exists() || $package->merchantAdmins()->where('merchants.id', $merchant->id)->exists();
        if (!$isOwner && !$isAdmin) {
            return response()->json(['message' => 'Forbidden: Only the merchant or an admin can invite.'], 403);
        }

        // Find the user by user_id (custom string)
        $invitee = User::where('user_id', $request->user_id)->firstOrFail();

        // Remove any previous invite for this user and package
        ThriftPackageInvite::where('thrift_package_id', $package->id)
            ->where('invited_user_id', $invitee->id)
            ->delete();

        // Set invited_by_id to the authenticated user's id if present, otherwise to null
        $invitedById = $user ? $user->id : null;
        if (!$invitedById && !$isOwner) {
            return response()->json(['message' => 'Unable to determine inviter.'], 422);
        }

        $invite = ThriftPackageInvite::create([
            'thrift_package_id' => $package->id,
            'invited_user_id' => $invitee->id,
            'invited_by_id' => $invitedById,
            'status' => 'pending',
        ]);

        // Notify the user
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
        $invite = ThriftPackageInvite::find($invite_id);
        if (!$invite) {
            return response()->json(['message' => 'Invite not found.'], 404);
        }
        // Ensure both IDs are treated as integers
        if ((int)$invite->invited_user_id !== (int)$user->id) {
            return response()->json(['message' => 'Forbidden: Not your invite.'], 403);
        }
        if ($invite->status !== 'pending') {
            return response()->json(['message' => 'This invitation has already been responded to and is final.'], 409);
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
        // Only show packages with status 'public'
        $packages = ThriftPackage::where('status', 'public')->with(['merchant', 'admins'])->get();
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
            } else if ($package->created_by_type === 'user' && $package->created_by_id) {
                $user = \App\Models\User::find($package->created_by_id);
                if ($user) {
                    $arr['user'] = [
                        'id' => $user->id,
                        'user_id' => $user->user_id,
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone_number' => $user->phone_number,
                        'profile_image' => $user->profile_image,
                    ];
                }
            }
            unset($arr['admins']); // Remove admins from the response
            return $arr;
        });
        // Always return a 200 with an array, even if empty
        return response()->json(['packages' => $packages], 200);
    }

    /**
     * User applies to join a thrift package
     */
    public function applyToPackage(Request $request, $id)
    {
        $user = Auth::user();
        $package = ThriftPackage::find($id);
        if (!$package) {
            return response()->json(['message' => 'Thrift package not found.'], 404);
        }
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
        $package = ThriftPackage::find($id);
        if (!$package) {
            return response()->json(['message' => 'Thrift package not found.'], 404);
        }
        $isOwner = $merchant && $package->merchant_id === $merchant->id;
        $isAdmin = ($user && $package->userAdmins()->where('users.id', $user->id)->exists())
            || ($merchant && $package->merchantAdmins()->where('merchants.id', $merchant->id)->exists());
        if (!$isOwner && !$isAdmin) {
            return response()->json(['message' => 'Forbidden: Only the merchant or an admi can view applications.'], 403);
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
        $isAdmin = $user && $package->userAdmins()->where('users.id', $user->id)->exists() || $package->merchantAdmins()->where('merchants.id', $merchant->id)->exists();
        if (!$isOwner && !$isAdmin) {
            return response()->json(['message' => 'Forbidden: Only the merchant or an admin can respond.'], 403);
        }
        // Make response final
        if ($application->status !== 'pending') {
            return response()->json(['message' => 'This application has already been responded to and is final.'], 409);
        }
        $application->status = $request->status;
        $application->responded_at = now();
        $application->save();
        if ($request->status === 'accepted') {
            \App\Models\ThriftContributor::firstOrCreate([
                'thrift_package_id' => $package->id,
                'user_id' => $application->user_id,
            ]);
        } else if ($request->status === 'rejected') {
            \App\Models\ThriftContributor::where([
                'thrift_package_id' => $package->id,
                'user_id' => $application->user_id,
            ])->delete();
        }
        // Notify user
        $application->user->notify(new ThriftPackageApplicationNotification($application));
        return response()->json(['message' => 'Application response recorded.', 'application' => $application]);
    }

    /**
     * Example for get-invitation endpoint (adjust as needed for your actual method)
     */
    public function getUserInvites(Request $request)
    {
        $user = Auth::user();
        // $invites = $user->thriftInvites()->with('thriftPackage')->get()->map(function($invite) {
        //     $arr = $invite->toArray();
        //     unset($arr['invited_by_merchant_id']);
        //     return $arr;
        // });
        return response()->json(['message' => 'Not implemented: thriftInvites relation missing on User model.']);
    }

    /**
     * Show a single public thrift package by id
     */
    public function showPublicPackage($id)
    {
        $package = ThriftPackage::where('id', $id)->where('status', 'public')->with(['merchant', 'admins'])->first();
        if (!$package) {
            return response()->json(['message' => 'Thrift package not found.'], 404);
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
        } else if ($package->created_by_type === 'user' && $package->created_by_id) {
            $user = \App\Models\User::find($package->created_by_id);
            if ($user) {
                $arr['user'] = [
                    'id' => $user->id,
                    'user_id' => $user->user_id,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'profile_image' => $user->profile_image,
                ];
            }
        }
        unset($arr['admins']);
        return response()->json($arr, 200);
    }

    /**
     * Save or update thrift package progress (multi-step, all-in-one endpoint)
     */
    public function saveProgress(Request $request)
    {
        $user = Auth::user();
        $merchant = Auth::guard('merchant')->user();
        $data = $request->input('data', []);
        $step = $request->input('step', 'all');
        $packageId = $request->input('package_id');
        $status = $request->input('status', 'draft');

        $details = $data['details'] ?? null;
        $terms = $data['terms'] ?? null;
        $contributors = $data['contributors'] ?? null;

        if (!$merchant && !$user) {
            return response()->json(['message' => 'Unauthorized: You do not have permission to create or update this package.'], 401);
        }

        $package = null;
        $isNew = false;
        if ($packageId) {
            $query = ThriftPackage::query();
            if ($merchant) {
                $query->where('id', $packageId)->where('merchant_id', $merchant->id);
            } elseif ($user) {
                $query->where('id', $packageId)
                    ->where(function($q) use ($user) {
                        $q->where('created_by_type', 'user')->where('created_by_id', $user->id);
                    });
            }
            $package = $query->first();
            if (!$package) {
                return response()->json(['message' => 'Thrift package not found or not accessible.'], 404);
            }
        }

        // If no package, create new
        if (!$package) {
            if (!$details) {
                return response()->json(['message' => 'Details required for new package.'], 422);
            }
            // Check terms_accepted before creating package
            if (empty($terms) || empty($terms['terms_accepted']) || $terms['terms_accepted'] !== true) {
                return response()->json(['message' => 'You must accept the Terms & Conditions before creating a thrift package.'], 403);
            }
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
            $validated = validator($details, $rules)->validate();
            $validated['status'] = $status;
            if ($merchant) {
                $validated['merchant_id'] = $merchant->id;
                $validated['created_by_type'] = 'merchant';
                $validated['created_by_id'] = $merchant->id;
            } elseif ($user) {
                $validated['merchant_id'] = null;
                $validated['created_by_type'] = 'user';
                $validated['created_by_id'] = $user->id;
            } else {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
            $package = ThriftPackage::create($validated);
            $isNew = true;
            // Only add user as admin, never merchant
            if ($user) {
                // Only add user as admin if user exists in users table
                if ($user && User::find($user->id)) {
                    $package->userAdmins()->syncWithoutDetaching([$user->id]);
                }
            }
        } else {
            // Update details if present
            if ($details) {
                $rules = [
                    'name' => [
                        'required',
                        'string',
                        'max:255',
                        Rule::unique('thrift_packages')->ignore($package->id)->where(function ($query) use ($user, $merchant, $package) {
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
                $validated = validator($details, $rules)->validate();
                $package->fill($validated);
            }
            $package->status = $status;
            $package->save();
        }

        // Update terms if present
        if ($terms) {
            $package->terms_accepted = $terms['terms_accepted'] ?? $package->terms_accepted;
            $package->save();
        }

        // Update contributors if present
        if ($contributors && is_array($contributors)) {
            $package->contributors()->delete();
            $invalidContributors = [];
            foreach ($contributors as $group) {
                if (is_array($group) && isset($group['id'], $group['type']) && is_array($group['id'])) {
                    foreach ($group['id'] as $singleId) {
                        if ($group['type'] === 'user') {
                            $userModel = \App\Models\User::find($singleId);
                            if ($userModel) {
                                ThriftContributor::firstOrCreate([
                                    'thrift_package_id' => $package->id,
                                    'user_id' => $singleId,
                                ]);
                            } else {
                                $invalidContributors[] = ['id' => $singleId, 'type' => 'user'];
                            }
                        } elseif ($group['type'] === 'contact') {
                            $contactModel = \App\Models\Contact::find($singleId);
                            if ($contactModel) {
                                // Only allow merchant to add their own contacts
                            if ($merchant && (int)$contactModel->merchant_id === (int)$merchant->id) {
                                    ThriftContributor::firstOrCreate([
                                        'thrift_package_id' => $package->id,
                                        'contact_id' => $singleId,
                                    ]);
                                } else {
                                    $invalidContributors[] = [
                                        'id' => $singleId,
                                        'type' => 'contact',
                                        'message' => 'Forbidden: This contact does not belong to you.'
                                    ];
                                }
                            } else {
                                $invalidContributors[] = ['id' => $singleId, 'type' => 'contact'];
                            }
                        }
                    }
                }
            }
            if (!empty($invalidContributors)) {
                $arr = $package->toArray();
                if ($package->merchant && isset($package->merchant->mer_id)) {
                    $arr['merchant_id'] = $package->merchant->mer_id;
                }
                $arr['is_new'] = $isNew;
                $arr['invalid_contributors'] = $invalidContributors;
                return response()->json($arr, 207);
            }
        }

        $arr = $package->toArray();
        if ($package->merchant && isset($package->merchant->mer_id)) {
            $arr['merchant_id'] = $package->merchant->mer_id;
        }
        $arr['is_new'] = $isNew;
        return response()->json($arr, $isNew ? 201 : 200);
    }

      /**
     * Initialize Paystack payment for thrift contribution
     */
    public function initializeContributionPayment(Request $request, $packageId)
    {
        $user = Auth::user();
        $merchant = Auth::guard('merchant')->user();
        $package = ThriftPackage::findOrFail($packageId);
        $amount = $request->input('amount');
        if (!$amount || $amount < 1) {
            return response()->json(['message' => 'Invalid amount.'], 422);
        }
        // Restrict merchant to only initiate payment for their own package or for their contact contributor
        if ($merchant) {
            \Log::info('Merchant attempting payment', ['merchant_id' => $merchant->id, 'package_merchant_id' => $package->merchant_id]);
            if ((int)$package->merchant_id !== (int)$merchant->id) {
                \Log::warning('Forbidden: Merchant does not own this package', ['merchant_id' => $merchant->id, 'package_merchant_id' => $package->merchant_id]);
                return response()->json(['message' => 'Forbidden: You do not own this package.'], 403);
            }
            $meta = $request->input('meta', []);
            if (isset($meta['contributor_type']) && $meta['contributor_type'] === 'contact' && isset($meta['contributor_id'])) {
                $contactId = $meta['contributor_id'];
                \Log::info('Merchant payment for contact contributor', ['contact_id' => $contactId]);
                $contact = \App\Models\Contact::where('id', $contactId)->where('merchant_id', $merchant->id)->first();
                $isContributor = \App\Models\ThriftContributor::where('thrift_package_id', $package->id)
                    ->where('contact_id', $contactId)
                    ->where('status', 'confirmed')
                    ->exists();
                \Log::info('Contact and contributor check', ['contact_found' => !!$contact, 'is_contributor' => $isContributor]);
                if (!$contact || !$isContributor) {
                    \Log::warning('Forbidden: Contact is not a confirmed contributor', ['contact_id' => $contactId, 'contact_found' => !!$contact, 'is_contributor' => $isContributor]);
                    return response()->json(['message' => 'Forbidden: Contact is not a confirmed contributor for this package.'], 403);
                }
            }
        }
        // Restrict user to only initiate payment for their own or admin package
        // Only check user access if merchant is NOT authenticated
        if (!$merchant && $user) {
            $isOwner = $package->created_by_type === 'user' && $package->created_by_id === $user->id;
            $isAdmin = $package->userAdmins()->where('users.id', $user->id)->exists();
            \Log::info('User attempting payment', ['user_id' => $user->id, 'is_owner' => $isOwner, 'is_admin' => $isAdmin]);
            if (!$isOwner && !$isAdmin) {
                \Log::warning('Forbidden: User does not have access to this package', ['user_id' => $user->id, 'package_id' => $package->id]);
                return response()->json(['message' => 'Forbidden: You do not have access to this package.'], 403);
            }
        }
        $email = $user ? $user->email : ($merchant ? $merchant->email : null);
        if (!$email) {
            return response()->json(['message' => 'No email found for payment.'], 422);
        }
        // Use meta from request if provided, else fallback to user/merchant
        $meta = $request->input('meta', []);
        if (empty($meta)) {
            $meta = [
                'thrift_package_id' => $package->id,
                'contributor_id' => $user ? $user->id : ($merchant ? $merchant->id : null),
                'contributor_type' => $user ? 'user' : 'merchant',
                'amount' => $amount,
            ];
        } else {
            $meta['thrift_package_id'] = $package->id;
            $meta['amount'] = $amount;
        }
        $paystackConfig = config('paystack');
        $response = \Http::withHeaders([
            'Authorization' => 'Bearer ' . $paystackConfig['secret_key'],
            'Content-Type' => 'application/json',
        ])->post($paystackConfig['base_url'] . '/transaction/initialize', [
            'email' => $email,
            'amount' => (int)$amount * 100, // Paystack expects kobo
            'metadata' => $meta,
        ]);
        if ($response->failed()) {
            return response()->json(['message' => 'Failed to initialize payment.', 'error' => $response->json()], 500);
        }
        return response()->json($response->json());
    }

       /**
     * Verify Paystack payment and update wallet
     */
    public function verifyContributionPayment(Request $request)
    {
        $reference = $request->input('reference');
        if (!$reference) {
            return response()->json(['message' => 'Reference is required.'], 422);
        }
        $paystackConfig = config('paystack');
        $response = \Http::withHeaders([
            'Authorization' => 'Bearer ' . $paystackConfig['secret_key'],
            'Content-Type' => 'application/json',
        ])->get($paystackConfig['base_url'] . '/transaction/verify/' . $reference);
        $result = $response->json();
        if (!$result['status'] || $result['data']['status'] !== 'success') {
            return response()->json(['message' => 'Payment not successful.', 'result' => $result], 400);
        }
        $meta = $result['data']['metadata'] ?? [];
        $amount = $result['data']['amount'] / 100; // Convert from kobo
        $contributorType = $meta['contributor_type'] ?? null;
        $contributorId = $meta['contributor_id'] ?? null;
        // Validate contributor exists
        $wallet = null;
        if ($contributorType === 'user') {
            $user = \App\Models\User::find($contributorId);
            if (!$user) {
                return response()->json(['message' => 'User not found for wallet creation.'], 422);
            }
            $wallet = \App\Models\Wallet::firstOrCreate(['user_id' => $contributorId], ['balance' => 0]);
        } elseif ($contributorType === 'merchant') {
            $merchant = \App\Models\Merchant::find($contributorId);
            if (!$merchant) {
                return response()->json(['message' => 'Merchant not found for wallet creation.'], 422);
            }
            $wallet = \App\Models\Wallet::firstOrCreate(['merchant_id' => $contributorId], ['balance' => 0]);
        } elseif ($contributorType === 'contact') {
            $contact = \App\Models\Contact::find($contributorId);
            if (!$contact) {
                return response()->json(['message' => 'Contact not found for wallet creation.'], 422);
            }
            $wallet = \App\Models\Wallet::firstOrCreate(['contact_id' => $contributorId], ['balance' => 0]);
        }
        if (!$wallet) {
            return response()->json(['message' => 'Wallet not found or could not be created.'], 500);
        }
        // Update wallet balance
        $wallet->balance += $amount;
        $wallet->save();
        // Log transaction
        $transaction = \App\Models\WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'contribution',
            'amount' => $amount,
            'reference' => $reference,
            'status' => 'success',
            'meta' => $result['data'],
        ]);
        return response()->json([
            'message' => 'Payment verified and wallet updated.',
            'wallet' => $wallet,
            'transaction' => $transaction,
            'paystack' => $result['data'],
        ]);
    }

      /**
     * Get wallet transaction history for user or merchant
     */
    public function walletTransactions(Request $request)
    {
        $user = Auth::user();
        $merchant = Auth::guard('merchant')->user();
        $wallet = null;
        if ($user) {
            $wallet = \App\Models\Wallet::where('user_id', $user->id)->first();
        } elseif ($merchant) {
            $wallet = \App\Models\Wallet::where('merchant_id', $merchant->id)->first();
            if (!$wallet) {
                // Try all contact wallets for merchant
                $contactIds = \App\Models\Contact::where('merchant_id', $merchant->id)->pluck('id');
                $wallet = \App\Models\Wallet::whereIn('contact_id', $contactIds)->first();
            }
        }
        if (!$wallet) {
            return response()->json(['message' => 'Wallet not found.'], 404);
        }
        $transactions = $wallet->transactions()->orderBy('created_at', 'desc')->get();
        return response()->json([
            'wallet' => $wallet,
            'transactions' => $transactions,
        ]);
    }

        /**
     * Show a single wallet transaction (receipt) by transactionId
     */
    public function showWalletTransaction($transactionId)
    {
        $user = Auth::user();
        $merchant = Auth::guard('merchant')->user();
        $transaction = \App\Models\WalletTransaction::where('reference', $transactionId)
            ->orWhere('id', $transactionId)->first();
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found.'], 404);
        }
        $wallet = $transaction->wallet;
        $hasAccess = false;
        if ($user && $wallet->user_id === $user->id) {
            $hasAccess = true;
        } elseif ($merchant) {
            if ($wallet->merchant_id === $merchant->id) {
                $hasAccess = true;
            } else if ($wallet->contact_id) {
                $contactIds = \App\Models\Contact::where('merchant_id', $merchant->id)->pluck('id')->toArray();
                if (in_array($wallet->contact_id, $contactIds)) {
                    $hasAccess = true;
                }
            }
        }
        if (!$hasAccess) {
            return response()->json(['message' => 'Forbidden: You do not have access to this transaction.'], 403);
        }
        return response()->json(['transaction' => $transaction]);
    }





}