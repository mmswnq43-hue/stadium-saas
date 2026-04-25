<?php

namespace App\Http\Resources\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StadiumDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'slug'         => $this->slug,
            'description'  => $this->description,
            'city'         => $this->city,
            'district'     => $this->district,
            'address'      => $this->address,
            'latitude'     => $this->latitude,
            'longitude'    => $this->longitude,
            'google_maps'  => $this->google_maps_url,
            'phone'        => $this->phone,
            'whatsapp'     => $this->whatsapp,
            'email'        => $this->email,
            'opens_at'     => $this->opens_at,
            'closes_at'    => $this->closes_at,
            'working_days' => $this->working_days,
            'is_featured'  => $this->is_featured,
            'amenities'    => $this->amenities,
            'fields'       => FieldResource::collection($this->activeFields),
        ];
    }
}
