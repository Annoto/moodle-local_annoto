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
 * Unit tests for the usage telemetry.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_annoto;

/**
 * Tests for \local_annoto\telemetry.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_annoto\telemetry
 */
final class telemetry_test extends \advanced_testcase {
    /**
     * Turn telemetry on, the way an admin saving the settings does.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('telemetryenabled', 1, 'local_annoto');
    }

    /**
     * Add an Annoto completion setting row for an activity.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $enabled COMPLETION_TRACKING_AUTOMATIC or another value
     */
    protected function add_completion_cfg(int $courseid, int $cmid, int $enabled): void {
        global $DB;
        $DB->insert_record('local_annoto_completion', (object)[
            'courseid' => $courseid,
            'cmid' => $cmid,
            'enabled' => $enabled,
            'usermodified' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * The parameters behind the existing GA4 dimensions keep their names and order.
     */
    public function test_collect_keeps_existing_parameters(): void {
        $this->assertSame([
            'site_url', 'site_name', 'moodle_release', 'moodle_version', 'plugin_release', 'plugin_version',
            'region', 'lang', 'user_count', 'course_count', 'client_configured', 'sso_configured',
            'dashboard_autoadd', 'locale_enabled', 'media_override', 'debug_logging', 'script_custom',
            'moodlejs_custom', 'activity_completion', 'activity_completion_active',
        ], array_keys(telemetry::collect()));
    }

    /**
     * The heartbeat carries every existing parameter, the usage counts and the session
     * parameters, within GA4's limit even with DebugView's debug_mode added.
     */
    public function test_heartbeat_params_fit_ga4_limit(): void {
        set_config('telemetry_debugmode', 1, 'local_annoto');
        $params = telemetry::build_params('annoto_heartbeat');

        $this->assertLessThanOrEqual(telemetry::MAX_EVENT_PARAMS, count($params));
        $this->assertSame(1, $params['engagement_time_msec']);
        $this->assertSame(telemetry::get_client_id(), $params['session_id']);
        foreach (['n_activities_completion_cfg', 'n_completions_7d'] as $name) {
            $this->assertArrayHasKey($name, $params);
            $this->assertIsInt($params[$name]);
        }
        foreach (array_keys(telemetry::collect()) as $name) {
            $this->assertArrayHasKey($name, $params);
        }
    }

    /**
     * Every other event stays within GA4's limits too.
     */
    public function test_other_events_fit_ga4_limits(): void {
        $extra = ['error_area' => 'sso', 'error_code' => 'token_encode_failed', 'error_count' => 2];
        foreach (['annoto_environment', 'annoto_config_change', 'annoto_uninstall', 'annoto_error'] as $event) {
            $params = telemetry::build_params($event, $extra);
            $this->assertLessThanOrEqual(telemetry::MAX_EVENT_PARAMS, count($params), $event);
            $this->assertArrayHasKey('site_id', $params, $event);
            foreach ($params as $name => $value) {
                $this->assertLessThanOrEqual(40, strlen($name));
                $this->assertLessThanOrEqual(telemetry::MAX_PARAM_LEN, \core_text::strlen((string)$value));
            }
        }
        $props = telemetry::build_user_properties();
        foreach (['region', 'plugin_release', 'moodle_release', 'site_id', 'site_env', 'country_code'] as $name) {
            $this->assertArrayHasKey($name, $props);
            $this->assertLessThanOrEqual(telemetry::MAX_USERPROP_LEN, \core_text::strlen($props[$name]['value']));
        }
    }

    /**
     * n_activities_completion_cfg counts activities with Annoto automatic completion, the same
     * value as activity_completion_active.
     */
    public function test_activities_completion_configured(): void {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $on = $gen->create_module('page', ['course' => $course->id]);
        $off = $gen->create_module('page', ['course' => $course->id]);
        $this->add_completion_cfg($course->id, $on->cmid, telemetry::COMPLETION_TRACKING_AUTOMATIC);
        $this->add_completion_cfg($course->id, $off->cmid, 0);

        $this->assertSame(1, telemetry::collect_usage()['n_activities_completion_cfg']);
        $this->assertSame(1, telemetry::collect()['activity_completion_active']);
    }

    /**
     * n_completions_7d counts recent completions on Annoto-completion activities only.
     */
    public function test_completions_7d(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $on = $gen->create_module('page', ['course' => $course->id]);
        $off = $gen->create_module('page', ['course' => $course->id]);
        $this->add_completion_cfg($course->id, $on->cmid, telemetry::COMPLETION_TRACKING_AUTOMATIC);
        $u1 = $gen->create_and_enrol($course, 'student');
        $u2 = $gen->create_and_enrol($course, 'student');
        $u3 = $gen->create_and_enrol($course, 'student');

        $add = function (int $cmid, int $userid, int $state, int $time) use ($DB) {
            $DB->insert_record('course_modules_completion', (object)[
                'coursemoduleid' => $cmid,
                'userid' => $userid,
                'completionstate' => $state,
                'timemodified' => $time,
            ]);
        };
        $add($on->cmid, $u1->id, COMPLETION_COMPLETE, time() - DAYSECS);
        $add($on->cmid, $u2->id, COMPLETION_COMPLETE, time() - 10 * DAYSECS);  // Too old.
        $add($on->cmid, $u3->id, COMPLETION_INCOMPLETE, time());               // Not complete.
        $add($off->cmid, $u1->id, COMPLETION_COMPLETE, time());                // Not Annoto completion.

        $this->assertSame(1, telemetry::collect_usage()['n_completions_7d']);
    }

    /**
     * site_id is anonymous and survives a change of wwwroot.
     */
    public function test_site_id_stable_across_wwwroot_change(): void {
        global $CFG;
        $before = telemetry::get_site_id();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{20}$/', $before);
        $this->assertStringNotContainsString($CFG->siteidentifier, $before);

        $CFG->wwwroot = 'https://moved.example.org/moodle';
        $this->assertSame($before, telemetry::get_site_id());
    }

    /**
     * Host name guesses for the site_env setting default.
     *
     * @dataProvider site_env_provider
     * @param string $url
     * @param string $expected
     */
    public function test_guess_site_env(string $url, string $expected): void {
        $this->assertSame($expected, telemetry::guess_site_env($url));
    }

    /**
     * Data for test_guess_site_env.
     *
     * @return array
     */
    public static function site_env_provider(): array {
        return [
            ['https://moodle.bfh.ch', 'production'],
            ['https://moodle-stage.bfh.ch', 'staging'],
            ['https://staging.moodle.huji.ac.il', 'staging'],
            ['https://next.moodle.example.edu', 'staging'],
            ['https://moodle-test.bfh.ch', 'test'],
            ['https://test2.example.edu/moodle', 'test'],
            ['https://contest.example.edu', 'production'],
            ['https://dev.example.edu', 'dev'],
            ['http://moodle.local', 'dev'],
            ['http://localhost:8080/moodle', 'dev'],
        ];
    }

    /**
     * The admin's site_env choice wins over the guess; an invalid value falls back to it.
     */
    public function test_site_env_setting(): void {
        global $CFG;
        // Install applies the setting's default (a guess from the install-time wwwroot) to config,
        // so clear it to exercise the "no setting -> guess from current wwwroot" fallback.
        set_config('siteenv', null, 'local_annoto');
        $CFG->wwwroot = 'https://moodle-test.example.edu';
        $this->assertSame('test', telemetry::get_site_env());
        set_config('siteenv', 'production', 'local_annoto');
        $this->assertSame('production', telemetry::get_site_env());
        set_config('siteenv', 'bogus', 'local_annoto');
        $this->assertSame('test', telemetry::get_site_env());
    }

    /**
     * country_code is the site's default country, upper case, or empty.
     */
    public function test_country_code(): void {
        global $CFG;
        $CFG->country = 'il';
        $this->assertSame('IL', telemetry::get_country_code());
        $CFG->country = '';
        $this->assertSame('', telemetry::get_country_code());
    }

    /**
     * Errors are queued with fixed codes only, and not at all when opted out.
     */
    public function test_record_error(): void {
        telemetry::record_error('sso', 'token_encode_failed');
        telemetry::record_error('sso', 'token_encode_failed');   // Same request: counted once.
        telemetry::record_error('nowhere', 'some_code');          // Unknown area.
        telemetry::record_error('client', 'Free text, no!');      // Not a code.
        $this->assertSame(['sso:token_encode_failed' => 1], telemetry::get_pending_errors());

        set_config('telemetryenabled', 0, 'local_annoto');
        telemetry::record_error('dashboard', 'lti_create_failed');
        $this->assertArrayNotHasKey('dashboard:lti_create_failed', telemetry::get_pending_errors());
    }

    /**
     * The environment event lists installed media plugins and the PHP major.minor version.
     */
    public function test_collect_environment(): void {
        global $CFG;
        $env = telemetry::collect_environment();
        $this->assertSame(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION, $env['php_version']);
        $this->assertSame($CFG->dbtype, $env['db_type']);
        // Core ships H5P activities and the YouTube/Video.js media players.
        $this->assertStringContainsString('h5p', $env['video_plugins']);
        $this->assertLessThanOrEqual(telemetry::MAX_PARAM_LEN, strlen($env['video_plugins']));
    }
}
