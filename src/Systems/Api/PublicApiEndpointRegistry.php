<?php

namespace DigitalRoyalty\Beacon\Systems\Api;

/**
 * Static registry of all public API endpoints.
 *
 * This is the single source of truth for:
 * - The admin endpoint toggle UI
 * - The public-facing API docs page
 * - Route registration (endpoints only activate when enabled)
 *
 * Each entry describes one logical endpoint. The 'key' is a stable identifier
 * stored in wp_options to track enabled/disabled state.
 */
final class PublicApiEndpointRegistry
{
    public const OPTION_ENABLED = 'dr_beacon_api_enabled_endpoints';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            // ── Posts ─────────────────────────────────────────────────────
            [
                'key'         => 'posts.list',
                'group'       => 'Posts',
                'method'      => 'GET',
                'path'        => '/posts',
                'title'       => 'List posts',
                'description' => 'Returns a paginated list of published posts.',
                'parameters'  => [
                    ['name' => 'page',     'type' => 'integer', 'required' => false, 'description' => 'Page number. Default: 1'],
                    ['name' => 'per_page', 'type' => 'integer', 'required' => false, 'description' => 'Results per page. Default: 20, max: 100'],
                    ['name' => 'search',   'type' => 'string',  'required' => false, 'description' => 'Filter results by search term'],
                ],
                'response_example' => '{ "data": [ { "id": "post-123", "title": "Hello World", "slug": "hello-world", "status": "published", "excerpt": "...", "published_at": "2025-01-01T00:00:00Z" } ], "total": 42, "page": 1, "per_page": 20 }',
            ],
            [
                'key'         => 'posts.get',
                'group'       => 'Posts',
                'method'      => 'GET',
                'path'        => '/posts/{id}',
                'title'       => 'Get a post',
                'description' => 'Returns a single post by its ID.',
                'parameters'  => [
                    ['name' => 'id', 'type' => 'string', 'required' => true, 'description' => 'The post identifier'],
                ],
                'response_example' => '{ "id": "post-123", "title": "Hello World", "slug": "hello-world", "content": "...", "status": "published", "categories": ["news"], "tags": ["announcement"], "published_at": "2025-01-01T00:00:00Z" }',
            ],
            [
                'key'         => 'posts.create',
                'group'       => 'Posts',
                'method'      => 'POST',
                'path'        => '/posts',
                'title'       => 'Create a post',
                'description' => 'Creates a new post. Requires an API key with write access.',
                'parameters'  => [
                    ['name' => 'title',   'type' => 'string',  'required' => true,  'description' => 'Post title'],
                    ['name' => 'content', 'type' => 'string',  'required' => false, 'description' => 'Post body content (HTML or plain text)'],
                    ['name' => 'status',  'type' => 'string',  'required' => false, 'description' => 'Post status: draft or published. Default: draft'],
                    ['name' => 'excerpt', 'type' => 'string',  'required' => false, 'description' => 'Short summary of the post'],
                ],
                'response_example' => '{ "id": "post-456", "title": "My New Post", "status": "draft", "created_at": "2025-06-01T12:00:00Z" }',
            ],
            [
                'key'         => 'posts.update',
                'group'       => 'Posts',
                'method'      => 'PATCH',
                'path'        => '/posts/{id}',
                'title'       => 'Update a post',
                'description' => 'Updates one or more fields on an existing post. Only provided fields are changed.',
                'parameters'  => [
                    ['name' => 'id',      'type' => 'string', 'required' => true,  'description' => 'The post identifier'],
                    ['name' => 'title',   'type' => 'string', 'required' => false, 'description' => 'New post title'],
                    ['name' => 'content', 'type' => 'string', 'required' => false, 'description' => 'New post body'],
                    ['name' => 'status',  'type' => 'string', 'required' => false, 'description' => 'New status: draft or published'],
                    ['name' => 'excerpt', 'type' => 'string', 'required' => false, 'description' => 'New excerpt'],
                ],
                'response_example' => '{ "success": true }',
            ],
            [
                'key'         => 'posts.delete',
                'group'       => 'Posts',
                'method'      => 'DELETE',
                'path'        => '/posts/{id}',
                'title'       => 'Delete a post',
                'description' => 'Moves a post to trash. This action can be reversed from the WordPress admin.',
                'parameters'  => [
                    ['name' => 'id', 'type' => 'string', 'required' => true, 'description' => 'The post identifier'],
                ],
                'response_example' => '{ "success": true }',
            ],

