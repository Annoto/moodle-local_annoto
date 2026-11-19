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
 * Strings for component 'local_annoto', language 'he'.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.LangFilesOrdering.UnexpectedComment
// phpcs:disable moodle.Files.LangFilesOrdering.IncorrectOrder

$string['pluginname'] = 'Annoto';

// Capabilities.
$string['annoto:moderatediscussion'] = 'ניהול דיונים ב-Annoto';
$string['annoto:managementdashboard'] = 'גישה ללוח הבקרה של Annoto';

// Annoto Setup.
$string['setupheading'] = 'הגדרת Annoto';
$string['clientid'] = 'מפתח API';
$string['clientiddesc'] = 'מזהה לקוח (ClientID) מסופק על ידי Annoto (יש לשמור בסודיות)';
$string['ssosecret'] = 'מפתח SSO סודי';
$string['ssosecretdesc'] = 'מפתח SSO סודי מסופק על ידי Annoto (יש לשמור בסודיות)';
$string['scripturl'] = 'כתובת סקריפט Annoto';
$string['scripturldesc'] = 'הזן כאן את כתובת הסקריפט של Annoto';
$string['moodlejsurl'] = 'כתובת סקריפט Annoto Moodle (CDN)';
$string['moodlejsurldesc'] = 'הזן כאן את כתובת הסקריפט של אינטגרציית Annoto Moodle‏ (moodle-local-js)';
$string['deploymentdomain'] = 'דומיין פריסה';
$string['deploymentdomaindesc'] = 'בחר את האזור עבור הווידג׳טים. שים לב שנתוני הווידג׳ט קשורים לאזור מסוים.';
$string['customdomain'] = 'דומיין פריסה מותאם אישית';
$string['customdomaindesc'] = 'ציין דומיין פריסה מותאם אישית. שים לב שנתוני הווידג׳ט קשורים לאזור מסוים.';
$string['eurregion'] = 'אזור אירופה';
$string['usregion'] = 'אזור ארה"ב';
$string['custom'] = 'מותאם אישית';

// Annoto dashboard (LTI).
$string['addingdashboard'] = 'הוסף לכל הקורסים';
$string['addingdashboard_desc'] = <<<EOD
אם מאופשר, לוח הבקרה של Annoto יתווסף אוטומטית לכל הקורסים.
אם מבוטל, ניתן להוסיף את לוח הבקרה של Annoto ידנית דרך "הוספת פעילות או משאב" בקורס הרצוי.\n
הערה: יש להגדיר את כלי ה-LTI של לוח הבקרה של Annoto בניהול האתר.
EOD;
$string['externaltoolsettings'] = 'לוח הבקרה של Annoto (LTI)';
$string['lti_activity_name'] = 'לוח הבקרה של Annoto';
$string['managementdashboard'] = 'תפקידי מנהל לוח הבקרה';
$string['managementdashboard_desc'] = 'ציין מי רשאי לגשת ללוח הבקרה של Annoto';

// Annoto settings.
$string['appsetingsheading'] = 'הגדרות Annoto';
$string['locale'] = 'שפה';
$string['locale_desc'] = 'אם מאופשר, תוגדר שפה לכל עמוד וקורס בהתאם להעדפות הקורס והמשתמש';
$string['moderatorroles'] = 'תפקידי מנהל דיון';
$string['moderatorrolesdesc'] = 'ציין מי רשאי לנהל דיונים (רק תפקידים הכוללים לפחות את ההרשאות הבאות יהיו זמינים: local/annoto:moderatediscussion).';
$string['debuglogging'] = 'אפשר רישום דיבאג';
$string['debuglogging_desc'] = 'אם מאופשר, הודעות יומן ברמת דיבאג מתוסף Annoto יוצגו. זה שימושי לפתרון תקלות ופיתוח, אך יש להשבית זאת בסביבת ייצור.';

// Media player settings.
$string['media_player_setting'] = 'הגדרות נגן מדיה';
$string['mediasettingsoverride'] = 'עקוף הגדרות מדיה של Moodle';
$string['mediasettingsoverridedesc'] = 'אפשר עקיפת הגדרות נגן המדיה של Moodle';
$string['defaultwidth'] = 'רוחב מדיה';
$string['defaultwidthdesc'] = 'רוחב נגן המדיה אם לא צוין רוחב והנגן לא יכול לקבוע את הרוחב בפועל של קובץ המדיה';
$string['defaultheight'] = 'גובה מדיה';
$string['defaultheightdesc'] = 'גובה נגן המדיה אם לא צוין גובה והנגן לא יכול לקבוע את הגובה בפועל של קובץ המדיה';

