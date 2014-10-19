<?php get_header(); ?>

	<div id="primary" class="site-content send-news">
		<div id="content" role="main">

			<article>
				<h1><? _e('Suggest news', OSC) ?></h1>

				<?
				try {
					if (\om\suggest\omSuggestContent::saveNews()) {
						echo '<div class="alert alert-success">' . __('Thanks for your news!', OSC) . '</div>';
					}
				} catch (Exception $e) {
					echo '<div class="alert alert-danger">' . $e->getMessage() . '</div>';
				}
				?>

				<? do_action('news_before_form') ?>

				<form action="<?= esc_attr($action) ?>" method="post">

					<div class="form-group">
						<label for="news-title"><? _e('Tip title', OSC) ?></label>
						<input type="text" class="form-control" value="" placeholder="<? esc_attr(_e('Title', OSC)) ?>"
						       required="required" name="title" id="news-title"/>
					</div>

					<? if (!is_user_logged_in()) { ?>
						<div class="form-group">
							<label for="news-name" class="form-label"><? _e('Your name', OSC) ?></label>
							<input type="text" class="form-control" content="" placeholder="<? esc_attr(_e('John Doe', OSC)) ?>"
							       required="required" name="name" id="news-name"/>
						</div>

						<div class="form-group">
							<label for="news-email"><? _e('Your email', OSC) ?></label>
							<input type="email" class="form-control" content=""
							       placeholder="<? esc_attr(_e('email@example.com', OSC)) ?>"
							       required="required" name="email" id="news-email"/>
						</div>
					<? } ?>

					<div>
						<? wp_editor(
							'', 'news-content', [
								'dfw' => false,
								'teeny' => true,
								'wpautop' => false,
								'quicktags' => false,
								'drag_drop_upload' => false,
								'media_buttons' => false,
								'editor_height' => 360,
								'textarea_name' => 'content',
								'editor_classes' => 'form-control'
							]
						); ?>
					</div>


					<? wp_nonce_field('send-news-nonce', 'send-news-nonce') ?>

					<? \om\suggest\omSuggestContent::captcha(); ?>

					<div class="actions text-right">
						<button type="submit" name="action" value="send" class="button btn btn-default"><? _e(
								'Submit news', OSC
							) ?></button>
					</div>

					<? do_action('news_after_form') ?>
				</form>

			</article>


		</div>

	</div>

<?php get_sidebar(); ?>
<?php get_footer(); ?>