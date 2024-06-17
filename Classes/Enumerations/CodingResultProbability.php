<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Enumerations;

use TYPO3\CMS\Core\Type\Enumeration;

final class CodingResultProbability extends Enumeration
{
    /**
     * @var int
     */
    public const ZERO_RESULTS = -1;

    /**
     * @var int
     */
    public const MANUALLY_IMPORT = -255;

    /**
     * @var int
     */
    public const MANUALLY_EDITOR = -256;
}
