#!/usr/bin/env python3
# This file is part of the local_annoto Moodle plugin tooling.
#
# One-shot helper: create the GA4 custom dimensions used by the plugin's usage
# telemetry, and set the property's event-data retention to 14 months.
#
# It is idempotent — dimensions that already exist are skipped, so it is safe to
# re-run.
#
# ---------------------------------------------------------------------------
# Setup
#   pip install google-analytics-admin
#
#   Auth (either one):
#     A) Service account with the "Editor" role on the GA4 property:
#          export GOOGLE_APPLICATION_CREDENTIALS=/path/to/service-account.json
#     B) Your own Google login:
#          gcloud auth application-default login
#
# Run
#   python create_custom_dimensions.py --property 123456789
#   (--property is the GA4 *numeric property ID*: Admin > Property details.
#    It is NOT the "G-XXXXXXXXXX" measurement id.)
# ---------------------------------------------------------------------------

import argparse

from google.analytics.admin_v1alpha import AnalyticsAdminServiceClient
from google.analytics.admin_v1alpha.types import CustomDimension, CustomMetric, DataRetentionSettings
from google.protobuf.field_mask_pb2 import FieldMask

# Event-scoped params we send on each annoto_install / annoto_heartbeat event.
# NOTE: no admin_email / admin_name — the plugin deliberately sends no personal
# data (Google Analytics' terms prohibit PII, and it keeps telemetry compliant).
EVENT_DIMENSIONS = [
    # Environment.
    "site_url",
    "site_name",
    "moodle_release",
    "moodle_version",
    "plugin_release",
    "plugin_version",
    "region",
    "lang",
    # Scale (numeric — see note at bottom about custom metrics).
    "user_count",
    "course_count",
    # Plugin configuration (on/off indicators).
    "client_configured",
    "sso_configured",
    "dashboard_autoadd",
    "locale_enabled",
    "media_override",
    "debug_logging",
    "script_custom",
    "moodlejs_custom",
    # Activity completion.
    "activity_completion",
    "activity_completion_active",  # numeric — also sent as the n_activities_completion_cfg metric.
    # Environment (annoto_environment event).
    "php_version",
    "db_type",
    "theme",
    "video_plugins",
    # Errors (annoto_error event).
    "error_code",
    "error_area",
]

# Usage counts, registered as custom *metrics* so reports can sum them. A parameter
# cannot be both a dimension and a metric, which is why these have their own names.
EVENT_METRICS = [
    "n_activities_completion_cfg",
    "n_completions_7d",
    "error_count",
]

# User-scoped user_properties we also send. Display names must be unique across
# the whole property, so the user-scoped ones get a " (user)" suffix.
USER_DIMENSIONS = [
    ("region", "Region (user)"),
    ("plugin_release", "Plugin release (user)"),
    ("moodle_release", "Moodle release (user)"),
    # Site identity: user properties because the heartbeat has no room left for them
    # (GA4 allows 25 parameters per event).
    ("site_id", "Site ID (user)"),
    ("site_env", "Site environment (user)"),
    ("country_code", "Country code (user)"),
]


def pretty(param):
    """site_url -> Site Url."""
    return param.replace("_", " ").title()


def existing_keys(client, property_path):
    """Return the set of (parameter_name, scope) already defined on the property."""
    keys = set()
    for dim in client.list_custom_dimensions(parent=property_path):
        keys.add((dim.parameter_name, dim.scope))
    return keys


def create(client, property_path, param, display, scope, have):
    if (param, scope) in have:
        print(f"  skip (exists): {param} [{scope.name}]")
        return
    client.create_custom_dimension(
        parent=property_path,
        custom_dimension=CustomDimension(
            parameter_name=param,
            display_name=display,
            scope=scope,
        ),
    )
    print(f"  created: {param} [{scope.name}]  ->  \"{display}\"")


def create_metric(client, property_path, param, have_metrics):
    if param in have_metrics:
        print(f"  skip (exists): {param} [metric]")
        return
    client.create_custom_metric(
        parent=property_path,
        custom_metric=CustomMetric(
            parameter_name=param,
            display_name=pretty(param),
            measurement_unit=CustomMetric.MeasurementUnit.STANDARD,
            scope=CustomMetric.MetricScope.EVENT,
        ),
    )
    print(f"  created: {param} [metric]")


def main():
    ap = argparse.ArgumentParser(description="Set up GA4 custom dimensions for Annoto telemetry.")
    ap.add_argument("--property", required=True, help="GA4 numeric property ID, e.g. 123456789")
    ap.add_argument("--skip-retention", action="store_true", help="Do not change data retention.")
    args = ap.parse_args()

    prop = f"properties/{args.property}"
    client = AnalyticsAdminServiceClient()
    have = existing_keys(client, prop)

    print("Event-scoped dimensions:")
    for param in EVENT_DIMENSIONS:
        create(client, prop, param, pretty(param), CustomDimension.DimensionScope.EVENT, have)

    print("User-scoped dimensions:")
    for param, display in USER_DIMENSIONS:
        create(client, prop, param, display, CustomDimension.DimensionScope.USER, have)

    print("Event-scoped metrics:")
    have_metrics = {m.parameter_name for m in client.list_custom_metrics(parent=prop)}
    for param in EVENT_METRICS:
        create_metric(client, prop, param, have_metrics)

    if not args.skip_retention:
        print("Data retention:")
        client.update_data_retention_settings(
            data_retention_settings=DataRetentionSettings(
                name=f"{prop}/dataRetentionSettings",
                event_data_retention=DataRetentionSettings.RetentionDuration.FOURTEEN_MONTHS,
            ),
            update_mask=FieldMask(paths=["event_data_retention"]),
        )
        print("  event data retention -> 14 months")

    print("Done.")


if __name__ == "__main__":
    main()

# ---------------------------------------------------------------------------
# Note on user_count / course_count:
#   These are numbers registered as *dimensions* (text labels), kept that way for
#   the existing reports. The n_* usage counts are the summable ones (EVENT_METRICS).
# ---------------------------------------------------------------------------
