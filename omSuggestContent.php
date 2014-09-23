<?php
namespace om\suggest;

/**
 * Plugin Name: omSuggestContent
 * Plugin URI: http://www.omdesign.cz
 * Description: Suggest content by users from public page
 * Version: 1.0
 * Author: Roman Ožana
 * Author URI: http://www.omdesign.cz/kontakt
 *
 * @author Roman Ožana <ozana@omdesign.cz>
 */

if (!class_exists('WP')) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit;
}

define('OSC', 'omSuggestContent'); // language

class omSuggestContent {

	/** @var string */
	public $url;

	protected static $captcha = '';
	protected static $antispam = 'captcha';

	function __construct() {
		add_action('init', [$this, 'init'], 999, 0);
		add_filter('template_redirect', [$this, 'template_redirect'], 999);
		add_filter('manage_edit-tips_columns', [$this, 'tips_columns'], 10, 1);
		add_filter('manage_tips_posts_custom_column', [$this, 'tips_columns_content'], 10, 2);
		add_action('transition_post_status', [$this, 'transition_post_status'], 10, 3);
		add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
		add_action('save_post', [$this, 'save_post']);
		static::$captcha = sanitize_title(get_bloginfo('name'));
	}

	public function init() {
		load_plugin_textdomain(OSC, false, dirname(plugin_basename(__FILE__)) . '/languages/');

		$this->url = __('send-tip', OSC);
		add_rewrite_tag("%$this->url%", '([^&]+)');
		add_rewrite_rule("$this->url/?", 'index.php?pagename=' . $this->url, 'top');

		register_post_type(
			'tips', [
				'labels' => [
					'name' => __('Tips', OSC),
					'singular_name' => __('Tip', OSC),
					'add_new' => __('Send tip', OSC),
					'add_new_item' => __('Send new tip', OSC),
				],
				'menu_icon' => 'dashicons-carrot',
				'public' => false,
				'show_ui' => true,
				'has_archive' => false,
				'hierarchical' => false,
				'menu_position' => 5,
				'show_in_nav_menus' => false,
				'supports' => false
			]
		);

		// flush rewrite rulles after plugin activation
		if (is_admin() && get_option('flush_rewrite_rules')) {
			flush_rewrite_rules(true);
			wp_cache_flush();
			delete_option('flush_rewrite_rules');
		}
	}

	public function tips_columns($columns) {
		return [
			'cb' => '<input type="checkbox" />',
			'tip' => __('Tip', OSC),
			'from' => __('From', OSC),
			'date' => __('Date', OSC),
		];
	}

	public function tips_columns_content($column, $post_id) {
		$post = get_post($post_id);

		switch ($column) {
			case 'tip':
				echo sprintf(
					'<h2 style="margin: 0;padding: 0;font-size: 21px"><a href="%s">%s</a></h2>', get_edit_post_link($post_id),
					$post->post_title
				);
				echo (isset($post->tip_url)) ? sprintf(
					'<a href="%s" target="_blank">%s</a>', $post->tip_url, $post->tip_url
				) : null;
				echo str_replace(']]>', ']]&gt;', apply_filters('the_content', $post->post_content));
				echo sprintf('<a href="%s" class="button">%s</a>', get_delete_post_link($post_id), __('Delete tip', OSC));
				break;
			case 'from':
				$email = get_the_author_meta('user_email', $post->post_author) ?: $post->tip_email;
				$name = get_the_author_meta('display_name', $post->post_author) ?: $post->tip_name;

				echo $name ? esc_html($name) : null;
				echo $email ? sprintf('<br/><a href="mailto:%s">%s</a>', $email, $email) : null;
				echo isset($post->tip_ip) ? '<br/>' . esc_html($post->tip_ip) : null;
				break;
		}
	}

	public function template_redirect() {
		global $wp_query;
		/** @var \WP_Query $wp_query */

		if (is_404() && get_query_var('pagename') === $this->url) {
			$wp_query->is_page = true;
			$wp_query->is_404 = false;
			$wp_query->is_home = false;

			$action = home_url($this->url);
			include is_file(TEMPLATEPATH . '/send-tip.php') ? TEMPLATEPATH . '/send-tip.php' : __DIR__ . '/send-tip.php';
			exit;
		}
	}

	public static function captcha() {
		?>
		<script type="text/javascript">
			document.write('<?= implode(
					"' + '" ,
					str_split(
						'<input type="hidden" name="' . static::$antispam . '" id="' . static::$antispam . '" value="' . static::$captcha . '" />', 1
					)
				) ?>');
		</script>

		<noscript>
			<p>
				<label for="<?= static::$antispam ?>">Napište "<em><?= static::$captcha ?></em>": </label>
				<input id="<?= static::$antispam ?>" name="<?= static::$antispam ?>" value="" required="required"
				       placeholder="<? _e('Captcha input', OSC) ?>"/>
			</p>
		</noscript>
	<?
	}

