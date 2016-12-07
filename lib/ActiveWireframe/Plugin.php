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

use ActivePublishing\Plugin\Service\Install;
use ActivePublishing\Service\Extension;
use ActiveWireframe\Pimcore\Console\Command\Web2PrintPdfCreationCommand;
use Pimcore\API\Plugin as PluginLib;
use Pimcore\Model\Asset;
use Pimcore\Model\Element\ValidationException;
use Pimcore\Tool\Session\Container;

/**
 * Class Plugin
 * @package ActiveWireframe
 */
class Plugin extends PluginLib\AbstractPlugin implements PluginLib\PluginInterface
{
    const PLUGIN_NAME = "ActiveWireframe";

    const PLUGIN_PATH = PIMCORE_PLUGINS_PATH . DIRECTORY_SEPARATOR . self::PLUGIN_NAME;

    const PLUGIN_PATH_DATA = PIMCORE_WEBSITE_PATH . "/plugins-data/" . self::PLUGIN_NAME;

    const PLUGIN_VAR_PATH = PIMCORE_WEBSITE_VAR . "/plugins/" . self::PLUGIN_NAME;

    const PLUGIN_VAR_PATH_INSTALL = self::PLUGIN_VAR_PATH . "/install.json";

    const ASSET_PAGES_TEMPLATES = "/gabarits-de-pages";

    const AREA_WIREFRAME = PIMCORE_WEBSITE_PATH . '/views/areas/active-wireframe';

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

            self::createThumbnails();
            self::createDirectoryTemplates();
            Install::installTable(self::PLUGIN_PATH . "/static/install/tables.json");
            Install::installDocType(self::PLUGIN_PATH . "/static/install/doctypes.json");
            Install::installAreas(self::PLUGIN_PATH . "/static/install/areas.zip",
                PIMCORE_WEBSITE_PATH . DIRECTORY_SEPARATOR . "views/areas/active-wireframe");
            Install::installTranslationAdmin(self::PLUGIN_PATH . "/static/install/texts.csv");
            Install::createLogInstall(1, self::PLUGIN_VAR_PATH_INSTALL);

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
            Asset\Service::createFolderByPath(self::ASSET_PAGES_TEMPLATES);
        }
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
            Install::createLogInstall(0, self::PLUGIN_VAR_PATH_INSTALL);
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

            if ($isInstalled = intval($conf->get('installed'))) {
                // Module extends
                if ($addin = Extension::getInstance(self::PLUGIN_NAME)->check()) {
                    $session = new Container(self::PLUGIN_NAME);
                    $session->__set('ActiveWireframeExtension', [
                        'includePathJS' => $addin['js'],
                        'includePathCSS' => $addin['css']
                    ]);
                }
            }

            return $isInstalled;
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

        // add a single command
        \Pimcore::getEventManager()->attach('system.console.init', function (\Zend_EventManager_Event $e) {
            $application = $e->getTarget();
            $application->add(new Web2PrintPdfCreationCommand());
        });
    }

}
