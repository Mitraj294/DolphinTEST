<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGroupRequest extends FormRequest
{
    
    public function authorize(): bool
    {
        return $this->user()->hasRole('organizationadmin');
    }

    
    public function rules(): array
    {
        $groupId = $this->route('id');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('groups', 'name')->ignore($groupId)->whereNull('deleted_at')
            ],
            
            'user_ids' => 'sometimes|array',
            'user_ids.*' => 'integer|exists:users,id',
            'member_ids' => 'sometimes|array',
            'member_ids.*' => 'integer|exists:users,id',
        ];
    }
}
