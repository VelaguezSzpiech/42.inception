<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php wp_title('|', true, 'right'); ?><?php bloginfo('name'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<nav class="site-nav">
    <div class="site-nav__inner">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="site-nav__logo">Inception</a>
        <div class="site-nav__links">
            <a href="<?php echo esc_url(home_url('/')); ?>">Home</a>
            <?php if (is_user_logged_in()) : ?>
                <a href="<?php echo esc_url(admin_url()); ?>">Dashboard</a>
                <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Log out</a>
            <?php else : ?>
                <a href="<?php echo esc_url(wp_login_url()); ?>">Log in</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
