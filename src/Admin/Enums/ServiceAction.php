<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Enums;

enum ServiceAction: string
{
    case Restart = 'restart';
    case Stop = 'stop';
    case Freeze = 'freeze';
    case Unfreeze = 'unfreeze';
}
