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
 * Admin setting that allows a user to pick appropriate roles for something.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Admin setting that allows a user to pick appropriate roles for something.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_annoto_admin_setting_custompickroles extends admin_setting_configmulticheckbox {
    /** @var array Array of capabilities which identify roles */
    private $types;

    /**
     * Constructs with item details.
     *
     * @param string $name Name of config variable
     * @param string $visiblename Display name
     * @param string $description Description
     * @param array $types Array of capabilities (usually moodle/legacy:something)
     * which identify roles that will be enabled by default. Default is the
     * student role
     */
    public function __construct($name, $visiblename, $description, $types) {
        parent::__construct($name, $visiblename, $description, null, null);
        $this->types = $types;
    }

    /**
     * Load roles as choices
     *
     * @return bool true=>success, false=>error
     */
    public function load_choices() {
        global $CFG, $DB;
        if (during_initial_install()) {
            return false;
        }
        if (is_array($this->choices)) {
            return true;
        }

        $reqcapabilities = 'local/annoto:moderatediscussion';

        if ($caproles = get_roles_with_capability($reqcapabilities, CAP_ALLOW)) {
            $this->choices = role_fix_names($caproles, null, ROLENAME_ORIGINAL, true);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Return the default setting for this control
     *
     * @return array Array of default settings
     */
    public function get_defaultsetting() {
        global $CFG;

        if (during_initial_install()) {
            return null;
        }
        $result = array();
        foreach ($this->types as $archetype) {
            if ($caproles = get_archetype_roles($archetype)) {
                foreach ($caproles as $caprole) {
                    $result[$caprole->id] = 1;
                }
            }
        }
        return $result;
    }
}
