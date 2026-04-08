<?php

namespace App\Http\Requests;

use App\Models\Service;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class AppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'start_at' => ['required', 'string'],
            'end_at'   => ['required', 'string'],

            'client_id' => ['nullable', 'integer', 'exists:clients,id'],

            'client_first_name' => ['nullable', 'string', 'max:100', 'required_without:client_id'],
            'client_last_name'  => ['nullable', 'string', 'max:100', 'required_without:client_id'],
            'client_phone'      => ['nullable', 'string', 'max:20'],

            'service_category_id' => ['required', 'integer', 'exists:service_categories,id'],
            'service_id'          => ['required', 'integer', 'exists:services,id'],

            'status'         => ['required', 'string'],
            'send_sms'       => ['nullable', 'boolean'],
            'notes'          => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_first_name.required_without' => 'First name is required when no existing client is selected.',
            'client_last_name.required_without'  => 'Last name is required when no existing client is selected.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            $catId = (int) $this->input('service_category_id');
            $svcId = (int) $this->input('service_id');

            if ($catId && $svcId) {
                $ok = Service::query()
                    ->where('id', $svcId)
                    ->where('category_id', $catId)
                    ->exists();

                if (!$ok) {
                    $v->errors()->add('service_id', 'Selected service does not belong to the chosen category.');
                }
            }
        });
    }
}
