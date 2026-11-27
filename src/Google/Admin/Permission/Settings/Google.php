<?php

namespace Nails\Cdn\Driver\Google\Admin\Permission\Settings;

use Nails\Admin\Interfaces\Permission;

class Google implements Permission
{
    public function label(): string
    {
        return 'Can manage CDN: Google Cloud Storage settings';
    }

    public function group(): string
    {
        return 'Settings';
    }
}
