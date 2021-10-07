<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class Reservation_Deposits_Checkout
 */
class Reservation_Deposits_Checkout
{
	
    public $deposit_enabled;
    public $deposit_amount;
    public $second_payment;

    /**
     * WC_Deposits_Checkout constructor.
     */
    public function __construct()
    {

        add_action('wc_deposits_enqueue_deposit_button_scripts', array($this, 'enqueue_scripts'), 20);
		add_action('woocommerce_checkout_update_order_review', array($this, 'update_order_review'), 10, 1);
		add_action('woocommerce_review_order_after_order_total', array($this, 'checkout_deposit_button'), 50);

        add_action('woocommerce_checkout_update_order_meta', array($this, 'checkout_update_order_meta'), 10, 2);
        add_action('woocommerce_review_order_after_order_total', array($this, 'review_order_after_order_total'));
        // Hook the payments gateways filter to remove the ones we don't want
        add_filter('woocommerce_available_payment_gateways', array($this, 'available_payment_gateways'));

    }

    
	/**
     * @brief enqeueue scripts
     */
    public function enqueue_scripts()
    {

        wp_enqueue_script('wc-deposits-checkout', WC_DEPOSITS_PLUGIN_URL . '/assets/js/reservation-checkout.js', array('jquery'), WC_DEPOSITS_VERSION, true);
		
		$message_deposit = 'Diposite';
        $message_full_amount = 'Full Amount';

        $script_args = array(
            'message' => array(
                'deposit' => $message_deposit,
                'full' => $message_full_amount
            )
        );
        wp_localize_script('wc-deposits-checkout', 'wc_deposits_checkout_options', $script_args);
    }
	
	
	/**
     *
     * @param $posted_data_string
     */
    public function update_order_review($posted_data_string)
    {

        parse_str($posted_data_string, $posted_data); 
        if (!is_array(WC()->cart->deposit_info)) WC()->cart->deposit_info = array();
        if (isset($posted_data['deposit-radio']) && $posted_data['deposit-radio'] === 'deposit') {
            WC()->cart->deposit_info['deposit_enabled'] = true;
            WC()->session->set('deposit_enabled', true);
        } elseif (isset($posted_data['deposit-radio']) && $posted_data['deposit-radio'] === 'full') {
            WC()->cart->deposit_info['deposit_enabled'] = false;
            WC()->session->set('deposit_enabled', false);
        } else {
            $default = get_option('wc_deposits_default_option');
            WC()->cart->deposit_info['deposit_enabled'] = $default === 'deposit' ? true : false;
            WC()->session->set('deposit_enabled', $default === 'deposit' ? true : false);
        }

    }
	
	
	/**
     * @brief shows Deposit slider in checkout mode
     */
    public function checkout_deposit_button()
    {

        //user restriction
        if (!is_user_logged_in()) {
            return;
        }

        $force_deposit = get_option('wc_deposits_checkout_mode_force_deposit');
        $deposit_amount = get_option('wc_deposits_checkout_mode_deposit_amount'); 
        $amount_type = get_option('wc_deposits_checkout_mode_deposit_amount_type');

        if ($amount_type === 'fixed' && $deposit_amount >= WC()->cart->total) {
            return;
        }

        $default_checked = get_option('wc_deposits_default_option', 'deposit');
        $basic_buttons = get_option('wc_deposits_use_basic_radio_buttons', true) === 'yes';
        $post_data = array();

		$deposit_text = esc_html__('Pay Deposit', 'reservation-deposits');
		$full_text = esc_html__('Full Amount', 'reservation-deposits');
		$deposit_option_text = esc_html__('Deposit Option', 'reservation-deposits');
        $selected_plan = '';
        $payment_plans = array();

        if (is_ajax() && isset($_POST['post_data'])) {
            parse_str($_POST['post_data'], $post_data);
            if (isset($post_data['deposit-radio'])) {
                $default_checked = $post_data['deposit-radio'];
            }

        }
		//WC()->cart->deposit_info['deposit_amount']    WC()->cart->deposit_info['deposit_amount']
		$has_payment_plans = $payment_plans = $selected_plan = "";
		$amount = $deposit_amount;
		if($amount_type == 'percentage'){
			$deposit_amount = WC()->cart->get_subtotal() / 100 * $deposit_amount;
		}
		$deposit_amount = ceil( $deposit_amount );
		
        $args = array(
            'force_deposit' => $force_deposit,
            'deposit_amount' => $amount,
            'basic_buttons' => $basic_buttons,
            'deposit_text' => $deposit_text,
            'full_text' => $full_text,
            'deposit_option_text' => $deposit_option_text,
            'default_checked' => $default_checked,
            'has_payment_plan' => $has_payment_plans,
            'payment_plans' => $payment_plans,
            'selected_plan' => $selected_plan,
        );
        wc_get_template('reservation-deposits-checkout-mode.php', $args, '', WC_DEPOSITS_TEMPLATE_PATH);

    }
	
	
	
