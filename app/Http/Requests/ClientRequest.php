<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'registration_date' => ['nullable', 'date'],
            'first_name'        => ['required', 'string', 'max:100'],
            'last_name'         => ['required', 'string', 'max:100'],
            'dob'               => ['required', 'date'],
            'mobile'            => ['required', 'string', 'max:20'],
            'email'             => ['required', 'email', 'max:150'],
            'address'           => ['nullable', 'string', 'max:255'],
            'city'              => ['nullable', 'string', 'max:100'],
            'gender'            => ['required', Rule::in(['Male', 'Female', 'Other'])],
            'notes'             => ['nullable', 'string', 'max:5000'],
            'comments'          => ['nullable', 'string', 'max:5000'],
        ];
    }
}
