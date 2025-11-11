<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    /**
     * @mixin \App\Models\Member
     *
     * @property int|null $id
     * @property string|null $first_name
     * @property string|null $last_name
     * @property string|null $email
     * @property string|null $phone
     * @property \Illuminate\Support\Collection<int, \App\Models\Group>|null $groups
     * @property string|null $member_role
     * @property \Illuminate\Support\Collection<int, \App\Models\MemberRole>|null $memberRoles
     */
    //Transform the resource into an array.
    //@param  \Illuminate\Http\Request  $request
    /**
     * @return array<string,mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'group_ids' => $this->whenLoaded('groups', $this->groups->pluck('id')->toArray()),
            'member_role' => $this->member_role,
            'member_role_ids' => $this->whenLoaded('memberRoles', $this->memberRoles->pluck('id')->toArray()),
            'memberRoles' => $this->whenLoaded('memberRoles', $this->memberRoles->map->only(['id', 'name'])->toArray()),
            'member_role_names' => $this->whenLoaded('memberRoles', $this->memberRoles->pluck('name')->toArray()),
        ];
    }
}
