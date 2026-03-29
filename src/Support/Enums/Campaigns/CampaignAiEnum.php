<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Campaigns;

final class CampaignAiEnum
{
    public const APEX  = 'apex';
    public const DELTA = 'delta';
    public const ORION = 'orion';
    public const NOVA  = 'nova';
    public const PULSE = 'pulse';
    public const ATLAS = 'atlas';

    public const OPTION_SELECTED_AI  = 'dr_beacon_campaign_ai';
    public const OPTION_ONBOARDING   = 'dr_beacon_campaign_onboarding';

    /** @return string[] */
    public static function all(): array
    {
        return [
            self::APEX,
            self::DELTA,
            self::ORION,
            self::NOVA,
            self::PULSE,
            self::ATLAS,
        ];
    }

    public static function isValid(string $key): bool
    {
        return in_array($key, self::all(), true);
    }

    /**
     * @return array{label:string, emoji:string, traits:string[], description:string, color:string}
     */
    public static function meta(string $key): array
    {
        return match ($key) {
            self::APEX  => [
                'label'       => 'Apex',
                'emoji'       => '⚡',
                'tagline'     => 'Maximum revenue. Minimum wasted effort.',
                'traits'      => ['Decisive', 'Aggressive', 'Results-driven'],
                'description' => 'The Apex playbook is Digital Royalty\'s most aggressive revenue framework — built around high-intent traffic, conversion-focused copy, and relentless pursuit of leads. When clients need results fast, this is the methodology we reach for.',
                'color'       => '#DC2626',
            ],
            self::DELTA => [
                'label'       => 'Delta',
                'emoji'       => '🧪',
                'tagline'     => 'Experiment relentlessly. Scale only what works.',
                'traits'      => ['Analytical', 'Methodical', 'Precise'],
                'description' => 'The Delta methodology is how Digital Royalty tests its way to peak performance. Structured experiments, measured outcomes, ruthless scaling of what works. The approach we apply when the data needs to do the talking.',
                'color'       => '#2563EB',
            ],
            self::ORION => [
                'label'       => 'Orion',
                'emoji'       => '🎯',
                'tagline'     => 'Strategy that sees the whole board.',
                'traits'      => ['Strategic', 'Structured', 'Insightful'],
                'description' => 'The Orion approach is Digital Royalty\'s strategic framework for long-game brands — unifying SEO, PPC, content, and social behind a single coherent vision. Every channel working in service of the broader positioning.',
                'color'       => '#7C3AED',
            ],
            self::NOVA  => [
                'label'       => 'Nova',
                'emoji'       => '🎨',
                'tagline'     => 'Brands and stories audiences actually remember.',
                'traits'      => ['Creative', 'Expressive', 'Audience-focused'],
                'description' => 'The Nova playbook is how Digital Royalty builds brand equity. Storytelling-led, emotionally resonant, audience-first. The methodology we apply when standing out matters more than short-term conversion.',
                'color'       => '#EC4899',
            ],
            self::PULSE => [
                'label'       => 'Pulse',
                'emoji'       => '🔥',
                'tagline'     => 'First to trend. First to win.',
                'traits'      => ['Energetic', 'Experimental', 'Fast-moving'],
                'description' => 'The Pulse methodology is Digital Royalty\'s playbook for fast-moving markets. Bold experimentation, trend-led execution, and rapid iteration — the approach we reach for when momentum is everything.',
                'color'       => '#F97316',
            ],
            self::ATLAS => [
                'label'       => 'Atlas',
                'emoji'       => '🛡️',
                'tagline'     => 'Steady, reliable results. No drama, ever.',
                'traits'      => ['Reliable', 'Steady', 'Controlled'],
                'description' => 'The Atlas framework is Digital Royalty\'s steady-hand methodology — consistent execution, managed risk, and reliable output across every channel. The approach we apply when stability and predictability are non-negotiable.',
                'color'       => '#059669',
            ],
            default => [
                'label'       => $key,
                'emoji'       => '🤖',
                'traits'      => [],
                'description' => '',
                'color'       => '#390d58',
            ],
        };
    }

    private function __construct() {}
}
