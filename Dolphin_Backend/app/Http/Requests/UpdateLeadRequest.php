<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadRequest extends FormRequest
{

    //Determine if the user is authorized to make this request.
    //@return bool

    public function authorize(): bool
    {
        return true;
    }


    //Get the validation rules that apply to the request.
    //@return array<string, mixed>

    public function rules(): array
    {
        // Handle the special case where only 'notes' is being updated via a PATCH request.
        if ($this->isMethod('patch') && $this->has('notes')) {
            // Get only the keys we care about, excluding internal Laravel keys
            $relevantKeys = array_diff(array_keys($this->all()), ['_method', '_token']);

            if (count($relevantKeys) === 1 && in_array('notes', $relevantKeys)) {
                return [
                    'notes' => 'nullable|string',
                ];
            }
        }

        // Standard validation rules for a full update.
        return [
            'first_name'        => 'required|string|max:255',
            'last_name'         => 'required|string|max:255',
            'email'             => 'required|email',
            'phone'             => 'required|regex:/^[6-9]\d{9}$/',
            'referral_source_id' => 'required|integer|exists:referral_sources,id',
            'find_us'           => 'nullable|integer|exists:referral_sources,id', // backward compatibility
            'organization_name' => 'required|string|max:500',
            'organization_size' => 'required|string',
            'notes'             => 'nullable|string',
            'address'           => 'required|string|max:500',
            'country_id'        => 'required|integer|exists:countries,id',
            'state_id'          => 'required|integer|exists:states,id',
            'city_id'           => 'required|integer|exists:cities,id',
            'zip'               => 'required|regex:/^[1-9][0-9]{5}$/',
        ];
    }
}
