<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Settings forcer.
 *
 * @package   tool_forced_settings
 * @author    Jordi Pujol Ahulló <jordi.pujol@urv.cat>
 * @copyright 2024 onwards to Universitat Rovira i Virgili (https://www.urv.cat)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_forced_settings\local;

// This file is loaded before setup.php, so autoloading is not available.
// We need to manually require the base dependencies.
// Additional loaders are loaded dynamically based on configuration.
// @codingStandardsIgnoreLine
require_once(__DIR__ . '/config_loader.php');
// @codingStandardsIgnoreLine
require_once(__DIR__ . '/loaders/json_loader.php');

use Exception;
use stdClass;

/**
 * Invoke this class in config.php to force Moodle settings.
 *
 * @package   tool_forced_settings
 * @author    Jordi Pujol Ahulló <jordi.pujol@urv.cat>
 * @copyright 2024 onwards to Universitat Rovira i Virgili (https://www.urv.cat)
 */
class forced_settings {
    /**
     * Invoke this API from config.php before setup.php to force any Moodle setting.
     *
     * The provided $cfg object is populated with forced settings from the configuration file.
     * This method directly modifies the $cfg object and does not return any value.
     *
     * @param stdClass $cfg The configuration object to populate (usually global $CFG)
     * @param string $filepath Path to the configuration file
     * @param array $customloaders Optional array of extension => loader_file_path for custom loaders
     * @throws Exception If file format is not supported
     */
    public static function from(stdClass $cfg, string $filepath, array $customloaders = []): void {
        $loader = self::get_loader_for_file($filepath, $customloaders);
        $settings = $loader->load($filepath);

        // Add loader class information to tool_forced_settings metadata.
        if (!isset($settings['tool_forced_settings'])) {
            $settings['tool_forced_settings'] = [];
        }
        $settings['tool_forced_settings']['loader'] = get_class($loader);

        self::add_forced_settings_from($cfg, $settings);
    }

    /**
     * Get the appropriate loader for the given file based on its extension.
     *
     * @param string $filepath Path to the configuration file
     * @param array $customloaders Custom loaders mapping: extension => loader file path
     * @return config_loader The loader instance
     * @throws Exception If no loader supports the file extension
     */
    private static function get_loader_for_file(string $filepath, array $customloaders): config_loader {
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);

        // If custom loaders provided, use them.
        if (!empty($customloaders) && isset($customloaders[$extension])) {
            return self::load_custom_loader($customloaders[$extension], $extension);
        }

        // Otherwise, assume loader exists in plugin directory.
        return self::load_plugin_loader($extension);
    }

    /**
     * Load a custom loader from specified file path.
     *
     * @param string $loaderfile Path to the loader file
     * @param string $extension File extension for error messages
     * @return config_loader The loader instance
     * @throws Exception If loader file not found or no valid class found
     */
    private static function load_custom_loader(string $loaderfile, string $extension): config_loader {
        $moodlehome = dirname(dirname(dirname(dirname(dirname(__DIR__)))));

        // Resolve relative paths to Moodle home directory.
        if ($loaderfile[0] !== '/') {
            $loaderfile = $moodlehome . '/' . $loaderfile;
        }

        if (!file_exists($loaderfile)) {
            throw new Exception("Loader file not found: {$loaderfile} for extension: {$extension}");
        }

        $declaredclassesbefore = get_declared_classes();
        require_once($loaderfile);
        $declaredclassesafter = get_declared_classes();

        // Find the new class that implements config_loader.
        $newclasses = array_diff($declaredclassesafter, $declaredclassesbefore);
        foreach ($newclasses as $classname) {
            $implements = class_implements($classname);
            if (isset($implements['tool_forced_settings\\local\\config_loader'])) {
                return new $classname();
            }
        }

        throw new Exception("No valid config_loader implementation found in: {$loaderfile}");
    }

    /**
     * Load a loader from the plugin's loaders directory.
     *
     * @param string $extension File extension
     * @return config_loader The loader instance
     * @throws \Exception If loader file or class not found
     */
    private static function load_plugin_loader(string $extension): config_loader {
        $loaderfile = __DIR__ . "/loaders/{$extension}_loader.php";
        $loaderclass = "tool_forced_settings\\local\\loaders\\{$extension}_loader";

        if (!file_exists($loaderfile)) {
            throw new Exception("No loader found for extension: {$extension}. Expected file: {$loaderfile}");
        }

        require_once($loaderfile);

        if (!class_exists($loaderclass, false)) {
            throw new Exception("Loader class not found: {$loaderclass} in file: {$loaderfile}");
        }

        return new $loaderclass();
    }

    /**
     * Adds forced settings to $cfg, depending on whether they are Moodle core or other plugins.
     *
     * @param stdClass $cfg The configuration object to populate
     * @param array $settings Parsed configuration settings
     */
    private static function add_forced_settings_from(stdClass $cfg, array $settings): void {
        foreach ($settings as $component => $values) {
            if ($component === 'moodle') {
                foreach ($values as $setting => $value) {
                    $cfg->{$setting} = $value;
                }
            } else {
                if (!isset($cfg->forced_plugin_settings)) {
                    $cfg->forced_plugin_settings = [];
                }
                $cfg->forced_plugin_settings[$component] = $values;
            }
        }
    }
}
