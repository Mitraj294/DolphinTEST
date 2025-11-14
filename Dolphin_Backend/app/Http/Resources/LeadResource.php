<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'status' => $this->status,
            'assessment_sent_at' => $this->assessment_sent_at,
            'registered_at' => $this->registered_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
