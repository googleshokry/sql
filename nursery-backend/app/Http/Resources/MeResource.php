<?php

namespace App\Http\Resources;

use App\Models\Nursery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class MeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'locale' => $this->locale,
            'nurseries' => $this->nurseries
                ->filter(fn (Nursery $n) => $n->isActive())
                ->map(fn (Nursery $n) => [
                    'id' => $n->id,
                    'name' => $n->name,
                    'roles' => $this->rolesIn($n),
                ])->values(),
        ];
    }
}
