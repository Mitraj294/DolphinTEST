<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssessmentScheduleRequest extends FormRequest
{
    
    

    public function authorize(): bool
    {
        
        
        return true;
    }

    
    

    
    public function rules(): array
    {
        return [
            'assessment_id' => 'required|exists:organization_assessments,id',
            'date' => 'required|date',
            'time' => 'required',
            'send_at' => 'sometimes|date',
            'timezone' => 'sometimes|string',
            'group_ids' => 'sometimes|array',
            'group_ids.*' => 'exists:groups,id',
            
            'user_ids' => 'sometimes|array',
            'user_ids.*' => 'exists:users,id',
            'member_ids' => 'sometimes|array',
            'member_ids.*' => 'exists:users,id',  
        ];
    }
}
