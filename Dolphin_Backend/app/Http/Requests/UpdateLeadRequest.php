<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }




    public function rules(): array
    {

        if ($this->isMethod('patch') && $this->has('notes')) {

            $relevantKeys = array_diff(array_keys($this->all()), ['_method', '_token']);

            if (count($relevantKeys) === 1 && in_array('notes', $relevantKeys)) {
                return [
                    'notes' => 'nullable|string',
                ];
            }
        }


        return [
            'first_name'        => 'required|string|max:255',
            'last_name'         => 'required|string|max:255',
            'email'             => 'required|email',
            'phone'             => 'required|regex:/^[6-9]\d{9}$/',
            'referral_source_id' => 'required|integer|exists:referral_sources,id',
            'find_us'           => 'nullable|integer|exists:referral_sources,id',
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
