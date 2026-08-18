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
 * Server-side usage telemetry via the Google Analytics 4 Measurement Protocol.
 *
 * Once enabled by the site administrator, the plugin "phones home" via cron: the
 * first scheduled-task run reports an install event, then a weekly heartbeat, so
 * Annoto can see which sites use the plugin. Only non-personal, site-level
 * information is sent (site URL/name, versions, region, aggregate counts) — no
 * administrator, learner or student personal data. All sending is best-effort and
 * never interrupts Moodle.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_annoto;

defined('MOODLE_INTERNAL') || die();

/**
 * Sends install/usage telemetry to Annoto's GA4 property.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class telemetry {

    // --------------------------------------------------------------------
    // GA4 credentials for Annoto's telemetry property (Measurement Protocol).
    // These ship with the plugin so telemetry can reach Annoto's GA4 stream; the
    // API secret is a write-only Measurement Protocol key. Sending is still gated:
    // nothing is transmitted until an administrator has saved the plugin settings
    // and not opted out (see is_enabled()). If these are blanked or left as the
    // PLACEHOLDER_* values below, telemetry no-ops entirely (see is_configured()).
    // --------------------------------------------------------------------

    /** @var string GA4 Measurement ID for Annoto's telemetry property (stream "moodle.org", id 3099607047). */
    const GA_MEASUREMENT_ID = 'G-TXK6YE54L1';

    /** @var string GA4 Measurement Protocol API secret — MUST be created under the SAME stream as the Measurement ID above. */
    const GA_API_SECRET = 'KqRuSIWdTuipYqPfCgNSog';

    /** @var string Shipped placeholder for the Measurement ID; telemetry no-ops while unchanged. */
    const PLACEHOLDER_MEASUREMENT_ID = 'G-XXXXXXXXXX';

    /** @var string Shipped placeholder for the API secret; telemetry no-ops while unchanged. */
    const PLACEHOLDER_API_SECRET = 'REPLACE_WITH_API_SECRET';

    /** @var string GA4 Measurement Protocol collect endpoint. */
    const GA_ENDPOINT = 'https://www.google-analytics.com/mp/collect';

    /** @var int Minimum number of seconds between heartbeats. */
    const HEARTBEAT_INTERVAL = WEEKSECS;

    /** @var int GA4 event parameter value length limit. */
    const MAX_PARAM_LEN = 100;

    /** @var int GA4 user-property value length limit. */
    const MAX_USERPROP_LEN = 36;

    /** @var string Shipped default for the widget bootstrap URL (settings.php scripturl). */
    const DEFAULT_SCRIPT_URL = 'https://cdn.annoto.net/widget/latest/bootstrap.js';

    /** @var string Shipped default for the Moodle integration script URL (settings.php moodlejsurl). */
    const DEFAULT_MOODLEJS_URL = 'https://cdn.annoto.net/moodle-local-js/latest/annoto.js';

    /**
     * @var int Mirrors \local_annoto\annoto_completion::COMPLETION_TRACKING_AUTOMATIC (a fixed
     * internal sentinel). Duplicated intentionally so telemetry never has to load that class
     * (its file name differs from its class name, so it is not autoloadable, and it also depends
     * on completionlib.php) — keeping this best-effort path robust.
     */
    const COMPLETION_TRACKING_AUTOMATIC = 9;

    /**
     * Whether telemetry may be sent.
     *
     * Gated on the telemetryenabled config having a stored value — i.e. an admin has
     * saved the plugin settings. The checkbox defaults to on, so a normal save
     * activates telemetry (unless the admin opts out). On the usual install path
     * (adding the plugin to an existing site) this stays silent until that save.
     *
     * Caveat: on a Moodle installed from scratch with this plugin already bundled in
     * the codebase (fresh web/CLI install), Moodle's admin_apply_default_settings()
     * persists the checkbox default without an interactive review, which activates
     * telemetry on the first cron run. This is acceptable for how the plugin is
     * distributed (contrib, added to existing sites). To require a strict opt-in on
     * every install path instead, change the settings.php checkbox default to 0.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        $val = get_config('local_annoto', 'telemetryenabled');
        // Unset -> the admin has not saved the plugin settings yet -> stay silent.
        if ($val === false || $val === null) {
            return false;
        }
        // Saved: on unless the admin explicitly opted out.
        return (bool)(int)$val;
    }

    /**
     * Resolve the GA4 Measurement ID (an optional config override wins over the constant).
     *
     * @return string
     */
    protected static function get_measurement_id(): string {
        $override = get_config('local_annoto', 'ga_measurement_id');
        return !empty($override) ? $override : self::GA_MEASUREMENT_ID;
    }

    /**
     * Resolve the GA4 Measurement Protocol API secret (config override wins over the constant).
     *
     * @return string
     */
    protected static function get_api_secret(): string {
        $override = get_config('local_annoto', 'ga_api_secret');
        return !empty($override) ? $override : self::GA_API_SECRET;
    }

    /**
     * Whether real GA credentials are configured (i.e. not the shipped placeholders).
     *
     * @return bool
     */
    protected static function is_configured(): bool {
        $id = self::get_measurement_id();
        $secret = self::get_api_secret();
        if (empty($id) || empty($secret)) {
            return false;
        }
        if ($id === self::PLACEHOLDER_MEASUREMENT_ID || $secret === self::PLACEHOLDER_API_SECRET) {
            // Still the shipped placeholder(s) — do not send.
            return false;
        }
        return true;
    }

    /**
     * Stable per-site GA client id, generated once and persisted in config.
     *
     * @return string
     */
    public static function get_client_id(): string {
        global $CFG;
        $clientid = get_config('local_annoto', 'telemetry_clientid');
        if (!empty($clientid)) {
            return $clientid;
        }
        // Derive a stable, non-reversible id from the unique site identifier.
        $seed = !empty($CFG->siteidentifier) ? $CFG->siteidentifier : $CFG->wwwroot;
        $clientid = substr(hash('sha256', $seed), 0, 16) . '.' . time();
        set_config('telemetry_clientid', $clientid, 'local_annoto');
        return $clientid;
    }

    /**
     * Gather the site/administrator metadata reported to Annoto.
     *
     * @return array associative array of scalar values.
     */
    public static function collect(): array {
        global $CFG, $DB;

        $site = get_site();

        $pluginman = \core_plugin_manager::instance();
        $info = $pluginman->get_plugin_info('local_annoto');

        // All of the plugin's own settings in one read.
        $settings = get_config('local_annoto');

        // Real people only: exclude deleted users and the guest/admin seed accounts (ids 1 and 2).
        $usercount = (int)$DB->count_records_select('user', 'deleted = 0 AND id > 2');
        // Exclude the site front-page course (id 1).
        $coursecount = (int)$DB->count_records_select('course', 'id > 1');

        // Activity-completion usage: how many activities actually have Annoto automatic
        // completion configured (a real usage indication, not just the on/off setting).
        $completionactive = 0;
        if ($DB->get_manager()->table_exists('local_annoto_completion')) {
            $completionactive = (int)$DB->count_records('local_annoto_completion',
                ['enabled' => self::COMPLETION_TRACKING_AUTOMATIC]);
        }

        // Whether the CDN URLs have been overridden from their shipped defaults.
        $scripturl = $settings->scripturl ?? '';
        $moodlejsurl = $settings->moodlejsurl ?? '';

        // NOTE: intentionally no administrator name/email or any other personal data.
        // Google Analytics' terms prohibit sending PII, and it keeps the telemetry
        // non-personal for privacy compliance.
        return [
            // Environment.
            'site_url'          => self::cap($CFG->wwwroot, self::MAX_PARAM_LEN),
            'site_name'         => self::cap(format_string($site->fullname), self::MAX_PARAM_LEN),
            'moodle_release'    => self::cap($CFG->release, self::MAX_PARAM_LEN),
            'moodle_version'    => self::cap((string)$CFG->version, self::MAX_PARAM_LEN),
            'plugin_release'    => self::cap($info ? $info->release : '', self::MAX_PARAM_LEN),
            'plugin_version'    => self::cap($info ? (string)$info->versiondb : '', self::MAX_PARAM_LEN),
            'region'            => self::cap($settings->deploymentdomain ?? '', self::MAX_PARAM_LEN),
            'lang'              => self::cap(current_language(), self::MAX_PARAM_LEN),
            // Scale.
            'user_count'        => $usercount,
            'course_count'      => $coursecount,
            // Plugin configuration (non-personal on/off indicators).
            'client_configured' => !empty($settings->clientid) ? 1 : 0,
            'sso_configured'    => !empty($settings->ssosecret) ? 1 : 0,
            'dashboard_autoadd' => !empty($settings->addingdashboard) ? 1 : 0,
            'locale_enabled'    => !empty($settings->locale) ? 1 : 0,
            'media_override'    => !empty($settings->mediasettingsoverride) ? 1 : 0,
            'debug_logging'     => !empty($settings->debuglogging) ? 1 : 0,
            'script_custom'     => (!empty($scripturl) && $scripturl !== self::DEFAULT_SCRIPT_URL) ? 1 : 0,
            'moodlejs_custom'   => (!empty($moodlejsurl) && $moodlejsurl !== self::DEFAULT_MOODLEJS_URL) ? 1 : 0,
            // Activity completion.
            'activity_completion'        => !empty($settings->activitycompletion) ? 1 : 0,
            'activity_completion_active' => $completionactive,
        ];
    }

    /**
     * Truncate a value to at most $max characters (multibyte-safe).
     *
     * @param mixed $value
     * @param int $max
     * @return string
     */
    protected static function cap($value, int $max): string {
        $value = (string)$value;
        if (\core_text::strlen($value) > $max) {
            return \core_text::substr($value, 0, $max);
        }
        return $value;
    }

    /**
     * POST a single event to GA4. Best-effort; never throws.
     *
     * Used by send_event() (which gates on the enabled toggle + throttle). Does
     * no gating itself beyond skipping the automated test harnesses.
     *
     * @param string $eventname GA4 event name, e.g. 'annoto_install'.
     * @return array{ok: bool, httpcode: int, errno: int, error: string}
     */
    protected static function dispatch(string $eventname): array {
        global $CFG;

        // Never phone home from the automated test harnesses.
        if ((defined('PHPUNIT_TEST') && PHPUNIT_TEST) || defined('BEHAT_SITE_RUNNING')) {
            return ['ok' => false, 'httpcode' => 0, 'errno' => 0, 'error' => 'skipped in test harness'];
        }

        try {
            $data = self::collect();
            $clientid = self::get_client_id();

            // Event-scoped parameters (GA4: up to 25 params; string values <= 100 chars).
            $params = $data;
            $params['engagement_time_msec'] = 1;
            $params['session_id'] = $clientid;

            // A few short values also as user properties so they can be used as dimensions
            // (GA4 user-property values must be <= 36 chars).
            $userproperties = [
                'region'         => ['value' => self::cap($data['region'], self::MAX_USERPROP_LEN)],
                'plugin_release' => ['value' => self::cap($data['plugin_release'], self::MAX_USERPROP_LEN)],
                'moodle_release' => ['value' => self::cap($data['moodle_release'], self::MAX_USERPROP_LEN)],
            ];

            $payload = [
                'client_id'       => $clientid,
                'events'          => [
                    ['name' => $eventname, 'params' => $params],
                ],
                'user_properties' => $userproperties,
            ];

            $url = self::GA_ENDPOINT . '?' . http_build_query([
                'measurement_id' => self::get_measurement_id(),
                'api_secret'     => self::get_api_secret(),
            ]);

            require_once($CFG->libdir . '/filelib.php');
            $curl = new \curl();
            $curl->setHeader('Content-Type: application/json');
            $curl->post($url, json_encode($payload), [
                'CURLOPT_TIMEOUT'        => 5,
                'CURLOPT_CONNECTTIMEOUT' => 3,
            ]);
            $httpcode = isset($curl->info['http_code']) ? (int)$curl->info['http_code'] : 0;
            $errno = (int)$curl->get_errno();
            $error = $errno ? (string)$curl->error : '';
            $ok = (!$errno && $httpcode >= 200 && $httpcode < 300);
            return ['ok' => $ok, 'httpcode' => $httpcode, 'errno' => $errno, 'error' => $error];
        } catch (\Throwable $e) {
            return ['ok' => false, 'httpcode' => 0, 'errno' => -1, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send a single telemetry event, respecting the admin toggle. Best-effort.
     *
     * @param string $eventname GA4 event name, e.g. 'annoto_install'.
     * @return bool true when the request was accepted (HTTP 2xx), false otherwise.
     */
    public static function send_event(string $eventname): bool {
        if (!self::is_enabled() || !self::is_configured()) {
            return false;
        }
        $res = self::dispatch($eventname);
        if (!$res['ok']) {
            self::log("send failed (event={$eventname}, http={$res['httpcode']}, errno={$res['errno']})");
            return false;
        }
        set_config('telemetry_lastsent', time(), 'local_annoto');
        self::log("sent (event={$eventname}, http={$res['httpcode']})");
        return true;
    }

    /**
     * Cron entry point: send a heartbeat at most once per HEARTBEAT_INTERVAL.
     *
     * The very first successful send is reported as the install signal.
     *
     * @return void
     */
    public static function maybe_send_heartbeat(): void {
        if (!self::is_enabled() || !self::is_configured()) {
            return;
        }
        $last = (int)get_config('local_annoto', 'telemetry_lastsent');
        if ($last && (time() - $last) < self::HEARTBEAT_INTERVAL) {
            return;
        }
        $eventname = $last ? 'annoto_heartbeat' : 'annoto_install';
        self::send_event($eventname);
    }

    /**
     * Developer log helper, gated on the plugin's debug logging setting.
     *
     * @param string $message
     * @return void
     */
    protected static function log(string $message): void {
        if (get_config('local_annoto', 'debuglogging')) {
            debugging('local_annoto telemetry: ' . $message, DEBUG_DEVELOPER);
        }
    }
}
