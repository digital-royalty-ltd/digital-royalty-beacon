<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Logging;

final class LogScope
{
    public const ADMIN = 'admin';
    public const REPORTS = 'reports';
    public const API = 'api';
    public const SYSTEM = 'system';

    private function __construct() {}
}
