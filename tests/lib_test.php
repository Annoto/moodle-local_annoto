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
 * Unit tests for local_annoto lib functions.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_annoto;

/**
 * Tests for token generation helpers in lib.php.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lib_test extends \advanced_testcase {

    /**
     * Load the global functions under test.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/local/annoto/lib.php');
    }

    /**
     * A logged-in user with no client id / SSO secret configured must not produce a
     * token, and must not raise PHP warnings (the plugin is simply not set up yet).
     *
     * @covers ::local_annoto_get_user_token
     * @dataProvider unconfigured_settings_provider
     * @param array $settings partial settings that are missing clientid and/or ssosecret
     */
    public function test_get_user_token_empty_when_not_configured(array $settings): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $token = local_annoto_get_user_token((object)$settings, 0);

        $this->assertSame('', $token);
        $this->assertDebuggingCalled();
    }

    /**
     * Data provider covering each unconfigured permutation: nothing set, only clientid,
     * only ssosecret, and an explicit null value.
     *
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function unconfigured_settings_provider(): array {
        return [
            'both missing' => [[]],
            'only clientid set' => [['clientid' => 'abc']],
            'only ssosecret set' => [['ssosecret' => 'topsecret']],
            'explicit nulls' => [['clientid' => null, 'ssosecret' => null]],
            'empty strings' => [['clientid' => '', 'ssosecret' => '']],
        ];
    }

    /**
     * A not-logged-in user always gets an empty token, before any settings are read.
     *
     * @covers ::local_annoto_get_user_token
     */
    public function test_get_user_token_empty_when_not_logged_in(): void {
        $this->resetAfterTest();
        $this->setUser(null);

        $this->assertSame('', local_annoto_get_user_token((object)[], 0));
    }

    /**
     * When both client id and SSO secret are set, a signed JWT is returned unchanged.
     *
     * @covers ::local_annoto_get_user_token
     */
    public function test_get_user_token_generated_when_configured(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($user);

        $settings = (object)[
            'clientid' => 'client-123',
            'ssosecret' => 'super-secret-value',
            'moderatorroles' => 'manager,editingteacher',
        ];

        $token = local_annoto_get_user_token($settings, $course->id);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        // A JWT has three dot-separated segments (header.payload.signature).
        $this->assertCount(3, explode('.', $token));
    }
    /**
     * The plugin counts as configured only once a client id is set; the SSO secret is optional.
     *
     * @covers ::local_annoto_is_configured
     */
    public function test_is_configured_requires_clientid(): void {
        $this->resetAfterTest();

        set_config('clientid', '', 'local_annoto');
        set_config('ssosecret', 'topsecret', 'local_annoto');
        $this->assertFalse(local_annoto_is_configured());

        set_config('clientid', 'client-123', 'local_annoto');
        set_config('ssosecret', '', 'local_annoto');
        $this->assertTrue(local_annoto_is_configured());
    }

    /**
     * On a target page the Kaltura hook tag is emitted only when the plugin is configured.
     *
     * @covers ::local_annoto_kaltura_hook_html
     */
    public function test_kaltura_hook_html_skipped_when_not_configured(): void {
        global $PAGE;
        $this->resetAfterTest();
        $PAGE->set_pagetype('mod-page-view');

        set_config('clientid', '', 'local_annoto');
        $this->assertSame('', local_annoto_kaltura_hook_html());

        set_config('clientid', 'client-123', 'local_annoto');
        $this->assertStringContainsString('/local/annoto/initkaltura.js', local_annoto_kaltura_hook_html());
    }

    /**
     * An unconfigured plugin queues no Annoto JavaScript on a target page.
     *
     * @covers ::local_annoto_init
     */
    public function test_init_adds_no_js_when_not_configured(): void {
        global $PAGE;
        $this->resetAfterTest();
        $PAGE->set_pagetype('mod-page-view');
        set_config('clientid', '', 'local_annoto');

        local_annoto_init();

        $this->assertStringNotContainsString('local_annoto', $PAGE->requires->get_end_code());
    }
}
