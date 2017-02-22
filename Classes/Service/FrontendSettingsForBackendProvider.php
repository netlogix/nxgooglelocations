<?php
namespace Netlogix\Nxgooglelocations\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\BackendConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Frontend\Page\PageRepository;

class FrontendSettingsForBackendProvider extends BackendConfigurationManager
{
    /**
     * @var ObjectManager
     */
    protected static $_objectManager;

    public static function getConfigurationForPageId($pageId)
    {
        self::assureObjectManager();
        self::resetBackendConfigurationManager();

        $oldTsfe = $GLOBALS['TSFE'];
        self::createTypoScriptFrontendController($pageId);

        $configurationManager = self::$_objectManager->get(ConfigurationManagerInterface::class);
        $settings = $configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS);

        if (!$oldTsfe) {
            unset($GLOBALS['TSFE']);
        } else {
            $GLOBALS['TSFE'] = $oldTsfe;
        }

        self::resetBackendConfigurationManager();
        return $settings;
    }

    protected static function resetBackendConfigurationManager()
    {
        $backendConfigurationManager = self::$_objectManager->get(BackendConfigurationManager::class);

        $backendConfigurationManager->configurationCache = [];
        $backendConfigurationManager->typoScriptSetupCache = [];
        $backendConfigurationManager->currentPageId = null;
    }

    protected static function assureObjectManager()
    {
        if (self::$_objectManager) {
            return;
        }
        self::$_objectManager = GeneralUtility::makeInstance(ObjectManager::class);
    }

    protected static function createTypoScriptFrontendController($pageId)
    {
        $pageRepository = self::$_objectManager->get(PageRepository::class);
        $GLOBALS['TSFE'] = new \stdClass();
        $GLOBALS['TSFE']->id = (string)$pageId;
        $GLOBALS['TSFE']->tmpl = new \stdClass();
        $GLOBALS['TSFE']->tmpl->rootLine = $pageRepository->getRootLine($pageId);
    }
}