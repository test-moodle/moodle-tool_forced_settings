# Forced Settings for Moodle

A Moodle admin tool plugin that sets up forced settings from external text files.

## Features

1. **JSON format support**: Built-in clean and readable configuration files
2. **Extensible architecture**: Easy to add new configuration formats
3. **Automatic format detection**: Based on file extension
4. **Custom loaders**: Use custom loaders without modifying plugin code
5. **Safe loading**: Error handling for production environments
6. **CLI validation tool**: Validate JSON configuration files
7. **Pre-setup execution**: Runs before Moodle setup, using only native PHP functions

## Quick Start

### Basic Usage (JSON files)

Add the following to your `config.php` before `require_once(__DIR__ . '/lib/setup.php');`:

```php
// Set up forced settings from configuration file.
require_once(__DIR__ . '/admin/tool/forced_settings/classes/local/forced_settings.php');
\tool_forced_settings\local\forced_settings::from($CFG, __DIR__ . '/.moodle_settings.json');
```

**Note**: The `from()` method directly modifies the provided `$CFG` object with the configuration from the file. It does not return any value.

### Configuration File Format

```json
{
  "moodle": {
    "debug": true,
    "dbname": "moodle",
    "dbhost": "mysql",
    "behat_profiles": {
      "default": {
        "browser": "firefox"
      }
    }
  },
  "auth_ldap": {
    "host_url": "ldaps://ldap.example.com"
  }
}
```

### Configuration Structure

All configuration must be organized by component:

- **moodle**: Core Moodle settings (stored in `$CFG`)
- **plugin_name**: Plugin settings (stored in `$CFG->forced_plugin_settings['plugin_name']`)

See [`.moodle_settings.example.json`](.moodle_settings.example.json) for a complete configuration example.

### Nested Settings

For JSON files, use nested objects:

```json
{
  "moodle": {
    "behat_profiles": {
      "default": {
        "browser": "firefox"
      }
    }
  }
}
```

## CLI Validation Tool

Validate configuration files of any supported format:

```bash
# Validate using built-in loader (based on file extension)
php admin/tool/forced_settings/cli/validate_config.php --file=.moodle_settings.json
php admin/tool/forced_settings/cli/validate_config.php --file=config.yaml

# Validate with custom loader
php admin/tool/forced_settings/cli/validate_config.php --file=config.yaml --loader=local/myloaders/yaml_loader.php

# Verbose output
php admin/tool/forced_settings/cli/validate_config.php --file=.moodle_settings.json --verbose
```

The validation tool:
- Detects the appropriate loader based on file extension
- Supports custom loaders via `--loader` parameter
- Validates syntax and structure
- Displays configuration in human-readable format
- Shows warnings for potential issues with `--verbose` flag

## Adding New File Formats

The plugin supports two methods for adding new file format loaders:

### Method 1: Add Loader to Plugin (Recommended for permanent formats)

1. Create a loader class in `admin/tool/forced_settings/classes/local/loaders/{extension}_loader.php`:

```php
<?php
namespace tool_forced_settings\local\loaders;

use Exception;
use tool_forced_settings\local\config_loader;

/**
 * YAML configuration loader.
 */
class yaml_loader implements config_loader {
    public function load(string $filepath): array {
        if (!file_exists($filepath)) {
            throw new Exception("File not found: {$filepath}");
        }

        // Parse YAML using native PHP functions or third-party library.
        // DO NOT use Moodle functions - they're not loaded yet!
        $content = file_get_contents($filepath);
        $data = yaml_parse($content);

        return $data; // Must return array organized by component sections.
    }

    public static function get_supported_extensions(): array {
        return ['yaml', 'yml'];
    }
}
```

2. Use it in `config.php`:

```php
\tool_forced_settings\local\forced_settings::from($CFG, __DIR__ . '/config.yaml');
```

The plugin automatically loads `yaml_loader.php` based on the file extension.

### Method 2: Custom Loaders (Without modifying plugin)

Use the third parameter to specify custom loaders:

```php
$customLoaders = [
    'yaml' => '/path/to/my_yaml_loader.php',
    'ini' => 'local/custom/ini_loader.php',  // Relative to Moodle home
];

\tool_forced_settings\local\forced_settings::from(
    $CFG,
    __DIR__ . '/config.yaml',
    $customLoaders
);
```

