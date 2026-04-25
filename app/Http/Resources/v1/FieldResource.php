<?php

namespace App\Http\Resources\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'sport_type'            => $this->sport_type,
            'sport_type_label'      => $this->sport_type_label,
            'size'                  => $this->size,
            'dimensions'            => $this->dimensions,
            'capacity'              => $this->capacity,
            'surface_type'          => $this->surface_type,
            'price_per_hour'        => $this->price_per_hour,
            'price_weekday'         => $this->price_weekday,
            'price_weekend'         => $this->price_weekend,
            'currency'              => $this->currency,
            'min_booking_duration'  => $this->min_booking_duration,
            'max_booking_duration'  => $this->max_booking_duration,
            'booking_slot_duration' => $this->booking_slot_duration,
            'has_lighting'          => (bool) $this->has_lighting,
            'is_covered'            => (bool) $this->is_covered,
            'has_ac'                => (bool) $this->has_ac,
            'features'              => $this->features,
            'image_url'             => $this->image_url,
            'images_urls'           => $this->images_urls,
            'notes'                 => $this->notes,
            'pricing_rules'         => $this->whenLoaded('pricingRules', function() {
                return $this->pricingRules->where('is_active', true)->map(fn($r) => [
                    'name'       => $r->name,
                    'type'       => $r->type,
                    'price'      => $r->price,
                    'price_type' => $r->price_type,
                    'days'       => $r->days_of_week,
                    'start_time' => $r->start_time,
                    'end_time'   => $r->end_time,
                ])->values();
            }),
        ];
    }
}
