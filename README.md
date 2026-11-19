# moodle-local_annoto
Annoto is an in-video collaboration solution that turns static, 1-way video Stream/VOD into an active group learning experience, where participants contribute, share and learn together. Users can lean forward and become participants and not just passive and lonely viewers, causing all users to be actively involved and return to the video content, sharing more ideas and creating more meaningful content. Resulting in higher engagement and retention, supported by comprehensive analytics and insights, that facilitate dramatic improvements to content, communications, and measurable outcomes. Annoto local plugin will identify videos at the content of the activities and will add annoto to matched video based on plugin settings.

Annoto Moodle Plugin can be found in the Moodle Plugins directory:
https://moodle.org/plugins/local_annoto

Setup instructions:
https://docs.annoto.net/setup/moodle/widget-integration

Test Annoto:
https://www.youtube.com/watch?v=IKg1PNLPLwQ

## Requirements

- **Moodle:** 3.11 up to 5.2 (declared via `$plugin->supported = [311, 502]`). The minimum required version is Moodle 3.11 (`$plugin->requires = 2021051700`).
- **PHP:** any PHP version supported by your Moodle release (Moodle 5.2 requires PHP 8.3+).
- A valid Annoto subscription (see note below).

On Moodle 4.4 and later the plugin uses the core Hooks API
(`db/hooks.php` → `local_annoto\hook_callbacks`); on Moodle 3.11–4.3 it falls
back to the legacy `lib.php` callbacks. Both paths are kept so a single release
supports the full 3.11–5.2 range.

## Embed Annoto Media (editor button)

Since 5.7.0 the plugin also provides the LTI 1.3 plumbing for the "Embed Annoto Media" button in
TinyMCE (`tiny_annoto`) and Atto (`atto_annoto`), rendered by `filter_annoto`. Design:
[docs/design/editor-embed.md](docs/design/editor-embed.md).

Admin setup:

1. Install `local_annoto` 5.7.0+, `filter_annoto`, and `tiny_annoto` (Moodle 4.1+) and/or `atto_annoto`.
2. Register the Annoto **media embed** LTI 1.3 tool (separate from the dashboard and assignment tools):
   *Site administration > Plugins > Activity modules > External tool > Manage tools*, paste
   `{APP_ENDPOINT}/lti/register_embed` (the EU or US Annoto endpoint of your Annoto host) in *Tool URL*,
   click *Add LTI Advantage*, then *Activate* the pending tool. In the tool settings keep
   *Supports Deep Linking (Content-Item Message)* on and set *Share launcher's name / email* to *Always*.
3. Ask Annoto to set the platform **authentication request URL** of this integration to
   `https://<your moodle>/local/annoto/lti/auth.php` (it forwards every other launch to `/mod/lti/auth.php`).
4. *Site administration > Plugins > Local plugins > Annoto > Embed Annoto Media*: pick the tool in
   *Annoto embed LTI tool* (or leave *Automatic*), optionally change the default size.
5. *Site administration > Plugins > Filters > Manage filters*: turn *Annoto media embed* **On**.
6. TinyMCE: the button appears automatically (toolbar "content" group and *Insert* menu).
   Atto: add `annoto` to *Site administration > Plugins > Text editors > Atto HTML editor > Atto toolbar settings*
   (for example `insert = equation, charmap, table, clear, annoto`).
7. Permissions: teachers, managers and course creators can embed by default
   (`local/annoto:embedmedia` + `tiny/annoto:use` / `atto/annoto:use`). To let students embed (e.g. in forum posts
   or online text submissions), allow those capabilities for the Student role, and ask Annoto to enable
   *mediaActivitySubmission* for the course group. Viewing needs `local/annoto:viewembed` (all users by default).

<p>
    <span style="font-size: 14pt;"><strong>Please Note: Subscription is required to use this service.</strong></span>
</p>

<p>
    <span style="font-size: 14pt;"><strong>Please feel free to contact us for any question and requests :</strong></span>
</p>
<ul>
    <li style="font-family: Calibri; font-size: 11pt;">
        <span style="font-size: 14pt;">
            <a href="mailto:Support@annoto.net">Support@Annoto.net</a>
        </span>
    </li>
    <li style="font-family: Calibri; font-size: 11pt;">
        <span style="font-size: 14pt;">You can book a short solution walk-through: <a href="https://meetings.hubspot.com/hen/sandbox">schedule&nbsp;a meeting</a>
        </span>
    </li>
</ul>
<p>


<p style="margin: 0in; font-family: Calibri; font-size: 11.0pt;">&nbsp;</p>
<h1 class="hero-title h2-mobile mt-0 is-revealing" style="visibility: visible;">
    <span style="font-size: 14pt;"><strong>
            <span style="text-decoration: underline;">Online Resources:</span>
        </strong></span>
</h1>
<ul>
    <li>
        <a href="https://docs.annoto.net/guides/">
            <span style="font-size: 14pt;"><strong>Online documents</strong></span>
        </a>
    </li>
    <li>
        <span style="font-size: 14pt;"><strong>
                <a href="https://www.youtube.com/channel/UCLpTqywRf1_DobM7rRulcTg">YouTube Channel</a>
            </strong></span>
    </li>
