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
 * first scheduled-task run reports an install event, then a weekly heartbeat (with
 * a companion environment event), so Annoto can see which sites use the plugin and
 * how much. Configuration changes, uninstalls and server-side errors are reported
 * as their own events. Only non-personal, site-level information is sent (site
 * URL/name, versions, aggregate counts, on/off flags) — no administrator, learner
 * or student personal data. All sending is best-effort and never interrupts Moodle.
 *
 * Events and their parameters:
 *  - annoto_install / annoto_heartbeat: collect() + collect_usage(), plus
 *    engagement_time_msec and session_id. GA4 accepts at most 25 parameters per
 *    event and these carry 24, so the site identity (site_id, site_env,
 *    country_code) travels as user properties instead.
 *  - annoto_environment: collect_environment(), sent right after each heartbeat.
 *  - annoto_config_change: config_flags(), when an admin save changes one of them.
 *  - annoto_uninstall: from db/uninstall.php.
 *  - annoto_error: queued by record_error(), sent by the daily telemetry task.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_annoto;

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

    /** @var string GA4 Measurement Protocol validation endpoint (checks a payload, records nothing). */
    const GA_VALIDATE_ENDPOINT = 'https://www.google-analytics.com/debug/mp/collect';

    /** @var int Minimum number of seconds between heartbeats. */
    const HEARTBEAT_INTERVAL = WEEKSECS;

    /** @var int Window of n_completions_7d, in seconds. */
    const USAGE_WINDOW = WEEKSECS;

    /** @var int GA4 event parameter value length limit. */
    const MAX_PARAM_LEN = 100;

    /** @var int GA4 user-property value length limit. */
    const MAX_USERPROP_LEN = 36;

    /** @var int GA4 limit on parameters per event. */
    const MAX_EVENT_PARAMS = 25;

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

    /** @var string[] Values of the siteenv setting. */
    const SITE_ENVS = ['production', 'staging', 'test', 'dev'];

    /** @var string[] Values of the error_area parameter of annoto_error. */
    const ERROR_AREAS = ['client', 'sso', 'completion', 'dashboard'];

    /** @var int Most distinct error codes kept waiting for the next send. */
    const MAX_PENDING_ERRORS = 20;

    /**
     * @var array Media-related plugins reported in video_plugins: label => components, any of
     * which being installed and enabled counts.
     */
    const VIDEO_PLUGINS = [
        'h5p'       => ['mod_h5pactivity'],
        'helixmedia' => ['mod_helixmedia'],
        'hvp'       => ['mod_hvp'],
        'kaltura'   => ['local_kaltura', 'mod_kalvidres', 'mod_kalvidassign', 'filter_kaltura'],
        'opencast'  => ['block_opencast', 'mod_opencast'],
        'panopto'   => ['block_panopto', 'mod_panoptocourseembed'],
        'videojs'   => ['media_videojs'],
        'videotime' => ['mod_videotime'],
        'vimeo'     => ['media_vimeo'],
        'youtube'   => ['media_youtube'],
        'zoom'      => ['mod_zoom'],
    ];

    /** @var bool Whether a config-change check is already registered for this request. */
    protected static $configchangescheduled = false;

    /** @var array Error keys already counted in this request, so a repeated failure writes config once. */
    protected static $errorsthisrequest = [];

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
     * Stable, anonymous identifier of this Moodle instance.
     *
     * A hash of Moodle's siteidentifier, which is generated once at install and does not
     * change with wwwroot. A staging or test copy restored from a production database
     * keeps that database's siteidentifier, so it reports the same site_id as production
     * and is told apart by site_env. Falls back to the persisted GA client id on the rare
     * site with no siteidentifier.
     *
     * @return string 20 hex characters
     */
    public static function get_site_id(): string {
        global $CFG;
        $seed = !empty($CFG->siteidentifier) ? $CFG->siteidentifier : self::get_client_id();
        return substr(hash('sha256', 'local_annoto/site_id:' . $seed), 0, 20);
    }

    /**
     * Guess the site environment from a site URL's host name.
     *
     * Matches whole host labels or label parts split on '.', '-' and '_' (so "contest.edu"
     * is not "test"), optionally followed by digits ("stage2", "test01").
     *
     * @param string $url site URL, e.g. $CFG->wwwroot
     * @return string one of SITE_ENVS
     */
    public static function guess_site_env(string $url): string {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return 'production';
        }
        if (
            $host === 'localhost' || preg_match('/\.(local|localhost|test)$/', $host)
                || preg_match('/^(127\.|10\.|192\.168\.)/', $host)
        ) {
            return 'dev';
        }
        $tokens = preg_split('/[.\-_]/', $host);
        $rules = [
            'staging' => '/^(stage|staging|stg|next|preprod|preview)\d*$/',
            'test'    => '/^(test|testing|qa|uat|sandbox)\d*$/',
            'dev'     => '/^(dev|develop|development)\d*$/',
        ];
        foreach ($rules as $env => $pattern) {
            foreach ($tokens as $token) {
                if (preg_match($pattern, $token)) {
                    return $env;
                }
            }
        }
        return 'production';
    }

    /**
     * The site environment: the admin setting, else a guess from wwwroot.
     *
     * @return string one of SITE_ENVS
     */
    public static function get_site_env(): string {
        global $CFG;
        $env = get_config('local_annoto', 'siteenv');
        if (in_array($env, self::SITE_ENVS, true)) {
            return $env;
        }
        return self::guess_site_env($CFG->wwwroot);
    }

    /**
     * The site's default country (ISO 3166-1 alpha-2), or '' when not set.
     *
     * @return string
     */
    public static function get_country_code(): string {
        global $CFG;
        $country = strtoupper(trim((string)($CFG->country ?? '')));
        return preg_match('/^[A-Z]{2}$/', $country) ? $country : '';
    }

    /**
     * Gather the site/administrator metadata reported to Annoto.
     *
     * These parameters are registered as GA4 custom dimensions and read by Annoto's
     * weekly report: their names and values must stay exactly as they are.
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
            $completionactive = (int)$DB->count_records(
                'local_annoto_completion',
                ['enabled' => self::COMPLETION_TRACKING_AUTOMATIC],
            );
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
            // Deprecated: holds the Annoto deployment domain (e.g. eu.annoto.net), not a
            // geography. Kept unchanged for the existing GA4 dimension; use country_code
            // (a user property) for the site's country.
            'region'            => self::cap($settings->deploymentdomain ?? '', self::MAX_PARAM_LEN),
            'lang'              => self::cap(current_language(), self::MAX_PARAM_LEN),
            // Scale.
            'user_count'        => $usercount,
            'course_count'      => $coursecount,
        ] + self::config_flags($settings) + [
            // Activities with Annoto automatic completion configured (local_annoto_completion rows
            // with enabled = COMPLETION_TRACKING_AUTOMATIC) — not learner completions. The same
            // value is sent as n_activities_completion_cfg, which GA4 can register as a metric.
            'activity_completion_active' => self::count_completion_configured(),
        ];
    }

    /**
     * The plugin's on/off configuration flags, as sent in the heartbeat and in
     * annoto_config_change.
     *
     * @param \stdClass|null $settings the plugin config, or null to read it.
     * @return array flag name => 0|1
     */
    public static function config_flags(?\stdClass $settings = null): array {
        if ($settings === null) {
            $settings = get_config('local_annoto');
        }
        // Whether the CDN URLs have been overridden from their shipped defaults.
        $scripturl = $settings->scripturl ?? '';
        $moodlejsurl = $settings->moodlejsurl ?? '';

        return [
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
            'activity_completion' => !empty($settings->activitycompletion) ? 1 : 0,
        ];
    }

    /**
     * Usage counts sent with the heartbeat, registered in GA4 as custom metrics.
     *
     * Only what Moodle itself records: how much the widget is used is known on Annoto's side.
     *
     * @return array parameter name => int
     */
    public static function collect_usage(): array {
        global $DB;
        $completions = 0;
        if ($DB->get_manager()->table_exists('local_annoto_completion')) {
            // Moodle completions in the window on activities with Annoto automatic completion.
            $completions = (int)$DB->count_records_sql(
                "SELECT COUNT(1)
                   FROM {course_modules_completion} cmc
                   JOIN {local_annoto_completion} ac ON ac.cmid = cmc.coursemoduleid
                  WHERE ac.enabled = :enabled AND cmc.completionstate > 0 AND cmc.timemodified >= :since",
                ['enabled' => self::COMPLETION_TRACKING_AUTOMATIC, 'since' => time() - self::USAGE_WINDOW]
            );
        }
        return [
            'n_activities_completion_cfg' => self::count_completion_configured(),
            'n_completions_7d'            => $completions,
        ];
    }

    /**
     * Activities with Annoto automatic completion configured.
     *
     * @return int
     */
    protected static function count_completion_configured(): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_annoto_completion')) {
            return 0;
        }
        return (int)$DB->count_records(
            'local_annoto_completion',
            ['enabled' => self::COMPLETION_TRACKING_AUTOMATIC]
        );
    }

    /**
     * Environment details, sent in the weekly annoto_environment event.
     *
     * @return array
     */
    public static function collect_environment(): array {
        global $CFG;
        return [
            'php_version'   => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            'db_type'       => self::cap($CFG->dbtype ?? '', self::MAX_PARAM_LEN),
            'theme'         => self::cap($CFG->theme ?? '', self::MAX_PARAM_LEN),
            'video_plugins' => self::cap(implode(',', self::find_video_plugins()), self::MAX_PARAM_LEN),
        ];
    }

    /**
     * Labels of the media-related plugins installed and enabled on this site.
     *
     * @return string[] sorted labels from VIDEO_PLUGINS
     */
    public static function find_video_plugins(): array {
        $pluginman = \core_plugin_manager::instance();
        $found = [];
        foreach (self::VIDEO_PLUGINS as $label => $components) {
            foreach ($components as $component) {
                $info = $pluginman->get_plugin_info($component);
                if (!$info || empty($info->versiondb)) {
                    continue;
                }
                // Null means the plugin type has no on/off switch: installed is enough.
                if ($info->is_enabled() === false) {
                    continue;
                }
                $found[] = $label;
                break;
            }
        }
        sort($found);
        return $found;
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
     * Event parameters for one telemetry event.
     *
     * @param string $eventname GA4 event name.
     * @param array $extra event-specific parameters (annoto_error).
     * @return array
     */
    public static function build_params(string $eventname, array $extra = []): array {
        global $CFG;
        switch ($eventname) {
            case 'annoto_install':
            case 'annoto_heartbeat':
                $params = self::collect() + self::collect_usage();
                break;
            case 'annoto_environment':
                $params = ['site_id' => self::get_site_id(), 'site_url' => self::cap($CFG->wwwroot, self::MAX_PARAM_LEN)]
                    + self::collect_environment();
                break;
            case 'annoto_config_change':
                $params = ['site_id' => self::get_site_id(), 'site_url' => self::cap($CFG->wwwroot, self::MAX_PARAM_LEN),
                    'site_env' => self::get_site_env()] + self::config_flags();
                break;
            case 'annoto_uninstall':
                $params = [
                    'site_id'        => self::get_site_id(),
                    'site_url'       => self::cap($CFG->wwwroot, self::MAX_PARAM_LEN),
                    'plugin_release' => self::cap(self::get_plugin_release(), self::MAX_PARAM_LEN),
                ];
                break;
            default:
                $params = ['site_id' => self::get_site_id()] + $extra;
                break;
        }
        // As every event has always carried: lets GA4 count the site as an active user.
        $params['engagement_time_msec'] = 1;
        $params['session_id'] = self::get_client_id();
        if (get_config('local_annoto', 'telemetry_debugmode')) {
            // Shows the event in GA4 DebugView.
            $params['debug_mode'] = 1;
        }
        return $params;
    }

    /**
     * User properties sent with every event: the release dimensions the report already
     * uses, and the site identity (site_id, site_env, country_code), which has no room
     * left among the heartbeat's event parameters.
     *
     * @return array GA4 user_properties structure
     */
    public static function build_user_properties(): array {
        global $CFG;
        $values = [
            // Deprecated: the deployment domain, kept as it is for the existing dimension.
            'region'         => (string)get_config('local_annoto', 'deploymentdomain'),
            'plugin_release' => self::get_plugin_release(),
            'moodle_release' => (string)$CFG->release,
            'site_id'        => self::get_site_id(),
            'site_env'       => self::get_site_env(),
            'country_code'   => self::get_country_code(),
        ];
        $props = [];
        foreach ($values as $name => $value) {
            $props[$name] = ['value' => self::cap($value, self::MAX_USERPROP_LEN)];
        }
        return $props;
    }

    /**
     * The plugin's release string as installed.
     *
     * @return string
     */
    protected static function get_plugin_release(): string {
        $info = \core_plugin_manager::instance()->get_plugin_info('local_annoto');
        return $info ? (string)$info->release : '';
    }

    /**
     * The full Measurement Protocol payload for one event.
     *
     * @param string $eventname GA4 event name.
     * @param array $extra event-specific parameters.
     * @return array
     */
    public static function build_payload(string $eventname, array $extra = []): array {
        return [
            'client_id'       => self::get_client_id(),
            'events'          => [
                ['name' => $eventname, 'params' => self::build_params($eventname, $extra)],
            ],
            'user_properties' => self::build_user_properties(),
        ];
    }

    /**
     * POST a payload to GA4. Best-effort; never throws.
     *
     * Does no gating itself beyond skipping the automated test harnesses.
     *
     * @param array $payload Measurement Protocol payload.
     * @param bool $validate post to the validation endpoint instead (records nothing).
     * @return array{ok: bool, httpcode: int, errno: int, error: string, body: string}
     */
    public static function post(array $payload, bool $validate = false): array {
        global $CFG;

        // Never phone home from the automated test harnesses.
        if ((defined('PHPUNIT_TEST') && PHPUNIT_TEST) || defined('BEHAT_SITE_RUNNING')) {
            return ['ok' => false, 'httpcode' => 0, 'errno' => 0, 'error' => 'skipped in test harness', 'body' => ''];
        }

        try {
            $url = ($validate ? self::GA_VALIDATE_ENDPOINT : self::GA_ENDPOINT) . '?' . http_build_query([
                'measurement_id' => self::get_measurement_id(),
                'api_secret'     => self::get_api_secret(),
            ]);

            require_once($CFG->libdir . '/filelib.php');
            $curl = new \curl();
            $curl->setHeader('Content-Type: application/json');
            $body = $curl->post($url, json_encode($payload), [
                'CURLOPT_TIMEOUT'        => 5,
                'CURLOPT_CONNECTTIMEOUT' => 3,
            ]);
            $httpcode = isset($curl->info['http_code']) ? (int)$curl->info['http_code'] : 0;
            $errno = (int)$curl->get_errno();
            $error = $errno ? (string)$curl->error : '';
            $ok = (!$errno && $httpcode >= 200 && $httpcode < 300);
            return ['ok' => $ok, 'httpcode' => $httpcode, 'errno' => $errno, 'error' => $error, 'body' => (string)$body];
        } catch (\Throwable $e) {
            return ['ok' => false, 'httpcode' => 0, 'errno' => -1, 'error' => $e->getMessage(), 'body' => ''];
        }
    }

    /**
     * Send a single telemetry event, respecting the admin toggle. Best-effort.
     *
     * @param string $eventname GA4 event name, e.g. 'annoto_install'.
     * @param array $extra event-specific parameters.
     * @return bool true when the request was accepted (HTTP 2xx), false otherwise.
     */
    public static function send_event(string $eventname, array $extra = []): bool {
        if (!self::is_enabled() || !self::is_configured()) {
            return false;
        }
        try {
            $payload = self::build_payload($eventname, $extra);
        } catch (\Throwable $e) {
            self::log("build failed (event={$eventname}): " . $e->getMessage());
            return false;
        }
        $res = self::post($payload);
        if (!$res['ok']) {
            self::log("send failed (event={$eventname}, http={$res['httpcode']}, errno={$res['errno']})");
            return false;
        }
        self::log("sent (event={$eventname}, http={$res['httpcode']})");
        return true;
    }

    /**
     * Cron entry point: send a heartbeat at most once per HEARTBEAT_INTERVAL.
     *
     * The very first successful send is reported as the install signal. Each successful
     * heartbeat is followed by an annoto_environment event.
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
        if (!self::send_event($eventname)) {
            return;
        }
        set_config('telemetry_lastsent', time(), 'local_annoto');
        // The flags now reported are the baseline the next config change is compared with.
        self::store_flags_snapshot(self::flags_snapshot());
        self::send_event('annoto_environment');
    }

    /**
     * Cron entry point: send the errors queued by record_error(), one event per code.
     *
     * @return void
     */
    public static function send_pending_errors(): void {
        $pending = self::get_pending_errors();
        if (!$pending || !self::is_enabled() || !self::is_configured()) {
            return;
        }
        foreach ($pending as $key => $count) {
            [$area, $code] = explode(':', $key, 2);
            $sent = self::send_event('annoto_error', [
                'error_area'  => $area,
                'error_code'  => $code,
                'error_count' => (int)$count,
            ]);
            if ($sent) {
                unset($pending[$key]);
            }
        }
        set_config('telemetry_pendingerrors', $pending ? json_encode($pending) : '', 'local_annoto');
    }

    /**
     * Queue a server-side failure for the next annoto_error send. Never throws.
     *
     * Errors are counted in config and sent by the daily telemetry task, so a failing page
     * view or web service call never waits on Google Analytics. The same code is counted
     * once per request.
     *
     * @param string $area one of ERROR_AREAS
     * @param string $code short fixed code, [a-z0-9_], at most 40 characters; no free text
     * @return void
     */
    public static function record_error(string $area, string $code): void {
        try {
            if (
                !in_array($area, self::ERROR_AREAS, true) || !preg_match('/^[a-z0-9_]{1,40}$/', $code)
                    || !self::is_enabled()
            ) {
                return;
            }
            $key = $area . ':' . $code;
            if (isset(self::$errorsthisrequest[$key])) {
                return;
            }
            self::$errorsthisrequest[$key] = true;
            $pending = self::get_pending_errors();
            if (!isset($pending[$key]) && count($pending) >= self::MAX_PENDING_ERRORS) {
                return;
            }
            $pending[$key] = ($pending[$key] ?? 0) + 1;
            set_config('telemetry_pendingerrors', json_encode($pending), 'local_annoto');
        } catch (\Throwable $e) {
            // Best effort: telemetry must never break the failing code path further.
            return;
        }
    }

    /**
     * Errors waiting to be sent.
     *
     * @return array "area:code" => count
     */
    public static function get_pending_errors(): array {
        $pending = json_decode((string)get_config('local_annoto', 'telemetry_pendingerrors'), true);
        return is_array($pending) ? $pending : [];
    }

    /**
     * Settings "updated" callback: check for a changed flag once this request ends.
     *
     * Several settings can change in one admin save, each calling this; the check runs once,
     * at shutdown, after every setting of the save is stored.
     *
     * @return void
     */
    public static function schedule_config_change_check(): void {
        if (self::$configchangescheduled) {
            return;
        }
        self::$configchangescheduled = true;
        \core_shutdown_manager::register_function([self::class, 'check_config_change']);
    }

    /**
     * Send annoto_config_change when a reported flag differs from the last one reported.
     *
     * With no baseline yet (no heartbeat sent since this version was installed), the
     * change is sent: a duplicate is cheaper than a missed change.
     *
     * @return bool whether an event was sent
     */
    public static function check_config_change(): bool {
        try {
            self::$configchangescheduled = false;
            $current = self::flags_snapshot();
            $previous = (string)get_config('local_annoto', 'telemetry_lastflags');
            if ($previous === $current) {
                return false;
            }
            if (!self::send_event('annoto_config_change')) {
                return false;
            }
            self::store_flags_snapshot($current);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * The reported flags (and site_env), serialised for comparison.
     *
     * @return string
     */
    protected static function flags_snapshot(): string {
        return json_encode(self::config_flags() + ['site_env' => self::get_site_env()]);
    }

    /**
     * Store the flags baseline for check_config_change().
     *
     * @param string $snapshot from flags_snapshot()
     * @return void
     */
    protected static function store_flags_snapshot(string $snapshot): void {
        set_config('telemetry_lastflags', $snapshot, 'local_annoto');
    }

    /**
     * Report the plugin's removal, from db/uninstall.php. Best-effort.
     *
     * @return bool
     */
    public static function send_uninstall(): bool {
        return self::send_event('annoto_uninstall');
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
