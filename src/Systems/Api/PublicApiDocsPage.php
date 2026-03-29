<?php

namespace DigitalRoyalty\Beacon\Systems\Api;

/**
 * Serves the public API documentation page.
 *
 * Intercepted via template_redirect when ?beacon_docs=1 is present.
 * Renders a self-contained HTML page with no theme dependency.
 */
final class PublicApiDocsPage
{
    public const QUERY_VAR = 'beacon_docs';

    public function register(): void
    {
        add_filter('query_vars', function (array $vars): array {
            $vars[] = self::QUERY_VAR;
            return $vars;
        });

        add_action('template_redirect', function (): void {
            if (!get_query_var(self::QUERY_VAR)) {
                return;
            }

            $this->render();
            exit;
        });
    }

    private function render(): void
    {
        $siteName  = get_bloginfo('name');
        $siteUrl   = get_site_url();
        $baseUrl   = $siteUrl . '/wp-json/beacon/public/v1';
        $endpoints = PublicApiEndpointRegistry::allWithState();

        $enabled  = array_values(array_filter($endpoints, fn ($ep) => $ep['enabled']));
        $disabled = array_values(array_filter($endpoints, fn ($ep) => !$ep['enabled']));

        $groups = [];
        foreach ($enabled as $ep) {
            $groups[$ep['group']][] = $ep;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>';
        echo '<html lang="en">';
        $this->renderHead($siteName);
        echo '<body>';
        $this->renderBody($siteName, $siteUrl, $baseUrl, $groups, $disabled);
        echo '</body></html>';
    }

    private function renderHead(string $siteName): void
    {
        $title = esc_html($siteName) . ' — API Documentation';
        ?>
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= $title ?></title>
        <style>
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
            :root {
                --brand:    #390d58;
                --brand-lt: #5a1a8a;
                --text:     #1a1a2e;
                --muted:    #6b7280;
                --border:   #e5e7eb;
                --bg:       #f9fafb;
                --white:    #ffffff;
                --green:    #059669;
                --red:      #dc2626;
                --red-bg:   #fef2f2;
                --red-bd:   #fecaca;
                --mono:     'SF Mono', 'Fira Code', 'Cascadia Code', monospace;
            }
            body   { font-family: system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--text); line-height: 1.6; }
            a      { color: var(--brand); text-decoration: none; }
            a:hover{ text-decoration: underline; }
            code, pre { font-family: var(--mono); }

            /* Layout */
            .wrap      { max-width: 1100px; margin: 0 auto; padding: 0 24px; }
            .header    { background: var(--brand); color: #fff; padding: 40px 0 32px; }
            .header h1 { font-size: 28px; font-weight: 700; letter-spacing: -0.5px; margin-bottom: 6px; }
            .header p  { color: rgba(255,255,255,0.75); font-size: 15px; }
            .header .badge { display: inline-block; background: rgba(255,255,255,0.15); border-radius: 9999px; padding: 3px 12px; font-size: 12px; font-weight: 500; margin-top: 12px; }
            .body      { padding: 48px 0 80px; }

            /* Sections */
            .section       { margin-bottom: 48px; }
            .section-title { font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: var(--muted); margin-bottom: 16px; }

            /* Info boxes */
            .info-grid     { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 24px; }
            .info-box      { border: 1px solid var(--border); border-radius: 12px; background: var(--white); padding: 20px 24px; }
            .info-box h3   { font-size: 14px; font-weight: 600; margin-bottom: 8px; }
            .info-box p, .info-box li { font-size: 13px; color: var(--muted); }
            .info-box ul   { padding-left: 18px; }
            .info-box li   { margin-bottom: 3px; }
            pre.code       { background: #1e1e2e; color: #cdd6f4; border-radius: 8px; padding: 12px 16px; font-size: 12px; overflow-x: auto; margin-top: 10px; }

            /* Group label */
            .group-label   { font-size: 13px; font-weight: 700; color: var(--brand); text-transform: uppercase; letter-spacing: 0.5px; margin: 32px 0 12px; border-bottom: 2px solid var(--brand); padding-bottom: 8px; }

            /* Endpoint card */
            .endpoint      { border: 1px solid var(--border); border-radius: 12px; background: var(--white); margin-bottom: 16px; overflow: hidden; }
            .ep-header     { display: flex; align-items: center; gap: 12px; padding: 14px 20px; border-bottom: 1px solid var(--border); }
            .ep-method     { font-size: 11px; font-weight: 700; letter-spacing: 0.5px; padding: 3px 8px; border-radius: 6px; min-width: 54px; text-align: center; flex-shrink: 0; }
            .GET    { background: #dbeafe; color: #1d4ed8; }
            .POST   { background: #dcfce7; color: #15803d; }
            .PATCH  { background: #fef9c3; color: #a16207; }
            .DELETE { background: #fee2e2; color: #b91c1c; }
            .ep-path  { font-family: var(--mono); font-size: 13px; font-weight: 600; }
            .ep-title { font-size: 13px; color: var(--muted); margin-left: auto; flex-shrink: 0; }

            /* Two-col body */
            .ep-body       { display: flex; }
            .ep-left       { flex: 0 0 60%; width: 60%; padding: 20px 24px; border-right: 1px solid var(--border); min-width: 0; }
            .ep-right      { flex: 0 0 40%; width: 40%; padding: 0; display: flex; flex-direction: column; background: #16161e; border-radius: 0 0 12px 0; min-width: 0; overflow: hidden; }
            .ep-desc       { font-size: 13px; color: var(--muted); margin-bottom: 16px; }

            /* Params */
            .param-table   { width: 100%; border-collapse: collapse; font-size: 12px; }
            .param-table th { text-align: left; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); padding: 5px 6px; border-bottom: 1px solid var(--border); }
            .param-table td { padding: 7px 6px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
            .param-table tr:last-child td { border-bottom: none; }
            .param-name    { font-family: var(--mono); font-size: 11px; font-weight: 600; color: var(--text); }
            .param-type    { color: #7c3aed; font-family: var(--mono); font-size: 11px; }
            .req-badge     { display: inline-block; background: #fef3c7; color: #92400e; border-radius: 4px; padding: 1px 5px; font-size: 10px; font-weight: 600; }
            .opt-badge     { display: inline-block; background: #f3f4f6; color: #6b7280; border-radius: 4px; padding: 1px 5px; font-size: 10px; font-weight: 600; }
            .no-params     { font-size: 12px; color: var(--muted); font-style: italic; }

            /* Code tabs */
            .tab-bar       { display: flex; gap: 0; border-bottom: 1px solid #2d2d3d; padding: 0 16px; flex-shrink: 0; }
            .tab-btn       { background: none; border: none; color: #888; font-size: 12px; font-weight: 500; padding: 10px 14px; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -1px; font-family: inherit; transition: color 0.15s; }
            .tab-btn:hover { color: #cdd6f4; }
            .tab-btn.active { color: #a6e3a1; border-bottom-color: #a6e3a1; }
            .tab-panel     { padding: 16px; overflow: auto; flex: 1; }
            pre.sample     { background: transparent; color: #cdd6f4; font-size: 12px; line-height: 1.6; white-space: pre; overflow-x: auto; }
            .keyword  { color: #cba6f7; }
            .string   { color: #a6e3a1; }
            .comment  { color: #585b70; }
            .var      { color: #89b4fa; }
            .func     { color: #89dceb; }
            .number   { color: #fab387; }

            /* Disabled */
            .disabled-section  { opacity: 0.65; }
            .disabled-badge    { display: inline-flex; align-items: center; gap: 5px; background: var(--red-bg); color: var(--red); border: 1px solid var(--red-bd); border-radius: 9999px; padding: 2px 10px; font-size: 12px; font-weight: 600; }
            .disabled-ep       { border-color: #fecaca; background: var(--red-bg); }
            .ep-header-disabled{ border-bottom: none; }

            /* Empty state */
            .empty-state { text-align: center; padding: 48px 24px; color: var(--muted); font-size: 14px; }

            @media (max-width: 720px) {
                .ep-body  { flex-direction: column; }
                .ep-left  { flex: none; width: 100%; border-right: none; border-bottom: 1px solid var(--border); }
                .ep-right { flex: none; width: 100%; border-radius: 0 0 12px 12px; }
                .ep-title { display: none; }
                .info-grid{ grid-template-columns: 1fr; }
            }
        </style>
        <script>
        function switchTab(groupId, tab) {
            document.querySelectorAll('[data-group="' + groupId + '"]').forEach(function(el) {
                el.style.display = 'none';
            });
            document.querySelectorAll('[data-tab-btn="' + groupId + '"]').forEach(function(el) {
                el.classList.remove('active');
            });
            var panel = document.getElementById(groupId + '-' + tab);
            if (panel) panel.style.display = 'block';
            var btn = document.querySelector('[data-tab-btn="' + groupId + '"][data-tab="' + tab + '"]');
            if (btn) btn.classList.add('active');
        }
        </script>
        </head>
        <?php
    }

    /**
     * @param array<string, array<int, array<string,mixed>>> $groups
     * @param array<int, array<string,mixed>>               $disabled
     */
    private function renderBody(
        string $siteName,
        string $siteUrl,
        string $baseUrl,
        array  $groups,
        array  $disabled
    ): void {
        ?>
        <div class="header">
            <div class="wrap">
                <h1><?= esc_html($siteName) ?> — API</h1>
                <p>Developer documentation for the <?= esc_html($siteName) ?> REST API.</p>
                <div class="badge">beacon/public/v1</div>
            </div>
        </div>

        <div class="body">
            <div class="wrap">

                <!-- Overview -->
                <div class="section">
                    <div class="section-title">Getting started</div>
                    <div class="info-grid">
                        <div class="info-box">
                            <h3>Authentication</h3>
                            <p>All requests must include an API key as a Bearer token in the <code>Authorization</code> header. Contact the site administrator to request a key.</p>
                            <pre class="code">Authorization: Bearer drb_your_api_key_here</pre>
                        </div>
                        <div class="info-box">
                            <h3>Base URL</h3>
                            <p>All endpoints are relative to the following base URL:</p>
                            <pre class="code"><?= esc_html($baseUrl) ?></pre>
                        </div>
                        <div class="info-box">
                            <h3>Response codes</h3>
                            <ul>
                                <li><strong>200</strong> — Success</li>
                                <li><strong>201</strong> — Created</li>
                                <li><strong>401</strong> — Missing or invalid API key</li>
                                <li><strong>403</strong> — Endpoint disabled</li>
                                <li><strong>404</strong> — Resource not found</li>
                                <li><strong>422</strong> — Validation error</li>
                                <li><strong>429</strong> — Rate limit exceeded</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Enabled endpoints -->
                <div class="section">
                    <div class="section-title">Endpoints</div>

                    <?php if (empty($groups)): ?>
                        <div class="empty-state">
                            No endpoints are currently enabled on this site. Contact the administrator.
                        </div>
                    <?php else: ?>
                        <?php foreach ($groups as $group => $eps): ?>
                            <div class="group-label"><?= esc_html($group) ?></div>
                            <?php foreach ($eps as $ep): ?>
                                <?= $this->renderEnabledEndpoint($ep, $baseUrl) ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Disabled endpoints -->
                <?php if (!empty($disabled)): ?>
                <div class="section disabled-section">
                    <div class="section-title">Disabled endpoints</div>
                    <p style="font-size:13px;color:#6b7280;margin-bottom:20px;">
                        The following endpoints exist but have been disabled by the site administrator.
                        They return <code>403 Forbidden</code> if called. Contact the administrator if you need access.
                    </p>
                    <?php foreach ($disabled as $ep): ?>
                        <div class="endpoint disabled-ep">
                            <div class="ep-header ep-header-disabled">
                                <span class="ep-method <?= esc_attr($ep['method']) ?>"><?= esc_html($ep['method']) ?></span>
                                <span class="ep-path">/beacon/public/v1<?= esc_html($ep['path']) ?></span>
                                <span class="ep-title"><?= esc_html($ep['title']) ?></span>
                                <span class="disabled-badge" style="margin-left:auto">Disabled</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $ep
     */
    private function renderEnabledEndpoint(array $ep, string $baseUrl): string
    {
        $method   = (string) $ep['method'];
        $path     = (string) $ep['path'];
        $params   = (array)  ($ep['parameters'] ?? []);
        $groupId  = 'ep-' . str_replace('.', '-', (string) $ep['key']);
        $samples  = $this->generateSamples($ep, $baseUrl);

        ob_start();
        ?>
        <div class="endpoint">
            <div class="ep-header">
                <span class="ep-method <?= esc_attr($method) ?>"><?= esc_html($method) ?></span>
                <span class="ep-path">/beacon/public/v1<?= esc_html($path) ?></span>
                <span class="ep-title"><?= esc_html($ep['title']) ?></span>
            </div>
            <div class="ep-body">
                <!-- Left: description + params -->
                <div class="ep-left">
                    <p class="ep-desc"><?= esc_html($ep['description']) ?></p>
                    <?php if (!empty($params)): ?>
                    <table class="param-table">
                        <thead>
                            <tr>
                                <th>Parameter</th>
                                <th>Type</th>
                                <th>Required</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($params as $p): ?>
                            <tr>
                                <td><span class="param-name"><?= esc_html($p['name']) ?></span></td>
                                <td><span class="param-type"><?= esc_html($p['type']) ?></span></td>
                                <td><?php if ($p['required']): ?><span class="req-badge">required</span><?php else: ?><span class="opt-badge">optional</span><?php endif; ?></td>
                                <td style="font-size:11px;color:#6b7280"><?= esc_html($p['description']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <p class="no-params">No parameters.</p>
                    <?php endif; ?>
                </div>

                <!-- Right: code tabs -->
                <div class="ep-right">
                    <div class="tab-bar">
                        <button class="tab-btn active"
                            data-tab-btn="<?= esc_attr($groupId) ?>"
                            data-tab="curl"
                            onclick="switchTab('<?= esc_attr($groupId) ?>', 'curl')">cURL</button>
                        <button class="tab-btn"
                            data-tab-btn="<?= esc_attr($groupId) ?>"
                            data-tab="php"
                            onclick="switchTab('<?= esc_attr($groupId) ?>', 'php')">PHP</button>
                        <button class="tab-btn"
                            data-tab-btn="<?= esc_attr($groupId) ?>"
                            data-tab="python"
                            onclick="switchTab('<?= esc_attr($groupId) ?>', 'python')">Python</button>
                        <button class="tab-btn"
                            data-tab-btn="<?= esc_attr($groupId) ?>"
                            data-tab="response"
                            onclick="switchTab('<?= esc_attr($groupId) ?>', 'response')">Response</button>
                    </div>
                    <div class="tab-panel" id="<?= esc_attr($groupId) ?>-curl" data-group="<?= esc_attr($groupId) ?>" style="display:block">
                        <pre class="sample"><?= $samples['curl'] ?></pre>
                    </div>
                    <div class="tab-panel" id="<?= esc_attr($groupId) ?>-php" data-group="<?= esc_attr($groupId) ?>" style="display:none">
                        <pre class="sample"><?= $samples['php'] ?></pre>
                    </div>
                    <div class="tab-panel" id="<?= esc_attr($groupId) ?>-python" data-group="<?= esc_attr($groupId) ?>" style="display:none">
                        <pre class="sample"><?= $samples['python'] ?></pre>
                    </div>
                    <div class="tab-panel" id="<?= esc_attr($groupId) ?>-response" data-group="<?= esc_attr($groupId) ?>" style="display:none">
                        <pre class="sample" style="color:#a6e3a1"><?= htmlspecialchars((string) ($ep['response_example'] ?? '')) ?></pre>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Sample generation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed> $ep
     * @return array{curl: string, php: string, python: string}
     */
    private function generateSamples(array $ep, string $baseUrl): array
    {
        $method     = (string) $ep['method'];
        $params     = (array)  ($ep['parameters'] ?? []);
        $resolvedPath = $this->resolvePath((string) $ep['path'], $params);
        $url          = $baseUrl . $resolvedPath;

        // Body params for POST/PATCH (exclude path params)
        $bodyParams = array_filter(
            $params,
            fn ($p) => !str_contains((string) $ep['path'], '{' . $p['name'] . '}')
                    && in_array($method, ['POST', 'PATCH'], true)
                    && $p['required']
        );

        $bodyData = [];
        foreach ($bodyParams as $p) {
            $bodyData[$p['name']] = $this->exampleValue($p);
        }

        return [
            'curl'   => $this->curlSample($method, $url, $bodyData),
            'php'    => $this->phpSample($method, $url, $bodyData),
            'python' => $this->pythonSample($method, $url, $bodyData),
        ];
    }

    /**
     * Replace {param} placeholders in a path with example values.
     *
     * @param array<int, array<string,mixed>> $params
     */
    private function resolvePath(string $path, array $params): string
    {
        return (string) preg_replace_callback('/\{(\w+)\}/', function (array $m) use ($params): string {
            foreach ($params as $p) {
                if ($p['name'] === $m[1]) {
                    return (string) $this->exampleValue($p);
                }
            }
            return 'example';
        }, $path);
    }

    /**
     * @param array<string, mixed> $param
     */
    private function exampleValue(array $param): mixed
    {
        $examples = [
            'id'      => 'post-123',
            'title'   => 'My New Post',
            'content' => 'Post content goes here.',
            'status'  => 'draft',
            'excerpt' => 'A brief summary.',
            'parent'  => 'page-10',
            'search'  => 'keyword',
        ];

        if (isset($examples[$param['name']])) {
            return $examples[$param['name']];
        }

        return match ($param['type']) {
            'integer' => 1,
            'boolean' => true,
            default   => 'example',
        };
    }

    /**
     * @param array<string, mixed> $body
     */
    private function curlSample(string $method, string $url, array $body): string
    {
        $hasBody = !empty($body);
        $lines   = [];

        $cmd = 'curl';
        if ($method !== 'GET') {
            $cmd .= ' -X ' . $method;
        }
        $cmd .= ' "' . $url . '"';
        $lines[] = $cmd . ' \\';
        $lines[] = '  -H "Authorization: Bearer drb_your_api_key_here"';

        if ($hasBody) {
            $lines[count($lines) - 1] .= ' \\';
            $lines[] = '  -H "Content-Type: application/json" \\';
            $lines[] = "  -d '" . json_encode($body, JSON_UNESCAPED_SLASHES) . "'";
        }

        return htmlspecialchars(implode("\n", $lines));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function phpSample(string $method, string $url, array $body): string
    {
        $hasBody = !empty($body);
        $lines   = [];

        $lines[] = '<?php';

        if ($hasBody) {
            $encoded  = json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $bodyVar  = '$payload = json_encode(' . $this->phpArrayLiteral($body) . ');';
            $lines[]  = '';
            $lines[]  = $bodyVar;
        }

        $lines[] = '';
        $lines[] = '$ch = curl_init(\'' . $url . '\');';
        $lines[] = 'curl_setopt_array($ch, [';
        $lines[] = '    CURLOPT_RETURNTRANSFER => true,';

        if ($method === 'POST') {
            $lines[] = '    CURLOPT_POST           => true,';
        } elseif (in_array($method, ['PATCH', 'DELETE'], true)) {
            $lines[] = '    CURLOPT_CUSTOMREQUEST  => \'' . $method . '\',';
        }

        if ($hasBody) {
            $lines[] = '    CURLOPT_POSTFIELDS     => $payload,';
        }

        $headers = ['\'Authorization: Bearer drb_your_api_key_here\''];
        if ($hasBody) {
            $headers[] = '\'Content-Type: application/json\'';
        }

        $lines[] = '    CURLOPT_HTTPHEADER     => [';
        foreach ($headers as $h) {
            $lines[] = '        ' . $h . ',';
        }
        $lines[] = '    ],';
        $lines[] = ']);';
        $lines[] = '';
        $lines[] = '$body     = curl_exec($ch);';
        $lines[] = 'curl_close($ch);';
        $lines[] = '';
        $lines[] = '$data = json_decode($body, true);';

        return htmlspecialchars(implode("\n", $lines));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function pythonSample(string $method, string $url, array $body): string
    {
        $hasBody = !empty($body);
        $lines   = [];

        $lines[] = 'import requests';
        $lines[] = '';

        $methodFn = strtolower($method);
        $call     = 'response = requests.' . $methodFn . '(';
        $lines[]  = $call;
        $lines[]  = '    \'' . $url . '\',';
        $lines[]  = '    headers={\'Authorization\': \'Bearer drb_your_api_key_here\'},';

        if ($hasBody) {
            $lines[] = '    json={';
            foreach ($body as $key => $val) {
                $valStr  = is_string($val) ? '\'' . addslashes($val) . '\'' : (is_bool($val) ? ($val ? 'True' : 'False') : (string) $val);
                $lines[] = '        \'' . $key . '\': ' . $valStr . ',';
            }
            $lines[] = '    },';
        }

        $lines[] = ')';
        $lines[] = '';
        $lines[] = 'data = response.json()';

        return htmlspecialchars(implode("\n", $lines));
    }

    /**
     * Convert a flat PHP array to a PHP array literal string (single-line).
     *
     * @param array<string, mixed> $arr
     */
    private function phpArrayLiteral(array $arr): string
    {
        $parts = [];
        foreach ($arr as $k => $v) {
            $valStr  = is_string($v) ? '\'' . addslashes($v) . '\'' : (is_bool($v) ? ($v ? 'true' : 'false') : (string) $v);
            $parts[] = '\'' . $k . '\' => ' . $valStr;
        }
        return '[' . implode(', ', $parts) . ']';
    }
}
