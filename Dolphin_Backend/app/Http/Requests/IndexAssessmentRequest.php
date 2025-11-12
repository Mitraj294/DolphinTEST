<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    
    public function rules(): array
    {
        return [
                'organization_id' => 'sometimes|integer|exists:organizations,id',
                'group_id' => 'sometimes|integer|exists:groups,id',
                'member_id' => 'sometimes|integer',
                'page' => 'sometimes|integer|min:1',
        ];
    }
}
