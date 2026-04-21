<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Enumeration;

enum BatchState: string
{
    case NEW = 'new';

    case VALIDATING = 'validating';

    case GEOCODING = 'running';

    case PERSISTING = 'persisting';

    case ERROR = 'error';

    case CLOSED = 'closed';
}
