<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Domain\Model;

enum CodingResultProbability: int
{
    case ZERO_RESULTS = -1;
    case MANUALLY_IMPORT = -255;
    case MANUALLY_EDITOR = -256;
}
