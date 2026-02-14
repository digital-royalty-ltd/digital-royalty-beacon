<?php

namespace DigitalRoyalty\Beacon\Admin\Views\Tools;

use DigitalRoyalty\Beacon\Admin\Actions\Views\Tools\ContentGeneratorAdminActions;
use DigitalRoyalty\Beacon\Admin\Views\ViewInterface;
use DigitalRoyalty\Beacon\Support\Enums\Admin\AdminPageEnum;
use WP_Error;
use WP_Taxonomy;

final class ContentGeneratorView implements ViewInterface
{
    public function slug(): string
    {
        return 'content-generator';
    }

    public function title(): string
    {
        return 'Content Generator';
    }

    public function description(): string
    {
        return 'Generate a new draft from your selected post type and taxonomy context. Review and publish manually.';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * Action hook target: admin_post_dr_beacon_generate_content
     */
    public function handleGenerate(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(__('Permission denied.', 'digital-royalty'));
        }

        if (
                !isset($_POST['dr_beacon_nonce']) ||
                !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dr_beacon_nonce'])), 'dr_beacon_generate_content')
        ) {
            wp_die(__('Security check failed.', 'digital-royalty'));
        }

        $postType = sanitize_text_field(wp_unslash($_POST['post_type'] ?? 'post'));
        if (!post_type_exists($postType)) {
            wp_die(__('Invalid post type.', 'digital-royalty'));
        }

        // Optional prompt (may be empty)
        $prompt = '';
        if (isset($_POST['prompt'])) {
            $prompt = sanitize_textarea_field(wp_unslash($_POST['prompt']));
        }

        // Tax inputs (same structure WP uses)
        $taxInputRaw = $_POST['tax_input'] ?? [];
        if (!is_array($taxInputRaw)) {
            $taxInputRaw = [];
        }

        // Only accept taxonomies that belong to the selected post type.
        $allowedTaxonomies = get_object_taxonomies($postType, 'names');

        // Build a sanitized taxonomy payload.
        $sanitizedTaxInput = [];
        foreach ($taxInputRaw as $taxonomy => $value) {
            $taxonomy = sanitize_key($taxonomy);

            if (!in_array($taxonomy, $allowedTaxonomies, true)) {
                continue;
            }

            $taxObj = get_taxonomy($taxonomy);
            if (!$taxObj instanceof WP_Taxonomy) {
                continue;
            }

            if ($taxObj->hierarchical) {
                // Expect array of term IDs.
                if (!is_array($value)) {
                    continue;
                }

                $termIds = array_map(static function ($id) {
                    return absint($id);
                }, $value);

                $termIds = array_values(array_filter($termIds, static fn($id) => $id > 0));

                $sanitizedTaxInput[$taxonomy] = $termIds;
            } else {
                // Expect comma-separated string of tags (names) from post_tags_meta_box.
                if (is_array($value)) {
                    $value = implode(',', array_map('sanitize_text_field', array_map('wp_unslash', $value)));
                } else {
                    $value = sanitize_text_field(wp_unslash((string) $value));
                }

                $sanitizedTaxInput[$taxonomy] = $value;
            }
        }

        /**
         * Call your Beacon API here (dashboard will use post type + tax context).
         * Replace this stub with ApiClient usage.
         */
        $generated = $this->fakeGenerateResponse($postType, $prompt, $sanitizedTaxInput);
        if (is_wp_error($generated)) {
            wp_die($generated->get_error_message());
        }

        $postId = wp_insert_post([
                'post_type'    => $postType,
                'post_status'  => 'draft',
                'post_title'   => $generated['title'] ?? 'Generated Draft',
                'post_content' => $generated['content'] ?? '',
        ], true);

        if ($postId instanceof WP_Error) {
            wp_die($postId->get_error_message());
        }

        // Apply taxonomies the same way the editor does.
        $this->applyTaxInputToPost($postId, $postType, $sanitizedTaxInput);

        wp_safe_redirect(admin_url('post.php?post=' . absint($postId) . '&action=edit'));
        exit;
    }

    public function render(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to access this page.', 'digital-royalty'));
        }

        $backUrl = add_query_arg([
                'page' => AdminPageEnum::TOOLS,
        ], admin_url('admin.php'));

        $postTypes = get_post_types([
                'public'  => true,
                'show_ui' => true,
        ], 'objects');

        $selectedPostType = isset($_GET['dr_beacon_post_type'])
                ? sanitize_text_field(wp_unslash($_GET['dr_beacon_post_type']))
                : 'post';

        if (!post_type_exists($selectedPostType)) {
            $selectedPostType = 'post';
        }

        // Ensure editor meta box callbacks exist.
        require_once ABSPATH . 'wp-admin/includes/meta-boxes.php';
        require_once ABSPATH . 'wp-admin/includes/template.php';

        $selfUrlBase = add_query_arg([
                'page' => AdminPageEnum::TOOLS,
                'tool' => $this->slug(),
        ], admin_url('admin.php'));

        ?>
        <div class="wrap dr-beacon-content-generator">
            <p>
                <a href="<?php echo esc_url($backUrl); ?>" class="button">← Back to Tools</a>
            </p>

            <h1><?php echo esc_html($this->title()); ?></h1>
            <p class="description"><?php echo esc_html($this->description()); ?></p>

            <!-- GET: switch post type (reload page) -->
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="max-width: 920px;">
                <input type="hidden" name="page" value="<?php echo esc_attr(AdminPageEnum::TOOLS); ?>">
                <input type="hidden" name="tool" value="<?php echo esc_attr($this->slug()); ?>">

