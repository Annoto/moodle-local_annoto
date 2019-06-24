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
 * External interface library for customfields component
 *
 * @package   local_annoto
 * @copyright Annoto Ltd.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . "/local/annoto/lib.php");

/**
 * Class local_annoto_external
 *
 * @copyright Annoto Ltd.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_annoto_external extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_jsparams_parameters() {
        return new external_function_parameters(
                array(
                  'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_DEFAULT, null),
                  'pageurl' => new external_value(PARAM_URL, 'Page URL', VALUE_DEFAULT, null),
                  'modid' => new external_value(PARAM_INT, 'Mod id', VALUE_OPTIONAL)
                )
        );
    }

    /**
     * Returns result
     * @return result
     */
    public static function get_jsparams_returns() {
        return new external_value(PARAM_TEXT, 'json jsparams');
    }

    /**
     * Get parameters for Anooto's JS script
     * @return array
     */
    public static function get_jsparams($courseid, $pageurl, $modid) {
      global $CFG;
      $course = get_course($courseid);

      // Get plugin global settings.
      $settings = get_config('local_annoto');

      // Set id of the video frame where script should be attached.
      $defaultplayerid = 'annoto_default_player_id';
      $isglobalscope = filter_var($settings->scope, FILTER_VALIDATE_BOOLEAN);

      // If scope is not Global - check if url is in access list.
      if (!$isglobalscope) {
          // ACL.
          $acltext = ($settings->acl) ? $settings->acl : null;
          $aclarr = preg_split("/\R/", $acltext);
          $iscourseinacl = false;
          $isurlinacl = false;

          $iscourseinacl = in_array($courseid, $aclarr);

          if (!$iscourseinacl) {
              $isurlinacl = in_array($pageurl, $aclarr);
          }
          $isaclmatch = ($iscourseinacl || $isurlinacl);
      }

      // Get login, logout urls.
      $loginurl = $CFG->wwwroot . "/login/index.php";
      $logouturl = $CFG->wwwroot . "/login/logout.php?sesskey=" . sesskey();

      // Get activity data for mediaDetails.
      if ($modid) {
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($modid);
        $cmtitle = $cm->name;
        $cmintro = $cm->content ?? ""; // Set empty value, if there is no valid intro.
      }

      // Locale settings.
      if ($settings->locale == "auto") {
          $lang = local_annoto_get_lang($course);
      } else {
          $lang = $settings->locale;
      }
      $widgetposition = 'right';
      $widgetverticalalign = 'center';
      if (stripos($settings->widgetposition, 'left') !== false) {
          $widgetposition = 'left';
      }
      if (stripos($settings->widgetposition, 'top') !== false) {
          $widgetverticalalign = 'top';
      }
      if (stripos($settings->widgetposition, 'bottom') !== false) {
          $widgetverticalalign = 'bottom';
      }

      $jsparams = array(
          'bootstrapUrl' => $settings->scripturl,
          'clientId' => $settings->clientid,
          'userToken' => local_annoto_get_user_token($settings, $courseid),
          'position' => $widgetposition,
          'alignVertical' => $widgetverticalalign,
          'widgetOverlay' => $settings->widgetoverlay,
          'featureTab' => !empty($settings->tabs) ? filter_var($settings->tabs, FILTER_VALIDATE_BOOLEAN) : true,
          'featureCTA' => !empty($settings->cta) ? filter_var($settings->cta, FILTER_VALIDATE_BOOLEAN) : false,
          'loginUrl' => $loginurl,
          'logoutUrl' => $logouturl,
          'mediaTitle' => $cmtitle,
          'mediaDescription' => $cmintro,
          'mediaGroupId' => $courseid,
          'mediaGroupTitle' => $course->fullname,
          'mediaGroupDescription' => $course->summary,
          'privateThread' => filter_var($settings->discussionscope, FILTER_VALIDATE_BOOLEAN),
          'locale' => $lang,
          'rtl' => filter_var((substr($lang, 0, 2) === "he"), FILTER_VALIDATE_BOOLEAN),
          'demoMode' => filter_var($settings->demomode, FILTER_VALIDATE_BOOLEAN),
          'defaultPlayerId' => $defaultplayerid,
          'zIndex' => !empty($settings->zindex) ? filter_var($settings->zindex, FILTER_VALIDATE_INT) : 100,
          'isGlobalScope' => $isglobalscope,
          'isACLmatch' => !empty($isaclmatch) ? filter_var($isaclmatch, FILTER_VALIDATE_BOOLEAN) : false,
      );

      return  json_encode($jsparams, JSON_HEX_TAG);
    }

}