	public static function saveTip() {
		if (!isset($_REQUEST['action'])) return;

		if (!isset($_REQUEST['send-tip-nonce']) || !wp_verify_nonce($_REQUEST['send-tip-nonce'], 'send-tip')) {
			throw new \Exception(__('Unable to submit this form, please refresh and try again.', OSC));
		}

		$captcha = isset($_POST[static::$antispam]) ? $_POST[static::$antispam] : null;
		$title = isset($_REQUEST['title']) ? sanitize_text_field($_REQUEST['title']) : null;
		$content = isset($_REQUEST['content']) ? $_REQUEST['content'] : null;
		$url = isset($_REQUEST['url']) ? $_REQUEST['url'] : null;


		if ($captcha !== static::$captcha) {
			throw new \Exception(sprintf(__('Invalid captcha value. We expect "%s".', OSC), static::$captcha));
		}

		if (!$title) throw new \Exception(_e('Title is mandatory.', OSC));
		if (!$content) throw new \Exception(_e('Content is mandatory.', OSC));

		if (is_user_logged_in()) {
			$current_user = wp_get_current_user();
			$email = $current_user->user_email;
			$name = $current_user->display_name;
		} else {
			$email = isset($_REQUEST['email']) ? $_REQUEST['email'] : null;
			$name = isset($_REQUEST['name']) ? $_REQUEST['name'] : null;

			if (!$name) throw new \Exception(_e('Name is  mandatory.', OSC));
			if (!$email) throw new \Exception(_e('Email is mandatory.', OSC));
		}

		$post = [
			'post_title' => $title,
			'post_content' => $content,
			'post_type' => 'tips',
			'post_author' => get_current_user_id(),
			'post_status' => 'publish',
		];

		/**
		 * Detect current user IP address
		 *
		 * @return mixed
		 */
		$getCurrentIp = function () {
			foreach (
				[
					'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP',
					'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'
				] as $key) {
				if (array_key_exists($key, $_SERVER) === true) {
					foreach (array_map('trim', explode(',', $_SERVER[$key])) as $ip) {
						if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
							return $ip;
						}
					}
				}
			}
		};

		if ($id = wp_insert_post($post)) {
			update_post_meta($id, 'tip_url', $url);
			update_post_meta($id, 'tip_name', $name);
			update_post_meta($id, 'tip_email', $email);
			update_post_meta($id, 'tip_ip', $getCurrentIp());
			return true;
		}
	}

	/**
	 * Send new post transaction email
	 *
	 * @param $new_status
	 * @param $old_status
	 * @param $post
	 */
	public function transition_post_status($new_status, $old_status, $post) {
		/** @var \WP_Post $post */
		if ($post->post_type === 'tips' && $old_status !== $new_status && $new_status === 'publish') {

			$to = apply_filters('tips_send_to_email', [get_option('admin_email')]);

			if ($to) {
				wp_mail(
					(array)$to,
					sprintf(__('%s: There is a new tip', OSC), get_bloginfo('name')),
					sprintf('<h2>%s</h2><div>%s</div>', $post->post_title, $post->post_content) .
					sprintf('<hr><a href="%s">' . __('Show tip', OSC) . '</a>', get_edit_post_link($post->ID))
				);
			}
		}
	}

	public function add_meta_boxes($post_id) {
		$screen = get_current_screen();
		if ($screen->id === 'tips') {
			add_action(
				'edit_form_after_editor', function ($post) {

					$current_user = wp_get_current_user();
					$email = $current_user->user_email;
					$name = $current_user->display_name;

					include __DIR__ . '/omSuggestContentEditor.phtml';
				}
			);
		}
	}

	public function save_post($id) {
		if (!isset($_POST['send-tip-nonce'])) return;
		if (!wp_verify_nonce($_POST['send-tip-nonce'], 'send-tip-nonce')) return;
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

		if ($_POST['post_type'] === 'tips') {
			update_post_meta($id, 'tip_url', sanitize_text_field($_POST['tip_url']));
			update_post_meta($id, 'tip_name', sanitize_text_field($_POST['tip_name']));
			update_post_meta($id, 'tip_email', sanitize_text_field($_POST['tip_email']));
		}
	}
}

register_activation_hook(
	__FILE__, function () {
		add_option('flush_rewrite_rules', true);
	}
);

register_deactivation_hook(
	__FILE__, function () {
		flush_rewrite_rules(true);
		wp_cache_flush();
	}
);

$GLOBALS['omSuggest'] = $omSuggest = new omSuggestContent();