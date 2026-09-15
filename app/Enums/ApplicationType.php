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
    case Apache = 'apache';
    case Nginx = 'nginx';
    case Mysql = 'mysql';
    case Postgres = 'postgres';
    case Redis = 'redis';
    case PhpFpm = 'php_fpm';
    case Mongodb = 'mongodb';
    case Docker = 'docker';
    case Memcached = 'memcached';
    case Haproxy = 'haproxy';
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
            self::Apache => 'Apache',
            self::Nginx => 'Nginx',
            self::Mysql => 'MySQL',
            self::Postgres => 'PostgreSQL',
            self::Redis => 'Redis',
            self::PhpFpm => 'PHP-FPM',
            self::Mongodb => 'MongoDB',
            self::Docker => 'Docker',
            self::Memcached => 'Memcached',
            self::Haproxy => 'HAProxy',
            self::Other => 'Other',
        };
    }
}
