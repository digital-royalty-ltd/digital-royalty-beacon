<?php

namespace DigitalRoyalty\Beacon\Admin\Views\Tools;

use DigitalRoyalty\Beacon\Admin\Actions\Views\Tools\ContentGeneratorAdminActions;
use DigitalRoyalty\Beacon\Admin\Views\ViewInterface;
use DigitalRoyalty\Beacon\Support\Enums\Admin\AdminPageEnum;
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

    public function render(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to access this page.', 'digital-royalty'));
        }

        $okParam = isset($_GET['dr_beacon_ok']) ? (string) $_GET['dr_beacon_ok'] : '0';
        $isOk = $okParam === '1';
        $msg = isset($_GET['dr_beacon_msg']) ? (string) $_GET['dr_beacon_msg'] : '';

        if ($msg !== '') {
            ?>
            <div class="notice notice-<?php echo esc_attr($isOk ? 'success' : 'error'); ?> is-dismissible">
                <p><?php echo esc_html($msg); ?></p>
            </div>
            <?php
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
