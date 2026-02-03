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
 * JSON file configuration loader.
 *
 * @package    tool_forced_settings
 * @author     Jordi Pujol Ahulló <jordi.pujol@urv.cat>
 * @copyright  2026 onwards to Universitat Rovira i Virgili <https://www.urv.cat>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_forced_settings\local\loaders;

use Exception;
use tool_forced_settings\local\config_loader;

/**
 * Loader for JSON format configuration files.
 *
 * Note: This class runs before Moodle setup, so it cannot use Moodle functions.
 * Only native PHP functions are available.
 */
class json_loader implements config_loader {
    /**
     * Load configuration from a JSON file.
     *
     * Note: Runs before Moodle setup. Only native PHP functions available.
     * In non-CLI mode, errors are silently ignored to avoid breaking Moodle initialization.
     *
     * @param string $filepath Path to the JSON file
     * @return array Configuration settings organized by sections
     * @throws Exception If the file cannot be loaded or parsed (CLI mode only)
     */
    public function load(string $filepath): array {
        $cliscript = defined('CLI_SCRIPT') && CLI_SCRIPT;

        if (!file_exists($filepath)) {
            $errormsg = __CLASS__ . ": File '$filepath' does not exist.";
            if ($cliscript) {
                throw new Exception($errormsg);
            }
            // Cannot use debugging() here - Moodle not loaded yet.
            return [];
        }

        $content = file_get_contents($filepath);
        if ($content === false) {
            $errormsg = __CLASS__ . ": Cannot read file '$filepath'.";
            if ($cliscript) {
                throw new Exception($errormsg);
            }
            // Cannot use debugging() here - Moodle not loaded yet.
            return [];
        }

        $jsonsettings = json_decode($content, true);
        $jsonerror = json_last_error();

        if ($jsonerror !== JSON_ERROR_NONE) {
            $errormsg = __CLASS__ . ": JSON parsing error in '$filepath': " . json_last_error_msg();
            if ($cliscript) {
                throw new Exception($errormsg);
            }
            // Cannot use debugging() here - Moodle not loaded yet.
            return [];
        }

        if (!is_array($jsonsettings)) {
            $errormsg = __CLASS__ . ": JSON content in '$filepath' is not a valid configuration structure.";
            if ($cliscript) {
                throw new Exception($errormsg);
            }
            // Cannot use debugging() here - Moodle not loaded yet.
            return [];
        }

        $jsonsettings['tool_forced_settings']['configfile'] = $filepath;
        return $this->ensure_content_in_sections($jsonsettings);
    }

    /**
     * Get the file extensions supported by this loader.
     *
     * @return array List of supported file extensions
     */
    public static function get_supported_extensions(): array {
        return ['json'];
    }

    /**
     * Relocates any Moodle core setting into 'moodle' section.
     *
     * Ensures any setting is in a section, either from a plugin or core.
     *
     * @param array $jsonsettings Array from the JSON file content
     * @return array All plugin settings in their sections and core settings in 'moodle' section
     */
    private function ensure_content_in_sections(array $jsonsettings): array {
        if (!isset($jsonsettings['moodle'])) {
            $jsonsettings['moodle'] = [];
        }
        foreach ($jsonsettings as $section => $values) {
            if (!is_array($values)) {
                $jsonsettings['moodle'][$section] = $values;
                unset($jsonsettings[$section]);
            }
        }
        return $jsonsettings;
    }
}
