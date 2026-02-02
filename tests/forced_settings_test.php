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

namespace tool_forced_settings;

use stdClass;
use tool_forced_settings\local\forced_settings;

/**
 * Tests for forced_settings class.
 *
 * @package    tool_forced_settings
 * @copyright  2026 onwards to Universitat Rovira i Virgili <https://www.urv.cat>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_forced_settings\local\forced_settings
 */
final class forced_settings_test extends \basic_testcase {
    /** @var stdClass The configuration object for testing. */
    private stdClass $cfg;

    /**
     * Set up test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->cfg = new stdClass();
    }

    /**
     * Get fixture file path.
     *
     * @param string $filename Fixture filename
     * @return string Full path to fixture file
     */
    private function get_fixture(string $filename): string {
        return __DIR__ . '/fixtures/' . $filename;
    }

    /**
     * Test that moodle section settings are loaded into $cfg.
     */
    public function test_moodle_section_loaded_into_cfg(): void {
        forced_settings::from($this->cfg, $this->get_fixture('valid_config.json'));

        // Verify moodle core settings are in $cfg directly.
        $this->assertTrue($this->cfg->debug);
        $this->assertEquals('testdb', $this->cfg->dbname);
        $this->assertEquals('localhost', $this->cfg->dbhost);
    }

    /**
     * Test that plugin sections are loaded into $cfg->forced_plugin_settings.
     */
    public function test_plugin_sections_loaded_into_forced_plugin_settings(): void {
        forced_settings::from($this->cfg, $this->get_fixture('valid_config.json'));

        // Verify plugin settings are in $cfg->forced_plugin_settings.
        $this->assertArrayHasKey('auth_ldap', $this->cfg->forced_plugin_settings);
        $this->assertArrayHasKey('local_myplug', $this->cfg->forced_plugin_settings);

        $this->assertEquals('ldaps://test.example.com', $this->cfg->forced_plugin_settings['auth_ldap']['host_url']);
        $this->assertEquals('3', $this->cfg->forced_plugin_settings['auth_ldap']['version']);
        $this->assertEquals('test-key-123', $this->cfg->forced_plugin_settings['local_myplug']['api_key']);
        $this->assertTrue($this->cfg->forced_plugin_settings['local_myplug']['enabled']);

        // Verify tool_forced_settings metadata is present.
        $this->assertArrayHasKey('tool_forced_settings', $this->cfg->forced_plugin_settings);
        $this->assertArrayHasKey('configfile', $this->cfg->forced_plugin_settings['tool_forced_settings']);
        $this->assertArrayHasKey('loader', $this->cfg->forced_plugin_settings['tool_forced_settings']);
        $this->assertStringContainsString('json_loader', $this->cfg->forced_plugin_settings['tool_forced_settings']['loader']);
    }

    /**
     * Test that nested structures in moodle section are preserved in $cfg.
     */
    public function test_nested_structures_preserved_in_cfg(): void {
        forced_settings::from($this->cfg, $this->get_fixture('nested_config.json'));

        // Verify nested structure is preserved in $cfg.
        $this->assertIsArray($this->cfg->behat_profiles);
        $this->assertArrayHasKey('default', $this->cfg->behat_profiles);
        $this->assertArrayHasKey('chrome', $this->cfg->behat_profiles);
        $this->assertEquals('firefox', $this->cfg->behat_profiles['default']['browser']);
        $this->assertEquals('chrome', $this->cfg->behat_profiles['chrome']['browser']);

        $this->assertIsArray($this->cfg->dboptions);
        $this->assertEquals(3306, $this->cfg->dboptions['dbport']);
    }

    /**
     * Test plugin loader auto-detection based on file extension.
     */
    public function test_plugin_loader_autodetection(): void {
        // Load .test file - should use test_loader from plugin automatically.
        forced_settings::from($this->cfg, $this->get_fixture('dummy.test'));

        // Verify test_loader was used (it returns specific test data).
        $this->assertTrue($this->cfg->debug);
        $this->assertEquals('yes', $this->cfg->test_loaded);
        $this->assertTrue($this->cfg->forced_plugin_settings['tool_forced_settings']['test_loader_used']);
    }

    /**
     * Test custom loader can be specified via second parameter.
     *
     * This demonstrates real-world usage where you explicitly specify
     * a loader path as the third parameter to settings_from().
     *
     * Example in config.php:
     * forced_settings::from(
     *     $CFG,
     *     '.moodle_settings.yaml',
     *     ['yaml' => 'local/myloaders/yaml_loader.php']
     * );
     */
    public function test_custom_loader(): void {
        // Explicitly specify custom loader path as third parameter.
        forced_settings::from(
            $this->cfg,
            $this->get_fixture('test_config.custom'),
            ['custom' => $this->get_fixture('custom_loader.php')]
        );

        // Verify custom loader was used and settings loaded into $cfg.
        $this->assertTrue($this->cfg->custom_loaded);
        $this->assertEquals('from_custom_loader', $this->cfg->custom_value);
    }

    /**
     * Test exception when file does not exist.
     */
    public function test_exception_file_not_found(): void {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('does not exist');

        forced_settings::from($this->cfg, '/nonexistent/file.json');
    }

    /**
     * Test exception when file extension is not supported.
     */
    public function test_exception_unsupported_format(): void {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No loader found for extension: xyz');

        forced_settings::from($this->cfg, $this->get_fixture('unsupported.xyz'));
    }

    /**
     * Test exception when custom loader file not found.
     */
    public function test_exception_custom_loader_not_found(): void {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Loader file not found');

        forced_settings::from(
            $this->cfg,
            $this->get_fixture('unsupported.xyz'),
            ['xyz' => '/nonexistent/loader.php']
        );
    }
}
