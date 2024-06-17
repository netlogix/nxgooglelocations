<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Enumerations;

use TYPO3\CMS\Core\Type\Enumeration;

final class BatchState extends Enumeration
{
    /**
     * @var string
     */
    public const __default = self::NEW;

    /**
     * @var string
     */
    public const NEW = 'new';

    /**
     * @var string
     */
    public const VALIDATING = 'validating';

    /**
     * @var string
     */
    public const GEOCODING = 'running';

    /**
     * @var string
     */
    public const PERSISTING = 'persisting';

    /**
     * @var string
     */
    public const CLOSED = 'closed';
}
