<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ThriftPackage;
use App\Models\ThriftContributor;
use App\Models\ThriftSlot;
use App\Models\ThriftTransaction;
use App\Models\Contact;

class ThriftPackageController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum']); // Add merchant guard/middleware as needed
    }

    // List all thrift packages for merchant
    public function index(Request $request)
    {
        $merchant = Auth::guard('merchant')->user();
        $packages = ThriftPackage::where('merchant_id', $merchant->id)->get();
        return response()->json($packages);
    }

    // Create thrift package (step 1)
    public function store(Request $request)
    {
        $merchant = Auth::guard('merchant')->user();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'total_amount' => 'required|numeric|min:1',
            'duration_days' => 'required|integer|min:1',
            'slots' => 'required|integer|min:1',
        ]);
        $validated['merchant_id'] = $merchant->id;
        $package = ThriftPackage::create($validated);
        return response()->json($package, 201);
    }

    // Show thrift package details
    public function show($id)
    {
        $merchant = Auth::guard('merchant')->user();
        $package = ThriftPackage::where('id', $id)->where('merchant_id', $merchant->id)->with(['contributors.contact', 'slots', 'transactions'])->firstOrFail();
        return response()->json($package);
    }

    // Update T&C
    public function updateTerms(Request $request, $id)
    {
        $merchant = Auth::guard('merchant')->user();
        $package = ThriftPackage::where('id', $id)->where('merchant_id', $merchant->id)->firstOrFail();
        $request->validate(['terms_accepted' => 'required|boolean']);
        $package->terms_accepted = $request->terms_accepted;
        $package->save();
        return response()->json($package);
    }

    // Add contributors
    public function addContributors(Request $request, $id)
    {
        $merchant = Auth::guard('merchant')->user();
        $package = ThriftPackage::where('id', $id)
            ->where('merchant_id', $merchant->id)
            ->firstOrFail();

        // Block if T&C not accepted
        if (!$package->terms_accepted) {
            return response()->json([
                'message' => 'You must accept the Terms & Conditions before adding contributors.'
            ], 403);
        }

        $request->validate([
            'contributor_ids' => 'required|array|min:1',
            'contributor_ids.*' => 'integer|exists:contacts,id',
        ]);
        $added = [];
        foreach ($request->contributor_ids as $contactId) {
            $added[] = ThriftContributor::firstOrCreate([
                'thrift_package_id' => $package->id,
                'contact_id' => $contactId,
            ]);
        }
        return response()->json(['contributors' => $added]);
    }

    // Get contributors
    public function getContributors($id)
    {
        $merchant = Auth::guard('merchant')->user();
        $package = ThriftPackage::where('id', $id)->where('merchant_id', $merchant->id)->firstOrFail();
        $contributors = $package->contributors()->with('contact')->get();
        return response()->json(['contributors' => $contributors]);
    }

    // Confirm contributors
    public function confirmContributors(Request $request, $id)
    {
        $merchant = Auth::guard('merchant')->user();
        $package = ThriftPackage::where('id', $id)
            ->where('merchant_id', $merchant->id)
            ->firstOrFail();

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
        $merchant = Auth::guard('merchant')->user();
        $package = ThriftPackage::where('id', $id)
            ->where('merchant_id', $merchant->id)
            ->with(['contributors' => function($q) {
                $q->where('status', 'confirmed')->orderBy('created_at', 'asc');
            }])
            ->firstOrFail();

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
        $merchant = Auth::guard('merchant')->user();
        $package = ThriftPackage::where('id', $id)->where('merchant_id', $merchant->id)->firstOrFail();
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
} 