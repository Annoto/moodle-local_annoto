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
 * Privacy class for requesting user data.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_annoto\privacy;

use core_privacy\local\metadata\collection;

/**
 * Privacy class for requesting user data.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        // This portfolio plugin does not store any data itself.
        // It has no database tables, and it purely acts as a conduit, sending data externally.
        \core_privacy\local\metadata\provider {
    /**
     * Returns meta data about this plugin.
     *
     * @param   collection $collection The initialised collection to add items to.
     * @return  collection A listing of user data stored through this plugin.
     */
    public static function get_metadata(collection $collection): collection {

        $collection->add_external_location_link('annoto', [
            'userid' => 'privacy:metadata:annoto:userid',
            'fullname' => 'privacy:metadata:annoto:fullname',
            'email' => 'privacy:metadata:annoto:email',
          ], 'privacy:metadata:annoto');

          return $collection;
    }
}