            // ── Pages ─────────────────────────────────────────────────────
            [
                'key'         => 'pages.list',
                'group'       => 'Pages',
                'method'      => 'GET',
                'path'        => '/pages',
                'title'       => 'List pages',
                'description' => 'Returns a paginated list of published pages.',
                'parameters'  => [
                    ['name' => 'page',     'type' => 'integer', 'required' => false, 'description' => 'Page number. Default: 1'],
                    ['name' => 'per_page', 'type' => 'integer', 'required' => false, 'description' => 'Results per page. Default: 20, max: 100'],
                    ['name' => 'parent',   'type' => 'string',  'required' => false, 'description' => 'Filter by parent page ID'],
                ],
                'response_example' => '{ "data": [ { "id": "page-10", "title": "About Us", "slug": "about-us", "status": "published" } ], "total": 12, "page": 1, "per_page": 20 }',
            ],
            [
                'key'         => 'pages.get',
                'group'       => 'Pages',
                'method'      => 'GET',
                'path'        => '/pages/{id}',
                'title'       => 'Get a page',
                'description' => 'Returns a single page by its ID.',
                'parameters'  => [
                    ['name' => 'id', 'type' => 'string', 'required' => true, 'description' => 'The page identifier'],
                ],
                'response_example' => '{ "id": "page-10", "title": "About Us", "slug": "about-us", "content": "...", "status": "published", "parent": null }',
            ],
            [
                'key'         => 'pages.create',
                'group'       => 'Pages',
                'method'      => 'POST',
                'path'        => '/pages',
                'title'       => 'Create a page',
                'description' => 'Creates a new page.',
                'parameters'  => [
                    ['name' => 'title',   'type' => 'string', 'required' => true,  'description' => 'Page title'],
                    ['name' => 'content', 'type' => 'string', 'required' => false, 'description' => 'Page body content'],
                    ['name' => 'status',  'type' => 'string', 'required' => false, 'description' => 'Page status: draft or published. Default: draft'],
                    ['name' => 'parent',  'type' => 'string', 'required' => false, 'description' => 'Parent page identifier'],
                ],
                'response_example' => '{ "id": "page-99", "title": "New Page", "status": "draft" }',
            ],
            [
                'key'         => 'pages.update',
                'group'       => 'Pages',
                'method'      => 'PATCH',
                'path'        => '/pages/{id}',
                'title'       => 'Update a page',
                'description' => 'Updates one or more fields on an existing page.',
                'parameters'  => [
                    ['name' => 'id',      'type' => 'string', 'required' => true,  'description' => 'The page identifier'],
                    ['name' => 'title',   'type' => 'string', 'required' => false, 'description' => 'New title'],
                    ['name' => 'content', 'type' => 'string', 'required' => false, 'description' => 'New body content'],
                    ['name' => 'status',  'type' => 'string', 'required' => false, 'description' => 'New status'],
                    ['name' => 'parent',  'type' => 'string', 'required' => false, 'description' => 'New parent page identifier'],
                ],
                'response_example' => '{ "success": true }',
            ],

            // ── Site ──────────────────────────────────────────────────────
            [
                'key'         => 'site.get',
                'group'       => 'Site',
                'method'      => 'GET',
                'path'        => '/site',
                'title'       => 'Get site info',
                'description' => 'Returns general information about this site: name, description, URL, and timezone.',
                'parameters'  => [],
                'response_example' => '{ "name": "My Website", "description": "Just another WordPress site", "url": "https://example.com", "timezone": "Europe/London", "language": "en-GB" }',
            ],
        ];
    }

    /**
     * @return string[]
     */
    public static function enabledKeys(): array
    {
        $stored = get_option(self::OPTION_ENABLED, []);

        return is_array($stored) ? $stored : [];
    }

    public static function isEnabled(string $key): bool
    {
        return in_array($key, self::enabledKeys(), true);
    }

    /**
     * @param string[] $keys
     */
    public static function setEnabled(array $keys): void
    {
        $valid = array_column(self::all(), 'key');
        $keys  = array_values(array_intersect($keys, $valid));

        update_option(self::OPTION_ENABLED, $keys, false);
    }

    /**
     * Returns all endpoints annotated with their enabled state.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function allWithState(): array
    {
        $enabled = self::enabledKeys();

        return array_map(static function (array $ep) use ($enabled): array {
            $ep['enabled'] = in_array($ep['key'], $enabled, true);
            return $ep;
        }, self::all());
    }
}
