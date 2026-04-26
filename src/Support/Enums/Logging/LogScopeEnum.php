<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Logging;

final class LogScopeEnum
{
    public const ADMIN = 'admin';
    public const REPORTS = 'reports';
    public const API = 'api';
    public const SYSTEM = 'system';
    public const WEBHOOK = 'webhook';

    /**
     * Cron / queue runners — automation scheduler, deferred runner, request
     * pollers. Distinct from `system` (one-off events like activation) so a
     * stuck queue can be inspected without sifting through unrelated noise.
     */
    public const BACKGROUND = 'background';

    private function __construct() {}
}
