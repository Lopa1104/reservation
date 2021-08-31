<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * @brief Adds a new panel to the WooCommerce Settings
 *
 */
class Reservation_Deposits_Admin_Settings
{

    public function __construct()
    {


        $allowed_html = array(
            'a' => array('href' => array(), 'title' => array()),
            'br' => array(), 'em' => array(),
            'strong' => array(), 'p' => array(),
            's' => array(), 'strike' => array(),
            'del' => array(), 'u' => array()  , 'b' => array()
        );

		
        // Hook the settings page
        add_filter('woocommerce_settings_tabs_array', array($this, 'settings_tabs_array'), 21);
        add_action('woocommerce_settings_wc-deposits', array($this, 'settings_tabs_wc_deposits'));
        add_action('woocommerce_update_options_wc-deposits', array($this, 'update_options_wc_deposits'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_settings_script'));
    }


	public function settings_tabs_array($tabs)
    {

        $tabs['wc-deposits'] = esc_html__('Deposits', 'reservation-deposits');
        return $tabs;
    }
	
	public function settings_tabs_wc_deposits()
    {
		?>
        <div id="checkout_mode">
            <?php
			
			$gateways_array = array();
            $gateways = WC()->payment_gateways()->payment_gateways();
            if (isset($gateways['wc-booking-gateway'])) unset($gateways['wc-booking-gateway']);// Protect the wc-booking-gateway

            foreach ($gateways as $key => $gateway) {

                $gateways_array[$key] = $gateway->title;
            }

            $cart_checkout_settings = array(

                'checkout_mode_title' => array(
                    'name' => esc_html__('Deposit on Checkout Mode', 'reservation-deposits'),
                    'type' => 'title',
                    'desc' => esc_html__('changes the way deposits work to be based on total amount at checkout button', 'reservation-deposits'),
                    'id' => 'wc_deposits_messages_title'
                ),
                'enable_checkout_mode' => array(
                    'name' => esc_html__('Enable checkout mode', 'reservation-deposits'),
                    'type' => 'checkbox',
                    'desc' => esc_html__('Check this to enable checkout mode, which makes deposits calculate based on total amount during checkout instead of per product', 'reservation-deposits'),
                    'id' => 'wc_deposits_checkout_mode_enabled',
                ),
                'checkout_mode_force_deposit' => array(
                    'name' => esc_html__('Force deposit', 'reservation-deposits'),
                    'type' => 'checkbox',
                    'desc' => esc_html__('If you check this, the customer will not be allowed to make a full payment during checkout', 'reservation-deposits'),
                    'id' => 'wc_deposits_checkout_mode_force_deposit',
                ),
                'checkout_mode_amount_type' => array(
                    'name' => esc_html__('Amount Type', 'reservation-deposits'),
                    'type' => 'select',
                    'desc' => esc_html__('Choose amount type', 'reservation-deposits'),
                    'id' => 'wc_deposits_checkout_mode_deposit_amount_type',
                    'options' => array(
                        'fixed' => esc_html__('Fixed', 'reservation-deposits'),
                        'percentage' => esc_html__('Percentage', 'reservation-deposits'),
                        'payment_plan' => esc_html__('Payment plan', 'reservation-deposits')
                    ),
                    'default' => 'percentage'
                ),
                'checkout_mode_amount_deposit_amount' => array(
                    'name' => esc_html__('Deposit Amount', 'reservation-deposits'),
                    'type' => 'number',
                    'desc' => esc_html__('Amount of deposit ( should not be more than 99 for percentage or more than order total for fixed', 'reservation-deposits'),
                    'id' => 'wc_deposits_checkout_mode_deposit_amount',
                    'default' => '50',
                    'custom_attributes' => array(
                        'min' => '0.0',
                        'step' => '0.01'
                    )
                ),
				'wc_deposits_disallowed_gateways_for_deposit' => array(
					'name' => esc_html__('Disallowed For Deposits', 'woocommerce-deposits'),
					'type' => 'multiselect',
					'class' => 'chosen_select',
					'options' => $gateways_array,
					'desc' => esc_html__('Disallowed For Deposits', 'woocommerce-deposits'),
					'id' => 'wc_deposits_disallowed_gateways_for_deposit',
				),
				'wc_deposits_disallowed_gateways_for_second_payment' => array(
					'name' => esc_html__('Disallowed For Partial Payments', 'woocommerce-deposits'),
					'type' => 'multiselect',
					'class' => 'chosen_select',
					'options' => $gateways_array,
					'desc' => esc_html__('Disallowed For Partial Payments', 'woocommerce-deposits'),
					'id' => 'wc_deposits_disallowed_gateways_for_second_payment',
				),

            );


            //payment plans
            $payment_plans = get_terms(array(
                    'taxonomy' => WC_DEPOSITS_PAYMENT_PLAN_TAXONOMY,
                    'hide_empty' => false
                )
            );

            $cart_checkout_settings['checkout_mode_end'] = array(
                'type' => 'sectionend',
                'id' => 'wc_deposits_checkout_mode_end'
            );


            woocommerce_admin_fields($cart_checkout_settings);

            ?>
            <?php do_action('wc_deposits_settings_tabs_checkout_mode_tab'); ?>

        </div>
        <?php
    }
	
	
	/**
     * @brief Save all settings on POST
     *
     * @return void
     */
    public function update_options_wc_deposits()
    {
        $allowed_html = array(
            'a' => array('href' => true, 'title' => true),
            'br' => array(), 'em' => array(),
            'strong' => array(), 'p' => array(),
            's' => array(), 'strike' => array(),
            'del' => array(), 'u' => array()
        );

        $settings = array();

        $settings['wc_deposits_checkout_mode_enabled'] = isset($_POST['wc_deposits_checkout_mode_enabled']) ? 'yes' : 'no';
        $settings['wc_deposits_checkout_mode_force_deposit'] = isset($_POST['wc_deposits_checkout_mode_force_deposit']) ? 'yes' : 'no';
        $settings['wc_deposits_checkout_mode_deposit_amount'] = isset($_POST['wc_deposits_checkout_mode_deposit_amount']) ? $_POST['wc_deposits_checkout_mode_deposit_amount'] : '0';
        $settings['wc_deposits_checkout_mode_deposit_amount_type'] = isset($_POST['wc_deposits_checkout_mode_deposit_amount_type']) ? $_POST['wc_deposits_checkout_mode_deposit_amount_type'] : 'percentage';
		
		//gateway options
		$settings ['wc_deposits_disallowed_gateways_for_deposit'] = isset($_POST['wc_deposits_disallowed_gateways_for_deposit']) ? $_POST['wc_deposits_disallowed_gateways_for_deposit'] : array();
		$settings ['wc_deposits_disallowed_gateways_for_second_payment'] = isset($_POST['wc_deposits_disallowed_gateways_for_second_payment']) ? $_POST['wc_deposits_disallowed_gateways_for_second_payment'] : array();

        foreach ($settings as $key => $setting) {
            update_option($key, $setting);

        }
    }
	
	
	public function enqueue_settings_script()
    {

        if (function_exists('get_current_screen')) {

            if (isset($_GET['page']) && $_GET['page'] === 'wc-settings' && isset($_GET['tab']) && $_GET['tab'] === 'wc-deposits') {

                wp_enqueue_script('jquery-ui-datepicker');

                wp_enqueue_script('wc-deposits-admin-settings', WC_DEPOSITS_PLUGIN_URL . '/assets/js/admin-settings.js', array('jquery', 'wp-color-picker'),WC_DEPOSITS_VERSION);
                wp_localize_script('wc-deposits-admin-settings', 'wc_deposits', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'strings' => array(
                        'success' => esc_html__('Updated successfully', 'reservation-deposits')
                    )

                ));
            }

        }

    }
	
	
}