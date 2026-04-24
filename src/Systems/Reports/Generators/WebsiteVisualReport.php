<?php

namespace DigitalRoyalty\Beacon\Systems\Reports\Generators;

use DigitalRoyalty\Beacon\Systems\Reports\ReportGeneratorInterface;

/**
 * Generates the website_visual report.
 *
 * Extracts the visual identity of the site by fetching the rendered
 * homepage and analysing all CSS (inline + linked stylesheets).
 * Produces colors, fonts, style attributes, and logo data.
 *
 * No API call — purely local extraction.
 */
final class WebsiteVisualReport implements ReportGeneratorInterface
{
    private string $renderedCss = '';
    private string $renderedHtml = '';
    private bool $fetched = false;

    public function type(): string
    {
        return 'website_visual';
    }

    public function version(): int
    {
        return 1;
    }

    public function generate(): array
    {
        $this->fetchRenderedPage();

        return [
            'colors' => $this->extractColors(),
            'fonts'  => $this->extractFonts(),
            'style'  => $this->analyseStyle(),
            'logo'   => $this->detectLogo(),
        ];
    }

    // -------------------------------------------------------------------------
    // Fetch rendered homepage + all stylesheets
    // -------------------------------------------------------------------------

    private function fetchRenderedPage(): void
    {
        if ($this->fetched) {
            return;
        }
        $this->fetched = true;

        $response = wp_remote_get(home_url('/'), [
            'timeout'    => 15,
            'user-agent' => 'BeaconVisualAnalyser/1.0',
            'sslverify'  => false,
        ]);

        if (is_wp_error($response)) {
            return;
        }

        $this->renderedHtml = (string) wp_remote_retrieve_body($response);
        if ($this->renderedHtml === '') {
            return;
        }

        $css = '';

        // Inline <style> blocks
        if (preg_match_all('/<style[^>]*>(.*?)<\/style>/si', $this->renderedHtml, $styleMatches)) {
            $css .= implode("\n", $styleMatches[1]);
        }

        // Linked stylesheets (skip wp-admin/wp-includes)
        if (preg_match_all('/<link[^>]+rel=["\']stylesheet["\'][^>]+href=["\']([^"\']+)["\']/i', $this->renderedHtml, $linkMatches)) {
            foreach ($linkMatches[1] as $href) {
                if (str_contains($href, '/wp-admin/') || str_contains($href, '/wp-includes/')) {
                    continue;
                }
                $sheet = wp_remote_get($href, ['timeout' => 5, 'sslverify' => false]);
                if (!is_wp_error($sheet)) {
                    $css .= "\n" . substr((string) wp_remote_retrieve_body($sheet), 0, 51200);
                }
            }
        }

        $this->renderedCss = $css;
    }

    // -------------------------------------------------------------------------
    // Colors
    // -------------------------------------------------------------------------

