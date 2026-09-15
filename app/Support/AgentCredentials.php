<?php

namespace App\Support;

use Illuminate\Support\Str;

class AgentCredentials
{
    public static function hash(string $plainText): string
    {
        return hash_hmac('sha256', $plainText, (string) config('app.key'));
    }

    public static function generateApiKey(): string
    {
        return 'saha_'.Str::lower(Str::random(40));
    }

    public static function generateAgentUid(): string
    {
        return 'AGT-'.Str::upper(Str::random(10));
    }

    public static function generateEnrollmentToken(): string
    {
        return 'enroll_'.Str::lower(Str::random(48));
    }
}
