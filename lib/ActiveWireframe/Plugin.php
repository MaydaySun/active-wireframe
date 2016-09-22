<?php
/**
 * Active Publishing
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2014-2016 Active Publishing http://www.activepublishing.fr
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License version 3 (GPLv3)
 */
namespace ActiveWireframe;

use ActivePublishing\Plugin\Service;
use ActivePublishing\Services\DocType;
use ActivePublishing\Services\Table;
use ActiveWireframe\Pimcore\Console\Command\Web2PrintPdfCreationCommand;
use Pimcore\API\Plugin as PluginLib;
use Pimcore\Model\Asset;
use Pimcore\Model\Element\ValidationException;

/**
 * Class Plugin
 * @package ActiveWireframe
 */
class Plugin extends PluginLib\AbstractPlugin implements PluginLib\PluginInterface
{
    const PLUGIN_NAME = "ActiveWireframe";

    const PLUGIN_PATH = PIMCORE_PLUGINS_PATH . DIRECTORY_SEPARATOR . self::PLUGIN_NAME;

    const PLUGIN_PATH_STATIC = PIMCORE_WEBSITE_PATH . "/static-plugins/" . self::PLUGIN_NAME;

    const PLUGIN_PATH_INSTALL = self::PLUGIN_PATH . "/static/install";

    const PLUGIN_PATH_TRANSLATION = self::PLUGIN_PATH . "/static/texts";

    const PLUGIN_PATH_INSTALL_TABLES = self::PLUGIN_PATH_INSTALL . "/tables.json";

    const PLUGIN_PATH_INSTALL_DOCTYPES = self::PLUGIN_PATH_INSTALL . "/doctypes.json";

    const PLUGIN_PATH_INSTALL_AREAS = self::PLUGIN_PATH_INSTALL . "/areas";

    const PLUGIN_VAR_PATH = PIMCORE_WEBSITE_VAR . "/plugins/" . self::PLUGIN_NAME;

    const PLUGIN_VAR_PATH_INSTALL = self::PLUGIN_VAR_PATH . "/install.txt";

    const PLUGIN_VAR_PATH_UNINSTALL = self::PLUGIN_VAR_PATH . "/uninstall.txt";

    const AW_EXTENSION_URL = "http://plugins-extensions.activepublishing.fr?module=ActiveWireframe";

    public static $_needsReloadAfterInstall = false;

    /**
     * @return string
     */
    public static function needsReloadAfterInstall()
    {
        return self::$_needsReloadAfterInstall;
    }

    /**
     * @return mixed
     */
    public static function install()
    {
        if (!self::composerExists()) {
            return 'ERROR: Active Publishing Composer does not exist.';
        }

        try {
            $plugin = new Service();

            // Install translation plugin
            $plugin->createTranslation(self::PLUGIN_PATH_TRANSLATION, self::getPrefixTranslate());

            // Add area directory
            if (file_exists(self::PLUGIN_PATH_INSTALL_AREAS)) {
                $plugin->createAreas(self::PLUGIN_PATH_INSTALL_AREAS);
            }

            // Add Tables
            if (file_exists(self::PLUGIN_PATH_INSTALL_TABLES)) {
                Table::createFromJson(self::PLUGIN_PATH_INSTALL_TABLES);
            }

            // Add doctypes
            if (file_exists(self::PLUGIN_PATH_INSTALL_DOCTYPES)) {
                DocType::createFromJson(self::PLUGIN_PATH_INSTALL_DOCTYPES);
            }

            // Add thumbnail active-wireframe-preview
            if (!Asset\Image\Thumbnail\Config::getByAutoDetect("active-wireframe-preview")) {
                $pipe = new Asset\Image\Thumbnail\Config();
                $pipe->setName("active-wireframe-preview");
                $pipe->setQuality(80);
                $pipe->setFormat("PNG");
                $pipe->save();
            }

            // Add thumbnail active-wireframe-preview
            if (!Asset\Image\Thumbnail\Config::getByAutoDetect("active-wireframe-print")) {
                $pipe = new Asset\Image\Thumbnail\Config();
                $pipe->setName("active-wireframe-print");
                $pipe->setQuality(100);
                $pipe->setFormat("PNG");
                $pipe->setHighResolution(3.2);
                $pipe->save();
            }

            // Create template directory
            self::createDirectoryTemplates();

            $plugin->createFileInstall(true, self::PLUGIN_VAR_PATH_INSTALL, self::PLUGIN_VAR_PATH_UNINSTALL);
        } catch (\Exception $ex) {
            return $ex->getMessage();
        }

        self::$_needsReloadAfterInstall = true;
        return 'Install success.';
    }

    /**
     * @return bool
     */
    public static function composerExists()
    {
        return file_exists(PIMCORE_DOCUMENT_ROOT . "/vendor/activepublishing");
    }

    /**
     * @return string
     */
    private static function getPrefixTranslate()
    {
        return mb_strtolower(preg_replace('/(?=(?<!^)[[:upper:]])/', '_', self::PLUGIN_NAME) . "_");
    }

    /**
     * Create directory template
     * @throws \Exception
     */
    public static function createDirectoryTemplates()
    {
        // création du dossier asset Template
        $assetRoot = Asset\Folder::getByPath("/gabarits-de-pages");

        if (!$assetRoot instanceof Asset\Folder) {
            $fichierRacine = Asset::getById(1);
            Asset\Service::createFolderByPath($fichierRacine->getFullPath() . '/gabarits-de-pages');
        }
    }

    /**
     * @return string
     */
    public static function uninstall()
    {
        if (!self::composerExists()) {
            return 'ERROR: Active Publishing Composer does not exist.';
        }

        try {
            $plugin = new Service();

            // Uninstall translation
            $plugin->rmTranslation(self::PLUGIN_PATH_TRANSLATION, self::getPrefixTranslate());

            $plugin->createFileInstall(false, self::PLUGIN_VAR_PATH_INSTALL, self::PLUGIN_VAR_PATH_UNINSTALL);
        } catch (\Exception $ex) {
            return $ex->getMessage();
        }

        self::$_needsReloadAfterInstall = true;
        return 'Uninstall success !';
    }

    /**
     * @return bool
     */
    public static function isInstalled()
    {
        return file_exists(self::PLUGIN_VAR_PATH_INSTALL);
    }

    /**
     * @throws \Zend_EventManager_Exception_InvalidArgumentException
     */
    public function init()
    {
        parent::init();

        // document.postAdd
        \Pimcore::getEventManager()->attach("document.postAdd", function (\Zend_EventManager_Event $e) {
            try {
                $handler = new Handler();
                $handler->postAdd($e);
            } catch (\Exception $ex) {
                throw new ValidationException($ex->getMessage(), $ex->getCode());
            }
        });

        //document.postUpdate
        \Pimcore::getEventManager()->attach("document.postUpdate", function (\Zend_EventManager_Event $e) {
            try {
                $handler = new Handler();
                $handler->postUpdate($e);
            } catch (\Exception $ex) {
                throw new ValidationException($ex->getMessage(), $ex->getCode());
            }
        });

        // document.postDelete
        \Pimcore::getEventManager()->attach("document.postDelete", function (\Zend_EventManager_Event $e) {
            try {
                $handler = new Handler();
                $handler->postDelete($e);
            } catch (\Exception $ex) {
                throw new ValidationException($ex->getMessage(), $ex->getCode());
            }
        });

        // Console
        \Pimcore::getEventManager()->attach('system.console.init', function (\Zend_EventManager_Event $e) {
            $application = $e->getTarget();
            // add a single command
            $application->add(new Web2PrintPdfCreationCommand());
        });
    }

}
