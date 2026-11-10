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
 * Tests for \local_annoto\telemetry and the local_annoto_widget_loaded web service.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_annoto\telemetry
 */
final class telemetry_test extends \advanced_testcase {

    /**
     * Load the external functions under test.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/local/annoto/externallib.php');
    }

    /**
     * Turn telemetry on, the way an admin saving the settings does.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('telemetryenabled', 1, 'local_annoto');
    }

    /**
     * Use the standard log store, unbuffered, so widget_loaded events land in the log table.
     */
    protected function enable_standard_log(): void {
        $this->preventResetByRollback();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        get_log_manager(true);
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
     * The heartbeat carries every existing and new usage parameter within GA4's 25.
     */
    public function test_heartbeat_params_fit_ga4_limit(): void {
        $this->enable_standard_log();
        $params = telemetry::build_params('annoto_heartbeat');

        $this->assertLessThanOrEqual(telemetry::MAX_EVENT_PARAMS, count($params));
        foreach (['n_courses_with_annoto', 'n_activities_with_annoto', 'n_activities_completion_cfg',
                'n_learners_active_7d', 'n_completions_7d'] as $name) {
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
     * Courses and activities count only where the widget was loaded: a course with Annoto on
     * counts, one with it off does not.
     */
    public function test_courses_and_activities_with_annoto(): void {
        $gen = $this->getDataGenerator();
        $courseon = $gen->create_course();
        $courseoff = $gen->create_course();
        $pageon = $gen->create_module('page', ['course' => $courseon->id]);
        $pageon2 = $gen->create_module('page', ['course' => $courseon->id]);
        $gen->create_module('page', ['course' => $courseoff->id]);

        $usage = telemetry::collect_usage();
        $this->assertSame(0, $usage['n_courses_with_annoto']);
        $this->assertSame(0, $usage['n_activities_with_annoto']);

        $user = $gen->create_and_enrol($courseon, 'student');
        $this->setUser($user);
        telemetry::record_widget_load($courseon->id, $pageon->cmid, \context_module::instance($pageon->cmid));
        telemetry::record_widget_load($courseon->id, $pageon2->cmid, \context_module::instance($pageon2->cmid));
        // The course page itself counts for the course, not as an activity.
        telemetry::record_widget_load($courseon->id, 0, \context_course::instance($courseon->id));
        // A second load of the same page adds nothing.
        telemetry::record_widget_load($courseon->id, $pageon->cmid, \context_module::instance($pageon->cmid));

        $usage = telemetry::collect_usage();
        $this->assertSame(1, $usage['n_courses_with_annoto']);
        $this->assertSame(2, $usage['n_activities_with_annoto']);

        // A deleted activity stops counting.
        course_delete_module($pageon2->cmid);
        $usage = telemetry::collect_usage();
        $this->assertSame(1, $usage['n_activities_with_annoto']);
    }

    /**
     * Nothing is recorded while telemetry is turned off.
     */
    public function test_widget_load_not_recorded_when_opted_out(): void {
        global $DB;
        set_config('telemetryenabled', 0, 'local_annoto');
        $course = $this->getDataGenerator()->create_course();

        $this->assertFalse(telemetry::record_widget_load($course->id, 0, \context_course::instance($course->id)));
        $this->assertSame(0, $DB->count_records('local_annoto_widgetload'));
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
     * n_learners_active_7d counts distinct users who loaded the widget in the last 7 days.
     */
    public function test_learners_active_7d(): void {
        global $DB, $SESSION;
        $this->enable_standard_log();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $context = \context_module::instance($page->cmid);

        foreach (['recent1', 'recent2', 'old'] as $name) {
            $user = $gen->create_and_enrol($course, 'student', ['username' => $name]);
            $this->setUser($user);
            $SESSION->local_annoto_widgetloads = [];
            telemetry::record_widget_load($course->id, $page->cmid, $context);
            // The same user again is still one user.
            $SESSION->local_annoto_widgetloads = [];
            telemetry::record_widget_load($course->id, $page->cmid, $context);
        }
        $old = $DB->get_record('user', ['username' => 'old']);
        $DB->set_field('logstore_standard_log', 'timecreated', time() - 8 * DAYSECS, ['userid' => $old->id]);

        $this->assertSame(2, telemetry::collect_usage()['n_learners_active_7d']);
    }

    /**
     * Without the standard log store the learner count is left out rather than sent as 0.
     */
    public function test_learners_active_7d_omitted_without_log_store(): void {
        set_config('enabled_stores', '', 'tool_log');
        get_log_manager(true);
        $usage = telemetry::collect_usage();
        $this->assertArrayNotHasKey('n_learners_active_7d', $usage);
        $this->assertArrayHasKey('engagement_time_msec', telemetry::build_params('annoto_heartbeat'));
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

    /**
     * The web service records a load for an enrolled user and rejects an activity of another course.
     *
     * @covers \local_annoto_external::widget_loaded
     */
    public function test_widget_loaded_external(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $other = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $otherpage = $gen->create_module('page', ['course' => $other->id]);
        $this->setUser($gen->create_and_enrol($course, 'student'));

        $result = \local_annoto_external::widget_loaded($course->id, $page->cmid);
        $result = \external_api::clean_returnvalue(\local_annoto_external::widget_loaded_returns(), $result);
        $this->assertTrue($result['status']);
        $this->assertTrue($DB->record_exists('local_annoto_widgetload',
            ['courseid' => $course->id, 'cmid' => $page->cmid]));

        $this->expectException(\moodle_exception::class);
        \local_annoto_external::widget_loaded($course->id, $otherpage->cmid);
    }
}
