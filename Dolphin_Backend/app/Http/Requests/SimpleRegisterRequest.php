<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SimpleRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,NULL,id,deleted_at,NULL',
            'password' => 'required|string|min:6',
            'confirm_password' => 'required|string|min:6|same:password',
            
            'phone_number' => 'required|regex:/^[6-9]\d{9}$/',
            'phone' => 'nullable|regex:/^[6-9]\d{9}$/',
        ];
    }
}
