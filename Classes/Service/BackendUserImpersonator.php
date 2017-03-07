<?php
namespace Netlogix\Nxgooglelocations\Service;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BackendUserImpersonator
{
    public function runAsBackendUser($backendUserId, callable $callback)
    {
        $oldBackendUser = $GLOBALS['BE_USER'];

        unset($GLOBALS['BE_USER']);
        $backendUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $backendUser->setBeUserByUid($backendUserId);
        $backendUser->fetchGroupData();
        $backendUser->backendSetUC();
        $GLOBALS['BE_USER'] = $backendUser;

        $result = $callback();

        unset($GLOBALS['BE_USER']);
        $GLOBALS['BE_USER'] = $oldBackendUser;

        return $result;
    }
}