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

use ActivePublishing\Service\DocType;
use ActivePublishing\Service\File;
use ActivePublishing\Service\Table;
use ActivePublishing\Service\Translation;
use ActivePublishing\Service\Plugin as APS_Plugin;
use ActiveWireframe\Console\Command\Web2PrintPdfCreationCommand;
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

    const PLUGIN_WEBSITE_PATH = PIMCORE_WEBSITE_PATH . "/activepublishing/" . self::PLUGIN_NAME;

    const PLUGIN_VAR_PATH = PIMCORE_WEBSITE_VAR . "/plugins/" . self::PLUGIN_NAME;

    const PLUGIN_VAR_PATH_INSTALL = self::PLUGIN_VAR_PATH . "/install.json";

    const ASSET_PAGES_TEMPLATES = "/active-wireframe";

    const AREA_WIREFRAME = PIMCORE_WEBSITE_PATH . '/views/areas/active-wireframe';

    /**
     * @var bool
     */
    public static $_needsReloadAfterInstall = false;

    /**
     * @return string
     */
    public static function needsReloadAfterInstall()
    {
        return self::$_needsReloadAfterInstall;
    }

    /**
     * @throws PluginLib\Exception
     */
    public static function composerExists()
    {
        if (!file_exists(PIMCORE_DOCUMENT_ROOT . "/vendor/activepublishing/pimcore-lib")) {
            throw new PluginLib\Exception('Please install Composer lib "activepublishing/pimcore-lib" before continuing');
        }
    }

    /**
     * @return mixed
     */
    public static function install()
    {
        self::composerExists();

        try {

            Translation::createFromFile(self::PLUGIN_PATH . "/static/install/texts.csv");
            Table::getInstance()->createTableOrUpdateFromJson(self::PLUGIN_PATH . "/static/install/tables.json");
            DocType::createDocTypeFromJson(self::PLUGIN_PATH . "/static/install/doctypes.json");

            self::UpdateArch();
            self::installAreas(self::PLUGIN_PATH . "/static/install/areas.zip", self::AREA_WIREFRAME);
            self::createThumbnails();
            self::createDirectoryTemplates();

            APS_Plugin::createLogInstall(1, self::PLUGIN_VAR_PATH_INSTALL);

            self::$_needsReloadAfterInstall = true;

        } catch (\Exception $ex) {
            return $ex->getMessage();
        }

        return 'Install success.';
    }

    /**
     * Ajouts les areas par défaut s'il ne sont pas présent
     *
     * @param $zip
     * @param $dst
     * @throws \Exception
     */
    public static function installAreas($zip, $dst)
    {
        if (file_exists($zip) and !file_exists($dst)) {
            if (!File::decompressZip($zip, $dst)) {
                Throw new \Exception("Decompression of zip is failed");
            }
        }
    }

    /**
     * Création des vignettes
     *
     * @throws \Exception
     */
    private static function createThumbnails()
    {
        // active-wireframe-preview
        if (!Asset\Image\Thumbnail\Config::getByAutoDetect("active-wireframe-preview")) {
            $pipe = new Asset\Image\Thumbnail\Config();
            $pipe->setName("active-wireframe-preview");
            $pipe->setQuality(75);
            $pipe->setFormat("PNG");
            $pipe->save();
        }

        // active-wireframe-print
        if (!Asset\Image\Thumbnail\Config::getByAutoDetect("active-wireframe-print")) {
            $pipe = new Asset\Image\Thumbnail\Config();
            $pipe->setName("active-wireframe-print");
            $pipe->setQuality(100);
            $pipe->setFormat("PNG");
            $pipe->setHighResolution(2);
            $pipe->save();
        }
    }

    /**
     * Création du dossier pour les templates de page
     *
     * @throws \Exception
     */
    private static function createDirectoryTemplates()
    {
        if (!Asset\Service::pathExists(self::ASSET_PAGES_TEMPLATES)) {
            Asset\Service::createFolderByPath(self::ASSET_PAGES_TEMPLATES);
        }
    }

    /**
     * Mise à jour de l'architecture ActiveWireframe
     */
    private static function UpdateArch()
    {
        // Avant v2.6.1
        if (Asset\Service::pathExists("/gabarits-de-pages") and $assetFolder = Asset::getByPath("/gabarits-de-pages")) {
            $assetFolder->setFilename('active-wireframe');
            $assetFolder->save();
        }

        // Avant v2.6.4
        if (file_exists(PIMCORE_WEBSITE_PATH . DIRECTORY_SEPARATOR . "plugins-data")) {
            File::cp(PIMCORE_WEBSITE_PATH . DIRECTORY_SEPARATOR . "plugins-data", self::PLUGIN_VAR_PATH);
            File::rm(PIMCORE_WEBSITE_PATH . DIRECTORY_SEPARATOR . "plugins-data");
        }
    }

    /**
     * @return string
     */
    public static function uninstall()
    {
        self::composerExists();

        try {

            APS_Plugin::createLogInstall(0, self::PLUGIN_VAR_PATH_INSTALL);

            self::$_needsReloadAfterInstall = true;

        } catch (\Exception $ex) {
            return $ex->getMessage();
        }

        return 'Uninstall success !';
    }

    /**
     * @return bool
     */
    public static function isInstalled()
    {
        if (file_exists(self::PLUGIN_VAR_PATH_INSTALL)) {
            $conf = new \Zend_Config_Json(self::PLUGIN_VAR_PATH_INSTALL);
            return intval($conf->get('installed'));
        }

        return false;
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
                Handler::getInstance()->postAdd($e);
            } catch (\Exception $ex) {
                throw new ValidationException($ex->getMessage(), $ex->getCode());
            }
        });

        // document.postUpdate
        \Pimcore::getEventManager()->attach("document.postUpdate", function (\Zend_EventManager_Event $e) {
            try {
                Handler::getInstance()->postUpdate($e);
            } catch (\Exception $ex) {
                throw new ValidationException($ex->getMessage(), $ex->getCode());
            }
        });

        // document.postDelete
        \Pimcore::getEventManager()->attach("document.postDelete", function (\Zend_EventManager_Event $e) {
            try {
                Handler::getInstance()->postDelete($e);
            } catch (\Exception $ex) {
                throw new ValidationException($ex->getMessage(), $ex->getCode());
            }
        });

        // add a single command for webtoprint
        \Pimcore::getEventManager()->attach('system.console.init', function (\Zend_EventManager_Event $e) {
            $application = $e->getTarget();
            $application->add(new Web2PrintPdfCreationCommand());
        });
    }

}
