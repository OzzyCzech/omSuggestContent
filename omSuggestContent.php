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
		add_filter('manage_edit-news_columns', [$this, 'news_columns'], 10, 1);
		add_filter('manage_news_posts_custom_column', [$this, 'news_columns_content'], 10, 2);
		add_action('transition_post_status', [$this, 'transition_post_status'], 10, 3);
		add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
		add_action('wp_insert_post_data', [$this, 'wp_insert_post_data']);
		static::$captcha = sanitize_title(get_bloginfo('name'));
		add_filter('add_menu_classes', [$this, 'add_menu_classes'], 999);
	}

	public function init() {

		load_plugin_textdomain(OSC, false, dirname(plugin_basename(__FILE__)) . '/languages/');

		$this->url = apply_filters('omSuggestContent_url', 'send-news');
		add_rewrite_tag("%$this->url%", '([^&]+)');
		add_rewrite_rule("$this->url/?", 'index.php?pagename=' . $this->url, 'top');

		register_post_type(
			'news', [
				'labels' => [
					'name' => __('News', OSC),
					'name_menu' => __(
							'News', OSC
						) . '<span class="update-plugins count-50" style="background-color:white;color:black"><span class="plugin-count">50</span></span>',
					'singular_name' => __('News', OSC),
					'add_new' => __('Send news', OSC),
					'add_new_item' => __('Send new news', OSC),
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

	public function news_columns($columns) {
		return [
			'cb' => '<input type="checkbox" />',
			'news' => __('News content', OSC),
			'from' => __('From', OSC),
			'date' => __('Date', OSC),
		];
	}

	public function news_columns_content($column, $post_id) {
		$post = get_post($post_id);

		switch ($column) {
			case 'news':
				echo sprintf(
					'<h2 style="margin: 0;padding: 0;font-size: 21px"><a href="%s">%s</a></h2>', get_edit_post_link($post_id),
					$post->post_title
				);
				echo str_replace(']]>', ']]&gt;', apply_filters('the_content', $post->post_content));
				echo sprintf('<a href="%s" class="button">%s</a>', get_delete_post_link($post_id), __('Delete news', OSC));
				break;
			case 'from':
				echo get_the_author_meta('display_name', $post->post_author);
				$email = get_the_author_meta('user_email', $post->post_author);
				echo $email ? sprintf('<br/><a href="mailto:%s">%s</a>', $email, $email) : null;
				break;
		}
	}

	public function template_redirect() {
		global $wp_query;
		/** @var \WP_Query $wp_query */

		if (is_404() && is_user_logged_in() && get_query_var('pagename') === $this->url) {
			$wp_query->is_page = true;
			$wp_query->is_404 = false;
			$wp_query->is_home = false;

			add_filter(
				'wp_title', function () {
					return __('Suggest news', OSC);
				}
			);

			$action = home_url($this->url);
			include is_file(TEMPLATEPATH . '/send-news.php') ? TEMPLATEPATH . '/send-news.php' : __DIR__ . '/send-news.php';
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

	public static function saveNews() {
		if (!isset($_REQUEST['action'])) return;

		if (!isset($_REQUEST['send-news-nonce']) || !wp_verify_nonce($_REQUEST['send-news-nonce'], 'send-news-nonce')) {
			throw new \Exception(__('Unable to submit this form, please refresh and try again.', OSC));
		}

		if (!is_user_logged_in()) throw new \Exception(__('Login is required, please login first.', OSC));

		$captcha = isset($_POST[static::$antispam]) ? $_POST[static::$antispam] : null;
		$title = isset($_REQUEST['title']) ? sanitize_text_field($_REQUEST['title']) : null;
		$content = isset($_REQUEST['content']) ? $_REQUEST['content'] : null;


		if ($captcha !== static::$captcha) {
			throw new \Exception(sprintf(__('Invalid captcha value. We expect "%s".', OSC), static::$captcha));
		}

		if (!$title) throw new \Exception(_e('Title is mandatory.', OSC));
		if (!$content) throw new \Exception(_e('Content is mandatory.', OSC));

		$post = [
			'post_title' => $title,
			'post_content' => $content,
			'post_type' => 'news',
			'post_author' => get_current_user_id(),
			'post_status' => 'publish',
		];

		return wp_insert_post($post);
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
		if ($post->post_type === 'news' && $old_status !== $new_status && $new_status === 'publish') {

			$to = apply_filters('omSuggestContent_email', [get_option('admin_email')]);

			if ($to) {
				wp_mail(
					(array)$to,
					sprintf(__('%s: There is a new news', OSC), get_bloginfo('name')),
					sprintf('<h2>%s</h2><div>%s</div>', $post->post_title, $post->post_content) .
					sprintf('<hr><a href="%s">' . __('Show news', OSC) . '</a>', get_edit_post_link($post->ID))
				);
			}
		}
	}

	public function add_meta_boxes($post_id) {
		$screen = get_current_screen();

		if ($screen->id === 'news') {
			remove_meta_box('submitdiv', 'news', 'side');
			add_action(
				'edit_form_after_editor', function ($post) {
					include __DIR__ . '/omSuggestContentEditor.phtml';
				}
			);

			add_meta_box(
				'submitdivx', __('Publish'),
				function ($post) {
					$target = apply_filters('omSuggestContent_moveto', 'post');
					$move = get_post_type_object($target);
					include __DIR__ . '/omSuggestContentSubmitbox.phtml';
				}, null, 'side', 'core'
			);
		}
	}

	public function wp_insert_post_data($data) {
		if (isset($_REQUEST['moveto']) && current_user_can('publish_posts') && $data['post_type'] === 'news') {
			$data['post_type'] = apply_filters('omSuggestContent_moveto', 'post');
			$data['post_status'] = 'draft';
		}
		return $data;
	}

	public function add_menu_classes($menu) {
		$count = wp_count_posts('news', 'readable')->publish;

		foreach ($menu as $key => $data) {
			if ($data[2] === 'edit.php?post_type=news') {
				$menu[$key][0] .= " <span class='update-plugins count-$count'><span class='plugin-count'>" . number_format_i18n(
						$count
					) . '</span></span>';
			}
		}

		return $menu;
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