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

Then **Admin → Data retention → Event data retention → 14 months → Save**
(default is 2 months).

> If you already created `admin_email` / `admin_name` custom dimensions in an
> earlier pass: the plugin no longer sends those (it sends **no** personal data),
> so they will simply stay empty. You can safely delete them.

## 3. Verify data is flowing
After setting real credentials and installing/upgrading the plugin on a test
Moodle, run its telemetry task and watch GA4 **Realtime** or **DebugView**:
```bash
php admin/cli/scheduled_task.php --execute='\local_annoto\task\telemetry'
```
Custom dimensions only start populating reports for data received **after** they
are created (they are not retroactive), so create them before you care about the
history.
