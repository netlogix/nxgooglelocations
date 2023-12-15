<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Service;

enum GeoCoderStatus: string
{
    case OK = 'OK';
    case ZERO_RESULTS = 'ZERO_RESULTS';
}
