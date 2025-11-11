<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @mixin \App\Models\User
     *
     * @property int|null $id
     * @property string|null $first_name
     * @property string|null $last_name
     * @property string|null $email
     * @property string|null $phone_number
     * @property int|null $organization_id
     * @property \Illuminate\Support\Collection<int, \App\Models\Role>|null $roles
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
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'organization_id' => $this->organization_id,
            'roles' => $this->whenLoaded('roles', $this->roles->map->only(['id', 'name'])->toArray()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