    private function extractColors(): array
    {
        $colors = [];

        // <meta name="theme-color">
        if (preg_match('/<meta\s+name=["\']theme-color["\']\s+content=["\']([^"\']+)["\']/i', $this->renderedHtml, $m)) {
            $colors['primary'] = trim($m[1]);
        }

        // Theme customizer
        foreach (['background_color' => 'background', 'accent_color' => 'accent'] as $mod => $role) {
            $val = get_theme_mod($mod);
            if (is_string($val) && $val !== '' && $val !== 'blank' && preg_match('/^[0-9a-fA-F]{3,8}$/', ltrim($val, '#'))) {
                $colors[$role] = '#' . ltrim($val, '#');
            }
        }

        // theme.json palette
        if (function_exists('wp_get_global_settings')) {
            $palette = wp_get_global_settings(['color', 'palette', 'theme']);
            if (is_array($palette)) {
                foreach ($palette as $entry) {
                    if (!is_array($entry) || !isset($entry['color'], $entry['slug'])) {
                        continue;
                    }
                    $role = $this->colorRole((string) $entry['slug']);
                    if ($role !== null && !isset($colors[$role])) {
                        $colors[$role] = (string) $entry['color'];
                    }
                }
            }
        }

        // CSS custom properties
        if (preg_match_all('/--([a-z0-9_-]*(?:primary|brand|accent|secondary|main|color|highlight|surface|muted|foreground|background|text)[a-z0-9_-]*)\s*:\s*(#[0-9a-fA-F]{3,8}|rgb[a]?\([^)]+\))\s*[;}/]/i', $this->renderedCss, $varMatches, PREG_SET_ORDER)) {
            foreach ($varMatches as $match) {
                $role = $this->colorRole(strtolower($match[1]));
                if ($role !== null && !isset($colors[$role])) {
                    $colors[$role] = trim($match[2]);
                }
            }
        }

        // Frequency analysis
        if (preg_match_all('/#([0-9a-fA-F]{6})\b/', $this->renderedCss, $hexMatches)) {
            $counts = array_count_values(array_map('strtolower', $hexMatches[1]));
            foreach ($counts as $hex => $count) {
                if ($this->isNeutral($hex)) {
                    unset($counts[$hex]);
                }
            }
            arsort($counts);
            $top = array_keys(array_slice($counts, 0, 3, true));

            if (!empty($top) && !isset($colors['primary'])) {
                $colors['primary'] = '#' . $top[0];
            }
            if (count($top) > 1 && !isset($colors['secondary'])) {
                $colors['secondary'] = '#' . $top[1];
            }
            if (count($top) > 2 && !isset($colors['accent'])) {
                $colors['accent'] = '#' . $top[2];
            }
        }

        // Try to find background, surface, text, muted_text from body styles
        if (!isset($colors['background']) && preg_match('/(?:body|:root|html)\s*\{[^}]*background(?:-color)?\s*:\s*(#[0-9a-fA-F]{3,8})/i', $this->renderedCss, $bgM)) {
            $colors['background'] = $bgM[1];
        }
        if (!isset($colors['text']) && preg_match('/(?:body)\s*\{[^}]*[^-]color\s*:\s*(#[0-9a-fA-F]{3,8})/i', $this->renderedCss, $txtM)) {
            $colors['text'] = $txtM[1];
        }

        return $colors;
    }

    private function colorRole(string $slug): ?string
    {
        $s = strtolower($slug);
        if (str_contains($s, 'primary') || str_contains($s, 'brand')) return 'primary';
        if (str_contains($s, 'secondary')) return 'secondary';
        if (str_contains($s, 'accent') || str_contains($s, 'highlight')) return 'accent';
        if (str_contains($s, 'surface')) return 'surface';
        if (str_contains($s, 'background') || str_contains($s, 'base')) return 'background';
        if (str_contains($s, 'muted') && str_contains($s, 'text')) return 'muted_text';
        if (str_contains($s, 'foreground') || str_contains($s, 'text') || str_contains($s, 'contrast')) return 'text';
        return null;
    }

    private function isNeutral(string $hex): bool
    {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return (abs($r - $g) < 15 && abs($g - $b) < 15) || ($r > 230 && $g > 230 && $b > 230) || ($r < 25 && $g < 25 && $b < 25);
    }

    // -------------------------------------------------------------------------
    // Fonts — heading vs body, with category
    // -------------------------------------------------------------------------

