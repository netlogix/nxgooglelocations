<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Service;

use TYPO3\CMS\Backend\Configuration\TypoScript\ConditionMatching\ConditionMatcher as BackendConditionMatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Extbase\Configuration\BackendConfigurationManager;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;
use TYPO3\CMS\Frontend\Configuration\TypoScript\ConditionMatching\ConditionMatcher as FrontendConditionMatcher;

class FrontendSettingsForBackendProvider
{
    /**
     * @var array<int, array<string, mixed>
     */
    protected static $configurationCache = [];

    /**
     * @return array<string, mixed>
     */
    public static function getConfigurationForPageId(int $pageId): array
    {
        if (isset(self::$configurationCache[$pageId])) {
            return self::$configurationCache[$pageId];
        }

        $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $pageId);
        assert($rootlineUtility instanceof RootlineUtility);
        $rootline = $rootlineUtility->get();

        self::prepareFrontendConditionMatcher($pageId, $rootline);
        self::prepareBackendConfigurationManager($pageId, $rootline);

        $backendConfigurationManager = GeneralUtility::makeInstance(BackendConfigurationManager::class);
        assert($backendConfigurationManager instanceof BackendConfigurationManager);
        $resetConfigurationCache = self::clearConfigurationCache($backendConfigurationManager);

        $result = $backendConfigurationManager->getConfiguration();
        self::$configurationCache[$pageId] = $result['settings'] ?? [];

        $resetConfigurationCache();

        return self::$configurationCache[$pageId];
    }

    /**
     * @param array<int, array<string, mixed>> $rootline
     */
    protected static function prepareFrontendConditionMatcher(int $pageId, array $rootline): void
    {
        $frontendConditionMatcher = GeneralUtility::makeInstance(FrontendConditionMatcher::class);
        assert($frontendConditionMatcher instanceof FrontendConditionMatcher);
        $frontendConditionMatcher->setPageId($pageId);
        $frontendConditionMatcher->setRootline($rootline);
        GeneralUtility::addInstance(FrontendConditionMatcher::class, $frontendConditionMatcher);
    }

    /**
     * @param array<int, array<string, mixed>> $rootline
     */
    protected static function prepareBackendConfigurationManager(int $pageId, array $rootline): void
    {
        $backendConditionMatcher = GeneralUtility::makeInstance(BackendConditionMatcher::class);
        assert($backendConditionMatcher instanceof BackendConditionMatcher);
        $backendConditionMatcher->setPageId($pageId);
        $backendConditionMatcher->setRootline($rootline);
        GeneralUtility::addInstance(BackendConditionMatcher::class, $backendConditionMatcher);
    }

    protected static function clearConfigurationCache(BackendConfigurationManager $configurationManager): callable
    {
        $typoScriptSetupCache = ObjectAccess::getProperty($configurationManager, 'typoScriptSetupCache', true);
        ObjectAccess::setProperty($configurationManager, 'typoScriptSetupCache', [], true);

        $configurationCache = ObjectAccess::getProperty($configurationManager, 'configurationCache', true);
        ObjectAccess::setProperty($configurationManager, 'configurationCache', [], true);

        return static function () use ($typoScriptSetupCache, $configurationCache, $configurationManager) {
            ObjectAccess::setProperty($configurationManager, 'typoScriptSetupCache', $typoScriptSetupCache, true);
            ObjectAccess::setProperty($configurationManager, 'configurationCache', $configurationCache, true);
        };
    }
}
