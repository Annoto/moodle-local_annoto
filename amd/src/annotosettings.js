/**
 * javscript for component 'local_annoto'.
 *
 * @package
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(["jquery", "core/log", "core/notification", "core/ajax"], function ($, log, notification, Ajax) {
    return {
        init: function () {
            if (!$(document).find('body#page-admin-setting-local_annoto').get(0)) {
                console.log("annotosettings: Not required");            
                return;
            }
            console.log("annotosettings: started...");
            Ajax.call([
                {
                    methodname: "local_annoto_get_defaults",
                    args: {},
                    done: function (response) {
                        console.log("annotosettings: response: ", response);
                        response.result
                            ? ((this.params = JSON.parse(response.params)),
                              this.hasAnnotoTag()
                                  ? log.info("AnnotoMoodle: plugin is disabled for this page using the Atto plugin.")
                                  : (this.bindsettings()))
                            : log.warn("AnnotoMoodle: action not permitted for user");
                    }.bind(this),
                    fail: notification.exception,
                },
            ]);
        },
        hasAnnotoTag: function () {
            return $("annoto").length > 0 && 0 === $("annotodisable").length;
        },
        bindsettings: function() {
            let that=this;
            console.log("bindsetting11111s:", this.params);
            $(document).on("change", "#id_s_local_annoto_deploymentdomain", function(){
                var newsetting = $(this).val();
                switch(newsetting) {
                    case "us.annoto.net":
                        $("#id_s_local_annoto_toolurl").val(`${that.params.toolurl_us}${that.params.toolurlpart}`)
                        $("#id_s_local_annoto_gradetoolurl").val(that.params.toolurl_us)
                    break;
                    case "eu.annoto.net":
                        $("#id_s_local_annoto_toolurl").val(`${that.params.toolurl_eu}${that.params.toolurlpart}`)
                        $("#id_s_local_annoto_gradetoolurl").val(that.params.toolurl_eu)
                    break;
                    case "custom":
                        let customdomain = $("#id_s_local_annoto_customdomain").val();
                        $("#id_s_local_annoto_toolurl").val(`${customdomain}${that.params.toolurlpart}`)
                        $("#id_s_local_annoto_gradetoolurl").val(customdomain)
                    break;
                    default:
                    // code block
                }
            });
            $(document).on("keyup", "#id_s_local_annoto_customdomain", function(){
                var newsetting = $(this).val();
                $("#id_s_local_annoto_toolurl").val(`${newsetting}${that.params.toolurlpart}`)
                $("#id_s_local_annoto_gradetoolurl").val(newsetting)
            });
        },
    };
});