    private function extractFonts(): array
    {
        $allFonts = [];

        // Google Fonts links
        if (preg_match_all('/fonts\.googleapis\.com\/css2?\?family=([^&"\']+)/i', $this->renderedHtml, $gfM)) {
            foreach ($gfM[1] as $param) {
                foreach (explode('|', urldecode($param)) as $entry) {
                    $name = str_replace('+', ' ', trim(explode(':', $entry)[0]));
                    if ($name !== '') $allFonts[] = $name;
                }
            }
        }

        // @font-face
        if (preg_match_all('/@font-face\s*\{[^}]*font-family\s*:\s*["\']?([^"\'};]+)/i', $this->renderedCss, $ffM)) {
            foreach ($ffM[1] as $name) {
                $name = trim($name, " \t\n\r\0\x0B'\"");
                if ($name !== '' && !$this->isGenericFont($name)) $allFonts[] = $name;
            }
        }

        // theme.json
        if (function_exists('wp_get_global_settings')) {
            $families = wp_get_global_settings(['typography', 'fontFamilies', 'theme']);
            if (is_array($families)) {
                foreach ($families as $f) {
                    if (is_array($f) && isset($f['fontFamily'])) {
                        $name = $this->firstFontName((string) $f['fontFamily']);
                        if ($name !== '') $allFonts[] = $name;
                    }
                }
            }
        }

        $allFonts = array_values(array_unique($allFonts));

        // Try to determine which is heading vs body from CSS
        $headingFont = null;
        $bodyFont = null;

        if (preg_match('/(?:h[1-3])[^{]*\{[^}]*font-family\s*:\s*([^;}{]+)/i', $this->renderedCss, $hM)) {
            $headingFont = $this->firstFontName($hM[1]);
        }
        if (preg_match('/(?:body|html|\.page|\.site|p\s*\{)[^{]*\{?[^}]*font-family\s*:\s*([^;}{]+)/i', $this->renderedCss, $bM)) {
            $bodyFont = $this->firstFontName($bM[1]);
        }

        // Fall back to the first two detected fonts
        if (!$headingFont && !empty($allFonts)) $headingFont = $allFonts[0];
        if (!$bodyFont && count($allFonts) > 1) $bodyFont = $allFonts[1];
        if (!$bodyFont) $bodyFont = $headingFont;

        return [
            'heading' => $headingFont ? ['family' => $headingFont, 'category' => $this->fontCategory($headingFont)] : null,
            'body'    => $bodyFont    ? ['family' => $bodyFont,    'category' => $this->fontCategory($bodyFont)]    : null,
        ];
    }

    private function firstFontName(string $raw): string
    {
        $first = trim(explode(',', $raw)[0], " \t\n\r\0\x0B'\"");
        return $this->isGenericFont($first) ? '' : $first;
    }

    private function isGenericFont(string $name): bool
    {
        return in_array(strtolower($name), ['serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui', 'ui-serif', 'ui-sans-serif', 'ui-monospace', 'ui-rounded', 'inherit', 'initial', 'unset'], true);
    }

    private function fontCategory(string $font): string
    {
        $lower = strtolower($font);
        $sansSerif = ['inter', 'manrope', 'poppins', 'outfit', 'dm sans', 'figtree', 'geist', 'roboto', 'lato', 'open sans', 'montserrat', 'raleway', 'nunito', 'source sans pro', 'noto sans', 'arial', 'helvetica', 'verdana', 'plus jakarta sans', 'space grotesk', 'sora', 'red hat display', 'cabinet grotesk', 'general sans'];
        $serif = ['playfair display', 'merriweather', 'lora', 'georgia', 'times new roman', 'libre baskerville', 'crimson text', 'dm serif display', 'fraunces', 'cormorant'];
        $mono = ['kode mono', 'fira code', 'jetbrains mono', 'source code pro', 'roboto mono', 'ibm plex mono', 'space mono', 'fira mono'];

        if (in_array($lower, $sansSerif, true)) return 'sans-serif';
        if (in_array($lower, $serif, true)) return 'serif';
        if (in_array($lower, $mono, true)) return 'monospace';
        return 'sans-serif'; // sensible default
    }

    // -------------------------------------------------------------------------
    // Style attributes
    // -------------------------------------------------------------------------

