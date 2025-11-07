<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'organization_id' => 'nullable|integer|exists:organizations,id',
            'date' => 'nullable|date',
            'time' => 'nullable|date_format:H:i',
        ];
    }
}
