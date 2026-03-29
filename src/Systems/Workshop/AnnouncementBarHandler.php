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

        if (empty($settings['enabled']) || empty($settings['message'])) {
            return;
        }

        $message     = wp_kses($settings['message'], ['a' => ['href' => [], 'target' => []], 'strong' => [], 'em' => []]);
        $link        = esc_url((string) ($settings['link'] ?? ''));
        $dismissible = !empty($settings['dismissible']);
        $barId       = 'dr-beacon-bar';

        echo '<div id="' . esc_attr($barId) . '" role="banner" style="display:none;">';
        echo '<span class="dr-bar-msg">';

        if ($link !== '') {
            echo '<a href="' . $link . '">' . $message . '</a>';
        } else {
            echo $message; // phpcs:ignore
        }

        echo '</span>';

        if ($dismissible) {
            echo '<button class="dr-bar-close" aria-label="Dismiss">&times;</button>';
        }

        echo '</div>';

        if ($dismissible) {
            echo '<script>
(function(){
    var bar = document.getElementById("' . esc_js($barId) . '");
    var key = "dr_bar_dismissed_' . esc_js(md5((string)($settings['message'] ?? ''))) . '";
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

        echo '<style>
#dr-beacon-bar {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 99999;
    background: ' . $bg . '; color: ' . $text . ';
    padding: 10px 20px; text-align: center;
    display: flex; align-items: center; justify-content: center; gap: 12px;
    font-size: 14px; line-height: 1.4;
}
#dr-beacon-bar a { color: inherit; text-decoration: underline; }
.dr-bar-close {
    background: none; border: 1px solid currentColor; color: inherit;
    border-radius: 50%; width: 22px; height: 22px; cursor: pointer;
    font-size: 14px; line-height: 1; padding: 0; flex-shrink: 0;
}
</style>' . "\n";
    }
}
