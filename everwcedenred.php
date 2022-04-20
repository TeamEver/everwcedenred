<?php
/* @wordpress-plugin
 * Plugin Name:       WooCommerce Edenred payment
 * Plugin URI:        http://www.team-ever.com
 * Description:       Accept Edenred payment in Woocommerce
 * Version:           3.6.8
 * Author:            Cyril CHALAMON - Team Ever
 * Author URI:        https://www.team-ever.com
 * Text Domain:       everwcedenred
 * Domain Path: /languages
 * License:           Tous droits réservés / Le droit d'auteur s'applique (All rights reserved / French copyright law applies)
 * Copyright:       Cyril CHALAMON - Team Ever
 * Author:       Cyril CHALAMON - Team Ever
 */
$active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
if(in_array('woocommerce/woocommerce.php', $active_plugins)){
  add_filter('woocommerce_payment_gateways', 'add_edenred_payment_gateway');
  function add_edenred_payment_gateway( $gateways ){
    $gateways[] = 'WC_Edenred_Payment_Gateway';
    return $gateways;
  }
  add_action('plugins_loaded', 'init_edenred_payment_gateway');
  function init_edenred_payment_gateway(){
    require 'class-everwcedenred.php';
  }
  add_action( 'plugins_loaded', 'edenred_payment_load_plugin_textdomain' );
  function edenred_payment_load_plugin_textdomain() {
    load_plugin_textdomain( 'everwcedenred', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
  }
  /**
   * Get and set edenred token to session using AUTH
  */
  function get_edenred_code() {
    WC()->session->__unset( 'edenred_access_error' );
    WC()->session->__unset( 'edenred_id_token' );
    WC()->session->__unset( 'edenred_access_token' );
    if (isset($_GET['access_token']) && !empty($_GET['access_token'])) {
        WC()->session->set( 'edenred_id_token', $_GET['id_token'] );
        WC()->session->set( 'edenred_access_token', $_GET['access_token'] );
    } else {
        WC()->session->set( 'edenred_access_token', false );
        WC()->session->set( 'edenred_access_error', 'token not valid' );
    }
  }
  add_action('wp_head', 'get_edenred_code');
  /**
   * Update payment method, set Edenred if token is received
  */
  function check_edenred_method($available_gateways) {
    if ( is_admin() || ! is_checkout()) {
        return;
    }
    $access_token = WC()->session->get( 'edenred_access_token' );
    if (isset($access_token) && !empty($access_token)) {
      $available_gateways['edenred']->chosen = true;
      return $available_gateways;
    }
    return $available_gateways;
  }
  add_filter('woocommerce_available_payment_gateways', 'check_edenred_method');
  /**
   * Reduce cart total on Edenred partial payment
   * @param $total
   * @return recalculated total
  */
  function edenred_reduce_total( $total, $cart ) {
      if ( is_admin() ) {
          return;
      }
      if (function_exists('is_plugin_active')) {
        $stuart = is_plugin_active('stuart/stuart.php');
      } else {
        $stuart = false;
      }
      if ( is_checkout()
        && !is_user_logged_in()
        && (bool)$stuart === false
      ) {
        //billing details
        $billing_first_name = $_POST['billing_first_name'];
        $billing_last_name = $_POST['billing_last_name'];
        $billing_company = $_POST['billing_company'];
        $billing_address_1 = $_POST['billing_address_1'];
        $billing_address_2 = $_POST['billing_address_2'];
        $billing_city = $_POST['billing_city'];
        $billing_state = $_POST['billing_state'];
        $billing_postcode = $_POST['billing_postcode'];
        $billing_country = $_POST['billing_country'];

        WC()->customer->set_billing_first_name(wc_clean( $billing_first_name )); 
        WC()->customer->set_billing_last_name(wc_clean( $billing_last_name )); 
        WC()->customer->set_billing_company(wc_clean( $billing_company ));  
        WC()->customer->set_billing_address_1(wc_clean( $billing_address_1 )); 
        WC()->customer->set_billing_address_2(wc_clean( $billing_address_2 )); 
        WC()->customer->set_billing_city(wc_clean( $billing_city )); 
        WC()->customer->set_billing_state(wc_clean( $billing_state )); 
        WC()->customer->set_billing_postcode(wc_clean( $billing_postcode )); 
        WC()->customer->set_billing_country(wc_clean( $billing_country )); 

        //shipping details
        $shipping_first_name = $_POST['shipping_first_name'];
        $shipping_last_name = $_POST['shipping_last_name'];
        $shipping_company = $_POST['shipping_company'];
        $shipping_address_1 = $_POST['shipping_address_1'];
        $shipping_address_2 = $_POST['shipping_address_2'];
        $shipping_city = $_POST['shipping_city'];
        $shipping_state = $_POST['shipping_state'];
        $shipping_postcode = $_POST['shipping_postcode'];
        $shipping_country = $_POST['shipping_country'];

        WC()->customer->set_shipping_first_name(wc_clean( $shipping_first_name )); 
        WC()->customer->set_shipping_last_name(wc_clean( $shipping_last_name )); 
        WC()->customer->set_shipping_company(wc_clean( $shipping_company ));    
        WC()->customer->set_shipping_address_1(wc_clean( $shipping_address_1 )); 
        WC()->customer->set_shipping_address_2(wc_clean( $shipping_address_2 )); 
        WC()->customer->set_shipping_city(wc_clean( $shipping_city )); 
        WC()->customer->set_shipping_state(wc_clean( $shipping_state )); 
        WC()->customer->set_shipping_postcode(wc_clean( $shipping_postcode )); 
        WC()->customer->set_shipping_country(wc_clean( $shipping_country ));

        $shipping_method = $_POST['shipping_method'];
        WC()->session->set('chosen_shipping_methods', wc_clean( $shipping_method ) );
      }

      $edenred_paid = WC()->session->get( 'edenred_captured_amount' );
      if (isset($edenred_paid) && (float)$edenred_paid > 0) {
        return $total - $edenred_paid;
      }
      return $total;
  }
  add_filter( 'woocommerce_calculated_total', 'edenred_reduce_total', 10, 2 );
  /**
   * Recalculate order total if Edenred partial payment is detected
   * @param $order object
  */
  function everwcedenred_recalculate_order_total( $order ) {
      if ( is_admin() ) {
          return;
      }
      // Do not recalculate order on edenred payment fully paid
      if ($order->get_payment_method_title() == 'everwcedenred'
        || $order->get_payment_method_title() == 'Edenred payment'
      ) {
        return;
      }
      // Do not reduce total if edenred partial payment is not detected
      $edenred_paid_amount = WC()->session->get( 'edenred_captured_amount' );
      if (!isset($edenred_paid_amount) || !$edenred_paid_amount) {
        return;
      }
      $capture_id = WC()->session->get( 'edenred_capture_id' );
      $authorization_id = WC()->session->get( 'edenred_authorization_id' );
      $edenred_mid = WC()->session->get( 'edenred_mid' );
      $edenred_access_token = WC()->session->get( 'edenred_access_token' );
      // Get order total
      $total = $order->get_total();

      ## -- Make your checking and calculations -- ##
      $new_total = $total + $edenred_paid_amount; // <== Edenred paid

      // Set the new calculated total
      $order->set_total( $new_total );
      // Update payment method
      $order->set_payment_method( 'Edenred & ' . $order->get_payment_method() );
      $order->set_payment_method_title( 'Edenred & ' . $order->get_payment_method_title() );
      $order->set_customer_note(
         __( 'Your Edenred account has been charged with : ', 'everwcedenred' ).wc_price( $edenred_paid_amount, array( 'currency' => $order->get_currency() ) )
      );
      
      // some notes to customer (replace false with true to make it public)
      $order->add_order_note( 'Payment received using Edenred.', false );
      // Save order meta data
      $order->update_meta_data( '_is_edenred', 1 );
      $order->update_meta_data( '_edenred_paid_amount', sanitize_text_field($edenred_paid_amount) );
      $order->update_meta_data( '_edenred_mid', sanitize_text_field($edenred_mid) );
      $order->update_meta_data( '_edenred_capture_id', sanitize_text_field($capture_id) );
      $order->update_meta_data( '_edenred_authorization_id', sanitize_text_field($authorization_id) );
      $order->update_meta_data( '_edenred_access_token', sanitize_text_field($edenred_access_token) );
      $order->save();

      WC()->session->__unset( 'edenred_paid_amount' );
      WC()->session->__unset( 'edenred_authorized_amount' );
      WC()->session->__unset( 'edenred_captured_amount' );
      WC()->session->__unset( 'edenred_left_to_pay' );
      WC()->session->__unset( 'edenred_mid' );
      WC()->session->__unset( 'edenred_capture_id' );
      WC()->session->__unset( 'edenred_authorization_id' );
      WC()->session->__unset( 'edenred_access_error' );
      WC()->session->__unset( 'edenred_id_token' );
      WC()->session->__unset( 'edenred_access_token' );
  }
  // add_action( 'woocommerce_checkout_create_order', 'everwcedenred_recalculate_order_total', 20, 1 );
  add_action( 'woocommerce_checkout_order_created', 'everwcedenred_recalculate_order_total', 20, 1 );
  /**
   * Show Edenred total to customer if there is a partial payment
   * @param int $order_id
   * @return unset edenred session data
   */
  function everwcedenred_thank_you( $order_id ) {
      if ( is_admin() ) {
          return;
      }
      // First save paid amount as order meta
      $order = new WC_Order( $order_id );
      $edenred_paid_amount = WC()->session->get( 'edenred_captured_amount' );
      if (isset($edenred_paid_amount) || !$edenred_paid_amount) {
        $order->update_meta_data( '_edenred_paid_amount', sanitize_text_field($edenred_paid_amount) );
        $edenred_paid = $order->get_meta('_edenred_paid_amount', true);
        $order->save();
      }
      // Then check if order meta exists and is allowed
      $order_paid_amount = $order->get_meta('_edenred_paid_amount', true);
      if (isset($order_paid_amount) || !$order_paid_amount || empty($order_paid_amount)) {
        return;
      }
      // Clean session data
      WC()->session->__unset( 'edenred_paid_amount' );
      WC()->session->__unset( 'edenred_authorized_amount' );
      WC()->session->__unset( 'edenred_captured_amount' );
      WC()->session->__unset( 'edenred_left_to_pay' );
      WC()->session->__unset( 'edenred_mid' );
      WC()->session->__unset( 'edenred_capture_id' );
      WC()->session->__unset( 'edenred_authorization_id' );
      WC()->session->__unset( 'edenred_access_error' );
      WC()->session->__unset( 'edenred_id_token' );
      WC()->session->__unset( 'edenred_access_token' );
  }
  add_action( 'woocommerce_before_thankyou', 'everwcedenred_thank_you', 10, 1 );
  /**
   * Show Edenred subtotal on admin order page if there is a partial payment
   * @param int $order_id
   * @return new subtotal line
   */
  function everwcedenred_admin_order_subtotal( $order_id ) {
    $order = wc_get_order( $order_id );
    $_edenred_capture_id = $order->get_meta('_edenred_capture_id', true);
    $edenred_paid_amount = $order->get_meta('_edenred_paid_amount', true);
    if (!isset($edenred_paid_amount) || !$edenred_paid_amount) {
      return;
    }
    ?>
        <tr>
          <td class="label"><?php esc_html_e( 'Including Edenred payment:', 'everwcedenred' ); ?></td>
          <td width="1%"></td>
          <td class="total">
            <?php echo wc_price( $edenred_paid_amount, array( 'currency' => $order->get_currency() ) ); ?></td>
        </tr>
    <?php
  }
  add_action( 'woocommerce_admin_order_totals_after_shipping', 'everwcedenred_admin_order_subtotal' );
  /**
   * Admin order meta box
   * Show Edenred transaction ID
  */
  // Add meta box
  add_action( 'add_meta_boxes', 'edenred_infos_box' );
  function edenred_infos_box() {
    add_meta_box(
        'edenred-infos-modal',
        'Edenred payment authorization_id',
        'edenred_infos_box_callback',
        'shop_order',
        'side',
        'core'
    );
  }
  // Callback
  function edenred_infos_box_callback( $post ) {
    $value = get_post_meta( $post->ID, '_edenred_authorization_id', true );
    $text = ! empty( $value ) ? esc_attr( $value ) : '';
    echo '<input type="text" name="edenred_authorization_id" id="edenred_infos_box" value="' . $text . '" />';
    echo '<input type="hidden" name="edenred_authorization_id_nonce" value="' . wp_create_nonce() . '">';
  }
  // Saving
  add_action( 'save_post', 'edenred_infos_save_meta_box_data' );
  function edenred_infos_save_meta_box_data( $post_id ) {
    // Only for shop order
    if ( 'shop_order' != $_POST[ 'post_type' ] ) {
        return $post_id;
    }
    // Check if our nonce is set (and our cutom field)
    if ( ! isset( $_POST[ 'edenred_authorization_id_nonce' ] ) && isset( $_POST['edenred_authorization_id'] ) ) {
        return $post_id;
    }
    $nonce = $_POST[ 'edenred_authorization_id_nonce' ];
    // Verify that the nonce is valid.
    if ( ! wp_verify_nonce( $nonce ) ) {
        return $post_id;
    }
    // Checking that is not an autosave
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return $post_id;
    }
    // Check the user’s permissions (for 'shop_manager' and 'administrator' user roles)
    if ( ! current_user_can( 'edit_shop_order', $post_id ) && ! current_user_can( 'edit_shop_orders', $post_id ) ) {
        return $post_id;
    }
    // Saving the data
    update_post_meta( $post_id, '_edenred_authorization_id', sanitize_text_field( $_POST[ 'edenred_authorization_id' ] ) );
  }
  // Display To My Account view Order
  add_action( 'woocommerce_order_details_after_order_table', 'display_edenred_authorization_id_in_order_view', 10, 1 );
  function display_edenred_authorization_id_in_order_view( $order ) {
      $edenred_authorization_id = get_post_meta( $order->get_id(), '_edenred_authorization_id', true );
      // Output Tracking box
      if( ! empty( $edenred_authorization_id ) && is_account_page() ) {
          echo '<p>Edenred authorization ID: '. $edenred_authorization_id .'</p>';
      }
  }
  /**
   * Load Edenred FO CSS file
   */
  function everwcedenred_front_scripts() {
    if ( is_checkout()) {
      $edenred_settings = get_option('woocommerce_edenred_settings');
      if ($edenred_settings['only_logged'] === 'yes'
        && !is_user_logged_in()
      ) {
        return;
      }
      $everwcedenred_url = plugin_dir_url( __FILE__ );
      wp_enqueue_style( 'everwcedenred-style', $everwcedenred_url . '/views/css/edenred.css' );
      // Load JS file for unlogged users, for saving their informations
      if (!is_user_logged_in()) {
        wp_register_script( 
            'everwcedenred-script', 
            $everwcedenred_url . '/views/js/edenred.js',
            array( 'jquery' )
        );
        wp_enqueue_script( 'everwcedenred-script' );
      }
    }
  }
  add_action( 'wp_enqueue_scripts', 'everwcedenred_front_scripts' );
  /**
   * Load admin CSS
   * @see https://wpchannel.com/creer-feuille-styles-back-office-wordpress/
   */
  function everwcedenred_admin_css() {
    if (isset($_GET['section']) && $_GET['section'] == 'edenred') {
      $admin_handle = 'everwcedenred_admin_css';
      $admin_stylesheet = 'https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css';

      wp_enqueue_style( $admin_handle, $admin_stylesheet );
    }
  }
  add_action('admin_print_styles', 'everwcedenred_admin_css', 11 );
  /**
   * Add plugin action links.
   *
   * Add a link to the settings page on the plugins.php page.
   *
   * @since 1.0.0
   *
   * @param  array  $links List of existing plugin action links.
   * @return array         List of modified plugin action links.
   */
  function everwcedenred_action_links( $links ) {
      $icon = plugin_dir_url(__FILE__) . 'assets/img/logo.ico';
      $logo = '<img src="' . $icon . '" class="img img-fluid icon-team-ever" alt="WooCommerce plugin by Team Ever" title="WooCommerce plugin by Team Ever" style="width:20px;height:20px;">';
      $links = array_merge( array(
          '<a href="' . esc_url( admin_url( '/admin.php?page=wc-settings&tab=checkout&section=edenred' ) ) . '">' . $logo . __( 'Settings', 'everwcedenred' ) . '</a>'
      ), $links );
      return $links;
  }
  add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'everwcedenred_action_links' );
  /**
   * Get Edenred order list
   * @param bool export for CSV file, bool export_all for rendereing all orders using edenred
   * @return array of orders or CSV file
  */
  function send_daily_edenred_orders_by_email() {
    $now = (new \DateTime())->format('Y-m-d H:i:s');
    $today = (new \DateTime())->setTime(0, 0);
    if ($today != $now) {
      return;
    }
    $last_call = get_option('edenred_admin_email_date_sent');
    if ($last_call
      && $last_call == strtotime(date('Y-m-d'))
    ) {
      return;
    }
    $admin_email = get_option('admin_email');
    $filename = 'edenred_orders.csv';
    $filepath = dirname( __FILE__ ).'/'.$filename;
    $header_row = array(
        __( 'ORDER ID', 'everwcedenred' ),
        __( 'CUSTOMER FIRSTNAME', 'everwcedenred' ),
        __( 'CUSTOMER LASTNAME', 'everwcedenred' ),
        __( 'CUSTOMER EMAIL', 'everwcedenred' ),
        __( 'ORDER TOTAL', 'everwcedenred' ),
        __( 'EDENRED PAID TOTAL', 'everwcedenred' ),
        __( 'ORDER CURRENCY', 'everwcedenred' ),
        __( 'ORDER DATE', 'everwcedenred' )
    );
    $data_rows = array();
    $orders_list = array();
    global $wpdb;
    $table = $wpdb->prefix . 'postmeta';
    $edenred_orders = $wpdb->get_results('SELECT post_id FROM '.$table.' WHERE meta_key = "_is_edenred" AND meta_value = 1', OBJECT);
    foreach ($edenred_orders as $e_order) {
        $order = wc_get_order((int)$e_order->post_id);
        if (strtotime(date('Y-m-d')) != strtotime($order->get_date_created()->date('Y-m-d'))) {
            continue;
        }
        if ($order) {
            $orders_list[] = array(
                (int)$e_order->post_id,
                $order->get_billing_first_name(),
                $order->get_billing_last_name(),
                $order->get_billing_email(),
                $order->get_total(),
                $order->get_meta('_edenred_paid_amount', true),
                $order->get_currency(),
                $order->get_date_created()->date('d-m-Y'),
            );
        }
    }
    if (empty($orders_list)) {
      return;
    }
    ob_end_clean();
    $fh = fopen( $filepath, 'w' );
    fprintf( $fh, chr(0xEF) . chr(0xBB) . chr(0xBF) );
    fputcsv( $fh, $header_row, ';' );
    foreach ( $orders_list as $data_row ) {
        fputcsv( $fh, $data_row, ';' );
    }
    rewind($fh);
    fclose($fh);

    $to = $admin_email;
    $subject = __( 'Daily Edenred orders', 'everwcedenred' );
    $body = __( 'Please find attached your daily Edenred orders report', 'everwcedenred' );
    $headers = array('Content-Type: text/html; charset=UTF-8');
    $send = wp_mail( $to, $subject, $body, $headers, array($filepath) );
    unlink($filepath);
    update_option('edenred_admin_email_date_sent', strtotime(date('Y-m-d')));
  }
  add_action('init', 'send_daily_edenred_orders_by_email' );
  function edenred_install() {
    // since wordpress 4.5.0
    $args = array(
        'taxonomy'   => 'product_cat'
    );
    $product_categories = get_terms($args);
    $array_for_settings = array();
    foreach ($product_categories as $cat) {
      $array_for_settings[] = (string)$cat->term_id;
    }
    $default_settings = get_option('woocommerce_edenred_settings');
    $default_settings['enabled'] = 'no';
    $default_settings['siret'] = '';
    $default_settings['mid'] = '';
    $default_settings['partial_payment'] = 'no';
    $default_settings['min_payment'] = '1';
    $default_settings['max_payment'] = '19';
    $default_settings['allowed_categories'] = $array_for_settings;
    $default_settings['title'] = 'Paiement par carte Ticket Restaurant® Edenred';
    $default_settings['description'] = 'Merci de préparer votre carte Ticket Restaurant® Edenred';
    update_option('woocommerce_edenred_settings', $default_settings);
  }
  register_activation_hook(__FILE__,'edenred_install');
}
