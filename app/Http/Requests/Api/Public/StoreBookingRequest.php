<?php

namespace App\Http\Requests\Api\Public;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'field_id'       => 'required|integer|exists:fields,id',
            'date'           => 'required|date|after_or_equal:today',
            'start_time'     => 'required|date_format:H:i',
            'end_time'       => 'required|date_format:H:i|after:start_time',
            'customer_name'  => 'required|string|max:100',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email',
            'discount_code'  => 'nullable|string',
            'customer_notes' => 'nullable|string|max:500',
        ];
    }
}
