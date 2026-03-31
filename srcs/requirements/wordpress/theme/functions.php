<?php
function inception_enqueue_styles() {
    wp_enqueue_style('inception-style', get_stylesheet_uri(), array(), '1.0');
    wp_enqueue_style('inter-font', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap', array(), null);
}
add_action('wp_enqueue_scripts', 'inception_enqueue_styles');

// Remove admin bar on front-end for cleaner look
add_filter('show_admin_bar', '__return_false');
