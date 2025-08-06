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
$string['clientiddesc'] = 'ClientID מסופק על ידי Annoto (יש לשמור בסודיות)';
$string['ssosecret'] = 'SSO סודי';
$string['ssosecretdesc'] = 'SSO סודי מסופק על ידי Annoto (יש לשמור בסודיות)';
$string['scripturl'] = 'כתובת סקריפט Annoto';
$string['scripturldesc'] = 'הזן כאן את כתובת הסקריפט של Annoto';
$string['deploymentdomain'] = 'דומיין פריסה';
$string['deploymentdomaindesc'] = 'בחר את האזור עבור הווידג׳טים. שים לב שהנתונים קשורים לאזור מסוים.';
$string['customdomain'] = 'דומיין פריסה מותאם אישית';
$string['customdomaindesc'] = 'ציין דומיין פריסה מותאם אישית. שים לב שהנתונים קשורים לאזור מסוים.';
$string['eurregion'] = 'אזור אירופה';
$string['usregion'] = 'אזור ארה"ב';
$string['custom'] = 'מותאם אישית';

// Annoto dashboard (LTI).
$string['addingdashboard'] = 'הוסף לכל הקורסים';
$string['addingdashboard_desc'] = 'אם מאופשר, לוח הבקרה של Annoto יתווסף אוטומטית לכל הקורסים. אם לא, ניתן להוסיף אותו ידנית דרך "הוספת פעילות או משאב" בקורס הרצוי.\nהערה: יש להגדיר את כלי ה-LTI של לוח הבקרה בממשק הניהול.';
$string['externaltoolsettings'] = 'לוח הבקרה של Annoto (LTI)';
$string['lti_activity_name'] = 'לוח הבקרה של Annoto';
$string['managementdashboard'] = 'תפקידי ניהול לוח הבקרה';
$string['managementdashboard_desc'] = 'ציין מי רשאי לגשת ללוח הבקרה של Annoto';

// Annoto settings.
$string['appsetingsheading'] = 'הגדרות Annoto';
$string['locale'] = 'שפה';
$string['locale_desc'] = 'אם מאופשר, תוגדר שפה לכל עמוד וקורס לפי העדפות הקורס והמשתמש';
$string['moderatorroles'] = 'תפקידי מנהל דיון';
$string['moderatorrolesdesc'] = 'ציין מי רשאי לנהל דיונים (רק תפקידים הכוללים את ההרשאה local/annoto:moderatediscussion יהיו זמינים).';
$string['debuglogging'] = 'אפשר רישום דיבאג';
$string['debuglogging_desc'] = 'אם מאופשר, הודעות דיבאג מהתוסף Annoto יירשמו. שימושי לפתרון תקלות ופיתוח, אך יש להשבית בסביבת ייצור.';

// Media player settings.
$string['media_player_setting'] = 'הגדרות נגן מדיה';
$string['mediasettingsoverride'] = 'עקוף הגדרות נגן מדיה של Moodle';
$string['mediasettingsoverridedesc'] = 'אפשר לעקוף את הגדרות נגן המדיה של Moodle';
$string['defaultwidth'] = 'רוחב מדיה';
$string['defaultwidthdesc'] = 'רוחב נגן המדיה אם לא צוין רוחב והנגן לא יכול לקבוע את הרוחב בפועל';
$string['defaultheight'] = 'גובה מדיה';
$string['defaultheightdesc'] = 'גובה נגן המדיה אם לא צוין גובה והנגן לא יכול לקבוע את הגובה בפועל';

// Activities completion.
$string['activitycompletion_settings'] = 'השלמת פעילות (בטא)';
$string['activitycompletion_enable'] = 'אפשר השלמת פעילות Annoto';
$string['activitycompletion_enabledesc'] = 'אם מאופשר, השלמת פעילות Annoto תהיה זמינה בהגדרות עמוד, תווית, h5p, hvp ו-Kaltura';

$string['annotocompletion'] = 'תנאי השלמת Annoto';
$string['completiontask'] = 'משימת השלמה של Annoto';
$string['numericrule'] = 'שדה זה חייב להיות מספרי';

$string['completionenabled'] = 'מעקב השלמה';
$string['completionenableddesc'] = 'בחר אם השלמת Annoto תופעל כברירת מחדל לפעילויות חדשות';

$string['annotocompletionview'] = 'דרוש צפייה בווידאו';
$string['annotocompletionviewhelp'] = 'כמה מהווידאו צריך להיצפות כדי שהפעילות תיחשב כהושלמה (אחוז)';
$string['annotocompletionviewprefix'] = 'אחוז מינימלי מהווידאו שעל הלומד לצפות בו:';
$string['annotocompletionviewsuffix'] = '%';

$string['annotocompletioncomments'] = 'דרוש תגובות';
$string['annotocompletioncommentshelp'] = 'מספר מינימלי של תגובות (כולל תגובות משנה) שעל הלומד לפרסם להשלמת הפעילות';
$string['annotocompletioncommentsprefix'] = 'מספר מינימלי של תגובות שעל הלומד לפרסם: ';

$string['annotocompletionreplies'] = 'דרוש תגובות משנה';
$string['annotocompletionreplieshelp'] = 'מספר מינימלי של תגובות משנה שעל הלומד לפרסם להשלמת הפעילות';
$string['annotocompletionrepliesprefix'] = 'מספר מינימלי של תגובות משנה שעל הלומד לפרסם: ';

$string['annotocompletionexpected'] = 'הגדר תזכורת בציר הזמן';
$string['annotocompletionexpectedhelp'] = 'הגדר תזכורת ללומד לעבוד על פעילות זו';

// Privacy API.
$string['privacy:metadata:annoto'] = 'כדי להשתלב עם שירות חיצוני, יש להחליף נתוני משתמש עם אותו שירות.';
$string['privacy:metadata:annoto:userid'] = 'מזהה המשתמש נשלח ממודל כדי לאפשר גישה לנתונים שלך במערכת החיצונית.';
$string['privacy:metadata:annoto:fullname'] = 'השם המלא שלך נשלח למערכת החיצונית לשיפור חוויית המשתמש.';
$string['privacy:metadata:annoto:email'] = 'כתובת הדוא"ל שלך נשלחת למערכת החיצונית לשיפור חוויית המשתמש.';
