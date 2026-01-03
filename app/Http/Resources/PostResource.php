<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'image_url' => $this->image_path
                ? asset('storage/' . $this->image_path)
                : null,
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
