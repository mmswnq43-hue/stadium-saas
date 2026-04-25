<?php

namespace App\Http\Requests\Api\Owner;

use Illuminate\Foundation\Http\FormRequest;

class StoreStadiumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => 'required|string|max:100',
            'description'  => 'nullable|string',
            'city'         => 'required|string|max:50',
            'district'     => 'nullable|string|max:50',
            'address'      => 'required|string|max:255',
            'latitude'     => 'nullable|numeric',
            'longitude'    => 'nullable|numeric',
            'opens_at'     => 'required|date_format:H:i',
            'closes_at'    => 'required|date_format:H:i|after:opens_at',
            'phone'        => 'nullable|string|max:20',
            'whatsapp'     => 'nullable|string|max:20',
            'email'        => 'nullable|email',
            'amenities'    => 'nullable|array',
            'working_days' => 'nullable|array',
            'working_days.*' => 'integer|between:0,6',
            'is_active'    => 'sometimes|boolean',
            'is_featured'  => 'sometimes|boolean',
        ];
    }
}
