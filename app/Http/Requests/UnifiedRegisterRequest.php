<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnifiedRegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_type' => ['required', Rule::in(['user', 'merchant'])],
            'password' => 'required|string|min:8|confirmed',

            // Do NOT require email or phone_number here!
            // Let the controller handle conditional validation.
            
            // Merchant specific fields (required if user_type is merchant)
            'business_name' => 'required_if:user_type,merchant|string|max:255',
            'address' => 'required_if:user_type,merchant|string|max:255',
            'reg_number' => 'required_if:user_type,merchant|string|max:255',
            'cac_certificate' => 'nullable|required_if:user_type,merchant|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_type.required' => 'The user type is required.',
            'user_type.in' => 'The user type must be either user or merchant.',
            'email.unique' => 'The email has already been taken.',
            'business_name.required_if' => 'The business name is required for merchants.',
            // 'phone_number.required_if' => 'The phone number is required for merchants.',
            'address.required_if' => 'The address is required for merchants.',
            'reg_number.required_if' => 'The registration number is required for merchants.',
            'cac_certificate.required_if' => 'The CAC certificate is required for merchants.',
            'email.required_without' => 'Either email or phone number is required for merchants.',
            'phone_number.required_without' => 'Either phone number or email is required for merchants.',
        ];
    }
}
