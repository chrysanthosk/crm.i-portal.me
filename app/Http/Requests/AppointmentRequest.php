<?php

namespace App\Http\Requests;

use App\Models\Service;
use App\Models\StaffAvailability;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

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
            // ── Service belongs to category check ───────────────
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

            // ── Staff availability check ─────────────────────────
            $staffId  = (int) $this->input('staff_id');
            $startRaw = trim((string) $this->input('start_at'));
            $endRaw   = trim((string) $this->input('end_at'));

            if ($staffId && $startRaw && $endRaw) {
                try {
                    $tz    = config('app.timezone');
                    $start = Carbon::parse($startRaw, $tz);
                    $end   = Carbon::parse($endRaw, $tz);

                    // day_of_week: 0=Monday … 6=Sunday (matches StaffAvailability::DAY_NAMES)
                    $dow = ($start->dayOfWeekIso - 1); // ISO: Mon=1 → 0

                    $avail = StaffAvailability::query()
                        ->where('staff_id', $staffId)
                        ->where('day_of_week', $dow)
                        ->first();

                    if ($avail) {
                        if ($avail->is_day_off) {
                            $dayName = StaffAvailability::DAY_NAMES[$dow] ?? 'that day';
                            $v->errors()->add('start_at', "This staff member has {$dayName} as a day off.");
                        } else {
                            $workStart = Carbon::createFromTimeString($avail->start_time, $tz)->setDateFrom($start);
                            $workEnd   = Carbon::createFromTimeString($avail->end_time, $tz)->setDateFrom($start);

                            if ($start->lt($workStart)) {
                                $v->errors()->add('start_at', "Appointment starts before staff working hours ({$avail->start_time}).");
                            }
                            if ($end->gt($workEnd)) {
                                $v->errors()->add('end_at', "Appointment ends after staff working hours ({$avail->end_time}).");
                            }
                        }
                    }
                    // If no availability row exists for this staff/day, no restriction applies.
                } catch (\Throwable) {
                    // Unparseable dates — let the basic rules catch it.
                }
            }
        });
    }
}
