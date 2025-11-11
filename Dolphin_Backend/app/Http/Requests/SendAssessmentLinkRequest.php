<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendAssessmentLinkRequest extends FormRequest
{
    // Determine if the user is authorized to make this request.
    // @return bool

    public function authorize(): bool
    {
        // For now, allow any authenticated user to send a link.
        // You can add more specific authorization logic here if needed.
        return true;
    }


    // Get the validation rules that apply to the request.
    // @return array

    /**
     * Validation rules for sending assessment link.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lead_id' => 'required|integer',
            'assessment_id' => 'required|integer',
        ];
    }
}
