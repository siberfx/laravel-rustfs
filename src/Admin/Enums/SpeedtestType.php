<?php

declare(strict_types=1);

namespace Siberfx\RustFs\Admin\Enums;

enum SpeedtestType: string
{
    case Object = 'object';
    case Drive = 'drive';
    case Net = 'net';
    case Site = 'site';
}
