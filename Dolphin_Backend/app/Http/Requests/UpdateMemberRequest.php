<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('organizationadmin');
    }


    public function rules(): array
    {
        return [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|regex:/^[6-9]\d{9}$/',
            'member_role' => 'sometimes|array',
            'member_role.*' => 'integer|exists:member_roles,id',
            'group_ids' => 'sometimes|array',
            'group_ids.*' => 'integer|exists:groups,id'
        ];
    }
}
