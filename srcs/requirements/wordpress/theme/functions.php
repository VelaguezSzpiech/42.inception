<?php
function inception_enqueue_styles() {
    wp_enqueue_style('inception-style', get_stylesheet_uri(), array(), '1.0');
    wp_enqueue_style('inter-font', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap', array(), null);
}
add_action('wp_enqueue_scripts', 'inception_enqueue_styles');

// Dark theme for wp-admin
function inception_admin_styles() {
    wp_enqueue_style('inception-admin', get_template_directory_uri() . '/admin-style.css', array(), '1.0');
    wp_enqueue_style('inter-font-admin', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap', array(), null);
}
add_action('admin_enqueue_scripts', 'inception_admin_styles');

// Dark theme for wp-login
function inception_login_styles() {
    wp_enqueue_style('inception-login', get_template_directory_uri() . '/admin-style.css', array(), '1.0');
    wp_enqueue_style('inter-font-login', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap', array(), null);
}
add_action('login_enqueue_scripts', 'inception_login_styles');

// Remove admin bar on front-end for cleaner look
add_filter('show_admin_bar', '__return_false');

// Enable comments
add_theme_support('automatic-feed-links');
add_theme_support('title-tag');
add_theme_support('post-thumbnails');

// Clean up wp-admin dashboard
function inception_remove_dashboard_widgets() {
    remove_meta_box('dashboard_primary', 'dashboard', 'side');       // WordPress Events and News
    remove_meta_box('dashboard_quick_press', 'dashboard', 'side');   // Quick Draft
    remove_meta_box('dashboard_site_health', 'dashboard', 'normal'); // Site Health
    remove_action('welcome_panel', 'wp_welcome_panel');              // Welcome panel
}
add_action('wp_dashboard_setup', 'inception_remove_dashboard_widgets');

// Hide update nag
function inception_hide_update_nag() {
    remove_action('admin_notices', 'update_nag', 3);
}
add_action('admin_head', 'inception_hide_update_nag');

// Hide update notices for non-critical display
function inception_admin_cleanup() {
    echo '<style>
        .update-nag, .updated, .notice-warning.notice-alt { display: none !important; }
        .wrap > .notice:not(.notice-error) { display: none !important; }
    </style>';
}
add_action('admin_head', 'inception_admin_cleanup');

// Allow guest comments: name required, email optional, no login needed
function inception_allow_guest_comments() {
    update_option('comment_registration', 0);
    update_option('require_name_email', 0);
    update_option('comment_moderation', 0);
    update_option('comment_previously_approved', 0);
}

// Require name but not email
function inception_require_comment_name($commentdata) {
    if (empty($commentdata['comment_author'])) {
        wp_die('Please enter your name.', 'Comment Error', array('back_link' => true));
    }
    return $commentdata;
}
add_filter('preprocess_comment', 'inception_require_comment_name');
add_action('after_setup_theme', 'inception_allow_guest_comments');

// Ensure comments are open on the front page
function inception_open_front_page_comments($open, $post_id) {
    $front_id = get_option('page_on_front');
    if ($front_id && $post_id == $front_id) {
        return true;
    }
    return $open;
}
add_filter('comments_open', 'inception_open_front_page_comments', 10, 2);
