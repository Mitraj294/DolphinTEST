<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    /**
     * @mixin \App\Models\Lead
     *
     * @property int|null $id
     * @property int|null $organization_id
     * @property string|null $first_name
     * @property string|null $last_name
     * @property string|null $email
     * @property string|null $phone_number
     * @property string|null $status
     * @property \Illuminate\Support\Carbon|null $assessment_sent_at
     * @property \Illuminate\Support\Carbon|null $registered_at
     * @property \Illuminate\Support\Carbon|null $created_at
     * @property \Illuminate\Support\Carbon|null $updated_at
     */
    /**
     * Transform the resource into an array.
     *
     * @return array<string,mixed>
     */
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