// Activities completion.
$string['activitycompletion_settings'] = 'השלמת פעילות';
$string['activitycompletion_enable'] = 'אפשר השלמת פעילות Annoto';
$string['activitycompletion_enabledesc'] = 'אם מאופשר, השלמת פעילות Annoto תהיה זמינה בהגדרות עמוד, תווית, Annoto LTI, h5p, hvp ו-Kaltura';

$string['annotocompletion'] = 'תנאי השלמת פעילות Annoto';
$string['completiontask'] = 'משימת השלמה של Annoto';
$string['numericrule'] = 'שדה זה חייב להיות מספרי';

$string['completionenabled'] = 'מעקב השלמה';
$string['completionenableddesc'] = 'בחר אם השלמת Annoto תופעל כברירת מחדל לפעילויות חדשות';

$string['annotocompletionview'] = 'דרוש השלמת צפייה בווידאו';
$string['annotocompletionviewhelp'] = 'כמה מאורך הווידאו צריך להיצפות על ידי הסטודנט כדי שהפעילות תיחשב כהושלמה (כיסוי)';
$string['annotocompletionviewprefix'] = 'אחוז מינימלי מהווידאו (כיסוי) שעל הלומד לצפות בו:';
$string['annotocompletionviewsuffix'] = '%';

$string['annotocompletioncomments'] = 'דרוש תגובות';
$string['annotocompletioncommentshelp'] = 'מספר מינימלי של תגובות (כולל תגובות משנה) שעל הלומד לפרסם כדי שהפעילות תיחשב כהושלמה';
$string['annotocompletioncommentsprefix'] = 'מספר מינימלי של תגובות שעל הלומד לפרסם: ';

$string['annotocompletionreplies'] = 'דרוש תגובות משנה';
$string['annotocompletionreplieshelp'] = 'מספר מינימלי של תגובות משנה לתגובות שעל הלומד לפרסם כדי שהפעילות תיחשב כהושלמה';
$string['annotocompletionrepliesprefix'] = 'מספר מינימלי של תגובות משנה שעל הלומד לפרסם: ';

$string['annotocompletionexpected'] = 'הגדר תזכורת בציר הזמן';
$string['annotocompletionexpectedhelp'] = 'הגדר תזכורת לסטודנט לעבוד על פעילות זו';

// Privacy API.
$string['privacy:metadata:annoto'] = 'על מנת להשתלב עם שירות חיצוני, יש צורך להחליף נתוני משתמש עם אותו שירות.';
$string['privacy:metadata:annoto:userid'] = 'מזהה המשתמש נשלח ממודל כדי לאפשר לך גישה לנתונים שלך במערכת החיצונית.';
$string['privacy:metadata:annoto:fullname'] = 'השם המלא שלך נשלח למערכת החיצונית כדי לאפשר חוויית משתמש טובה יותר.';
$string['privacy:metadata:annoto:email'] = 'כתובת הדוא"ל שלך נשלחת למערכת החיצונית כדי לאפשר חוויית משתמש טובה יותר.';

// Usage analytics (telemetry).
$string['telemetryenabled'] = 'שיתוף נתוני שימוש עם Annoto';
$string['telemetryenabled_desc'] = 'שליחת נתוני התקנה ושימוש ל-Annoto. ביטול הסימון מבטל את השיתוף.';
$string['telemetrytask'] = 'משימת נתוני שימוש של Annoto';
$string['siteenv'] = 'סביבת האתר';
$string['siteenv_desc'] = 'איזה עותק של ה-Moodle שלכם זה, נשלח עם נתוני השימוש כדי שעותקי בדיקה ו-staging לא ייספרו כהתקנות נפרדות. מולא מראש לפי ניחוש מכתובת האתר.';
$string['siteenv_production'] = 'ייצור';
$string['siteenv_staging'] = 'Staging';
$string['siteenv_test'] = 'בדיקה';
$string['siteenv_dev'] = 'פיתוח';

// Privacy API - usage telemetry.
$string['privacy:metadata:annoto_telemetry'] = 'כדי לסייע ל-Annoto להבין ולתמוך בהתקנות התוסף, מידע לא-אישי על האתר (כתובת, שם, מזהה אתר אנונימי, סביבה, מדינה, גרסאות, סביבת השרת, תוספי מדיה מותקנים, מספרים מצטברים של משתמשים, קורסים והשלמת פעילויות Annoto, תצורת התוסף וקודי שגיאה קצרים) נשלח ל-Annoto באמצעות Google Analytics. שום מידע אישי על מנהלי המערכת, לומדים או תלמידים אינו נשלח.';
$string['privacy:metadata:annoto_telemetry:siteurl'] = 'כתובת האתר ושמו נשלחים כדי לזהות את ההתקנה.';

