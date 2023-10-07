<?php
/*
Plugin Name: Combined Featured Image Setter
Description: Check for posts every four hours without a featured image, sets them to draft, analyzes the word count, and sets a relevant featured image from Pixabay.
Version: 1.0
*/

add_action('admin_menu', 'register_custom_admin_page');

function register_custom_admin_page() {
    add_menu_page('Featured Image Setter', 'Featured Image Setter', 'manage_options', 'featured-image-setter', 'display_admin_page');
}

function display_admin_page() {
    echo '<div class="wrap">';
    echo '<h1>Manual Featured Image Setter</h1>';

    // Check if the function has been triggered
    if (isset($_POST['run_function']) && check_admin_referer('run_check_and_update_posts_nonce')) {
        check_and_update_posts();
        echo '<div class="updated"><p>Function has been run.</p></div>';
    }

    echo '<form method="post" action="">';
    wp_nonce_field('run_check_and_update_posts_nonce');
    echo '<input type="submit" name="run_function" value="Run Function" class="button button-primary" />';
    echo '</form>';

    echo '</div>'; 
}

define('PIXABAY_API_KEY', 'ENTER YOUR PIXABAY API KEY HERE');   // Enter Pixabay API KEY here
define('PIXABAY_LOG_FILE', dirname(__FILE__) . '/pixabay_log.txt');

// Activation hook
register_activation_hook(__FILE__, 'plugin_activate');
function plugin_activate() {
    if (!wp_next_scheduled('check_and_update_posts_event')) {
        wp_schedule_event(time(), 'four_hours', 'check_and_update_posts_event');
    }
}

add_filter('cron_schedules', 'add_four_hours_cron_interval');
function add_four_hours_cron_interval($schedules) {
    $schedules['four_hours'] = array(
        'interval' => 14400, // Four hours in seconds (60 * 60 * 4)
        'display' => __('Every Four Hours')
    );
    return $schedules;
}

add_action('check_and_update_posts_event', 'check_and_update_posts');
function check_and_update_posts() {
    $args = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => '_thumbnail_id',
                'compare' => 'NOT EXISTS'
            ),
        ),
    );

    $posts = new WP_Query($args);

    if ($posts->have_posts()) {
        while ($posts->have_posts()) {
            $posts->the_post();
            $post_id = get_the_ID();
            
            // Set the post to draft
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => 'draft'
            ));

            // Get the word that has the highest occurrence, ignoring stop words
            $content = get_the_content();
            $words = str_word_count(strip_tags($content), 1);
            
            // Define a list of words to exclude
            $excluded_words = array('the', 'a', 'an', 'but', 'and', 'or', 'is', 'in', 'of', 'to', 'for', 'with', 'as', 'on', 'at', 'by');
            $filtered_words = array_diff($words, $excluded_words);
            
            $word_counts = array_count_values($filtered_words);
            arsort($word_counts);
            $this_article_is_about = key($word_counts);
            
            // Get and set the featured image based on $this_article_is_about
            set_pixabay_featured_image_based_on_word($post_id, $this_article_is_about);
            
            // Republish the post
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => 'publish'
            ));
        }
        wp_reset_postdata();
    }
}

function set_pixabay_featured_image_based_on_word($post_id, $search_word) {
    $query = rawurlencode(utf8_encode($search_word));
    $url = 'https://pixabay.com/api/?key=' . PIXABAY_API_KEY . '&image_type=photo&q=' . $query;

    $response = wp_remote_get($url);
    if (is_wp_error($response)) {
        error_log('API error: ' . $response->get_error_message(), 3, PIXABAY_LOG_FILE);
        return;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($data['hits']) && count($data['hits']) > 0) {
        // Select the first image from the results
        $selected_image_url = $data['hits'][0]['webformatURL'];
        attach_featured_image($selected_image_url, $post_id);
    } else {
        error_log('No images found for the word: ' . $search_word, 3, PIXABAY_LOG_FILE);
    }
}

function attach_featured_image($image_url, $post_id) {
    $response = wp_safe_remote_get($image_url);

    if (is_wp_error($response)) {
        error_log('Error downloading image: ' . $response->get_error_message(), 3, PIXABAY_LOG_FILE);
        error_log('Image URL: ' . $image_url, 3, PIXABAY_LOG_FILE);
        return false;
    }

    // Log the Pixabay image URL to the file before proceeding with image attachment
    file_put_contents(PIXABAY_LOG_FILE, 'Pixabay Image URL: ' . $image_url . PHP_EOL, FILE_APPEND);

    $image_data = wp_remote_retrieve_body($response);

    $upload_dir = wp_upload_dir();
    $upload_path = wp_mkdir_p($upload_dir['path']) ? $upload_dir['path'] : $upload_dir['basedir'];
    $filename = time() . '-' . uniqid();
    $file_location = $upload_path . '/' . $filename;

    file_put_contents($file_location, $image_data);

    $file_info = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $file_info->buffer($image_data);

    $attachment = [
        'post_mime_type' => $mime_type,
        'post_title' => '',
        'post_content' => '',
        'post_status' => 'inherit'
    ];

    $attachment_id = wp_insert_attachment($attachment, $file_location, $post_id);

    if (is_wp_error($attachment_id)) {
        error_log('Error inserting attachment: ' . $attachment_id->get_error_message(), 3, PIXABAY_LOG_FILE);
        return false;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_location);

    if (is_wp_error($attachment_data)) {
        error_log('Error generating attachment metadata: ' . $attachment_data->get_error_message(), 3, PIXABAY_LOG_FILE);
        return false;
    }

    wp_update_attachment_metadata($attachment_id, $attachment_data);
    update_post_meta($post_id, '_thumbnail_id', $attachment_id);

    return

 true;
}

// Deactivation hook to unschedule the event
register_deactivation_hook(__FILE__, 'plugin_deactivate');
function plugin_deactivate() {
    wp_clear_scheduled_hook('check_and_update_posts_event');
}

?>