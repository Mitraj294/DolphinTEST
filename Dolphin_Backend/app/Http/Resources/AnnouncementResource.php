<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Announcement
 *
 * @property int|null $id
 * @property string|null $message
 * @property int|null $sender_id
 * @property string|null $schedule_date
 * @property string|null $schedule_time
 * @property \Illuminate\Support\Collection<int, \App\Models\Organization>|null $organizations
 * @property \Illuminate\Support\Collection<int, \App\Models\Group>|null $groups
 * @property \Illuminate\Support\Collection<int, \App\Models\User>|null $admins
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AnnouncementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string,mixed>
     */
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
            // optional relationships
            'organizations' => $this->whenLoaded('organizations', $this->organizations->map->only(['id', 'name'])->toArray()),
            'groups' => $this->whenLoaded('groups', $this->groups->map->only(['id', 'name'])->toArray()),
            'admins' => $this->whenLoaded('admins', $this->admins->map->only(['id', 'first_name', 'last_name', 'email'])->toArray()),
        ];
    }
}
