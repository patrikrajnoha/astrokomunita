<?php

namespace App\Http\Resources\Export;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationPreferenceExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'iss_alerts' => (bool) $this->iss_alerts,
            'good_conditions_alerts' => (bool) $this->good_conditions_alerts,
        ];
    }
}
