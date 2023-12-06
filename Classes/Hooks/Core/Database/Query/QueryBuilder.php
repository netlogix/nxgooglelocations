<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Hooks\Core\Database\Query;

use TYPO3\CMS\Core\Database\Query\QueryBuilder as CoreQueryBuilder;

class QueryBuilder extends CoreQueryBuilder
{
    /**
     * In contrast to the core QueryBuilder::addSelect(), this one skips
     * the "quoteIdentifiersForSelect" step.
     *
     * @see \TYPO3\CMS\Core\Database\Query\QueryBuilder::addSelect
     */
    public static function addUnquotedSelect(CoreQueryBuilder $queryBuilder, string ...$selects): CoreQueryBuilder
    {
        $queryBuilder->concreteQueryBuilder->addSelect(...$selects);

        return $queryBuilder;
    }
}
