<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Enumerations;

use TYPO3\CMS\Core\Type\Enumeration;

final class GeoCoderStatus extends Enumeration
{
    /**
     * @var string
     */
    final public const OK = 'OK';

    /**
     * @var string
     */
    final public const ZERO_RESULTS = 'ZERO_RESULTS';
}
