<?php
/* @wordpress-plugin
 * Plugin Name:       WooCommerce Edenred payment
 * Plugin URI:        http://www.team-ever.com
 * Description:       Accept Edenred payment in Woocommerce
 * Version:           5.0.2
 * Author:            Cyril CHALAMON - Team Ever
 * Author URI:        https://www.team-ever.com
 * Text Domain:       everwcedenred
 * Domain Path: /languages
 * License:           Tous droits réservés / Le droit d'auteur s'applique (All rights reserved / French copyright law applies)
 * Copyright:       Cyril CHALAMON - Team Ever
 * Author:       Cyril CHALAMON - Team Ever
 */

add_filter('woocommerce_payment_gateways', 'add_edenred_payment_gateway');
function add_edenred_payment_gateway( $gateways ){
  $gateways[] = 'WC_Gateway_Edenred';
  return $gateways;
}
add_action('plugins_loaded', 'edenred_payment_init');
function edenred_payment_init() {
    // Charger le domaine de texte pour l'internationalisation
    load_plugin_textdomain('everwcedenred', false, basename(dirname(__FILE__)) . '/languages/');
    
    
    // Initialiser la passerelle de paiement après que WordPress et tous les plugins ont été chargés
    add_action('wp_loaded', 'init_edenred_payment_gateway');
}

function init_edenred_payment_gateway() {
    // Inclure la classe de la passerelle de paiement après que tous les plugins ont été chargés
    require 'class-wc-gateway-edenred.php';
    new WC_Gateway_Edenred();
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
function adjust_cart_total( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) )
        return;

    if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 )
        return;

    // Exemple de condition, assurez-vous de remplacer ceci par votre logique spécifique
    // Par exemple, vérifier si une session spécifique est définie indiquant le montant déjà payé
    $is_partial_payment = isset( WC()->session->edenred_partial_payment );

    if ( $is_partial_payment ) {
        $paid_amount = WC()->session->edenred_partial_payment; // Le montant déjà payé
        $total = $cart->cart_contents_total - $paid_amount;

        if ( $total < 0 ) {
            $total = 0; // Assurez-vous que le total n'est pas négatif
        }

        // Ajuster le total du panier
        $cart->set_total( $total );
    }
}
add_action( 'woocommerce_before_calculate_totals', 'adjust_cart_total', 10, 1 );
add_action( 'woocommerce_checkout_create_order', 'everwcedenred_recalculate_order_total', 20, 1 );
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
// add_action( 'woocommerce_before_thankyou', 'everwcedenred_thank_you', 10, 1 );
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
  $default_settings['max_payment'] = '25';
  $default_settings['allowed_categories'] = $array_for_settings;
  $default_settings['title'] = 'Paiement par carte Ticket Restaurant® Edenred';
  $default_settings['description'] = 'Merci de préparer votre carte Ticket Restaurant® Edenred';
  update_option('woocommerce_edenred_settings', $default_settings);
}
add_action('init', 'add_partial_payment_order_status');
function add_partial_payment_order_status() {
    register_post_status('wc-partially-paid', array(
        'label'                     => _x('Partiellement payé', 'Statut de commande', 'everwcedenred'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Partiellement payé <span class="count">(%s)</span>', 'Partiellement payés <span class="count">(%s)</span>', 'everwcedenred')
    ));
}

add_filter('wc_order_statuses', 'add_partial_payment_to_order_statuses');
function add_partial_payment_to_order_statuses($order_statuses) {
    $order_statuses['wc-partially-paid'] = _x('Partiellement payé', 'Statut de commande', 'everwcedenred');
    return $order_statuses;
}
register_activation_hook(__FILE__,'edenred_install');