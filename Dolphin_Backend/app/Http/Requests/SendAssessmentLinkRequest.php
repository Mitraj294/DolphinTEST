<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendAssessmentLinkRequest extends FormRequest
{
    
    

    public function authorize(): bool
    {
        
        
        return true;
    }

    
    

    
    public function rules(): array
    {
        return [
            'lead_id' => 'required|integer',
            'assessment_id' => 'required|integer',
        ];
    }
}
