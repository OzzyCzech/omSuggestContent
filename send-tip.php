<?php get_header(); ?>

	<div id="primary" class="site-content send-tip">
		<div id="content" role="main">

			<article>
				<h1><? _e('Send a tip', OSC) ?></h1>

				<?
				try {
					if (\om\suggest\omSuggestContent::saveTip()) {
						echo '<div class="alert alert-success">' . __('Thanks for your tip!', OSC) . '</div>';
					}
				} catch (Exception $e) {
					echo '<div class="alert alert-danger">' . $e->getMessage() . '</div>';
				}
				?>

				<? do_action('tips_before_form') ?>

				<form action="<?= esc_attr($action) ?>" method="post">

					<div class="form-group">
						<label for="tip-title"><? _e('Tip title', OSC) ?></label>
						<input type="text" class="form-control" value="" placeholder="<? esc_attr(_e('Title', OSC)) ?>"
						       required="required" name="title" id="tip-title"/>
					</div>

					<div class="form-group">
						<label for="tip-url"><? _e('URL', OSC) ?></label>
						<input type="url" class="form-control" value=""
						       placeholder="<? esc_attr(_e('http://www.example.com', OSC)) ?>"
						       name="url" id="tip-url"/>
					</div>

					<? if (!is_user_logged_in()) { ?>
						<div class="form-group">
							<label for="tip-name" class="form-label"><? _e('Your name', OSC) ?></label>
							<input type="text" class="form-control" content="" placeholder="<? esc_attr(_e('John Doe', OSC)) ?>"
							       required="required" name="name" id="tip-name"/>
						</div>

						<div class="form-group">
							<label for="tip-email"><? _e('Your email', OSC) ?></label>
							<input type="email" class="form-control" content=""
							       placeholder="<? esc_attr(_e('email@example.com', OSC)) ?>"
							       required="required" name="email" id="tip-email"/>
						</div>
					<? } ?>

					<div>
						<? wp_editor(
							'', 'tip-content', [
								'dfw' => false,
								'teeny' => true,
								'wpautop' => false,
								'quicktags' => false,
								'drag_drop_upload' => false,
								'media_buttons' => false,
								'editor_height' => 180,
								'textarea_name' => 'content',
								'editor_classes' => 'form-control'
							]
						); ?>
					</div>


					<? wp_nonce_field('send-tip', 'send-tip-nonce') ?>

					<? \om\suggest\omSuggestContent::captcha(); ?>

					<div class="actions text-right">
						<button type="submit" name="action" value="send" class="button btn btn-default"><? _e(
								'Submit tip', OSC
							) ?></button>
					</div>

					<? do_action('tips_after_form') ?>
				</form>

			</article>


		</div>

	</div>

<?php get_sidebar(); ?>
<?php get_footer(); ?>