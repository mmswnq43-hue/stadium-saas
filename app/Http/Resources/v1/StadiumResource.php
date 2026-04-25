<?php

namespace App\Http\Resources\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StadiumResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'slug'         => $this->slug,
            'city'         => $this->city,
            'district'     => $this->district,
            'address'      => $this->address,
            'latitude'     => $this->latitude,
            'longitude'    => $this->longitude,
            'google_maps'  => $this->google_maps_url,
            'phone'        => $this->phone,
            'whatsapp'     => $this->whatsapp,
            'opens_at'     => $this->opens_at,
            'closes_at'    => $this->closes_at,
            'is_featured'  => $this->is_featured,
            'amenities'    => $this->amenities,
            'fields_count' => $this->active_fields_count ?? $this->activeFields->count(),
            'sport_types'  => $this->activeFields->pluck('sport_type')->unique()->values(),
            'price_from'   => $this->activeFields->min('price_per_hour'),
            'distance'     => $this->distance,
        ];
    }
}
