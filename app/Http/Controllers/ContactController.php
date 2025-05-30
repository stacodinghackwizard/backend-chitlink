<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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

        $contact = Contact::create([
            'merchant_id' => Auth::id(),
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
        ]);

        return response()->json(['message' => 'Contact created successfully.', 'contact' => $contact], 201);
    }

    // Get all contacts for the authenticated merchant
    public function index(Request $request)
    {
        $merchant = Auth::guard('merchant')->user();
        if (!$merchant) {
            return response()->json(['message' => 'Unauthorized, What are you doing bro!'], Response::HTTP_UNAUTHORIZED);
        }

        $perPage = 10; 
        $contacts = Contact::where('merchant_id', Auth::id())->paginate($perPage);

        return response()->json($contacts);
    }

    // Update a contact
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
            $contact = Contact::findOrFail($id);

            // Check if the contact belongs to the authenticated merchant
            if ($contact->merchant_id !== Auth::id()) {
                return response()->json(['message' => 'Unauthorized, What are you doing bro!'], Response::HTTP_UNAUTHORIZED);
            }

            $contact->update($request->only('name', 'email', 'phone_number'));

            return response()->json(['message' => 'Contact updated successfully.', 'contact' => $contact]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Contact not found.'], Response::HTTP_NOT_FOUND);
        }
    }

    // Delete a contact
    public function destroy($id)
    {
        $merchant = Auth::guard('merchant')->user();
        if (!$merchant) {
            return response()->json(['message' => 'Unauthorized, What are you doing bro!'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $contact = Contact::findOrFail($id);

            // Check if the contact belongs to the authenticated merchant
            if ($contact->merchant_id !== Auth::id()) {
                return response()->json(['message' => 'Unauthorized, What are you doing bro!'], Response::HTTP_UNAUTHORIZED);
            }

            $contact->delete();

            return response()->json(['message' => 'Contact deleted successfully.']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Contact not found.'], Response::HTTP_NOT_FOUND);
        }
    }
}
