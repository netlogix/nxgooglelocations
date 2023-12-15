<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Enumerations;

use TYPO3\CMS\Core\Type\Enumeration;

final class BatchState extends Enumeration
{
    /**
     * @var string
     */
    final public const __default = self::NEW;

    /**
     * @var string
     */
    final public const NEW = 'new';

    /**
     * @var string
     */
    final public const VALIDATING = 'validating';

    /**
     * @var string
     */
    final public const GEOCODING = 'running';

    /**
     * @var string
     */
    final public const PERSISTING = 'persisting';

    /**
     * @var string
     */
    final public const CLOSED = 'closed';
}
