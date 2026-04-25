<?php

namespace App\Http\Resources\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'booking_number'  => $this->booking_number,
            'status'          => $this->status,
            'status_label'    => $this->status_label,
            'payment_status'  => $this->payment_status,
            'field'           => new FieldResource($this->whenLoaded('field')),
            'stadium'         => [
                'name'    => $this->field->stadium->name ?? null,
                'city'    => $this->field->stadium->city ?? null,
                'address' => $this->field->stadium->address ?? null,
            ],
            'booking_date'    => $this->booking_date->format('Y-m-d'),
            'start_time'      => $this->start_time,
            'end_time'        => $this->end_time,
            'duration_minutes'=> $this->duration_minutes,
            'customer_name'   => $this->customer_name,
            'customer_phone'  => $this->customer_phone,
            'pricing'         => [
                'subtotal'        => $this->subtotal,
                'discount_amount' => $this->discount_amount,
                'tax_amount'      => $this->tax_amount,
                'total_amount'    => $this->total_amount,
                'currency'        => $this->currency,
            ],
            'customer_notes'  => $this->customer_notes,
            'can_be_cancelled'=> $this->can_be_cancelled,
            'created_at'      => $this->created_at->toIso8601String(),
        ];
    }
}
