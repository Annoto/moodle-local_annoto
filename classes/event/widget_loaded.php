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
 * The Annoto widget was loaded on a video for a user.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_annoto\event;

/**
 * The Annoto widget was loaded on a video for a user.
 *
 * Triggered by the local_annoto_widget_loaded web service, which the CDN bundle calls once it
 * found a video on the page and loaded the widget on it. Its context is the activity, or the
 * course when the video is on the course page. The telemetry counts distinct users of these
 * events (n_learners_active_7d); the user id stays in Moodle's log and is never sent.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class widget_loaded extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventwidgetloaded', 'local_annoto');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' loaded the Annoto widget in the context with id '$this->contextid'.";
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if ($this->contextlevel != CONTEXT_MODULE && $this->contextlevel != CONTEXT_COURSE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE or CONTEXT_COURSE.');
        }
    }
}
