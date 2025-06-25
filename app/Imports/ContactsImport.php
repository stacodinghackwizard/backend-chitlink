<?php

namespace App\Imports;

use App\Models\Contact;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

class ContactsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        $merchant = Auth::guard('merchant')->user();

        // Only import if merchant is authenticated
        if (!$merchant) {
            return null;
        }

        // Avoid duplicate contacts by email for this merchant
        $existing = Contact::where('merchant_id', $merchant->id)
            ->where('email', $row['email'] ?? null)
            ->first();

        if ($existing) {
            return null;
        }

        return new Contact([
            'merchant_id'   => $merchant->id,
            'name'          => $row['name'],
            'email'         => $row['email'] ?? null,
            'phone_number'  => $row['phone_number'] ?? null,
            'profile_image' => $row['profile_image'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            '*.name' => 'required|string|max:255',
            '*.email' => 'required|email',
            '*.phone_number' => 'nullable|string',
        ];
    }
}
