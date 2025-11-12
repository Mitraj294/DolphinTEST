<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'message' => $this->message,
            'sender_id' => $this->sender_id,
            'schedule_date' => $this->schedule_date,
            'schedule_time' => $this->schedule_time,
            'scheduled_at' => $this->schedule_date && $this->schedule_time ? $this->schedule_date . ' ' . $this->schedule_time : null,
            'sent_at' => $this->sent_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            'organizations' => $this->whenLoaded('organizations', $this->organizations->map->only(['id', 'name'])->toArray()),
            'groups' => $this->whenLoaded('groups', $this->groups->map->only(['id', 'name'])->toArray()),
            'admins' => $this->whenLoaded('admins', $this->admins->map->only(['id', 'first_name', 'last_name', 'email'])->toArray()),
        ];
    }
}
