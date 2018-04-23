<?php
namespace WatsonConv;

add_action('wp_loaded', array('WatsonConv\Frontend', 'register_scripts'));
add_action('wp_enqueue_scripts', array('WatsonConv\Frontend', 'chatbox_popup'));
add_action('wp_footer', array('WatsonConv\Frontend', 'render_div'));
add_shortcode('watson-chat-box', array('WatsonConv\Frontend', 'chatbox_shortcode'));

class Frontend {
    const VERSION = '0.6.4';

    public static function enqueue_styles($force_full_screen = null) {
        wp_enqueue_style('watsonconv-chatbox');

        $font_size = get_option('watsonconv_font_size', 11);
        $color_rgb = sscanf(get_option('watsonconv_color', '#23282d'), "#%02x%02x%02x");
        $messages_height = get_option('watsonconv_size', 200);
        $position = explode('_', get_option('watsonconv_position', 'bottom_right'));
        
        $is_dark = self::luminance($color_rgb) <= 0.5;
        $text_color = $is_dark ? 'white' : 'black';

        $main_color = vsprintf('rgb(%d, %d, %d)', $color_rgb);
        $main_color_light = vsprintf('rgba(%d, %d, %d, 0.7)', $color_rgb);

        foreach ($color_rgb as $index => $channel) {
            $color_rgb[$index] = $channel * 0.9;
        }

        $main_color_dark = vsprintf('rgb(%d, %d, %d)', $color_rgb);

        if (is_null($force_full_screen)) {
            $full_screen_settings = get_option('watsonconv_full_screen');
            $full_screen_query = isset($full_screen_settings['query']) ?
                $full_screen_settings['query'] : '@media screen and (max-width:640px) { %s }';
        } else {
            $full_screen_query = $force_full_screen ? '%s' : '';
        }

        $inline_style = get_option('watsonconv_css_cache');

        if (!$inline_style) {
            $inline_style = '
                #message-container #messages .watson-message,
                    #watson-box #watson-header,
                    #watson-fab
                {
                    background-color: '.$main_color.';
                    color: '.$text_color.';
                }

                #watson-box #message-send
                {
                    background-color: '. $main_color .';
                }

                #watson-box #message-send:hover
                {
                    background-color: '. ($is_dark ? $main_color_light : $main_color_dark) .';
                }
                
                #watson-box #message-send svg
                {
                fill: '. ($is_dark ? $text_color : 'rgba(0, 0, 0, 0.9)') .';
                }

                #message-container #messages .message-option
                {
                    border-color: '. ($is_dark ? $main_color : 'rgba(0, 0, 0, 0.9)') .';
                    color: '. ($is_dark ? $main_color : 'rgba(0, 0, 0, 0.9)') .';
                }

                #message-container #messages .message-option:hover
                {
                    border-color: '. ($is_dark ? $main_color_light : 'rgba(0, 0, 0, 0.6)') .';
                    color: '. ($is_dark ? $main_color_light : 'rgba(0, 0, 0, 0.6)') .';
                }

                #watson-box #messages > div:not(.message) > a
                {
                    color: '. ($is_dark ? $main_color : $text_color) .';
                }

                #watson-fab-float
                {
                    '.$position[0].': 5vmin;
                    '.$position[1].': 5vmin;
                }

                #watson-box .watson-font
                {
                    font-size: '.$font_size.'pt;
                }

                #watson-float
                {
                    '.$position[0].': 5vmin;
                    '.$position[1].': 5vmin;
                }
                #watson-box
                {
                    width: '.(0.825*$messages_height + 4.2*$font_size).'pt;
                    height: auto;
                }
                #message-container
                {
                    height: '.$messages_height.'pt
                }
                
                @media (max-width:768px)  {
                    #watson-box .watson-font
                    {
                        font-size: 16px;
                    }
                }' . 
                sprintf(
                    $full_screen_query, 
                    '#watson-float #watson-box
                    {
                        width: 100%;
                        height: 100%;
                    }

                    #watson-box
                    {
                        max-width: 100%;
                    }
                
                    #watson-float
                    {
                        top: 0;
                        right: 0;
                        bottom: 0;
                        left: 0;
                        transform: translate(0, 0) !important;
                    }

                    #watson-float #message-container
                    {
                        height: auto;
                    }
                    #chatbox-body
                    {           
                        display: flex; 
                        flex-direction: column;
                    }'
                );
            
            if (is_null($force_full_screen)) {
                update_option('watsonconv_css_cache', $inline_style);
            }
        }

        wp_add_inline_style('watsonconv-chatbox', $inline_style);
    }

    private static function luminance($srgb) {
        $lin_rgb = array_map(function($val) {
            $val /= 255;

            if ($val <= 0.03928) {
                return $val / 12.92;
            } else {
                return pow(($val + 0.055) / 1.055, 2.4);
            }
        }, $srgb);

        return 0.2126 * $lin_rgb[0] + 0.7152 * $lin_rgb[1] + 0.0722 * $lin_rgb[2];
    }

