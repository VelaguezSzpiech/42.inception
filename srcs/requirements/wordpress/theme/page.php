<?php get_header(); ?>

<div class="section">
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <article class="card post-card post-single">
            <h1><?php the_title(); ?></h1>
            <div class="post-content">
                <?php the_content(); ?>
            </div>
        </article>

        <?php if (comments_open() || get_comments_number()) : ?>
            <?php comments_template(); ?>
        <?php endif; ?>

    <?php endwhile; endif; ?>
</div>

<?php get_footer(); ?>
