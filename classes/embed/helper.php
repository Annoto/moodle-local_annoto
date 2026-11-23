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

namespace local_annoto\embed;

use context_course;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * "Embed Annoto Media" (editor button) LTI 1.3 helper.
 *
 * Flow (all LTI 1.3, through mod_lti's keys and tool registration):
 *  - embed/select.php starts an OIDC login for a Deep Linking request (editor dialog).
 *  - lti/auth.php (the platform authorization endpoint registered with Annoto) answers with a
 *    LtiDeepLinkingRequest whose return URL is embed/return.php.
 *  - embed/return.php validates the LtiDeepLinkingResponse and hands a placeholder link to the editor.
 *  - filter_annoto turns the placeholder into an iframe to embed/launch.php, which starts a per-viewer
 *    LtiResourceLinkRequest to the Annoto launch URL.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /** Prefix of the launch ids this plugin stores in $SESSION and sends in lti_message_hint. */
    const LAUNCHID_PREFIX = 'localannotoembed_';

    /** Launch kind: Deep Linking request from the editor. */
    const KIND_DEEPLINK = 'deeplink';

    /** Launch kind: per-viewer resource link launch of an embedded item. */
    const KIND_LAUNCH = 'launch';

    /** Path of the launch page; the placeholder link points here and filter_annoto looks for it. */
    const LAUNCH_PATH = '/local/annoto/embed/launch.php';

    /** CSS class of the placeholder link inserted by the editor plugins. */
    const PLACEHOLDER_CLASS = 'local-annoto-embed';

    /** Default embed width when the tool sends no iframe size. */
    const DEFAULT_WIDTH = 640;

    /** Default embed height when the tool sends no iframe size. */
    const DEFAULT_HEIGHT = 360;

    /** Max number of custom parameters carried by a placeholder. */
    const MAX_CUSTOM = 10;

    /**
     * Returns the LTI 1.3 tool type (lti_types record) used for Embed Annoto Media, or null.
     *
     * The admin setting local_annoto/embedltitype wins; when it is 0 (auto), the first configured
     * LTI 1.3 tool on the Annoto deployment domain whose name or base URL mentions "embed" is used.
     *
     * @return stdClass|null
     */
    public static function get_tool_type(): ?stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/lti/locallib.php');

        $typeid = (int) get_config('local_annoto', 'embedltitype');
        if ($typeid > 0) {
            $type = $DB->get_record('lti_types', ['id' => $typeid]);
            if ($type && $type->ltiversion === LTI_VERSION_1P3 && (int) $type->state === LTI_TOOL_STATE_CONFIGURED) {
                return $type;
            }
            return null;
        }

        $domain = self::get_deployment_domain();
        $types = $DB->get_records(
            'lti_types',
            ['ltiversion' => LTI_VERSION_1P3, 'state' => LTI_TOOL_STATE_CONFIGURED, 'course' => SITEID],
            'id ASC'
        );
        foreach ($types as $type) {
            $host = (string) parse_url((string) $type->baseurl, PHP_URL_HOST);
            if ($host === '' || !self::host_matches_domain($host, $domain)) {
                continue;
            }
            if (stripos($type->baseurl, 'embed') !== false || stripos($type->name, 'embed') !== false) {
                return $type;
            }
        }
        return null;
    }

    /**
     * Whether the editor button can work on this site (an embed tool type is configured).
     *
     * @return bool
     */
    public static function is_configured(): bool {
        try {
            return self::get_tool_type() !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Whether the current user may open the Annoto picker in this context.
     *
     * @param \context $context Editor context.
     * @return bool
     */
    public static function can_embed(\context $context): bool {
        $coursecontext = $context->get_course_context(false);
        if (!$coursecontext) {
            $coursecontext = context_course::instance(SITEID);
        }
        return has_capability('local/annoto:embedmedia', $coursecontext);
    }

    /**
     * Returns the course for an editor context (site course when the context is outside a course).
     *
     * @param \context $context
     * @return stdClass course record
     */
    public static function get_course_for_context(\context $context): stdClass {
        $coursecontext = $context->get_course_context(false);
        $courseid = $coursecontext ? $coursecontext->instanceid : SITEID;
        return get_course($courseid);
    }

    /**
     * Configured Annoto deployment domain (eu.annoto.net, us.annoto.net or custom).
     *
     * @return string
     */
    public static function get_deployment_domain(): string {
        $domain = get_config('local_annoto', 'deploymentdomain');
        if ($domain === 'custom') {
            $domain = get_config('local_annoto', 'customdomain');
        }
        return strtolower(trim((string) $domain));
    }

    /**
     * Whether $host is $domain or a subdomain of it.
     *
     * @param string $host
     * @param string $domain
     * @return bool
     */
    public static function host_matches_domain(string $host, string $domain): bool {
        $host = strtolower($host);
        $domain = strtolower(ltrim($domain, '.'));
        if ($domain === '') {
            return false;
        }
        return $host === $domain || substr($host, -strlen('.' . $domain)) === '.' . $domain;
    }

    /**
     * Validates that a launch URL returned by the tool may receive signed launches for this tool type.
     *
     * The URL must be https (unless the tool URL itself is http, for local testing) and on the same
     * host as the tool type's tool URL or OIDC login URL, so a crafted placeholder can never make
     * Moodle send a signed id_token with the viewer's identity anywhere else.
     *
     * @param string $url
     * @param stdClass $type lti_types record
     * @return bool
     */
    public static function is_valid_launch_url(string $url, stdClass $type): bool {
        $parts = parse_url($url);
        if (empty($parts['host']) || empty($parts['scheme'])) {
            return false;
        }
        $config = lti_get_type_config($type->id);
        $allowedhosts = [];
        $allowedschemes = ['https'];
        foreach ([$type->baseurl, $config['toolurl'] ?? '', $config['initiatelogin'] ?? ''] as $candidate) {
            $host = parse_url((string) $candidate, PHP_URL_HOST);
            if ($host) {
                $allowedhosts[] = strtolower($host);
                if (parse_url((string) $candidate, PHP_URL_SCHEME) === 'http') {
                    $allowedschemes[] = 'http';
                }
            }
        }
        return in_array(strtolower($parts['host']), $allowedhosts, true)
            && in_array(strtolower($parts['scheme']), $allowedschemes, true);
    }

    /**
     * Cleans custom parameters coming from a content item or a placeholder.
     *
     * @param mixed $custom object/array of key => scalar
     * @return array
     */
    public static function clean_custom($custom): array {
        $result = [];
        if (!is_array($custom) && !is_object($custom)) {
            return $result;
        }
        foreach ((array) $custom as $key => $value) {
            if (count($result) >= self::MAX_CUSTOM) {
                break;
            }
            if (!is_string($key) || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $key) || !is_scalar($value)) {
                continue;
            }
            $value = clean_param((string) $value, PARAM_TEXT);
            if (\core_text::strlen($value) > 255 || strpos($value, "\n") !== false) {
                continue;
            }
            $result[$key] = $value;
        }
        return $result;
    }

    /**
     * Stores the launch data in the session and returns the OIDC third-party login initiation form.
     *
     * @param stdClass $type lti_types record
     * @param int $courseid
     * @param string $kind self::KIND_DEEPLINK or self::KIND_LAUNCH
     * @param string $targetlinkuri The tool URL the launch should end on
     * @param array $data Extra data for lti/auth.php (resource link id, custom params, title...)
     * @return string HTML that auto-submits the login request
     */
    public static function initiate_login(
        stdClass $type,
        int $courseid,
        string $kind,
        string $targetlinkuri,
        array $data = []
    ): string {
        global $CFG, $SESSION, $USER;

        $config = lti_get_type_type_config($type->id);
        if (empty($config->lti_initiatelogin) || empty($config->lti_clientid)) {
            throw new moodle_exception('embed_notconfigured', 'local_annoto');
        }

        $launchid = self::LAUNCHID_PREFIX . random_string(20);
        $SESSION->$launchid = json_encode([
            'kind' => $kind,
            'typeid' => (int) $type->id,
            'courseid' => $courseid,
            'targetlinkuri' => $targetlinkuri,
            'data' => $data,
            'time' => time(),
        ]);

        if (isset($config->lti_forcessl) && $config->lti_forcessl == '1') {
            $targetlinkuri = lti_ensure_url_is_https($targetlinkuri);
        }

        $params = [
            'iss' => $CFG->wwwroot,
            'target_link_uri' => $targetlinkuri,
            'login_hint' => $USER->id,
            'lti_message_hint' => json_encode(['launchid' => $launchid]),
            'client_id' => $config->lti_clientid,
            'lti_deployment_id' => $config->typeid,
        ];

        return self::auto_post_form($config->lti_initiatelogin, $params, 'ltiInitiateLoginForm');
    }

    /**
     * Returns the stored launch data for a launch id from lti_message_hint, or null if it is not ours.
     * The data is removed from the session (single use).
     *
     * @param string $ltimessagehint raw lti_message_hint
     * @return array|null
     */
    public static function consume_launch(string $ltimessagehint): ?array {
        global $SESSION;
        $hint = json_decode($ltimessagehint);
        if (
            !is_object($hint) || empty($hint->launchid) || !is_string($hint->launchid)
                || strpos($hint->launchid, self::LAUNCHID_PREFIX) !== 0
        ) {
            return null;
        }
        $launchid = $hint->launchid;
        if (!preg_match('/^[A-Za-z0-9_]+$/', $launchid) || empty($SESSION->$launchid)) {
            return [];
        }
        $data = json_decode($SESSION->$launchid, true);
        unset($SESSION->$launchid);
        if (!is_array($data) || empty($data['time']) || $data['time'] < time() - HOURSECS) {
            return [];
        }
        return $data;
    }

    /**
     * Whether an lti_message_hint belongs to this plugin (without consuming it).
     *
     * @param string $ltimessagehint
     * @return bool
     */
    public static function is_own_hint(string $ltimessagehint): bool {
        $hint = json_decode($ltimessagehint);
        return is_object($hint) && !empty($hint->launchid) && is_string($hint->launchid)
            && strpos($hint->launchid, self::LAUNCHID_PREFIX) === 0;
    }

    /**
     * Builds the signed LtiDeepLinkingRequest.
     *
     * @param stdClass $type lti_types record
     * @param stdClass $course
     * @param string $nonce
     * @return array [endpoint, params]
     */
    public static function build_deep_linking_request(stdClass $type, stdClass $course, string $nonce): array {
        $returnurl = new moodle_url('/local/annoto/embed/return.php', [
            'course' => $course->id,
            'sesskey' => sesskey(),
        ]);
        $request = lti_build_content_item_selection_request(
            $type->id,
            $course,
            $returnurl,
            get_string('embed_dialogtitle', 'local_annoto'),
            '',
            [],
            ['iframe', 'window'],
            false, // Auto create.
            false, // Multiple.
            false, // Unsigned.
            false, // Can confirm.
            false, // Copy advice.
            $nonce
        );
        return [$request->url, $request->params];
    }

    /**
     * Builds the signed LtiResourceLinkRequest for one embedded item, for the current user.
     *
     * Mirrors lti_get_launch_data() for a tool type without a mod_lti instance: no gradebook/AGS
     * service parameters are added (editor embeds have no grade item).
     *
     * @param stdClass $type lti_types record
     * @param stdClass $course
     * @param string $endpoint Validated Annoto launch URL
     * @param array $data ['rl' => resource link id, 'custom' => array, 'title' => string]
     * @param string $nonce
     * @return array [endpoint, params]
     */
    public static function build_resource_link_request(
        stdClass $type,
        stdClass $course,
        string $endpoint,
        array $data,
        string $nonce
    ): array {
        $typeconfig = lti_get_type_config($type->id);
        foreach (['sendname', 'sendemailaddr', 'acceptgrades'] as $key) {
            if (!isset($typeconfig[$key])) {
                $typeconfig[$key] = LTI_SETTING_NEVER;
            }
        }

        $customstr = '';
        foreach (self::clean_custom($data['custom'] ?? []) as $key => $value) {
            $customstr .= $key . '=' . $value . "\n";
        }

        $instance = new stdClass();
        $instance->id = 0;
        $instance->cmid = 0;
        $instance->course = $course->id;
        $instance->typeid = $type->id;
        $instance->resource_link_id = clean_param($data['rl'] ?? '', PARAM_ALPHANUMEXT);
        $instance->name = clean_param($data['title'] ?? '', PARAM_TEXT);
        $instance->toolurl = $endpoint;
        $instance->instructorcustomparameters = $customstr;
        $instance->instructorchoicesendname = LTI_SETTING_DELEGATE;
        $instance->instructorchoicesendemailaddr = LTI_SETTING_DELEGATE;
        $instance->instructorchoiceacceptgrades = LTI_SETTING_NEVER;
        $instance->servicesalt = '';

        $messagetype = 'basic-lti-launch-request';
        $params = lti_build_request($instance, $typeconfig, $course, $type->id, false, $messagetype);
        $params = array_merge($params, lti_build_standard_message(
            $instance,
            lti_get_organizationid($typeconfig),
            LTI_VERSION_1P3,
            $messagetype
        ));
        $tool = clone $type;
        $tool->enabledcapability = $typeconfig['enabledcapability_LtiResourceLinkRequest'] ?? '';
        $tool->parameter = $typeconfig['parameter_LtiResourceLinkRequest'] ?? '';
        $params = array_merge($params, lti_build_custom_parameters(
            null,
            $tool,
            $instance,
            $params,
            $typeconfig['customparameters'] ?? '',
            $customstr,
            false
        ));
        $params['launch_presentation_document_target'] = 'iframe';
        $params['launch_presentation_return_url'] = (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);

        $signed = lti_sign_jwt($params, $endpoint, $type->clientid, $type->id, $nonce);
        return [$endpoint, $signed];
    }

    /**
     * Turns the first usable content item of a Deep Linking response into placeholder HTML.
     *
     * @param string $contentitems JSON from lti_convert_from_jwt() (@graph format)
     * @param stdClass $type lti_types record
     * @return string|null placeholder HTML, or null when nothing usable was returned (cancel)
     */
    public static function placeholder_from_content_items(string $contentitems, stdClass $type): ?string {
        $json = json_decode($contentitems);
        $items = $json->{'@graph'} ?? [];
        foreach ($items as $item) {
            if (empty($item->url) || !is_string($item->url)) {
                continue;
            }
            if (!self::is_valid_launch_url($item->url, $type)) {
                throw new moodle_exception('embed_invalidurl', 'local_annoto');
            }
            $width = (int) ($item->placementAdvice->displayWidth ?? 0);
            $height = (int) ($item->placementAdvice->displayHeight ?? 0);
            return self::build_placeholder([
                'url' => $item->url,
                'custom' => self::clean_custom($item->custom ?? []),
                'rl' => 'annoto-' . random_string(24),
                'title' => isset($item->title) && is_string($item->title) ? $item->title : '',
                'width' => $width,
                'height' => $height,
            ]);
        }
        return null;
    }

    /**
     * Builds the placeholder link stored in the text. It is plain HTML that survives HTML Purifier;
     * with filter_annoto it renders as an iframe, without it it opens the media in a new tab.
     *
     * @param array $embed url, custom, rl, title, width, height
     * @return string
     */
    public static function build_placeholder(array $embed): string {
        $url = self::build_launch_url($embed, 0);
        $title = trim((string) ($embed['title'] ?? ''));
        if ($title === '') {
            $title = get_string('embed_defaulttitle', 'local_annoto');
        }
        return \html_writer::link($url, s($title), [
            'class' => self::PLACEHOLDER_CLASS,
            'target' => '_blank',
            'rel' => 'noopener',
        ]);
    }

    /**
     * Builds the launch.php URL for an embed.
     *
     * @param array $embed url, custom, rl, title, width, height
     * @param int $courseid Course the embed is displayed in (0 = unknown)
     * @return moodle_url
     */
    public static function build_launch_url(array $embed, int $courseid): moodle_url {
        $params = [
            'url' => $embed['url'],
            'rl' => $embed['rl'],
        ];
        $custom = self::clean_custom($embed['custom'] ?? []);
        if ($custom) {
            $params['custom'] = json_encode($custom);
        }
        if (!empty($embed['title'])) {
            $params['title'] = \core_text::substr((string) $embed['title'], 0, 255);
        }
        [$width, $height] = self::clean_size((int) ($embed['width'] ?? 0), (int) ($embed['height'] ?? 0));
        $params['w'] = $width;
        $params['h'] = $height;
        if ($courseid > 0) {
            $params['course'] = $courseid;
        }
        return new moodle_url(self::LAUNCH_PATH, $params);
    }

    /**
     * Clamps an embed size, falling back to the admin defaults.
     *
     * @param int $width
     * @param int $height
     * @return int[] [width, height]
     */
    public static function clean_size(int $width, int $height): array {
        if ($width < 100 || $width > 3840) {
            $width = (int) get_config('local_annoto', 'embedwidth') ?: self::DEFAULT_WIDTH;
        }
        if ($height < 100 || $height > 2160) {
            $height = (int) get_config('local_annoto', 'embedheight') ?: self::DEFAULT_HEIGHT;
        }
        return [$width, $height];
    }

    /**
     * Returns an auto-submitting POST form.
     *
     * @param string $action
     * @param array $params
     * @param string $formid
     * @return string
     */
    public static function auto_post_form(string $action, array $params, string $formid): string {
        $r = '<form action="' . s($action) . '" name="' . $formid . '" id="' . $formid . '" method="post" ' .
            'enctype="application/x-www-form-urlencoded">' . "\n";
        foreach ($params as $key => $value) {
            $r .= '  <input type="hidden" name="' . s($key) . '" value="' . s($value) . '"/>' . "\n";
        }
        $r .= "</form>\n";
        $r .= "<script type=\"text/javascript\">\n//<![CDATA[\ndocument.getElementById('" . $formid . "').submit();\n//]]>\n" .
            "</script>\n";
        return $r;
    }

    /**
     * Echoes a minimal HTML page wrapping $body (used for auto-submitting LTI forms).
     *
     * @param string $body
     */
    public static function output_raw_page(string $body): void {
        echo "<!DOCTYPE html>\n<html><head><meta charset=\"utf-8\"><title>Annoto</title></head><body>\n" . $body .
            "</body></html>";
    }

    /**
     * Shows the cross-site repost page when the session cookie was not sent with a cross-site POST.
     * Returns true when the page was output and the caller must stop.
     *
     * @param moodle_url $pageurl URL of the calling page
     * @return bool
     */
    public static function repost_if_needed(moodle_url $pageurl): bool {
        global $PAGE, $_POST;
        if (!empty($_POST['repost'])) {
            unset($_POST['repost']);
            return false;
        }
        if (isloggedin() || empty($_POST)) {
            return false;
        }
        header_remove('Set-Cookie');
        $PAGE->set_pagelayout('popup');
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url($pageurl);
        $output = $PAGE->get_renderer('mod_lti');
        $page = new \mod_lti\output\repost_crosssite_page($_SERVER['REQUEST_URI'], $_POST);
        echo $output->header();
        echo $output->render($page);
        echo $output->footer();
        return true;
    }
}
