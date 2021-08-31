<?php
/**
 * Plugin Name:          Reservation Deposits
 * Plugin URI:           https://www.cmsminds.com/
 * Description:          Adds deposits for our reservation system.
 * Version:              1.0.0
 * Author:               Lopa
 * Author URI:           https://github.com/Lopa1104
 * Text Domain:          reservation-deposits
 * Domain Path:          /locale
 * Requires at least:    5.3
 * WC requires at least: 3.7.0
 * WC tested up to:      5.5.2
 *
 * Copyright:            © 2021, cmsminds.
 * License:              GNU General Public License v3.0
 * License URI:          http://www.gnu.org/licenses/gpl-3.0.html
 */
 
 
 
 
require_once('includes/reservation-deposits-functions.php'); 
class WC_Deposits
{

	// Components
	public $cart;
	public $coupons;
	public $add_to_cart;
	public $orders;
	public $taxonomies;
	public $reminders;
	public $emails;
	public $checkout;
	public $compatibility;
	public $gateways;
	public $admin_product;
	public $admin_order;
	public $admin_list_table_orders;
	public $admin_list_table_partial_payments;
	public $admin_settings;
	public $admin_reports;
	public $admin_notices = array();
	public $admin_auto_updates;
	public $wc_version_disabled = false;

	
	public static function &get_depositeplugin()
	{
		if (!isset($GLOBALS['wc_deposits']))
			$GLOBALS['wc_deposits'] = new WC_Deposits();


		return $GLOBALS['wc_deposits'];
	}
	
