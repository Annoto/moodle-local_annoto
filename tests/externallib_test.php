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
 * Unit tests for local_annoto external functions.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_annoto;

/**
 * Tests for the set_completion external function.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_annoto_external::set_completion
 */
final class externallib_test extends \advanced_testcase {

    /**
     * Load the external functions under test.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/local/annoto/externallib.php');
    }

    /**
     * Create a course with a page module that has Annoto automatic completion tracking.
     *
     * @param bool $visible whether the module is visible to students
     * @return array{0: \stdClass, 1: \stdClass} the course and the module
     */
    private function create_tracked_module(bool $visible): array {
        set_config('activitycompletion', 1, 'local_annoto');
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'visible' => (int)$visible]);
        $completion = new annoto_completion(0, (object) [
            'courseid' => $course->id,
            'cmid' => $page->cmid,
            'enabled' => annoto_completion::COMPLETION_TRACKING_AUTOMATIC,
        ]);
        $completion->create();

        return [$course, $page];
    }

    /**
     * Build the JSON payload the bundle sends for a module.
     *
     * @param int $cmid course module id
     * @return string
     */
    private static function payload(int $cmid): string {
        return json_encode(['cmid' => $cmid, 'completion' => 50]);
    }

    /**
     * An enrolled student on a visible module stores their completion.
     */
    public function test_set_completion_stores_for_enrolled_student(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_tracked_module(true);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $result = \local_annoto_external::set_completion(self::payload($page->cmid));

        $this->assertTrue($result['status']);
        $this->assertSame(1, annoto_completiondata::count_records(['userid' => $student->id]));
    }

    /**
     * A student must not write completion for a module hidden from them.
     */
    public function test_set_completion_rejects_hidden_module(): void {
        $this->resetAfterTest();
        [$course, $page] = $this->create_tracked_module(false);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\require_login_exception::class);
        \local_annoto_external::set_completion(self::payload($page->cmid));
    }

    /**
     * A user who is not enrolled in the course must not write completion.
     */
    public function test_set_completion_rejects_unenrolled_user(): void {
        $this->resetAfterTest();
        [, $page] = $this->create_tracked_module(true);
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\require_login_exception::class);
        \local_annoto_external::set_completion(self::payload($page->cmid));
    }
}
