<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,NULL,id,deleted_at,NULL',
            'password' => 'required|string|min:6',
            'confirm_password' => 'required|string|min:6|same:password',
            'phone_number' => 'required|regex:/^[6-9]\d{9}$/',
            'phone' => 'nullable|regex:/^[6-9]\d{9}$/',
            'referral_source_id' => 'required|integer|exists:referral_sources,id',
            'referral_other_text' => 'nullable|string|required_if:referral_source_id,10',
            'find_us' => 'nullable|integer|exists:referral_sources,id',
            'name' => 'required|string|max:500',
            'organization_name' => 'nullable|string|max:500',
            'size' => 'required|string',
            'organization_size' => 'nullable|string',
            'address_line_1' => 'required|string|max:500',
            'address' => 'nullable|string|max:500',
            'address_line_2' => 'nullable|string|max:500',
            'country_id' => 'required|integer|exists:countries,id',
            'country' => 'nullable|integer|exists:countries,id',
            'state_id' => 'required|integer|exists:states,id',
            'state' => 'nullable|integer|exists:states,id',
            'city_id' => 'required|integer|exists:cities,id',
            'city' => 'nullable|integer|exists:cities,id',
            'zip_code' => 'required|regex:/^[1-9][0-9]{5}$/',
            'zip' => 'nullable|regex:/^[1-9][0-9]{5}$/',
            'lead_id' => 'nullable|integer|exists:leads,id',
        ];
    }
}