                <table class="form-table" role="presentation" style="margin-top:0;">
                    <tr>
                        <th scope="row">
                            <label for="dr_beacon_post_type"><?php esc_html_e('Post Type', 'digital-royalty'); ?></label>
                        </th>
                        <td>
                            <select
                                    name="dr_beacon_post_type"
                                    id="dr_beacon_post_type"
                                    required
                                    onchange="this.form.submit();"
                            >
                                <?php foreach ($postTypes as $postType): ?>
                                    <option value="<?php echo esc_attr($postType->name); ?>" <?php selected($selectedPostType, $postType->name); ?>>
                                        <?php echo esc_html($postType->labels->singular_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <p class="description">
                                <?php esc_html_e('Changing this reloads the page to show the relevant taxonomies.', 'digital-royalty'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </form>

            <!-- POST: generate content -->
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(ContentGeneratorAdminActions::ACTION); ?>">
                <?php wp_nonce_field(ContentGeneratorAdminActions::ACTION, 'dr_beacon_nonce'); ?>

                <input type="hidden" name="post_type" value="<?php echo esc_attr($selectedPostType); ?>">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="prompt"><?php esc_html_e('Optional prompt', 'digital-royalty'); ?></label>
                        </th>
                        <td>
                        <textarea
                                name="prompt"
                                id="prompt"
                                rows="5"
                                class="large-text"
                                placeholder="Optional. Leave blank to let Beacon generate using site context."
                        ></textarea>
                        </td>
                    </tr>
                </table>

                <?php $this->renderTaxonomyMetaBoxes($selectedPostType); ?>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Generate Draft', 'digital-royalty'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }


    private function renderTaxonomyMetaBoxes(string $postType): void
    {
        $taxonomies = get_object_taxonomies($postType, 'objects');
        if (empty($taxonomies)) {
            return;
        }

        // Dummy post-like object, ID 0 is fine for UI rendering.
        $post = (object) [
                'ID'        => 0,
                'post_type' => $postType,
        ];

        // Use the current screen ID to register meta boxes locally.
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $screenId = $screen ? $screen->id : 'settings_page_dr-beacon';

        // Add one meta box per taxonomy, using core callbacks.
        foreach ($taxonomies as $tax) {
            if (!$tax instanceof WP_Taxonomy) {
                continue;
            }

            if (empty($tax->show_ui)) {
                continue;
            }

            if ($tax->hierarchical) {
                add_meta_box(
                        'taxonomy-' . $tax->name,
                        esc_html($tax->labels->name),
                        'post_categories_meta_box',
                        $screenId,
                        'advanced',
                        'default',
                        ['taxonomy' => $tax->name]
                );
            } else {
                add_meta_box(
                        'tagsdiv-' . $tax->name,
                        esc_html($tax->labels->name),
                        'post_tags_meta_box',
                        $screenId,
                        'advanced',
                        'default',
                        ['taxonomy' => $tax->name]
                );
            }
        }

        ?>
        <h2 style="margin-top:18px;"><?php esc_html_e('Taxonomies', 'digital-royalty'); ?></h2>
        <p class="description">
            <?php esc_html_e('These selections help Beacon tighten the generated content. This UI matches the editor.', 'digital-royalty'); ?>
        </p>
        <div id="poststuff">
            <div id="post-body" class="metabox-holder">
                <div id="post-body-content"></div>
                <div id="postbox-container-1" class="postbox-container" style="float:none;width:auto;">
                    <?php
                    // Output "advanced" context boxes in a single column.
                    do_meta_boxes($screenId, 'advanced', $post);
                    ?>
                </div>
            </div>
            <br class="clear" />
        </div>
        <?php
    }

    private function applyTaxInputToPost(int $postId, string $postType, array $taxInput): void
    {
        $allowedTaxonomies = get_object_taxonomies($postType, 'names');

        foreach ($taxInput as $taxonomy => $value) {
            if (!in_array($taxonomy, $allowedTaxonomies, true)) {
                continue;
            }

            $taxObj = get_taxonomy($taxonomy);
            if (!$taxObj instanceof WP_Taxonomy) {
                continue;
            }

            if ($taxObj->hierarchical) {
                // Term IDs.
                $termIds = is_array($value) ? array_map('absint', $value) : [];
                $termIds = array_values(array_filter($termIds, static fn($id) => $id > 0));

                if (!empty($termIds)) {
                    wp_set_object_terms($postId, $termIds, $taxonomy, false);
                }
            } else {
                // Comma-separated names.
                $names = array_filter(array_map('trim', explode(',', (string) $value)));
                if (!empty($names)) {
                    wp_set_object_terms($postId, $names, $taxonomy, false);
                }
            }
        }
    }

    /**
     * Replace this with your real ApiClient call.
     */
    private function fakeGenerateResponse(string $postType, string $prompt, array $taxInput)
    {
        $titleBits = ['Generated', ucfirst($postType)];
        if (!empty($prompt)) {
            $titleBits[] = 'From Prompt';
        }

        $title = implode(' ', $titleBits);

        $content = "This is placeholder content.\n\n";
        $content .= "Post type: " . $postType . "\n";
        $content .= "Prompt provided: " . (!empty($prompt) ? 'yes' : 'no') . "\n";
        $content .= "Tax context: " . wp_json_encode($taxInput) . "\n";

        return [
                'title'   => $title,
                'content' => $content,
        ];
    }
}