    public static function get_settings() {
        $twilio_config = get_option('watsonconv_twilio');

        $call_configured = (bool)(
            !empty($twilio_config['sid']) && 
            !empty($twilio_config['auth_token']) && 
            get_option('watsonconv_twiml_sid') &&
            get_option('watsonconv_call_id') &&
            get_option('watsonconv_call_recipient')
        );

        return array(
            'delay' => (int) get_option('watsonconv_delay', 0),
            'minimized' => get_option('watsonconv_minimized', 'no'),
            'position' => explode('_', get_option('watsonconv_position', 'bottom_right')),
            'title' => get_option('watsonconv_title', ''),
            'clearText' => get_option('watsonconv_clear_text', 'Clear Messages'),
            'messagePrompt' => get_option('watsonconv_message_prompt', 'Type a Message'),
            'fullScreen' => get_option('watsonconv_full_screen', 'no'),
            'showSendBtn' => get_option('watsonconv_send_btn', 'no'),
            'fabConfig' => array(
                'iconPos' => get_option('watsonconv_fab_icon_pos', 'left'),
                'text' => get_option('watsonconv_fab_text', '')
            ),
            'callConfig' => array(
                'useTwilio' => get_option('watsonconv_use_twilio', 'no'),
                'configured' => $call_configured,
                'recipient' => get_option('watsonconv_call_recipient'),
                'callTooltip' => get_option('watsonconv_call_tooltip'),
                'callButton' => get_option('watsonconv_call_button'),
                'callingText' => get_option('watsonconv_calling_text')
            )
        );
    }

    public static function chatbox_popup() {
        $ip_addr = API::get_client_ip();

        $page_selected =
            get_option('watsonconv_show_on', 'all') == 'all' ||
            (is_front_page() && get_option('watsonconv_home_page', 'false') == 'true') ||
            is_page(get_option('watsonconv_pages', array(-1))) ||
            is_single(get_option('watsonconv_posts', array(-1))) ||
            in_category(get_option('watsonconv_categories', array(-1)));

        $total_requests = get_option('watsonconv_total_requests', 0) +
            get_transient('watsonconv_total_requests') ?: 0;
        $client_requests = get_option("watsonconv_requests_$ip_addr", 0) +
            get_transient("watsonconv_requests_$ip_addr") ?: 0;

        $credentials = get_option('watsonconv_credentials');
        $is_enabled = !empty($credentials) && (!isset($credentials['enabled']) || $credentials['enabled'] == 'true');

        if ($page_selected &&
            (get_option('watsonconv_use_limit', 'no') == 'no' ||
                $total_requests < get_option('watsonconv_limit', 10000)) &&
            (get_option('watsonconv_use_client_limit', 'no') == 'no' ||
                $client_requests < get_option('watsonconv_client_limit', 100)) &&
            $is_enabled) {

            self::enqueue_styles();
            $settings = self::get_settings();
            
            if ($settings['callConfig']['useTwilio'] == 'yes' && $settings['callConfig']['callConfigured']) {
                wp_enqueue_script('twilio-js', 'https://media.twiliocdn.com/sdk/js/client/v1.4/twilio.min.js');
            }

            wp_enqueue_script('watsonconv-chat-app');
            wp_localize_script('watsonconv-chat-app', 'watsonconvSettings', $settings);
        }
    }

    public static function chatbox_shortcode() {
        $ip_addr = API::get_client_ip();

        $total_requests = get_option('watsonconv_total_requests', 0) +
            get_transient('watsonconv_total_requests') ?: 0;
        $client_requests = get_option("watsonconv_requests_$ip_addr", 0) +
            get_transient("watsonconv_requests_$ip_addr") ?: 0;

        $credentials = get_option('watsonconv_credentials');
        $is_enabled = !empty($credentials) && (!isset($credentials['enabled']) || $credentials['enabled'] == 'true');

        if ((get_option('watsonconv_use_limit', 'no') == 'no' ||
                $total_requests < get_option('watsonconv_limit', 10000)) &&
            (get_option('watsonconv_use_client_limit', 'no') == 'no' ||
                $client_requests < get_option('watsonconv_client_limit', 100)) &&
            $is_enabled) 
        {
            if (!wp_script_is('watsonconv-chat-app', 'enqueued')) {
                self::enqueue_styles();
                $settings = self::get_settings();
                
                if ($settings['callConfig']['useTwilio'] == 'yes' && $settings['callConfig']['callConfigured']) {
                    wp_enqueue_script('twilio-js', 'https://media.twiliocdn.com/sdk/js/client/v1.4/twilio.min.js');
                }
    
                wp_enqueue_script('watsonconv-chat-app');
                wp_localize_script('watsonconv-chat-app', 'watsonconvSettings', $settings);
            }

            return '<div id="watsonconv-inline-box"></div>';
        }

        return '';
    }

    public static function render_div() {
        ?>
            <div id="watsonconv-floating-box"></div>
        <?php
    }

    public static function register_scripts() {
        wp_register_script('watsonconv-chat-app', WATSON_CONV_URL.'app.js', array(), self::VERSION, true);
        wp_register_style('watsonconv-chatbox', WATSON_CONV_URL.'css/chatbox.css', array('dashicons'), self::VERSION);
    }
}
