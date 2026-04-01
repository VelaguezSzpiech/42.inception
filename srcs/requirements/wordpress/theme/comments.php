<?php if (post_password_required()) return; ?>

<?php if (comments_open()) : ?>
    <?php comment_form(array(
        'title_reply'          => 'Post a Comment',
        'label_submit'         => 'Post',
        'comment_notes_before' => '',
        'comment_notes_after'  => '',
        'class_form'           => 'comment-form',
        'class_submit'         => 'btn',
    )); ?>
<?php endif; ?>

<?php if (have_comments()) : ?>
    <div class="recent-comments">
        <h3 class="comments-title">Recent Comments</h3>
        <ol class="comment-list">
            <?php wp_list_comments(array(
                'style'       => 'ol',
                'short_ping'  => true,
                'callback'    => 'inception_comment',
            )); ?>
        </ol>
        <?php if (get_comment_pages_count() > 1) : ?>
            <nav class="comment-nav">
                <?php previous_comments_link('&larr; Older'); ?>
                <?php next_comments_link('Newer &rarr;'); ?>
            </nav>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
function inception_comment($comment, $args, $depth) {
    $tag = ($args['style'] === 'div') ? 'div' : 'li';
    ?>
    <<?php echo $tag; ?> id="comment-<?php comment_ID(); ?>" <?php comment_class('comment-item'); ?>>
        <div class="comment-body">
            <div class="comment-header">
                <strong class="comment-author"><?php comment_author(); ?></strong>
                <time class="comment-date"><?php comment_date(); ?> at <?php comment_time(); ?></time>
            </div>
            <div class="comment-text">
                <?php comment_text(); ?>
            </div>
            <?php comment_reply_link(array_merge($args, array(
                'depth'     => $depth,
                'max_depth' => $args['max_depth'],
            ))); ?>
        </div>
    <?php
}
?>
