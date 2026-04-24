<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Systems\Automations\AutomationScheduler;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST endpoints for managing automation schedules.
 *
 * GET    /admin/automation-schedules                — list all schedules
 * POST   /admin/automation-schedules                — create a schedule
 * DELETE /admin/automation-schedules/{id}            — delete a schedule
 * PATCH  /admin/automation-schedules/{id}            — toggle enable/disable
 */
final class AutomationScheduleController
{
    private readonly AutomationScheduler $scheduler;

    public function __construct()
    {
        $this->scheduler = new AutomationScheduler();
    }

    public function registerRoutes(): void
    {
        $perm = fn () => current_user_can('manage_options');

        register_rest_route('beacon/v1', '/admin/automation-schedules', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'list'],
                'permission_callback' => $perm,
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'create'],
                'permission_callback' => $perm,
            ],
        ]);

        register_rest_route('beacon/v1', '/admin/automation-schedules/(?P<id>[a-f0-9-]+)', [
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'delete'],
                'permission_callback' => $perm,
            ],
            [
                'methods'             => 'PATCH',
                'callback'            => [$this, 'toggle'],
                'permission_callback' => $perm,
            ],
        ]);

        register_rest_route('beacon/v1', '/admin/automation-schedules/frequencies', [
            'methods'             => 'GET',
            'callback'            => [$this, 'frequencies'],
            'permission_callback' => $perm,
        ]);
    }

    public function list(WP_REST_Request $request): WP_REST_Response
    {
        $automationKey = $request->get_param('automation_key');

        $schedules = $this->scheduler->all();

        if (is_string($automationKey) && $automationKey !== '') {
            $schedules = array_values(array_filter(
                $schedules,
                fn ($s) => ($s['automation_key'] ?? '') === $automationKey
            ));
        }

        return new WP_REST_Response($schedules, 200);
    }

    public function create(WP_REST_Request $request): WP_REST_Response
    {
        $automationKey = trim((string) ($request->get_param('automation_key') ?? ''));
        $frequency     = trim((string) ($request->get_param('frequency') ?? ''));
        $time          = trim((string) ($request->get_param('time') ?? '09:00'));
        $dayOfWeek     = $request->get_param('day_of_week');
        $endBehavior   = trim((string) ($request->get_param('end_behavior') ?? 'infinite'));
        $parameters    = $request->get_param('parameters');

        if ($automationKey === '') {
            return new WP_REST_Response(['error' => 'automation_key is required.'], 400);
        }

        if ($frequency === '') {
            return new WP_REST_Response(['error' => 'frequency is required.'], 400);
        }

        try {
            $schedule = $this->scheduler->create(
                automationKey: $automationKey,
                frequency: $frequency,
                time: $time,
                dayOfWeek: is_string($dayOfWeek) ? $dayOfWeek : null,
                endBehavior: $endBehavior,
                parameters: is_array($parameters) ? $parameters : []
            );

            return new WP_REST_Response($schedule, 201);
        } catch (\InvalidArgumentException $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function delete(WP_REST_Request $request): WP_REST_Response
    {
        $id = (string) $request->get_param('id');

        if (!$this->scheduler->delete($id)) {
            return new WP_REST_Response(['error' => 'Schedule not found.'], 404);
        }

        return new WP_REST_Response(['ok' => true], 200);
    }

    public function toggle(WP_REST_Request $request): WP_REST_Response
    {
        $id      = (string) $request->get_param('id');
        $enabled = (bool) $request->get_param('enabled');

        if (!$this->scheduler->toggle($id, $enabled)) {
            return new WP_REST_Response(['error' => 'Schedule not found.'], 404);
        }

        return new WP_REST_Response(['ok' => true, 'enabled' => $enabled], 200);
    }

    public function frequencies(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(AutomationScheduler::frequencyLabels(), 200);
    }
}
