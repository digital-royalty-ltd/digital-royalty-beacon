<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Logging;

final class LogScopeEnum
{
    public const ADMIN = 'admin';
    public const REPORTS = 'reports';
    public const API = 'api';
    public const SYSTEM = 'system';
    public const WEBHOOK = 'webhook';

    private function __construct() {}
}
