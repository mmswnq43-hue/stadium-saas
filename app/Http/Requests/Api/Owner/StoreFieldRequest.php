<?php

namespace App\Http\Requests\Api\Owner;

use Illuminate\Foundation\Http\FormRequest;

class StoreFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                  => 'required|string|max:100',
            'sport_type'            => 'required|string|max:50',
            'size'                  => 'required|string|max:20',
            'dimensions'            => 'nullable|string|max:50',
            'capacity'              => 'required|integer|min:1',
            'surface_type'          => 'required|string|max:50',
            'price_per_hour'        => 'required|numeric|min:0',
            'price_weekday'         => 'nullable|numeric|min:0',
            'price_weekend'         => 'nullable|numeric|min:0',
            'min_booking_duration'  => 'sometimes|integer|min:30',
            'max_booking_duration'  => 'sometimes|integer|gt:min_booking_duration',
            'booking_slot_duration' => 'sometimes|integer|in:30,60,90,120',
            'has_lighting'          => 'sometimes|boolean',
            'is_covered'            => 'sometimes|boolean',
            'has_ac'                => 'sometimes|boolean',
            'features'              => 'nullable|array',
            'notes'                 => 'nullable|string',
            'is_active'             => 'sometimes|boolean',
        ];
    }
}