	/**
     * @brief Updates the order metadata with deposit information
     *
     * @return void
     */
    public function checkout_update_order_meta($order_id)
    {

        $order = wc_get_order($order_id);


        if ($order->get_type() === 'reservation_payment') {
            return;
        }
		
		if (!isset(WC()->cart->deposit_info['deposit_enabled'])){
			
			if (isset($_POST['deposit-radio']) && $_POST['deposit-radio'] === 'deposit') {
				
				$deposit_amount = get_option('wc_deposits_checkout_mode_deposit_amount'); 
        		$amount_type = get_option('wc_deposits_checkout_mode_deposit_amount_type');
				if($amount_type == 'percentage'){
					$deposit_amount = WC()->cart->get_subtotal() / 100 * $deposit_amount;
				}
				
				$deposit_amount = ceil( $deposit_amount );
				$remaining_amount = (WC()->cart->get_subtotal()) - $deposit_amount;
				
				
				$deposit_data = array(
					'id' => '',
					'title' => esc_html__('Deposit', 'reservation-deposits'),
					'type' => 'deposit',
					'total' => $deposit_amount,
				);
				
				$unlimited = array(
					'id' => '',
					'title' => esc_html__('Future Payment', 'reservation-deposits'),
					'type' => 'second_payment',
					'total' => $remaining_amount
				);
				$sorted_schedule = array('deposit' => $deposit_data) + array('unlimited' => $unlimited);
				
				$deposit_breakdown = "";
				
				//echo $deposit_amount.'-----------'.$remaining_amount;
				$order->add_meta_data('_wc_deposits_payment_schedule', $sorted_schedule, true);
				$order->add_meta_data('_wc_deposits_order_version', WC_DEPOSITS_VERSION, true);
				$order->add_meta_data('_wc_deposits_order_has_deposit', 'yes', true);
				$order->add_meta_data('_wc_deposits_deposit_paid', 'no', true);
				$order->add_meta_data('_wc_deposits_second_payment_paid', 'no', true);
				$order->add_meta_data('_wc_deposits_deposit_amount', $deposit_amount, true);
				$order->add_meta_data('_wc_deposits_second_payment', $remaining_amount, true);
				$order->add_meta_data('_wc_deposits_deposit_breakdown', $deposit_breakdown, true);
				$order->add_meta_data('_wc_deposits_deposit_payment_time', ' ', true);
				$order->add_meta_data('_wc_deposits_second_payment_reminder_email_sent', 'no', true);
				$order->save();
				
			}

		} else {
            $order_has_deposit = $order->get_meta('_wc_deposits_order_has_deposit', true);

            if ($order_has_deposit === 'yes') {

                $order->delete_meta_data('_wc_deposits_payment_schedule');
                $order->delete_meta_data('_wc_deposits_order_version');
                $order->delete_meta_data('_wc_deposits_order_has_deposit');
                $order->delete_meta_data('_wc_deposits_deposit_paid');
                $order->delete_meta_data('_wc_deposits_second_payment_paid');
                $order->delete_meta_data('_wc_deposits_deposit_amount');
                $order->delete_meta_data('_wc_deposits_second_payment');
                $order->delete_meta_data('_wc_deposits_deposit_breakdown');
                $order->delete_meta_data('_wc_deposits_deposit_payment_time');
                $order->delete_meta_data('_wc_deposits_second_payment_reminder_email_sent');

                // remove deposit meta from items
                foreach ($order->get_items() as $order_item) {
                    $order_item->delete_meta_data('wc_deposit_meta');
                    $order_item->save();
                }
                $order->save();

            }
        }
		
		
    }
	
	
	/**
     * @brief Display deposit value in checkout order totals review area
     */
    public function review_order_after_order_total()
    {
        if (!is_ajax()) return;
        if (wcdp_checkout_mode()) {

            $deposit_amount = get_option('wc_deposits_checkout_mode_deposit_amount');
            $amount_type = get_option('wc_deposits_checkout_mode_deposit_amount_type');

            if ($amount_type === 'fixed' && $deposit_amount >= WC()->cart->total) {
                WC()->cart->deposit_info['deposit_enabled'] = false;
            }

            $default_checked = get_option('wc_deposits_default_option', 'deposit');

            if ($default_checked === 'deposit' || (is_ajax() && isset($_POST['post_data']))) { 

                $display_rows = true;
                if ((is_ajax() && isset($_POST['post_data']))) {
                    parse_str($_POST['post_data'], $post_data);
                    $display_rows = isset($post_data['deposit-radio']) && $post_data['deposit-radio'] === 'deposit';
                }
				
				//echo WC()->cart->deposit_info['deposit_enabled'].'-----'.WC()->cart->deposit_info['deposit_amount']; die('kkkkkkkkkkkk');
                if ($display_rows && isset(WC()->cart->deposit_info['deposit_enabled']) && WC()->cart->deposit_info['deposit_enabled'] === true) {


                    $to_pay_text = esc_html__(get_option('wc_deposits_to_pay_text'), 'reservation-deposits');
                    $future_payment_text = esc_html__(get_option('wc_deposits_second_payment_text'), 'reservation-deposits');


                    if ($to_pay_text === false) {
                        $to_pay_text = esc_html__('To Pay', 'reservation-deposits');
                    }


                    if ($future_payment_text === false) {
                        $future_payment_text = esc_html__('Future Payments', 'reservation-deposits');
                    }
                    $to_pay_text = stripslashes($to_pay_text);
                    $future_payment_text = stripslashes($future_payment_text);
					
					
					if($amount_type == 'percentage'){
						$deposit_amount = WC()->cart->get_subtotal() / 100 * $deposit_amount;
					}
					$deposit_amount = ceil($deposit_amount);
                    ?>


                    <tr class="order-paid">
                        <th><?php echo $to_pay_text; ?></th>
                        <td data-title="<?php echo $to_pay_text; ?>">
                            <strong><?php echo wc_price($deposit_amount); ?></strong>
                        </td>
                    </tr>
                    <tr class="order-remaining">
                        <th><?php echo $future_payment_text; ?></th>
                        <td data-title="<?php echo $future_payment_text; ?>">
                            <strong><?php echo wc_price(WC()->cart->get_total('edit') - $deposit_amount); ?></strong>
                        </td>
                    </tr>
                    <?php


                }
            }

        }

    }
	
	/**
     * @brief Removes the unwanted gateways from the settings page when there's a deposit
     *
     * @return mixed
     */
    public function available_payment_gateways($gateways)
    {
        $has_deposit = false;


		if (wcdp_checkout_mode() && is_ajax() && isset($_POST['post_data'])) {
			parse_str($_POST['post_data'], $post_data);

			if (isset($post_data['deposit-radio']) && $post_data['deposit-radio'] === 'deposit') {
				$has_deposit = true;
				
				$disallowed_gateways = get_option('wc_deposits_disallowed_gateways_for_deposit');
				
				if (is_array($disallowed_gateways)) {
					foreach ($disallowed_gateways as $value) {
						unset($gateways[$value]);
					}
				}
			}

		}


        return $gateways;
    }
	
}
