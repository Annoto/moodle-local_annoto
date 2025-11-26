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
 * local_annoto
 *
 * @package    local_annoto
 * @copyright  2025 Avi Levy <avi@sysbind.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_annoto;

/**
 * Unit tests for the local_annoto_get_jsparam function and related functionality.
 *
 * @group local_annoto
 */
final class get_jsparam_test extends \advanced_testcase {
    /** @var \stdClass $course Test course. */
    private $course;

    /**
     * Standard setup for tests.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        // Create a basic course used by tests.
        $this->course = self::getDataGenerator()->create_course([
            'fullname' => 'Annoto Test Course',
            'summary' => 'Annoto course summary',
        ]);

        // Ensure $PAGE has a context (needed by user token/picture generation).
        global $PAGE;
        $PAGE->set_context(\context_system::instance());

        // Sensible defaults for plugin config used by jsparam.
        $this->configure_plugin([
            'scripturl' => 'https://cdn.annoto.example/bootstrap.js',
            'clientid' => 'client-xyz',
            'locale' => 0,
            'activitycompletion' => 0,
            'moderatorroles' => '',
        ]);
    }

    /**
     * Helper: set multiple plugin configs for local_annoto.
     *
     * @param array $values key => value
     * @return void
     */
    private function configure_plugin(array $values): void {
        foreach ($values as $k => $v) {
            \set_config($k, $v, 'local_annoto');
        }
    }

    /**
     * Test to ensure a guest user has expected default configuration without providing a cmid.
     *
     * This test verifies that:
     * - The basic configuration values are passed through correctly.
     * - Guest users have no token, standard user scope, and no enrolment.
     * - Course metadata (id, fullname, summary) is returned correctly.
     * - No cmid is provided and locale is disabled.
     * - Login/logout URLs are properly set.
     * - The deployment domain matches the expected helper value.
     * - Moodle version and release information are properly passed through.
     * - Activity completion is disabled and untouched by default.
     *
     * @covers \local_annoto_get_jsparam
     * @covers \local_annoto_get_deployment_domain
     */
    public function test_guest_user_has_expected_defaults_without_cmid(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/annoto/lib.php');

        // Ensure guest (not-logged-in).
        $this->setUser(0);

        $params = \local_annoto_get_jsparam($this->course->id, null);

        // Basic config passthrough.
        $this->assertSame('https://cdn.annoto.example/bootstrap.js', $params['bootstrapUrl']);
        $this->assertSame('client-xyz', $params['clientId']);

        // Token & scope for guest.
        $this->assertSame('', $params['userToken']);
        $this->assertSame('user', $params['userScope']);

        // Enrolment false for guest.
        $this->assertFalse($params['userIsEnrolled']);

        // Group and course metadata.
        $this->assertSame((int)$this->course->id, $params['mediaGroupId']);
        $this->assertSame($this->course->fullname, $params['mediaGroupTitle']);
        $this->assertSame($this->course->summary, $params['mediaGroupDescription']);

        // No cmid provided.
        $this->assertNull($params['cmid']);

        // Locale disabled returns boolean false.
        $this->assertFalse($params['locale']);

        // Login / logout URLs.
        $this->assertSame($CFG->wwwroot . '/login/index.php', $params['loginUrl']);
        $this->assertStringStartsWith($CFG->wwwroot . '/login/logout.php', $params['logoutUrl']);

        // Deployment domain equals helper value.
        $this->assertSame(\local_annoto_get_deployment_domain(), $params['deploymentDomain']);

        // Moodle version info passthrough.
        $this->assertSame($CFG->version, $params['moodleVersion']);
        $this->assertSame($CFG->release, $params['moodleRelease']);

        // Activity completion untouched/disabled by default.
        $this->assertFalse($params['activityCompletionEnabled']);
        $this->assertNull($params['activityCompletionReq']);
    }

    /**
     * Test that a logged-in and enrolled user with locale enabled
     * receives the correct parameters including locale and token.
     *
     * @covers \local_annoto_get_jsparam
     */
    public function test_logged_in_enrolled_user_locale_enabled(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/annoto/lib.php');

        // Enable locale.
        $this->configure_plugin(['locale' => 1]);

        // Create & enrol user.
        $user = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($user->id, $this->course->id);
        $this->setUser($user);

        // Set a course language explicitly.
        $DB->set_field('course', 'lang', 'he', ['id' => $this->course->id]);

        $params = \local_annoto_get_jsparam($this->course->id, null);

        // Enrolled user must be detected and receive token.
        $this->assertTrue($params['userIsEnrolled']);
        $this->assertNotSame('', $params['userToken']);
        $this->assertSame('user', $params['userScope']);

        // Locale equals course language when enabled.
        $this->assertSame('he', $params['locale']);
    }

    /**
     * Test that with the CMID provided, media fields are set properly
     * and activity completion defaults are applied accordingly.
     *
     * @covers \local_annoto_get_jsparam
     */
    public function test_with_cmid_sets_media_fields_and_completion_defaults(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/annoto/lib.php');

        // Logged-in user (no specific permissions needed).
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        // Create a simple core module instance in the course (page).
        $page = self::getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'name' => 'Annoto Test Page',
            'intro' => 'Intro text for Annoto page.',
        ]);

        // Ensure activitycompletion config is enabled but no records exist → should remain disabled in params.
        $this->configure_plugin(['activitycompletion' => 1]);

        $params = \local_annoto_get_jsparam($this->course->id, $page->cmid);

        // CMID and media fields.
        $this->assertSame($page->cmid, $params['cmid']);
        $this->assertSame('Annoto Test Page', $params['mediaTitle']);
        $this->assertIsString($params['mediaDescription']);

        // Activity completion should still be disabled with no completion record.
        $this->assertFalse($params['activityCompletionEnabled']);
        $this->assertNull($params['activityCompletionReq']);
    }

    /**
     * Test that a moderator role sets user scope to 'super-mod'
     * and ensures enrolment is true.
     *
     * This test verifies the behavior when a user is assigned
     * a role with the `local/annoto:moderatediscussion` capability
     * and enrolled in a course.
     *
     * @covers \local_annoto_get_jsparam
     */
    public function test_moderator_role_sets_super_mod_and_enrolment_true(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/annoto/lib.php');

        $user = self::getDataGenerator()->create_user();
        $roleid = self::getDataGenerator()->create_role();

        // Grant the moderator capability to the role and assign in course.
        \assign_capability('local/annoto:moderatediscussion', CAP_ALLOW, $roleid, \context_system::instance());
        $coursecontext = \context_course::instance($this->course->id);
        \role_assign($roleid, $user->id, $coursecontext->id);

        // Enrol user to make sure userIsEnrolled = true.
        self::getDataGenerator()->enrol_user($user->id, $this->course->id);

        // Mark the role id as allowed moderator role in config.
        $this->configure_plugin(['moderatorroles' => (string)$roleid]);

        // Login after role/cap changes so session reflects permissions.
        $this->setUser($user);

        $params = \local_annoto_get_jsparam($this->course->id, null);

        $this->assertSame('super-mod', $params['userScope']);
        $this->assertTrue($params['userIsEnrolled']);
        $this->assertNotSame('', $params['userToken']);
    }
}
