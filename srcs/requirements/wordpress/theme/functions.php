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
