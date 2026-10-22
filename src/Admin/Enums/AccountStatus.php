<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Enums;

enum AccountStatus: string
{
    case Enabled = 'enabled';
    case Disabled = 'disabled';
}