	/**
	 * @brief Constructor
	 *
	 * @return void
	 */
	private function __construct()
	{
		define('WC_DEPOSITS_VERSION', '1.0.0');
		define('WC_DEPOSITS_TEMPLATE_PATH', untrailingslashit(plugin_dir_path(__FILE__)) . '/templates/');
		define('WC_DEPOSITS_PLUGIN_PATH', plugin_dir_path(__FILE__));
		define('WC_DEPOSITS_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
		define('WC_DEPOSITS_MAIN_FILE', __FILE__);
		define('WC_DEPOSITS_PAYMENT_PLAN_TAXONOMY', 'reservation_payment_plan');

		$this->compatibility = new stdClass();

		if (version_compare(PHP_VERSION, '5.6.0', '<')) {


			if (is_admin()) {
				add_action('admin_notices', array($this, 'show_admin_notices'));
				$this->enqueue_admin_notice(sprintf(esc_html__('%s Requires PHP version %s or higher.'), esc_html__('Reservation Deposits', 'reservation-deposits'), '5.6'), 'error');
			}

			return;

		}

		add_action('init', array($this, 'check_version_disable'), 0);
		
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts_and_styles'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
		add_action('woocommerce_init', array($this, 'admin_includes'));
		add_action('woocommerce_init', array($this, 'includes'));
		add_action('init', array($this, 'register_desites_payment_post_type'), 4);

		if (is_admin()) {

			//plugin row urls in plugins page
			add_action('admin_notices', array($this, 'show_admin_notices'));

		}

	}
	
	
	function check_version_disable()
	{
		if (function_exists('WC') && version_compare(WC()->version, '3.7.0', '<')) {

			$this->wc_version_disabled = true;

			if (is_admin()) {
				add_action('admin_notices', array($this, 'show_admin_notices'));
				$this->enqueue_admin_notice(sprintf(esc_html__('%s Requires WooCommerce version %s or higher.'), esc_html__('Reservation Deposits', 'reservation-deposits'), '3.7.0'), 'error');
			}
		}

	}
	
	
	function register_desites_payment_post_type() {

		if ($this->wc_version_disabled) return;
		wc_register_order_type(
			'reservation_payment',

			array(
				// register_post_type() params
				'labels' => array(
					'name' => esc_html__('Partial Payments', 'woocommerce-deposits'),
					'singular_name' => esc_html__('Partial Payment', 'woocommerce-deposits'),
					'edit_item' => esc_html_x('Edit Partial Payment', 'custom post type setting', 'woocommerce-deposits'),
					'search_items' => esc_html__('Search Partial Payments', 'woocommerce-deposits'),
					'parent' => esc_html_x('Order', 'custom post type setting', 'woocommerce-deposits'),
					'menu_name' => esc_html__('Partial Payments', 'woocommerce-deposits'),
				),
				'public' => false,
				'show_ui' => true,
				'capability_type' => 'shop_order',
				'capabilities' => array(
					'create_posts' => 'do_not_allow',
				),
				'map_meta_cap' => true,
				'publicly_queryable' => false,
				'exclude_from_search' => true,
				'show_in_menu' =>  'woocommerce',
				'hierarchical' => false,
				'show_in_nav_menus' => false,
				'rewrite' => false,
				'query_var' => false,
				'supports' => array('title', 'custom-fields'),
				'has_archive' => false,

				// wc_register_order_type() params
				'exclude_from_orders_screen' => true,
				'add_order_meta_boxes' => true,
				'exclude_from_order_count' => true,
				'exclude_from_order_views' => true,
				'exclude_from_order_webhooks' => true,
				'exclude_from_order_reports' => true,
				'exclude_from_order_sales_reports' => true,
				'class_name' => 'RD_Payment',
			)

		);

	}
	
	/**
	 * @brief Enqueues front-end styles
	 *
	 * @return void
	 */
	public function enqueue_styles()
	{
		if ($this->wc_version_disabled) return;
		if (!$this->is_disabled()) {
			wp_enqueue_style('reservation-deposits-frontend-styles', plugins_url('assets/css/style.css', __FILE__), array(), WC_DEPOSITS_VERSION);
		}
	}
	
	/**
	 * @brief Load admin scripts and styles
	 * @return void
	 */
	public function enqueue_admin_scripts_and_styles()
	{
		wp_enqueue_script('jquery');
		wp_enqueue_style('wc-deposits-admin-style', plugins_url('assets/css/admin-style.css', __FILE__), WC_DEPOSITS_VERSION);
	}

	/**
	 * @brief Display all buffered admin notices
	 *
	 * @return void
	 */
	public function show_admin_notices()
	{
		foreach ($this->admin_notices as $notice) {
			?>
			<div class='notice notice-<?php echo esc_attr($notice['type']); ?>'>
				<p><?php echo $notice['content']; ?></p></div>
			<?php
		}
	}

	/**
	 * @brief Add a new notice
	 *
	 * @param $content String notice contents
	 * @param $type String Notice class
	 *
	 * @return void
	 */
	public function enqueue_admin_notice($content, $type)
	{
		array_push($this->admin_notices, array('content' => $content, 'type' => $type));
	}

	/**
	 * @return bool
	 */
	public function is_disabled()
	{
		return get_option('wc_deposits_site_wide_disable') === 'yes';
	}
	/**
	 * @brief Load admin includes
	 *
	 * @return void
	 */
	public function admin_includes()
	{
		if ($this->wc_version_disabled) return;

		include('includes/admin/class-reservation-deposits-admin-settings.php');
		$this->admin_settings = new Reservation_Deposits_Admin_Settings($this);
	}
	
	
	/**
	 * @brief Load classes
	 *
	 * @return void
	 */
	public function includes()
	{

		if ($this->wc_version_disabled) return;
		if (!$this->is_disabled()) {

			include('includes/class-reservation-deposits-checkout.php');

			$this->checkout = new Reservation_Deposits_Checkout();
		}
		
		require('includes/class-reservation-payment.php');
		require('includes/class-reservation-deposits-orders.php');
		$this->orders = new Reservation_Deposits_Orders($this);
	}
	
	public static function plugin_activated()
	{

		update_option('wc_deposits_instance', time() + (86400 * 7));

		/*if (!wp_next_scheduled('woocommerce_deposits_second_payment_reminder')) {
			wp_schedule_event(time(), 'daily', 'woocommerce_deposits_second_payment_reminder');
		}*/
	}

	public static function plugin_deactivated()
	{
		//wp_clear_scheduled_hook('woocommerce_deposits_second_payment_reminder');

	}
}

WC_Deposits::get_depositeplugin();
	