    private function analyseStyle(): array
    {
        $css = $this->renderedCss;

        if ($css === '') {
            return [
                'border_radius'  => 'medium',
                'shadow_style'   => 'none',
                'spacing'        => 'normal',
                'visual_density' => 'clean',
                'button_style'   => 'rounded-solid',
            ];
        }

        // Border radius
        $radiusValues = [];
        if (preg_match_all('/border-radius\s*:\s*(\d+(?:\.\d+)?)(px|rem|em)/i', $css, $rM)) {
            foreach ($rM[1] as $i => $val) {
                $px = (float) $val;
                if ($rM[2][$i] === 'rem' || $rM[2][$i] === 'em') $px *= 16;
                $radiusValues[] = $px;
            }
        }
        $avgRadius = !empty($radiusValues) ? array_sum($radiusValues) / count($radiusValues) : 6;
        $borderRadius = match (true) {
            $avgRadius < 2  => 'none',
            $avgRadius < 6  => 'small',
            $avgRadius < 12 => 'medium',
            $avgRadius < 20 => 'large',
            default         => 'pill',
        };

        // Shadows
        $shadowCount = (int) preg_match_all('/box-shadow\s*:/i', $css);
        $hasSoftShadows = (bool) preg_match('/box-shadow\s*:[^;]*0\s+\d+px\s+\d{2,}px/i', $css);
        $shadowStyle = match (true) {
            $shadowCount < 3  => 'none',
            $hasSoftShadows   => 'soft',
            $shadowCount > 15 => 'heavy',
            default           => 'subtle',
        };

        // Spacing — look at padding/margin values
        $padValues = [];
        if (preg_match_all('/padding(?:-(?:top|bottom|left|right))?\s*:\s*(\d+(?:\.\d+)?)(px|rem|em)/i', $css, $pM)) {
            foreach ($pM[1] as $i => $val) {
                $px = (float) $val;
                if ($pM[2][$i] === 'rem' || $pM[2][$i] === 'em') $px *= 16;
                $padValues[] = $px;
            }
        }
        $avgPad = !empty($padValues) ? array_sum($padValues) / count($padValues) : 16;
        $spacing = match (true) {
            $avgPad < 10 => 'compact',
            $avgPad < 20 => 'normal',
            $avgPad < 32 => 'spacious',
            default      => 'airy',
        };

        // Visual density
        $gradientCount = (int) preg_match_all('/(?:linear|radial|conic)-gradient/i', $css);
        $borderCount = (int) preg_match_all('/border\s*:\s*(?:1px|thin)/i', $css);
        $animCount = (int) preg_match_all('/@keyframes|animation\s*:/i', $css);
        $visualDensity = 'clean';
        if ($gradientCount > 5 || $animCount > 5 || $borderCount > 20) {
            $visualDensity = 'detailed';
        } elseif ($shadowCount < 3 && $gradientCount < 2 && $borderCount < 5) {
            $visualDensity = 'minimal';
        }

        // Button style
        $buttonStyle = 'rounded-solid';
        if (preg_match('/(?:\.btn|\.button|button)\s*\{[^}]*/i', $css, $btnBlock)) {
            $btn = $btnBlock[0];
            $hasOutline = (bool) preg_match('/border\s*:.*(?:1px|2px)/i', $btn);
            $hasTransparentBg = (bool) preg_match('/background(?:-color)?\s*:\s*transparent/i', $btn);
            $hasPillRadius = (bool) preg_match('/border-radius\s*:\s*(?:9999|1000|50)(?:px|%)/i', $btn);

            if ($hasPillRadius && $hasOutline) {
                $buttonStyle = 'pill-outline';
            } elseif ($hasPillRadius) {
                $buttonStyle = 'pill-solid';
            } elseif ($hasOutline || $hasTransparentBg) {
                $buttonStyle = 'rounded-outline';
            }
        }

        return [
            'border_radius'  => $borderRadius,
            'shadow_style'   => $shadowStyle,
            'spacing'        => $spacing,
            'visual_density' => $visualDensity,
            'button_style'   => $buttonStyle,
        ];
    }

