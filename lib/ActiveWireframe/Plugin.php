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
use ActiveWireframe\Pimcore\Console\Command\Web2PrintPdfCreationCommand;
use Pimcore\API\Plugin as PluginLib;
use Pimcore\Model\Asset;
use Pimcore\Model\Element\ValidationException;

/**
 * Class Plugin
 *
 * @package ActiveWireframe
 */
class Plugin extends PluginLib\AbstractPlugin implements PluginLib\PluginInterface
{
    const PLUGIN_NAME = "ActiveWireframe";

    const PLUGIN_PATH = PIMCORE_PLUGINS_PATH . DIRECTORY_SEPARATOR . self::PLUGIN_NAME;

    const PLUGIN_PATH_DATA = PIMCORE_WEBSITE_PATH . "/plugins-data/" . self::PLUGIN_NAME;

    const PLUGIN_VAR_PATH = PIMCORE_WEBSITE_VAR . "/plugins/" . self::PLUGIN_NAME;

    const PLUGIN_VAR_PATH_INSTALL = self::PLUGIN_VAR_PATH . "/install.json";

    const ASSET_PAGES_TEMPLATES = "/active-wireframe";

    const AREA_WIREFRAME = PIMCORE_WEBSITE_PATH . '/views/areas/active-wireframe';

    /**
     * @var bool
     */
    public static $_needsReloadAfterInstall = false;

    /**
     * @static
     * @return string
     */
    public static function needsReloadAfterInstall()
    {
        return self::$_needsReloadAfterInstall;
    }

    /**
     * @static
     * @return mixed
     */
    public static function install()
    {
        if (!self::composerExists()) {
            return 'ERROR: Active Publishing Composer does not exist.';
        }

        try {

            self::installTranslationAdmin(self::PLUGIN_PATH . "/static/install/texts.csv");
            self::installTable(self::PLUGIN_PATH . "/static/install/tables.json");
            self::installDocType(self::PLUGIN_PATH . "/static/install/doctypes.json");
            self::installAreas(self::PLUGIN_PATH . "/static/install/areas.zip", self::AREA_WIREFRAME);
            self::createThumbnails();
            self::createDirectoryTemplates();

            APS_Plugin::createLogInstall(1, self::PLUGIN_VAR_PATH_INSTALL);

        } catch (\Exception $ex) {
            return $ex->getMessage();
        }

        self::$_needsReloadAfterInstall = true;
        return 'Install success.';
    }

    /**
     * @static
     * @return bool
     */
    public static function composerExists()
    {
        return file_exists(PIMCORE_DOCUMENT_ROOT . "/vendor/activepublishing");
    }

    /**
     * Create thumbnails
     *
     * @static
     * @throws \Exception
     */
    private static function createThumbnails()
    {
        // Add thumbnail active-wireframe-preview
        if (!Asset\Image\Thumbnail\Config::getByAutoDetect("active-wireframe-preview")) {
            $pipe = new Asset\Image\Thumbnail\Config();
            $pipe->setName("active-wireframe-preview");
            $pipe->setQuality(90);
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
    }

    /**
     * Create directory pages template
     *
     * @static
     * @throws \Exception
     */
    private static function createDirectoryTemplates()
    {
        if (!Asset\Service::pathExists(self::ASSET_PAGES_TEMPLATES)) {

            // Befor v2.6.1
            if (Asset\Service::pathExists("/gabarits-de-pages")
                and $assetFolder = Asset::getByPath("/gabarits-de-pages")
            ) {
                $assetFolder->setFilename('active-wireframe');
                $assetFolder->save();
            } else {
                Asset\Service::createFolderByPath(self::ASSET_PAGES_TEMPLATES);
            }
        }
    }

    /**
     * Add area directory
     *
     * @static
     * @param $zip
     * @param $dst
     * @throws \Exception
     */
    public static function installAreas($zip, $dst)
    {
        if (!file_exists($zip) ) {
            Throw new \Exception("File not exist");
        }

        if (!file_exists($dst)) {
            if (!File::decompressZip($zip, $dst)) {
                Throw new \Exception("Decompression of zip is failed");
            }
        }
    }

    /**
     * Install Doctype
     *
     * @static
     * @param $jsonDefinition
     * @throws \Exception
     */
    public static function installDocType($jsonDefinition)
    {
        if (!file_exists($jsonDefinition)) {
            Throw new \Exception("File not exist");
        }

        if (!DocType::createDocTypeFromJson($jsonDefinition)) {
            Throw new \Exception("Creation doctypes is failed");
        }
    }

    /**
     * Install Table in database
     *
     * @static
     * @param $jsonDefinition
     * @throws \Exception
     */
    public static function installTable($jsonDefinition)
    {
        if (!file_exists($jsonDefinition)) {
            Throw new \Exception("File not exist");
        }

        if (!Table::getInstance()->createTableOrUpdateFromJson($jsonDefinition)) {
            Throw new \Exception("Creation table in database is failed");
        }
    }

    /**
     * Install translation Admin
     *
     * @static
     * @param $translationFile
     * @return array|bool
     * @throws \Exception
     */
    public static function installTranslationAdmin($translationFile)
    {
        if(!file_exists($translationFile)) {
            Throw new \Exception("File not exist");
        }

        return Translation::createFromFile($translationFile);
    }

    /**
     * @static
     * @return string
     */
    public static function uninstall()
    {
        if (!self::composerExists()) {
            return 'ERROR: Active Publishing Composer does not exist.';
        }

        try {
            APS_Plugin::createLogInstall(0, self::PLUGIN_VAR_PATH_INSTALL);
        } catch (\Exception $ex) {
            return $ex->getMessage();
        }

        self::$_needsReloadAfterInstall = true;
        return 'Uninstall success !';
    }

    /**
     * @static
     * @return bool
     */
    public static function isInstalled()
    {
        if (file_exists(self::PLUGIN_VAR_PATH_INSTALL)) {
            $conf = new \Zend_Config_Json(self::PLUGIN_VAR_PATH_INSTALL);
            return intval($conf->get('installed'));
        }

        return 0;
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
