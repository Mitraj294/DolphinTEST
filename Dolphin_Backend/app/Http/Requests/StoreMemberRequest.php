<?php

namespace App\Http\Requests;

/**
 * DEPRECATED REQUEST: This validates data for the obsolete 'members' table.
 * 
 * The 'members' and 'member_roles' tables no longer exist.
 * Use organization_member pivot table and user_roles instead.
 * 
 * This request is kept ONLY for backwards compatibility with MemberController.
 */

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('organizationadmin');
    }

    public function rules(): array
    {
        return [
            'first_name'    => 'required|string|max:255',
            'last_name'     => 'required|string|max:255',
            'email'         => 'required|email|unique:members,email,NULL,id,deleted_at,NULL',
            'phone'         => 'required|string|regex:/^[6-9]\d{9}$/',
            'member_role'   => 'required|array|min:1',
            'member_role.*' => 'integer|exists:member_roles,id',
            'group_ids'     => 'sometimes|array',
            'group_ids.*'   => 'integer|exists:groups,id',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email address is already registered with another member.',
            'phone.unique' => 'This phone number is already registered with another member.',
            'phone.regex' => 'Phone number must be a valid Indian mobile number (10 digits starting with 6-9).',
            'member_role.required' => 'At least one role must be selected for the member.',
            'member_role.*.exists' => 'One or more selected roles are invalid.',
            'group_ids.*.exists' => 'One or more selected groups are invalid.',
        ];
    }
}
