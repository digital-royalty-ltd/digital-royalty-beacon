<?php

namespace DigitalRoyalty\Beacon\Admin;

final class SettingsPage
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function addMenu(): void
    {
        add_options_page(
            'Beacon',
            'Beacon',
            'manage_options',
            'dr-beacon',
            [$this, 'render']
        );
    }

    public function registerSettings(): void
    {
        register_setting('dr_beacon', 'dr_beacon_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1>Beacon</h1>
            <form method="post" action="options.php">
                <?php settings_fields('dr_beacon'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="dr_beacon_api_key">API Key</label></th>
                        <td>
                            <input
                                name="dr_beacon_api_key"
                                id="dr_beacon_api_key"
                                type="password"
                                class="regular-text"
                                value="<?php echo esc_attr(get_option('dr_beacon_api_key', '')); ?>"
                                autocomplete="off"
                            />
                            <p class="description">Paste the API key from your Beacon dashboard.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save'); ?>
            </form>
        </div>
        <?php
    }
}