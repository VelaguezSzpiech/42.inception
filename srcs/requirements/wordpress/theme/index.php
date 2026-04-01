<?php get_header(); ?>

<div class="section">
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <article class="card post-card">
            <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
            <div class="post-meta">
                <?php the_date(); ?> &middot; <?php the_author(); ?>
            </div>
            <div class="post-excerpt">
                <?php the_excerpt(); ?>
            </div>
        </article>
    <?php endwhile; else : ?>
        <div class="card">
            <p>No posts found.</p>
        </div>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