// Embed Annoto Media (editor button, LTI 1.3 Deep Linking).
$string['annoto:embedmedia'] = 'הטמעת מדיה של Annoto מעורך הטקסט';
$string['annoto:viewembed'] = 'צפייה במדיה של Annoto המוטמעת בטקסט';
$string['embedheading'] = 'הטמעת מדיה של Annoto (כפתור בעורך)';
$string['embedheading_desc'] = 'מאפשר למרצים (ובאופן אופציונלי גם לסטודנטים) ללחוץ על כפתור Annoto בעורך TinyMCE או Atto כדי להעלות, להקליט או לבחור סרטון ולהטמיע אותו בכל טקסט. נדרשים: כלי ה-LTI 1.3 של Annoto להטמעת מדיה (רישום באמצעות כתובת הרישום של Annoto להטמעה), המסנן filter_annoto, ותוספי העורך tiny_annoto ו/או atto_annoto. ברישום ה-LTI ב-Annoto, כתובת בקשת האימות של הפלטפורמה חייבת להיות <code>{$a->authurl}</code> (היא מעבירה כל הפעלה אחרת ל-/mod/lti/auth.php של Moodle). תשובות Deep Linking חוזרות אל <code>{$a->returnurl}</code>.';
$string['embedltitype'] = 'כלי LTI של Annoto להטמעה';
$string['embedltitype_desc'] = 'הכלי החיצוני מסוג LTI 1.3 שנרשם להטמעת מדיה של Annoto (לא לוח הבקרה ולא כלי המטלות). "אוטומטי" בוחר את כלי ה-LTI 1.3 המוגדר הראשון בדומיין הפריסה של Annoto ששמו או כתובת הכלי שלו מכילים "embed".';
$string['embedltitype_auto'] = 'אוטומטי (איתור לפי דומיין הפריסה)';
$string['embedwidth'] = 'רוחב ברירת מחדל להטמעה';
$string['embedwidth_desc'] = 'רוחב בפיקסלים של מדיה מוטמעת כאשר Annoto אינו שולח גודל.';
$string['embedheight'] = 'גובה ברירת מחדל להטמעה';
$string['embedheight_desc'] = 'גובה בפיקסלים של מדיה מוטמעת כאשר Annoto אינו שולח גודל.';
$string['embed_dialogtitle'] = 'הטמעת מדיה של Annoto';
$string['embed_defaulttitle'] = 'מדיה של Annoto';
$string['embed_loading'] = 'פותח את Annoto…';
$string['embed_inserted'] = 'המדיה נוספה לטקסט.';
$string['embed_cancelled'] = 'לא נבחר דבר.';
$string['embed_notconfigured'] = 'הטמעת מדיה של Annoto אינה מוגדרת באתר זה: לא הוגדר כלי LTI 1.3 של Annoto להטמעה. פנו למנהל ה-Moodle כדי לרשום אותו ולבחור אותו בהגדרות תוסף Annoto.';
$string['embed_invalidurl'] = 'הקישור למדיה של Annoto אינו מפנה לכלי ההטמעה של Annoto שהוגדר.';
$string['embed_invalidresponse'] = 'Annoto החזיר תשובה לא תקינה. נסו שוב.';
$string['privacy:metadata:annoto_lti_embed'] = 'כאשר בוחרים מדיה של Annoto בעורך או צופים בה במקום שבו הוטמעה, הפעלת LTI 1.3 שולחת מידע על המשתמש לכלי ההטמעה של Annoto.';
$string['privacy:metadata:annoto_lti_embed:userid'] = 'מזהה המשתמש ב-Moodle.';
$string['privacy:metadata:annoto_lti_embed:fullname'] = 'שמו המלא של המשתמש, אם כלי ה-LTI מוגדר לשלוח אותו.';
$string['privacy:metadata:annoto_lti_embed:email'] = 'כתובת הדוא"ל של המשתמש, אם כלי ה-LTI מוגדר לשלוח אותה.';
$string['privacy:metadata:annoto_lti_embed:roles'] = 'תפקידי ה-LTI של המשתמש בקורס.';
$string['privacy:metadata:annoto_lti_embed:courseid'] = 'מזהה הקורס, שמו המקוצר ושמו המלא.';