</ul>
<p>&nbsp;</p>
    <span style="font-size: 14pt;">------------------------------------------------------------------------------------------------------------------------------</span>
</p>
<h1 class="hero-title h2-mobile mt-0 is-revealing" style="visibility: visible;">Overview</h1>
<p class="hero-title h2-mobile mt-0 is-revealing" style="visibility: visible;">
    <span style="font-size: 12pt;">Annoto is an in-video collaboration solution that enables students to contribute, share, and learn together, promoting an interactive, group experience.</span>
    <br />
    <span style="font-size: 12pt;">Annoto allows your students to lean forward allowing them to be actively involved and return to the video content, sharing ideas, and learn with their peers.</span>
</p>
<p class="hero-title h2-mobile mt-0 is-revealing" style="visibility: visible;">
    <span style="font-size: 12pt;">Enabling an active video viewing experience will result in higher engagement, retention and continuous enrichment of video content. Annoto&rsquo;s comprehensive analytics and insights, allows you to curate and monitor learners activities and gain insights on the video content.</span>
</p>
<p>
    <br />
    <span style="font-family: Arial; font-size: 12pt;">Annoto Declared Winner of the Higher or Further Education Digital Services Category at 21st Annual Bett Awards</span>
    <br />
    <span style="color: #26282a; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 12pt;">Bett is the world's leading four day showcase of education technology solutions</span>
    <br />
    <a style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 15px;" href="https://finance.yahoo.com/news/annoto-declared-winner-higher-further-140000589.html" target="[object Object]">Annoto declared winner higher further</a>
    <br />
    <br />
</p>
    Short Expliner video (2min) : www.youtube.com/embed/w9qIgsD2Ox4
<p>&nbsp;</p>
<div class="body-wrap boxed-container">
    <div class="hero-inner">
        <div class="hero-copy">
            <h1 class="hero-title h2-mobile mt-0 is-revealing" style="visibility: visible;">VALUE PROPOSITION</h1>
            <p>
                <span style="font-size: 12pt;">Annoto is a revolutionary platform that transforms how organizations and consumers communicate and learn around digital video content &mdash; positioned at the most critical place of the learning system, between the user and the video content, giving the ability to have the most impact on the user learning process and the video content delivered.</span>
            </p>
            <p><strong>Learner</strong>
                <br />&bull; Transform individual content consumption to collaborative experience.
                <br />&bull; Bring the benefits of in-person experience to on-demand online learning.
                <br />&bull; Gamification
                <br />&bull; Knowledge sharing
                <br />&bull; Improve learning experience
            </p>
            <strong>Instructor &amp; Faculty</strong>
        </div>
        <div class="hero-copy">&bull; Provide forecast which students are likely to drop out</div>
        <div class="hero-copy">&bull; Curate and monitor student activities
            <br />&bull; Gain insights on media consumption and user activity
            <br />&bull; Implement blended learning methods with learner participation
            <br />&bull; Gain continuous enrichment of video content
            <br />&bull; Empower Knowledge Sharing
        </div>
        <div class="hero-copy">&bull; Conduct Research</div>
        <div class="hero-copy">
            <span style="font-family: sans-serif; font-size: 1rem;">&bull;Supports variety of media sources.</span>
        </div>
        <div class="hero-illustration">
            <div class="hero-illustration-browser is-revealing" style="visibility: visible;">&nbsp;</div>
            <div class="hero-ball hero-ball-5 is-revealing" style="visibility: visible;">&nbsp;</div>
            <div class="hero-ball hero-ball-5 is-revealing" style="visibility: visible;">&nbsp;</div>
        </div>
    </div>
</div>
<div class="body-wrap boxed-container">
    <div class="hero-inner">
        <div class="hero-copy">
            <div class="container-sm">
                <h1 class="hero-title h2-mobile mt-0 is-revealing" style="visibility: visible;">ANALYTICS AND INSIGHTS</h1>
                <p class="hero-paragraph is-revealing" style="visibility: visible;">
                    <span style="font-size: 12pt;">Our unique value proposition and application allows us to collect new user behavioral data, about users and the content they are viewing in addition to the environment they are in, giving a view on how and when users interact with your video content and with each other.</span>
                </p>
                <span style="font-size: 12pt;">&bull; How user interact and communicate during the video</span>
                <br />
                <span style="font-size: 12pt;">&bull; Which Video moments made the most impact on the users.</span>
            </div>
            <div class="container-sm">
                <span style="font-size: 12pt;">&bull; Trends - showing how engagement changes over time. Explore how users video views and user-generated content (discussions involvement) changes over time side by side.</span>
                <br />
                <span style="font-size: 12pt;">&bull; What are the most engaging or watched videos?</span>
                <br />
                <span style="font-size: 12pt;">&bull; Video analytics such as completion rate watch through and churn rate.</span>
                <br />
                <span style="font-size: 12pt;">&bull; Users engagement analytics (e.g. most engaged users.)</span>
            </div>
        </div>
    </div>
</div>

