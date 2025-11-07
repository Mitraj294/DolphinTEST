<?php

namespace App\Http\Requests;

/**
 * DEPRECATED REQUEST: This validates data for the obsolete 'answers' table.
 * 
 * The 'answers' and 'questions' tables no longer exist.
 * Use assessment_responses table instead.
 * 
 * This request is kept ONLY for backwards compatibility with AnswerController.
 * Create a new StoreAssessmentResponseRequest for new implementations.
 */

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'answers' => 'required|array|min:1',
            'answers.*.question_id' => 'required|integer|exists:questions,id',
            'answers.*.answer' => 'required|array',
        ];
    }
}