Custom loaders can use any class name and namespace, as long as they implement `config_loader`:

```php
<?php
namespace My\Custom\Namespace;

use tool_forced_settings\local\config_loader;

class MyYamlLoader implements config_loader {
    public function load(string $filepath): array {
        // Your implementation
    }

    public static function get_supported_extensions(): array {
        return ['yaml', 'yml'];
    }
}
```

**Important Notes:**

- **Plugin loaders** must follow naming convention: `{extension}_loader` class in namespace `tool_forced_settings\local\loaders`
- **Custom loaders** can have any name - the plugin detects classes implementing `config_loader` automatically
- **Paths**: Relative paths are resolved from Moodle home directory
- **No Moodle functions**: Loaders run before `setup.php`, only native PHP functions available

## API Reference

### `forced_settings::from(stdClass $cfg, string $filepath, array $customloaders = []): void`

Main method to load and apply configuration settings directly to the provided `$cfg` object.

**Parameters:**
- `$cfg` (stdClass): The configuration object to populate (usually global `$CFG`)
- `$filepath` (string): Absolute or relative path to the configuration file
- `$customloaders` (array, optional): Associative array mapping file extensions to loader file paths
  - Format: `['extension' => 'path/to/loader.php']`
  - Paths can be relative (to Moodle home) or absolute
  - Default: `[]` (uses plugin's built-in loaders)

**Returns:**
- `void`: This method directly modifies the provided `$cfg` object and does not return any value

**Throws:**
- `Exception`: If file format is not supported or loader not found

**Examples:**

```php
// Basic usage with built-in JSON loader
\tool_forced_settings\local\forced_settings::from($CFG, '.moodle_settings.json');

// With custom loaders
$loaders = ['yaml' => 'local/custom/yaml_loader.php'];
\tool_forced_settings\local\forced_settings::from($CFG, 'config.yaml', $loaders);
```

### CLI Tool: `validate_config.php`

Command-line tool to validate configuration files of any supported format.

**Usage:**
```bash
php admin/tool/forced_settings/cli/validate_config.php --file=<filepath> [--loader=<loader_path>] [--verbose]
```

**Parameters:**
- `--file` or `-f` (required): Path to configuration file to validate
- `--loader` or `-l` (optional): Path to custom loader file
- `--verbose` or `-v` (optional): Display detailed validation information

**Features:**
- Automatic format detection based on file extension
- Support for custom loaders
- Syntax and structure validation
- Human-readable configuration display
- Warning detection for empty values

**Examples:**
```bash
# Validate JSON with built-in loader
php admin/tool/forced_settings/cli/validate_config.php --file=.moodle_settings.json

# Validate with custom loader
php admin/tool/forced_settings/cli/validate_config.php --file=config.yaml --loader=local/custom/yaml_loader.php

# Verbose output
php admin/tool/forced_settings/cli/validate_config.php -f=.moodle_settings.json -v
```

## Testing

The plugin includes comprehensive PHPUnit tests that document and verify all functionality.

### Running Tests

```bash
# Run all plugin tests
vendor/bin/phpunit --testsuite tool_forced_settings_testsuite

# Run specific test file
vendor/bin/phpunit admin/tool/forced_settings/tests/forced_settings_test.php
vendor/bin/phpunit admin/tool/forced_settings/tests/json_loader_test.php
```

### Test Coverage

**forced_settings_test.php** - Tests for the main forced_settings class:
- Loading configuration from JSON files
- Plugin loader auto-detection
- Custom loader support
- Path resolution (relative and absolute)
- Nested configuration structures
- Multiple plugin sections
- Error handling (file not found, unsupported formats)
- Void return type verification

**json_loader_test.php** - Tests for the JSON loader:
- Valid JSON parsing
- Nested objects support
- Data type preservation (strings, integers, floats, booleans, arrays, null)
- UTF-8 character handling
- Large file processing
- Error handling (invalid JSON, empty files, non-array content)
- Supported extensions verification

### Test Documentation

The tests serve as living documentation showing:
- How to load configuration from files
- How to use custom loaders
- How path resolution works
- Expected error messages and exception handling
- All supported features and edge cases

## License

GPL v3 or later

## Author

Jordi Pujol Ahulló <jordi.pujol@urv.cat>

## Copyright

2026 onwards Universitat Rovira i Virgili (https://www.urv.cat)
