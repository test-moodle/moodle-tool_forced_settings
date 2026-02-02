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
 * CLI script to validate configuration files.
 *
 * This script validates configuration files using the appropriate loader
 * and displays the content in a human-readable format.
 *
 * @package    tool_forced_settings
 * @author     Jordi Pujol Ahulló <jordi.pujol@urv.cat>
 * @copyright  2026 onwards to Universitat Rovira i Virgili <https://www.urv.cat>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once("{$CFG->libdir}/clilib.php");

use tool_forced_settings\local\config_formatter;
use tool_forced_settings\local\config_loader;
use tool_forced_settings\local\forced_settings;

// Define options.
[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'file' => null,
        'loader' => null,
        'verbose' => false,
    ],
    [
        'h' => 'help',
        'f' => 'file',
        'l' => 'loader',
        'v' => 'verbose',
    ]
);

// Display help if requested.
if ($options['help'] || !$options['file']) {
    echo "Validate and display configuration files.

Usage:
    php validate_config.php --file=<filepath> [--loader=<loader_path>] [--verbose]
    php validate_config.php -f <filepath> [-l <loader_path>] [-v]

Options:
    -h, --help          Print this help message
    -f, --file          Path to configuration file to validate (required)
    -l, --loader        Path to custom loader file (optional)
                        If not specified, uses plugin's built-in loader based on file extension
    -v, --verbose       Display detailed validation information

Examples:
    # Validate JSON file using built-in loader
    php validate_config.php --file=.moodle_settings.json

    # Validate with custom loader
    php validate_config.php --file=config.yaml --loader=local/myloaders/yaml_loader.php

    # Verbose output
    php validate_config.php -f .moodle_settings.json -v
";
    exit(0);
}

$filepath = $options['file'];
$loaderpath = $options['loader'];
$verbose = $options['verbose'];

// Convert relative path to absolute path if needed.
if (!is_file($filepath) && !empty($filepath)) {
    // Try relative to current directory.
    $testpath = getcwd() . '/' . $filepath;
    if (is_file($testpath)) {
        $filepath = $testpath;
    } else {
        // Try relative to Moodle root.
        $testpath = $CFG->dirroot . '/' . $filepath;
        if (is_file($testpath)) {
            $filepath = $testpath;
        }
    }
}

// Verify file exists.
if (!file_exists($filepath)) {
    cli_error("File not found: {$filepath}");
}

// Prepare loader configuration.
$customloaders = [];
if (!empty($loaderpath)) {
    $extension = pathinfo($filepath, PATHINFO_EXTENSION);
    $customloaders[$extension] = $loaderpath;
}

// Load the forced_settings class.
require_once($CFG->dirroot . '/admin/tool/forced_settings/classes/local/forced_settings.php');

cli_heading("Configuration Validation Results");
echo "File: " . realpath($filepath) . "\n";
echo "Size: " . filesize($filepath) . " bytes\n";
if (!empty($loaderpath)) {
    echo "Custom loader: " . $loaderpath . "\n";
}
echo str_repeat('-', 80) . "\n\n";

try {
    // Use forced_settings to validate by attempting to load the file.
    // We'll capture the data by temporarily using a custom global variable.
    $tempdata = null;

    // Create a temporary loader that captures data without modifying $CFG.
    $loaderinstance = get_loader_instance($filepath, $customloaders);

    // Load the data using the loader.
    $data = $loaderinstance->load($filepath);

    // Validation successful.
    cli_heading("VALIDATION SUCCESSFUL", 0);
    echo "Configuration file is valid\n\n";

    // Display content in human-readable format.
    cli_heading("Configuration Content");

    echo config_formatter::format($data);
    echo "\n";

    // Additional validation checks.
    if ($verbose) {
        echo "\n";
        cli_heading("Additional Checks");
        $warnings = [];
        check_empty_values($data, '', $warnings);

        if (!empty($warnings)) {
            echo "Warnings found:\n";
            foreach ($warnings as $warning) {
                echo "  - {$warning}\n";
            }
            echo "\n";
        } else {
            echo "No warnings found.\n\n";
        }
    }

    echo "\n" . str_repeat('-', 80) . "\n";
    echo "Total sections: " . count($data) . "\n";
    echo "Validation completed successfully.\n";
} catch (Exception $e) {
    // Validation failed.
    cli_heading("VALIDATION FAILED", 0);
    echo "Error: " . $e->getMessage() . "\n";

    if ($verbose) {
        echo "\nStack trace:\n";
        echo $e->getTraceAsString() . "\n";
    }

    exit(1);
}

exit(0);

/**
 * Get loader instance for the given file.
 *
 * @param string $filepath Path to configuration file
 * @param array $customloaders Custom loaders configuration
 * @return \tool_forced_settings\local\config_loader The loader instance
 * @throws Exception If loader cannot be found
 */
function get_loader_instance(string $filepath, array $customloaders): \tool_forced_settings\local\config_loader {
    $extension = pathinfo($filepath, PATHINFO_EXTENSION);

    // Load necessary files.
    require_once(__DIR__ . '/../classes/local/config_loader.php');

    // If custom loader specified, use it.
    if (!empty($customloaders[$extension])) {
        return load_custom_loader($customloaders[$extension], $extension);
    }

    // Otherwise use plugin loader.
    return load_plugin_loader($extension);
}

/**
 * Load a custom loader from specified file path.
 *
 * @param string $loaderfile Path to the loader file
 * @param string $extension File extension for error messages
 * @return \tool_forced_settings\local\config_loader The loader instance
 * @throws Exception If loader file not found or no valid class found
 */
function load_custom_loader(string $loaderfile, string $extension): \tool_forced_settings\local\config_loader {
    global $CFG;

    // Resolve relative paths to Moodle home directory.
    if ($loaderfile[0] !== '/') {
        $loaderfile = $CFG->dirroot . '/' . $loaderfile;
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
 * @return \tool_forced_settings\local\config_loader The loader instance
 * @throws Exception If loader file or class not found
 */
function load_plugin_loader(string $extension): \tool_forced_settings\local\config_loader {
    $loaderfile = __DIR__ . "/../classes/local/loaders/{$extension}_loader.php";
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
 * Recursively check for empty values in the configuration structure.
 *
 * @param mixed $data The data to check
 * @param string $path Current path in the structure
 * @param array $warnings Array to collect warnings
 * @return void
 */
function check_empty_values(mixed $data, string $path, array &$warnings): void {
    if (!is_array($data)) {
        return;
    }

    foreach ($data as $key => $value) {
        $currentpath = $path ? "{$path}.{$key}" : $key;
        if (is_string($value) && $value === '') {
            $warnings[] = "Empty string value at: {$currentpath}";
        } else if (is_array($value)) {
            check_empty_values($value, $currentpath, $warnings);
        }
    }
}
