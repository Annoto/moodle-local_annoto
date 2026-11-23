# Embed Annoto Media from the Moodle editor (TinyMCE / Atto)

Status: Proposed (Nov 19 2026). ClickUp: 86c92dt05 (Kaye College), 124f7bm8wug (Jerusalem College, students too).

## Goal

Faculty (optionally students) click the Annoto icon in TinyMCE or Atto, the Annoto "Embed Annoto Media"
picker opens (upload / record / URL), and the chosen video is embedded in the text, the same way
Kaltura's `tiny_kalturamedia` / `atto_kalturamedia` + `filter_kaltura` work, and the same Deep Linking
model Annoto already uses for the Brightspace RichTextEditor quicklink.

## Pieces

| Plugin | Repo | Role |
| --- | --- | --- |
| `local_annoto` (this repo) | Annoto/moodle-local_annoto | LTI 1.3 plumbing: `embed/select.php`, `lti/auth.php`, `embed/return.php`, `embed/launch.php`, `classes/embed/helper.php`, settings, capabilities |
| `filter_annoto` | Annoto/moodle-filter_annoto (new) | Turns the placeholder link into a responsive iframe to `embed/launch.php` |
| `tiny_annoto` | Annoto/moodle-tiny_annoto (new) | TinyMCE button + Insert menu item (Moodle 4.1+) |
| `atto_annoto` | Annoto/moodle-atto-plugin (rewrite) | Atto button (Moodle 3.11-4.5, and 5.x with `editor_atto` installed) |

## Flow

1. Editor button opens `/local/annoto/embed/select.php?contextid=…` in a dialog iframe
   (Tiny `windowManager.openUrl`, Atto `M.core.dialogue`; both add `allow="camera; microphone; display-capture"`).
2. `select.php` checks `local/annoto:embedmedia` in the course, finds the embed tool type and posts an
   OIDC third-party login initiation to the tool (`/hosts/:hostId/lti/login/:integrationId`), with
   `lti_message_hint = {"launchid":"localannotoembed_…"}`. The launch data (kind, type, course) is kept in `$SESSION`.
3. Annoto redirects to the platform authorization endpoint. **It must be `/local/annoto/lti/auth.php`**
   (see "Why our own auth endpoint"). For our hints it returns a signed `LtiDeepLinkingRequest`
   (`lti_build_content_item_selection_request`, accept `ltiResourceLink`, targets iframe/window, single item)
   with `deep_link_return_url = /local/annoto/embed/return.php?course=…&sesskey=…`. Any other hint is
   handed to core `/mod/lti/auth.php` in the same request, so normal LTI activities keep working.
4. The Annoto picker (`/hosts/:hostId/lti/embed/select`) runs inside the dialog and POSTs an
   `LtiDeepLinkingResponse` to `return.php`. `return.php` re-posts cross-site (cookies), checks sesskey and
   capability, validates the JWT with `lti_convert_from_jwt` (tool key set, iss = client id), takes the first
   `ltiResourceLink` (`url = {APP_ENDPOINT}/hosts/{hostId}/lti/embed/launch`, `title`, `iframe` size,
   `custom.nn_activity_id`), checks the URL host is the tool's host, and posts a placeholder to the opener editor:
   `<a class="local-annoto-embed" href="/local/annoto/embed/launch.php?url=…&rl=annoto-<random>&custom={…}&title=…&w=…&h=…">Title</a>`.
5. `filter_annoto` renders the link as `<iframe src="/local/annoto/embed/launch.php?…&course=<course of the page>">`.
   Without the filter the link opens the same page in a new tab.
6. `launch.php` (viewer) checks `require_login(course)` + `local/annoto:viewembed`, re-validates the URL host
   and starts an OIDC login with kind `launch`; `lti/auth.php` answers with an `LtiResourceLinkRequest` to the
   Annoto launch URL for the current viewer (roles from `lti_get_ims_role`: Instructor if
   `moodle/course:manageactivities`, else Learner, Administrator for admins), `context_id` = course,
   `resource_link_id` = the stable `rl` generated at insert time, `custom.nn_activity_id` from the item.
   No AGS / gradebook claims are sent (no grade item exists for an editor embed).

## Why our own auth endpoint

Core `/mod/lti/auth.php` only knows two cases: a launch of a real `mod_lti` course module, and a Deep Linking
request whose return URL is hard-coded to `/mod/lti/contentitem_return.php` and which requires
`moodle/course:manageactivities` + `mod/lti:addcoursetool`. Editor embeds need a custom return URL, student
access, and resource link launches without a course module, so the tool must send the OIDC authorization
request to `local/annoto/lti/auth.php`. Panopto does the same (`blocks/panopto/lib/lti/auth.php`).
Dynamic registration stores Moodle's `/mod/lti/auth.php` from `openid-configuration`, so Annoto has to set the
platform authorization endpoint of the integration to `/local/annoto/lti/auth.php` (open question for Annoto backend).

## Security notes

- The launch URL in a placeholder is untrusted input: both `return.php` and `launch.php`/`auth.php` only accept
  https URLs on the tool type's own host, so a crafted link cannot send a signed id_token with a viewer's
  identity anywhere else.
- Custom parameters: max 10, keys `[A-Za-z0-9_]{1,64}`, scalar single-line values.
- Session launch ids are single use and expire after 1 hour; `login_hint` must equal the current user;
  `redirect_uri` must be one of the tool's registered redirection URIs.
- `return.php` posts to `window.parent` with this site's origin only; Tiny/Atto check the message source.

## Limits / follow-ups

- Copying content to another course works (the course comes from where the text is shown); the Annoto
  activity is the same `nn_activity_id`.
- Grade passback is not supported for editor embeds.
- Moodle app (mobile): the iframe needs a web session; not tested.
- Recording inside nested iframes depends on Annoto's picker requesting camera/mic in an iframe that is itself
  in Moodle's dialog iframe (both carry `allow`).
