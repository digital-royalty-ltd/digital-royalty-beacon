<?php

namespace DigitalRoyalty\Beacon\Systems\Workshop;

use DigitalRoyalty\Beacon\Support\Enums\Admin\AnnouncementBarEnum;

final class AnnouncementBarHandler
{
    public function register(): void
    {
        add_action('wp_footer', [$this, 'output'], 5);
        add_action('wp_head', [$this, 'outputStyles'], 999);
    }

    public function output(): void
    {
        $settings = (array) get_option(AnnouncementBarEnum::OPTION_SETTINGS, []);

        if (empty($settings['enabled']) || empty($settings['message']) || !$this->isActiveForSchedule($settings)) {
            return;
        }

        $message      = wp_kses($settings['message'], ['a' => ['href' => [], 'target' => []], 'strong' => [], 'em' => [], 'br' => []]);
        $buttonLabel  = sanitize_text_field((string) ($settings['button_label'] ?? ''));
        $buttonUrl    = esc_url((string) ($settings['button_url'] ?? ''));
        $dismissible  = !empty($settings['dismissible']);
        $dismissVersion = (int) ($settings['dismiss_version'] ?? 1);
        $barId        = 'dr-beacon-bar';
        $messageHash  = md5(wp_json_encode([
            'message'      => (string) ($settings['message'] ?? ''),
            'button_label' => $buttonLabel,
            'button_url'   => $buttonUrl,
            'dismiss_version' => $dismissVersion,
        ]));

        echo '<div id="' . esc_attr($barId) . '" role="banner" style="display:none;">';
        echo '<div class="dr-bar-msg">' . $message . '</div>'; // phpcs:ignore

        if ($buttonLabel !== '' && $buttonUrl !== '') {
            echo '<a class="dr-bar-button" href="' . $buttonUrl . '">' . esc_html($buttonLabel) . '</a>';
        }
        if ($dismissible) {
            echo '<button class="dr-bar-close" aria-label="Dismiss">&times;</button>';
        }

        echo '</div>';

        if ($dismissible) {
            echo '<script>
(function(){
    var bar = document.getElementById("' . esc_js($barId) . '");
    var key = "dr_bar_dismissed_' . esc_js($messageHash) . '";
    if (!localStorage.getItem(key)) { bar.style.display = "flex"; }
    var btn = bar.querySelector(".dr-bar-close");
    if (btn) { btn.addEventListener("click", function(){ bar.style.display="none"; localStorage.setItem(key,"1"); }); }
})();
</script>' . "\n";
        } else {
            echo '<script>document.getElementById("' . esc_js($barId) . '").style.display="flex";</script>' . "\n";
        }
    }

    public function outputStyles(): void
    {
        $settings = (array) get_option(AnnouncementBarEnum::OPTION_SETTINGS, []);

        if (empty($settings['enabled'])) {
            return;
        }

        $bg   = esc_attr((string) ($settings['bg_color'] ?? '#1d2327'));
        $text = esc_attr((string) ($settings['text_color'] ?? '#ffffff'));
        $button = esc_attr((string) ($settings['button_color'] ?? '#ffffff'));

        echo '<style>
#dr-beacon-bar {
    position: fixed; top: 0; left: 0; right: 0; z-index: 99999;
    background: ' . $bg . '; color: ' . $text . ';
    padding: 10px 20px; text-align: center;
    display: flex; align-items: center; justify-content: center; gap: 12px;
    flex-wrap: wrap; font-size: 14px; line-height: 1.4;
}
#dr-beacon-bar .dr-bar-msg a { color: inherit; text-decoration: underline; }
.dr-bar-button {
    display:inline-flex; align-items:center; justify-content:center;
    padding: 6px 12px; border-radius: 999px; text-decoration:none !important;
    background: ' . $button . '; color: ' . $bg . ' !important; font-weight: 600;
}
.dr-bar-close {
    background: none; border: 1px solid currentColor; color: inherit;
    border-radius: 50%; width: 22px; height: 22px; cursor: pointer;
    font-size: 14px; line-height: 1; padding: 0; flex-shrink: 0;
}
</style>' . "\n";
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function isActiveForSchedule(array $settings): bool
    {
        $now = current_time('timestamp');
        $startAt = (string) ($settings['start_at'] ?? '');
        $endAt = (string) ($settings['end_at'] ?? '');

        if ($startAt !== '') {
            $startTs = strtotime(str_replace('T', ' ', $startAt));
            if ($startTs !== false && $now < $startTs) {
                return false;
            }
        }

        if ($endAt !== '') {
            $endTs = strtotime(str_replace('T', ' ', $endAt));
            if ($endTs !== false && $now > $endTs) {
                return false;
            }
        }

        return true;
    }
}
