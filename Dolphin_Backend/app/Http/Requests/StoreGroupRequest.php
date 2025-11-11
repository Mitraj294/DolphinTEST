<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('organizationadmin');
    }

    /**
     * Validation rules for creating a group.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:groups,name,NULL,id,deleted_at,NULL',
            'user_ids' => 'sometimes|array',
            'user_ids.*' => 'integer|exists:users,id',
            'member_ids' => 'sometimes|array',
            'member_ids.*' => 'integer|exists:users,id',
        ];
    }
}
