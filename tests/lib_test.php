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

    /**
     * Set up a course with activity completion and the plugin configured for Annoto completion,
     * and log in an enrolled student.
     *
     * @return array{0: \stdClass, 1: \stdClass} the course and the student
     */
    private function setup_completion_course(): array {
        set_config('clientid', 'client-123', 'local_annoto');
        set_config('ssosecret', 'super-secret-value', 'local_annoto');
        set_config('activitycompletion', 1, 'local_annoto');
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        return [$course, $student];
    }

    /**
     * Save Annoto completion requirements for a course module, as the module settings form does.
     *
     * @param \stdClass $course the course
     * @param int $cmid the course module id
     * @param int $enabled annoto_completion tracking constant
     * @return annoto_completion the saved record
     */
    private function create_annoto_completion(\stdClass $course, int $cmid, int $enabled): annoto_completion {
        $record = new annoto_completion(0, (object)[
            'courseid' => $course->id,
            'cmid' => $cmid,
            'enabled' => $enabled,
            'totalview' => 80,
            'comments' => 1,
            'replies' => 0,
        ]);
        $record->create();
        return $record;
    }

    /**
     * On the course page (no course module) a label with Annoto completion is sent in
     * cmCompletions with the current user's progress, since the label has no page of its own.
     *
     * @covers ::local_annoto_get_jsparam
     */
    public function test_get_jsparam_course_page_sends_label_completions(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_completion_course();
        $label = $this->getDataGenerator()->create_module('label', ['course' => $course->id]);
        $completion = $this->create_annoto_completion($course, $label->cmid, annoto_completion::COMPLETION_TRACKING_AUTOMATIC);
        (new annoto_completiondata(0, (object)[
            'completionid' => $completion->get('id'),
            'userid' => $student->id,
            'data' => '{"completion":50,"comments":0,"replies":0}',
        ]))->create();

        $params = local_annoto_get_jsparam($course->id, 0);

        $this->assertFalse($params['activityCompletionEnabled']);
        $this->assertNull($params['activityCompletionReq']);
        $this->assertCount(1, $params['cmCompletions']);
        $req = $params['cmCompletions'][0];
        $this->assertEquals($label->cmid, $req->cmid);
        $this->assertEquals(80, $req->totalview);
        $this->assertEquals(1, $req->comments);
        $this->assertSame('{"completion":50,"comments":0,"replies":0}', $req->user_data->data);
    }

    /**
     * Every activity type that offers Annoto completion is sent from the course page, each with the
     * current user's own progress, so its completion shows on the course page too.
     *
     * @covers ::local_annoto_get_jsparam
     * @covers ::local_annoto_get_course_cm_completions
     */
    public function test_get_jsparam_course_page_sends_all_supported_types(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_completion_course();
        $label = $this->getDataGenerator()->create_module('label', ['course' => $course->id]);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $lti = $this->getDataGenerator()->create_module('lti', ['course' => $course->id]);
        $progress = [];
        foreach ([$label, $page, $lti] as $i => $mod) {
            $completion = $this->create_annoto_completion($course, $mod->cmid, annoto_completion::COMPLETION_TRACKING_AUTOMATIC);
            $progress[$mod->cmid] = '{"completion":' . (10 * ($i + 1)) . ',"comments":0,"replies":0}';
            (new annoto_completiondata(0, (object)[
                'completionid' => $completion->get('id'),
                'userid' => $student->id,
                'data' => $progress[$mod->cmid],
            ]))->create();
        }

        $params = local_annoto_get_jsparam($course->id, 0);

        $this->assertCount(3, $params['cmCompletions']);
        $modnames = [$label->cmid => 'label', $page->cmid => 'page', $lti->cmid => 'lti'];
        foreach ($params['cmCompletions'] as $req) {
            $this->assertArrayHasKey($req->cmid, $progress);
            $this->assertSame($modnames[$req->cmid], $req->modname);
            $this->assertSame($progress[$req->cmid], $req->user_data->data);
        }
    }

    /**
     * Activities the user cannot see, with Annoto completion off, or of a type that does not offer
     * Annoto completion are not sent from the course page.
     *
     * @covers ::local_annoto_get_jsparam
     * @covers ::local_annoto_get_course_cm_completions
     */
    public function test_get_jsparam_course_page_skips_hidden_disabled_and_unsupported(): void {
        $this->resetAfterTest();
        [$course] = $this->setup_completion_course();
        $hidden = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'visible' => 0]);
        $disabled = $this->getDataGenerator()->create_module('label', ['course' => $course->id]);
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $this->create_annoto_completion($course, $hidden->cmid, annoto_completion::COMPLETION_TRACKING_AUTOMATIC);
        $this->create_annoto_completion($course, $disabled->cmid, annoto_completion::COMPLETION_TRACKING_NONE);
        $this->create_annoto_completion($course, $forum->cmid, annoto_completion::COMPLETION_TRACKING_AUTOMATIC);

        $params = local_annoto_get_jsparam($course->id, 0);

        $this->assertSame([], $params['cmCompletions']);
    }

    /**
     * A teacher gets the requirements without any progress: progress is sent only to a user scope.
     *
     * @covers ::local_annoto_get_course_cm_completions
     */
    public function test_get_course_cm_completions_no_progress_for_moderator_scope(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_completion_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $completion = $this->create_annoto_completion($course, $page->cmid, annoto_completion::COMPLETION_TRACKING_AUTOMATIC);
        (new annoto_completiondata(0, (object)[
            'completionid' => $completion->get('id'),
            'userid' => $student->id,
            'data' => '{"completion":50,"comments":0,"replies":0}',
        ]))->create();

        $reqs = local_annoto_get_course_cm_completions(get_course($course->id), 'super-mod');

        $this->assertCount(1, $reqs);
        $this->assertFalse(property_exists($reqs[0], 'user_data'));
    }

    /**
     * Another user's progress on a label is never sent: user_data is only the current user's.
     *
     * @covers ::local_annoto_get_jsparam
     */
    public function test_get_jsparam_course_page_omits_other_users_progress(): void {
        $this->resetAfterTest();
        [$course] = $this->setup_completion_course();
        $other = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $label = $this->getDataGenerator()->create_module('label', ['course' => $course->id]);
        $completion = $this->create_annoto_completion($course, $label->cmid, annoto_completion::COMPLETION_TRACKING_AUTOMATIC);
        (new annoto_completiondata(0, (object)[
            'completionid' => $completion->get('id'),
            'userid' => $other->id,
            'data' => '{"completion":100,"comments":3,"replies":0}',
        ]))->create();

        $params = local_annoto_get_jsparam($course->id, 0);

        $this->assertCount(1, $params['cmCompletions']);
        $this->assertFalse(property_exists($params['cmCompletions'][0], 'user_data'));
    }

    /**
     * With activity completion turned off in the plugin settings nothing is sent from the course page.
     *
     * @covers ::local_annoto_get_jsparam
     */
    public function test_get_jsparam_course_page_empty_when_completion_disabled(): void {
        $this->resetAfterTest();
        [$course] = $this->setup_completion_course();
        $label = $this->getDataGenerator()->create_module('label', ['course' => $course->id]);
        $this->create_annoto_completion($course, $label->cmid, annoto_completion::COMPLETION_TRACKING_AUTOMATIC);
        set_config('activitycompletion', 0, 'local_annoto');

        $params = local_annoto_get_jsparam($course->id, 0);

        $this->assertSame([], $params['cmCompletions']);
    }

    /**
     * An activity page keeps sending its own requirements as before, and no course page list.
     *
     * @covers ::local_annoto_get_jsparam
     */
    public function test_get_jsparam_activity_page_unchanged(): void {
        $this->resetAfterTest();
        [$course] = $this->setup_completion_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $label = $this->getDataGenerator()->create_module('label', ['course' => $course->id]);
        $this->create_annoto_completion($course, $page->cmid, annoto_completion::COMPLETION_TRACKING_AUTOMATIC);
        $this->create_annoto_completion($course, $label->cmid, annoto_completion::COMPLETION_TRACKING_AUTOMATIC);

        $params = local_annoto_get_jsparam($course->id, $page->cmid);

        $this->assertTrue($params['activityCompletionEnabled']);
        $this->assertEquals($page->cmid, $params['activityCompletionReq']->cmid);
        $this->assertSame([], $params['cmCompletions']);
    }
}
