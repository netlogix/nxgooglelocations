<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Domain\Model;

enum BatchState: string
{
    case NEW = 'new';
    case VALIDATING = 'validating';
    case GEOCODING = 'running';
    case PERSISTING = 'persisting';
    case CLOSED = 'closed';
}
