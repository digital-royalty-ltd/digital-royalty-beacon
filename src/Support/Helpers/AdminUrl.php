<?php

namespace DigitalRoyalty\Beacon\Support\Helpers;


use DigitalRoyalty\Beacon\Support\Enums\Admin\Screens\ScreenEnum;

final class AdminUrl
{
    public static function screen(string $screenSlug, array $query = []): string
    {
        return add_query_arg(
            array_merge(['page' => $screenSlug], $query),
            admin_url('admin.php')
        );
    }

    public static function tool(string $toolSlug, array $query = []): string
    {
        return add_query_arg(
            array_merge([
                'page' => ScreenEnum::TOOLS,
                'tool' => $toolSlug,
            ], $query),
            admin_url('admin.php')
        );
    }
}
