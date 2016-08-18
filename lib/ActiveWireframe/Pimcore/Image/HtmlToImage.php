<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) 2009-2016 pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace ActiveWireframe\Pimcore\Image;

use Pimcore\Tool\Console;

class HtmlToImage
{

    /**
     * @return bool
     */
    public static function isSupported()
    {
        return (bool)self::getWkhtmltoimageBinary();
    }

    /**
     * @return bool
     */
    public static function getWkhtmltoimageBinary()
    {
        foreach (["wkhtmltoimage", "wkhtmltoimage-amd64"] as $app) {
            $wk2img = Console::getExecutable($app);
            if ($wk2img) {
                return $wk2img;
            }
        }

        return false;
    }

    /**
     * @param $url
     * @param $outputFile
     * @param string $format
     * @param array $options
     * @return bool
     */
    public static function convert($url, $outputFile, $format = "png", $options = [])
    {
        // add parameter pimcore_preview to prevent inclusion of google analytics code, cache, etc.
        $url .= (strpos($url, "?") ? "&" : "?") . "pimcore_preview=true";

        // Options WK
        $optionsStr = "";
        if (!empty($options)) {
            foreach ($options as $key => $value) {
                $optionsStr .= " --" . (string)$key;
                if ($value !== null && $value !== "") {
                    $optionsStr .= " " . (string)$value;
                }
            }
        } else {
            $optionsStr = " --width 794 --height 1122 --zoom 1 --quality 94";
        }

        $arguments = $optionsStr . " --format " . $format . " \"" . $url . "\" " . $outputFile;

        // use xvfb if possible
        if ($xvfb = self::getXvfbBinary()) {
            $command = $xvfb . " --auto-servernum --server-args=\"-screen 0, 1280x1024x24\" " .
                self::getWkhtmltoimageBinary() . " --use-xserver" . $arguments;
        } else {
            $command = self::getWkhtmltoimageBinary() . $arguments;
        }

        Console::exec($command, PIMCORE_LOG_DIRECTORY . "/wkhtmltoimage.log", 60);

        if (file_exists($outputFile) && filesize($outputFile) > 1000) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public static function getXvfbBinary()
    {
        return Console::getExecutable("xvfb-run");
    }
}
