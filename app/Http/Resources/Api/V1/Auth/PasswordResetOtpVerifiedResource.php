<?php

namespace App\Http\Resources\Api\V1\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PasswordResetOtpVerifiedResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'message' => 'OTP verified successfully.',
            'data' => [
                'channel' => (string) $this->resource['channel'],
                'destination' => (string) $this->resource['destination'],
                'verification_token' => (string) $this->resource['verification_token'],
            ],
        ];
    }
}
