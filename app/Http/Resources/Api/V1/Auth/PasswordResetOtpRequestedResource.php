<?php

namespace App\Http\Resources\Api\V1\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PasswordResetOtpRequestedResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'channel' => (string) $this->resource['channel'],
            'destination' => (string) $this->resource['destination'],
            'expires_at' => (string) $this->resource['expires_at'],
        ];

        if (config('otp.expose_code_in_response', true)) {
            $data['otp_code'] = (string) $this->resource['otp_code'];
        }

        return [
            'message' => 'OTP sent successfully.',
            'data' => $data,
        ];
    }
}
