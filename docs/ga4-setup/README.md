# GA4 setup for Annoto plugin telemetry

The `local_annoto` plugin phones home to a GA4 property via the Measurement
Protocol (see `classes/telemetry.php`). GA4 **collects** our custom event params
and user properties, but will **not show them in reports** until they are
registered as custom dimensions. This folder helps with that one-time setup.

## 1. Create the property + stream
- One GA4 **property** (e.g. "Annoto Moodle Plugin – Telemetry").
- One **Web** data stream inside it (not App). The "Website URL" is just a label
  — it does **not** restrict which sites can send data, so any Moodle install on
  any domain reports into this single stream.
- Copy the **Measurement ID** (`G-XXXXXXXXXX`) and create a **Measurement
  Protocol API secret** (stream → *Measurement Protocol API secrets* → *Create*).
- Paste both into `classes/telemetry.php` (`GA_MEASUREMENT_ID`, `GA_API_SECRET`).

## 2. Register custom dimensions + set retention

### Option A — run the script (recommended, does everything)
```bash
pip install google-analytics-admin
export GOOGLE_APPLICATION_CREDENTIALS=/path/to/service-account.json  # Editor on the property
python create_custom_dimensions.py --property <NUMERIC_PROPERTY_ID>
```
Idempotent — safe to re-run. The numeric property ID is in
**Admin → Property details** (not the `G-…` id).

### Option B — click it in the UI
**Admin → Custom definitions → Create custom dimension**, once per row:

| Dimension name | Scope | Event parameter / User property |
|---|---|---|
| Site Url | Event | `site_url` |
| Site Name | Event | `site_name` |
| Moodle Release | Event | `moodle_release` |
| Moodle Version | Event | `moodle_version` |
| Plugin Release | Event | `plugin_release` |
| Plugin Version | Event | `plugin_version` |
| Region | Event | `region` |
| Lang | Event | `lang` |
| User Count | Event | `user_count` |
| Course Count | Event | `course_count` |
| Client Configured | Event | `client_configured` |
| Sso Configured | Event | `sso_configured` |
| Dashboard Autoadd | Event | `dashboard_autoadd` |
| Locale Enabled | Event | `locale_enabled` |
| Media Override | Event | `media_override` |
| Debug Logging | Event | `debug_logging` |
| Script Custom | Event | `script_custom` |
| Moodlejs Custom | Event | `moodlejs_custom` |
| Activity Completion | Event | `activity_completion` |
| Activity Completion Active | Event | `activity_completion_active` |
| Region (user) | User | `region` |
| Plugin release (user) | User | `plugin_release` |
| Moodle release (user) | User | `moodle_release` |
| Php Version | Event | `php_version` |
| Db Type | Event | `db_type` |
| Theme | Event | `theme` |
| Video Plugins | Event | `video_plugins` |
| Error Code | Event | `error_code` |
| Error Area | Event | `error_area` |
| Site ID (user) | User | `site_id` |
| Site environment (user) | User | `site_env` |
| Country code (user) | User | `country_code` |

Then **Admin → Custom definitions → Custom metrics → Create custom metric**
(scope Event, unit Standard) for `n_activities_completion_cfg`,
`n_completions_7d` and `error_count`.

Then **Admin → Data retention → Event data retention → 14 months → Save**
(default is 2 months).

> If you already created `admin_email` / `admin_name` custom dimensions in an
> earlier pass: the plugin no longer sends those (it sends **no** personal data),
> so they will simply stay empty. You can safely delete them.

## Events

| Event | When | Parameters |
|---|---|---|
| `annoto_install` | first send | as the heartbeat |
| `annoto_heartbeat` | weekly | the 20 dimensions above, the `n_*` metrics, `engagement_time_msec`, `session_id` |
| `annoto_environment` | after each heartbeat | `site_id`, `site_url`, `php_version`, `db_type`, `theme`, `video_plugins` |
| `annoto_config_change` | an admin save changes a reported flag | `site_id`, `site_url`, `site_env` and the on/off flags |
| `annoto_uninstall` | plugin removed (best effort) | `site_id`, `site_url`, `plugin_release` |
| `annoto_error` | daily, for failures queued since the last send | `site_id`, `error_area`, `error_code`, `error_count` |

Every event also carries the user properties `region` (deprecated: it holds the
deployment domain, not a geography), `plugin_release`, `moodle_release`,
`site_id`, `site_env` and `country_code`.

`n_activities_completion_cfg` counts activities with Annoto automatic completion
configured (the same value as `activity_completion_active`, as a metric).
`n_completions_7d` counts Moodle completions in the last 7 days on activities with
Annoto automatic completion. Widget usage is not sent: Annoto already has it.

## 3. Verify data is flowing
After setting real credentials and installing/upgrading the plugin on a test
Moodle, run its telemetry task and watch GA4 **Realtime** or **DebugView**:
```bash
php admin/cli/scheduled_task.php --execute='\local_annoto\task\telemetry'
```
To see one event without waiting for the weekly schedule, use the plugin's CLI:
```bash
php local/annoto/cli/telemetry.php --event=annoto_heartbeat --validate   # GA4 validation only
php admin/cli/cfg.php --component=local_annoto --name=telemetry_debugmode --set=1
php local/annoto/cli/telemetry.php --event=annoto_heartbeat --send       # shows in DebugView
```
Custom dimensions only start populating reports for data received **after** they
are created (they are not retroactive), so create them before you care about the
history.
