<?php

namespace App\Enums;

enum ApplicationType: string
{
    case Php = 'php';
    case Laravel = 'laravel';
    case NodeJs = 'nodejs';
    case Java = 'java';
    case Python = 'python';
    case DotNet = 'dotnet';
    case Api = 'api';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Php => 'PHP',
            self::Laravel => 'Laravel',
            self::NodeJs => 'Node.js',
            self::Java => 'Java',
            self::Python => 'Python',
            self::DotNet => '.NET',
            self::Api => 'API',
            self::Other => 'Other',
        };
    }
}