    // -------------------------------------------------------------------------
    // Logo
    // -------------------------------------------------------------------------

    private function detectLogo(): array
    {
        $primaryUrl = null;
        $faviconUrl = null;
        $hasSymbol = false;
        $preferredBg = 'light';

        // Custom logo from customizer
        $customLogoId = (int) get_theme_mod('custom_logo', 0);
        if ($customLogoId > 0) {
            $url = wp_get_attachment_image_url($customLogoId, 'full');
            if (is_string($url) && $url !== '') {
                $primaryUrl = $url;
                $mime = (string) get_post_mime_type($customLogoId);
                $hasSymbol = str_contains($mime, 'svg');
            }
        }

        // Favicon
        $faviconId = (int) get_option('site_icon', 0);
        if ($faviconId > 0) {
            $url = wp_get_attachment_image_url($faviconId, 'full');
            if (is_string($url) && $url !== '') {
                $faviconUrl = $url;
                if (!$hasSymbol) $hasSymbol = true; // favicon is a symbol
            }
        }

        // Scan rendered HTML header for logo
        if (!$primaryUrl && $this->renderedHtml !== '') {
            $searchArea = $this->renderedHtml;
            if (preg_match('/<header[^>]*>(.*?)<\/header>/si', $this->renderedHtml, $hM)) {
                $searchArea = $hM[1];
            } else {
                $searchArea = substr($this->renderedHtml, 0, (int) (strlen($this->renderedHtml) * 0.3));
            }

            if (preg_match_all('/<img\s[^>]*>/i', $searchArea, $imgMatches)) {
                foreach ($imgMatches[0] as $imgTag) {
                    if (preg_match('/(?:src|alt|class|id)\s*=\s*["\'][^"\']*logo[^"\']*["\']/i', $imgTag)
                        && preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $imgTag, $srcM)) {
                        $src = $srcM[1];
                        if (str_starts_with($src, 'http') || str_starts_with($src, '//')) {
                            $primaryUrl = str_starts_with($src, '//') ? 'https:' . $src : $src;
                            if (str_contains($src, '.svg')) $hasSymbol = true;
                            break;
                        }
                    }
                }
            }

            // Inline SVG logo
            if (!$primaryUrl && preg_match('/<a[^>]*(?:class|id)="[^"]*logo[^"]*"[^>]*>.*?<svg/si', $searchArea)) {
                $hasSymbol = true;
            }
        }

        // Scan header.php as fallback
        if (!$primaryUrl) {
            foreach ([get_stylesheet_directory(), get_template_directory()] as $dir) {
                $path = $dir . '/header.php';
                if (!file_exists($path)) continue;
                $header = (string) file_get_contents($path);
                if (preg_match_all('/<img\s[^>]*>/i', $header, $imgMatches)) {
                    foreach ($imgMatches[0] as $imgTag) {
                        if (preg_match('/(?:src|alt|class|id)\s*=\s*["\'][^"\']*logo[^"\']*["\']/i', $imgTag)
                            && preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $imgTag, $srcM)
                            && str_starts_with($srcM[1], 'http')) {
                            $primaryUrl = $srcM[1];
                            break 2;
                        }
                    }
                }
            }
        }

        // Preferred background — check if header/body is dark
        if (preg_match('/(?:header|\.header|\.site-header)\s*\{[^}]*background(?:-color)?\s*:\s*#([0-9a-fA-F]{6})/i', $this->renderedCss, $hdrBg)) {
            $brightness = (hexdec(substr($hdrBg[1], 0, 2)) + hexdec(substr($hdrBg[1], 2, 2)) + hexdec(substr($hdrBg[1], 4, 2))) / 3;
            $preferredBg = $brightness < 80 ? 'dark' : 'light';
        }

        return [
            'primary_url'          => $primaryUrl,
            'favicon_url'          => $faviconUrl,
            'has_symbol'           => $hasSymbol,
            'preferred_background' => $preferredBg,
        ];
    }
}
