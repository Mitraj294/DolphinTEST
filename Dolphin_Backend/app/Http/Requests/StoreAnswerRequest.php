<?php

namespace App\Http\Requests;

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
