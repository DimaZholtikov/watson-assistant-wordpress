<?php
// Email Notificator
namespace WatsonConv;

class Email_Notificator {
    public function __construct() {
        add_action("init", array(__CLASS__, "run"));
    }

    public static function run() {
        // // Is it a cron job running right now?
        // $doing_cron = false;
        // if(defined( 'DOING_CRON' )) {
        //     if(DOING_CRON) {
        //         $doing_cron = true;
        //     }
        // }

        $enabled = get_option('watsonconv_notification_enabled', '') === 'yes';
        if ($enabled) {
            $prev_ts = intval(get_option('watsonconv_notification_summary_prev_ts', 0));
            $dt = time() - $prev_ts;

            $interval = intval(get_option('watsonconv_notification_summary_interval', 0));

            if ($interval > 0 && $dt > $interval) {

                $task_exists = \WatsonConv\Background_Task_Runner::task_already_exists("send_email_notification", $prev_ts);
                if(!$task_exists) {
                    \WatsonConv\Background_Task_Runner::new_task("send_email_notification", $prev_ts);
                }
            }
        }
    }

    public static function reset_summary_prev_ts() {
        update_option('watsonconv_notification_summary_prev_ts', time());
    }

    /**
     * @param bool $force_send
     * @param string $emails
     * @return bool
     */
    public static function send_summary_notification($force_send=false, $emails=NULL) {
        $res = false;

        if(empty($emails)) {
            $emails = get_option('watsonconv_notification_email_to', '');
        }

        $emails_array = explode(",", $emails);
        $errors_array = array();
        $siteUrl = get_option("home", get_site_url());
        $siteName = get_option("blogname", $siteUrl);
        $email_from = get_option("admin_email");
        $date_format = get_option("date_format", "F j, Y");
        $time_format = get_option("time_format", "g:i a");
        $datetime_format = "{$date_format} {$time_format}";
        $count = 0;
        foreach($emails_array as $email) {
            $email = trim($email);

            $prev_ts = intval(get_option('watsonconv_notification_summary_prev_ts', 0));
            $topic = "People are talking to chatbot on {$siteName}";
            $headers = array(
                "Content-Type: text/html; charset=UTF-8",
                "From: Watson Assistant on {$siteUrl} <{$email_from}>"
            );
            $count = self::get_session_count_since_last_time($prev_ts);
            if ($count > 0 || $force_send) {
                // Base64 representation of Watson Assistant plugin logo
                $image_base64 = file_get_contents(WATSON_CONV_PATH.'includes/logo-base64.txt');
                // Date since last message
                $since_string = date_i18n($datetime_format, $prev_ts);
                // Plural affix
                $plural_affix = $count > 1 || $count == 0 ? 's' : '';
                // Message content
                $message_content = "
                    <p>
                        <strong style='font-size: 2em'>
                            <span style='opacity: 0.7'>Chatbot had</span>
                            <span> {$count} </span>
                            <span style='opacity: 0.7'>
                                conversation{$plural_affix} on 
                                <a href='{$siteUrl}'>{$siteName}</a>
                            </span>
                        </strong>
                    </p>
                    <p style='opacity: 0.5'>since {$since_string}</p>
                    <img style='height: 64px; width: 64px; margin-top: 16px' src='{$image_base64}'>
                    <p>
                        <strong style='opacity: 0.7'>
                            This e-mail was generated by Watson Assistant plugin for WordPress.
                        </strong>
                    </p>
                ";

                // Table container for email
                $message_container = "
                    <table style='width: 100%'>
                        <tr>
                            <td style='text-align: left'>
                                <div>
                                    {$message_content}
                                </div>
                            </td>
                        </tr>
                    </table>
                ";
                $res = wp_mail($email, $topic, $message_container, $headers);

                if(!$res) {
                    array_push($errors_array, $GLOBALS['phpmailer']->ErrorInfo);
                }
            }
        }

        if($count > 0) {
            self::reset_summary_prev_ts();
        }

        if(count($errors_array) > 0) {
            return $errors_array;
        }
        else {
            return true;
        }
    }

    /**
     * @param integer $since_ts - unix timestamp
     * @return integer
     */
    private static function get_session_count_since_last_time($since_ts) {
        global $wpdb;
        $tname = \WatsonConv\Storage::get_full_table_name('sessions');
        $count = intval($wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.$tname.' WHERE s_created > FROM_UNIXTIME(%d)', $since_ts)));
        return $count;
    }
}

new Email_Notificator();