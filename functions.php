<?php
/*
Plugin Name: RocketFront Connect
Plugin URI: https://www.kodeala.com
Description: Automate AWS CloudFront CDN clearing and cache management by seamlessly integrating the WP Rocket plugin with C3 Cloudfront Cache Controller.
Version: 1.0.0
Author: Kodeala
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: rocketfront-connect

Requires at least: 5.0
Tested up to: 6.4.3

Requires WP Rocket: Yes
Requires C3 Cloudfront Cache Controller: Yes
*/

if ( ! defined( 'ABSPATH' ) ) exit;

require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
use Aws\CloudFront\CloudFrontClient;
function kodealarocketfront_initialize_cloudfront_wprocket_connection() {
    //Check if Cloudfront plugin is active
    if (is_plugin_active('c3-cloudfront-clear-cache/c3-cloudfront-clear-cache.php')) {
        //Fetch Autoload.php from cloudfront plugin.
        require_once WP_PLUGIN_DIR . '/c3-cloudfront-clear-cache/vendor/autoload.php';

        $region = get_option('kodealarocketfront_selected_region', 'us-east-1');

        $c3_settings = get_option('c3_settings');
        $cloudfront = null;
        
        if (is_array($c3_settings) && !empty($c3_settings['access_key']) && !empty($c3_settings['secret_key'])) {
            $cloudfront = new CloudFrontClient([
                'version' => 'latest',
                'region' => esc_html(sanitize_text_field($region)),
                'credentials' => [
                    'key' => esc_html(sanitize_text_field($c3_settings['access_key'])),
                    'secret' => esc_html(sanitize_text_field($c3_settings['secret_key'])),
                ],
            ]);
        }
        function kodealarocketfront_invalidate_cloudfront_cache($cloudfront) {
            if (defined('KODEALAROCKETFRONT_CACHE_INVALIDATED')) {
                return;
            }
            define('KODEALAROCKETFRONT_CACHE_INVALIDATED', true);
            
            $c3_settings = get_option('c3_settings');
            $result = $cloudfront->createInvalidation([
                'DistributionId' => esc_html(sanitize_text_field($c3_settings['distribution_id'])),
                'InvalidationBatch' => [
                    'CallerReference' => time(),
                    'Paths' => [
                        'Quantity' => 1,
                        'Items' => ['/*'],
                    ],
                ],
            ]);
        }

        if (!empty($cloudfront)) {
            add_action('after_rocket_clean_domain', function () use ($cloudfront) {
                kodealarocketfront_invalidate_cloudfront_cache($cloudfront);
            });
            add_action('after_rocket_clean_minify', function () use ($cloudfront) {
                kodealarocketfront_invalidate_cloudfront_cache($cloudfront);
            });
            add_action('after_rocket_clean_cache', function () use ($cloudfront) {
                kodealarocketfront_invalidate_cloudfront_cache($cloudfront);
            });
        }

    function kodealarocketfront_add_region_dropdown() {
        add_options_page(
            'CloudFront Regions',
            'CloudFront Regions',
            'manage_options',
            'kodealarocketfront-region-settings',
            'kodealarocketfront_region_settings_page'
        );
    }
    add_action('admin_menu', 'kodealarocketfront_add_region_dropdown');

    function kodealarocketfront_region_settings_page() {
        if (!isset($_POST['kodealarocketfront_nonce_field']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kodealarocketfront_nonce_field'])), 'kodealarocketfront_nonce_action')) {
            $selected_region = isset($_POST['kodealarocketfront_selected_region']) ? sanitize_text_field(wp_unslash($_POST['kodealarocketfront_selected_region'])) : '';
            if (!empty($selected_region)) {
                update_option('kodealarocketfront_selected_region', $selected_region);
            }
        }
        $regions = [
            'us-east-1' => 'US East (N. Virginia)',
            'us-east-2' => 'US East (Ohio)',
            'us-west-1' => 'US West (N. California)',
            'us-west-2' => 'US West (Oregon)',
            'ap-south-1' => 'Asia Pacific (Mumbai)',
            'ap-northeast-3' => 'Asia Pacific (Osaka)',
            'ap-northeast-2' => 'Asia Pacific (Seoul)',
            'ap-southeast-1' => 'Asia Pacific (Singapore)',
            'ap-southeast-2' => 'Asia Pacific (Sydney)',
            'ap-northeast-1' => 'Asia Pacific (Tokyo)',
            'ca-central-1' => 'Canada (Central)',
            'eu-central-1' => 'Europe (Frankfurt)',
            'eu-west-1' => 'Europe (Ireland)',
            'eu-west-2' => 'Europe (London)',
            'eu-west-3' => 'Europe (Paris)',
            'eu-north-1' => 'Europe (Stockholm)',
            'sa-east-1' => 'South America (São Paulo)',
            'af-south-1' => 'Africa (Cape Town)',
            'ap-east-1' => 'Asia Pacific (Hong Kong)',
            'ap-south-2' => 'Asia Pacific (Hyderabad)',
            'ap-southeast-3' => 'Asia Pacific (Jakarta)',
            'ap-southeast-4' => 'Asia Pacific (Melbourne)',
            'ca-west-1' => 'Canada (Calgary)',
            'eu-south-1' => 'Europe (Milan)',
            'eu-south-2' => 'Europe (Spain)',
            'eu-central-2' => 'Europe (Zurich)',
            'me-south-1' => 'Middle East (Bahrain)',
            'me-central-1' => 'Middle East (UAE)',
            'il-central-1' => 'Israel (Tel Aviv)'
        ];

        $region = get_option('kodealarocketfront_selected_region', 'us-east-1');
        ?>
        <div class="wrap">
            <h1>Region Settings</h1>
            <form method="post">
                <label for="kodealarocketfront_selected_region">Select CloudFront Region:</label>
                <select name="kodealarocketfront_selected_region" id="kodealarocketfront_selected_region">
                    <?php foreach ($regions as $region_code => $region_name){
                        $selected = ($region == $region_code) ? 'selected' : '';
                    ?>
                        <option value="<?php echo esc_html(sanitize_text_field($region_code)); ?>" <?php echo esc_html(sanitize_text_field($selected)); ?>><?php echo esc_html(sanitize_text_field($region_name)); ?></option>
                    <?php }; ?>
                </select>
                <?php
                    wp_nonce_field('kodealarocketfront_nonce_action', 'kodealarocketfront_nonce_field', true, false);
                    submit_button('Save Changes');
                ?>
            </form>
        </div>
        <?php
        }
    }
}
add_action('plugins_loaded', 'kodealarocketfront_initialize_cloudfront_wprocket_connection');
?>