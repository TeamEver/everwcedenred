<?php
/**
 * WooCommerce Edenred Payment Gateway
 * License:           Tous droits réservés / Le droit d'auteur s'applique (All rights reserved / French copyright law applies)
 * Copyright:       Cyril CHALAMON - Team Ever
 * Author:       Cyril CHALAMON - Team Ever
 */

class WC_Edenred_Payment_Gateway extends WC_Payment_Gateway{
    /**
     * Payment gateway construct method
     * @see https://rudrastyh.com/woocommerce/payment-gateway-plugin.html
    */
    public function __construct(){
        $this->id = 'edenred';
        $this->version = '3.6.8';
        $this->plugin_name = 'everwcedenred';
        $this->icon = plugin_dir_url(__FILE__).'views/img/edenred-icon.png';
        $this->method_title = __('Edenred payment','everwcedenred');
        $this->method_description = __( 'Allows payment from an Edenred Ticket Restaurant® card', 'everwcedenred' );
        $this->title = __('Edenred Payment','everwcedenred');
        $this->has_fields = true;
        $this->supports           = array(
            'products',
            'refunds',
        );
        $this->init_form_fields();
        $this->init_settings();
        $this->checkout_url = wc_get_checkout_url();
        if (substr($this->checkout_url, -1) == '/') {
            $this->checkout_url = substr($this->checkout_url, 0, -1);
        }

        // User settings
        $this->enabled = $this->get_option('enabled');
        // $this->getbalance = $this->get_option('getbalance');
        $this->edenred_validation_url = 'https://edenred.team-ever.com/edenred/';
        $this->siret = $this->get_option('siret');
        $this->partial_payment = $this->get_option('partial_payment');
        $this->max_payment = $this->get_option('max_payment');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->allowed_categories = $this->get_option('allowed_categories');
        $this->mid = $this->get_option('mid');
        $this->sandbox = $this->get_option('sandbox');
        $this->only_logged = $this->get_option('only_logged');
        $this->login_link = $this->get_edenred_login_link();
        // Action hooks
        add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));
        add_action( 'woocommerce_api_edenred', array( $this, 'webhook' ) );
        add_filter( 'woocommerce_available_payment_gateways', array( $this, 'edenred_unset_gateway' ) );
    }

    public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'         => __( 'Enable/Disable', 'everwcedenred' ),
                    'type'             => 'checkbox',
                    'label'         => __( 'Enable Edenred payment', 'everwcedenred' ),
                    'description'     => __( 'Enable Edenred payment on your shop', 'everwcedenred' ),
                    'desc_tip'        => true,
                    'default'         => 'yes'
                ),
                'sandbox' => array(
                    'title'         => __( 'Enable sandbox', 'everwcedenred' ),
                    'type'             => 'checkbox',
                    'label'         => __( 'Enable sandbox mode', 'everwcedenred' ),
                    'description'     => __( 'For tests only, no payment will be saved on Edenred', 'everwcedenred' ),
                    'desc_tip'        => true,
                    'default'         => 'no'
                ),
                'only_logged' => array(
                    'title'         => __( 'Edenred for logged users only', 'everwcedenred' ),
                    'type'             => 'checkbox',
                    'label'         => __( 'Else Endered will be available for everyone', 'everwcedenred' ),
                    'description'     => __( 'Will enable Edenred only for logged users', 'everwcedenred' ),
                    'desc_tip'        => true,
                    'default'         => 'no'
                ),
                'siret' => array(
                    'title'         => __( 'Point of sale SIRET', 'everwcedenred' ),
                    'type'             => 'text',
                    'description'     => __( 'Please add point of sale SIRET (format 123456789123)', 'everwcedenred' ),
                    'default'        => __( '123456789123', 'everwcedenred' ),
                    'desc_tip'        => true,
                ),
                'mid' => array(
                    'title'         => __( 'Edenred MID', 'everwcedenred' ),
                    'type'             => 'text',
                    'description'     => __( 'Will be automatically set when your Edenred account is valid', 'everwcedenred' ),
                    'default'        => __( '12345', 'everwcedenred' ),
                    'desc_tip'        => true,
                ),
                // 'getbalance' => array(
                //     'title'         => __( 'Get Edenred account balance before payment', 'everwcedenred' ),
                //     'type'             => 'checkbox',
                //     'label'         => __( 'Get Edenred amount before payment', 'everwcedenred' ),
                //     'description'     => __( 'Else customers will be blocked if balance is not enough on their Edenred account', 'everwcedenred' ),
                //     'desc_tip'        => true,
                //     'default'         => 'no'
                // ),
                'partial_payment' => array(
                    'title'         => __( 'Enable partial Edenred payment', 'everwcedenred' ),
                    'type'             => 'checkbox',
                    'label'         => __( 'Enable partial Edenred payment', 'everwcedenred' ),
                    'description'     => __( 'Customers will be able to partial pay their orders using Edenred', 'everwcedenred' ),
                    'desc_tip'        => true,
                    'default'         => 'yes'
                ),
                'max_payment' => array(
                    'title'         => __( 'Edenred max allowed amount', 'everwcedenred' ),
                    'type'             => 'number',
                    'description'     => __( 'Max allowed payement amount, leave 0 for no use, only if partial payments are allowed', 'everwcedenred' ),
                    'default'        => 0,
                    'desc_tip'        => true,
                ),
                'title' => array(
                    'title'         => __( 'Edenred payment Title', 'everwcedenred' ),
                    'type'             => 'text',
                    'description'     => __( 'This controls the payment title', 'everwcedenred' ),
                    'default'        => __( 'Edenred payment', 'everwcedenred' ),
                    'desc_tip'        => true,
                ),
                'description' => array(
                    'title' => __( 'Customer Message', 'everwcedenred' ),
                    'type' => 'textarea',
                    'default' => 'Please prepare your Edenred card',
                    'description'     => __( 'The message which you want it to appear to the customer in the checkout page.', 'everwcedenred' ),
                    'desc_tip'        => true,

                ),
                'allowed_categories' => array(
                    'title' => __( 'Allowed categories', 'everwcedenred' ),
                    'type' => 'multiselect',
                    'class'       => 'wc-enhanced-select',
                    'options'     => $this->get_product_categories_array(),
                    'description'     => __( 'Only products in allowed categories will be bought using Edenred.', 'everwcedenred' ),
                    'desc_tip'        => true,
                )
         );
    }

    /**
     * Admin Panel Options
     * - Default options set on plugin install
     * @return void
     */
    public function admin_options() {
        if (isset($_GET['action'] )
            && $_GET['action'] == 'everwcedenred_clicked'
            && check_admin_referer('everwcedenred_clicked')
        )  {
            $this->get_edenred_orders(
                true,
                $_GET['export_all']
            );
        }
        if (isset($_GET['action'] )
            && $_GET['action'] == 'everwcedenred_get_mid'
            && check_admin_referer('everwcedenred_get_mid')
        )  {
            $siret = $this->siret;
            $mid = $this->get_edenred_mid($siret);
            if (isset($mid) && !empty($mid)) {
                $edenred_settings = get_option('woocommerce_edenred_settings');
                $edenred_settings['mid'] = $mid;
                update_option('woocommerce_edenred_settings', $edenred_settings);
                wp_redirect( admin_url( '/admin.php?page=wc-settings&tab=checkout&section=edenred' ) );
                exit;
            }
        }
        ?>
        <div class="edenred-container">
            <div class="row">
                <div class="alert alert-info col-md-6">
                    <h3><?php echo __( 'Edenred settings', 'everwcedenred' ) ?></h3>
                    <p>
                        <a href="https://www.team-ever.com/contact/" target="_blank" class="btn btn-info">
                            <?php echo __( 'Ask Team Ever for help', 'everwcedenred' ) ?> 
                        </a>
                        <br>
                        <a href="<?php echo esc_url( admin_url( '/admin.php?page=wc-settings&tab=checkout&section=edenred&action=everwcedenred_clicked&_wpnonce=' ) ); ?><?php echo wp_create_nonce( 'everwcedenred_clicked' )?>" target="_blank" class="btn btn-success">
                            <?php echo __( 'Export daily Edenred orders', 'everwcedenred' ) ?> 
                        </a>
                        <a href="<?php echo esc_url( admin_url( '/admin.php?page=wc-settings&tab=checkout&section=edenred&action=everwcedenred_clicked&export_all=1&_wpnonce=' ) ); ?><?php echo wp_create_nonce( 'everwcedenred_clicked' )?>" target="_blank" class="btn btn-success">
                            <?php echo __( 'Export all Edenred orders', 'everwcedenred' ) ?> 
                        </a>
                    </p>
                </div>
                <div class="alert alert-info col-md-6">
                    <h3><?php echo __( 'How to configure this plugin ?', 'everwcedenred' ) ?></h3>
                    <a href="<?php echo esc_url( admin_url( '/admin.php?page=wc-settings&tab=checkout&section=edenred&action=everwcedenred_get_mid&_wpnonce=' ) ); ?><?php echo wp_create_nonce( 'everwcedenred_get_mid' )?>#woocommerce_edenred_siret" class="btn btn-success">
                        <?php echo __( 'Get my MID', 'everwcedenred' ) ?> 
                    </a>
                    <a href="https://www.team-ever.com/woocommerce-paiement-edenred-carte-restaurant#tab-description" class="btn btn-success" target="_blank">
                        <?php echo __( 'Read documentation', 'everwcedenred' ) ?> 
                    </a>
                    <br>
                    <!-- check all steps -->
                    <ul class="list-group">
                        <?php if ($this->enabled == 'no' || empty($this->mid)) {
                            ?>
                            <?php if (empty($this->mid)) {
                                ?>
                            <li class="list-group-item">
                                <a href="https://eu.docusign.net/Member/PowerFormSigning.aspx?PowerFormId=61544af3-765c-4f32-b71b-1a43bdb52880&env=eu&acct=fcd0ac15-d4b8-4fe3-a67a-508a372ea607&v=2" target="_blank"><?php echo __( 'First, sign the Edenred contract by clicking here', 'everwcedenred' ) ?></a>
                            </li>
                            <?php
                            } ?>
                            <?php if (empty($this->siret)) {
                            ?>
                            <li class="list-group-item"><?php echo __( 'Enter the siret number of your point of sale (not that of your office) in the configuration of the plugin, this will allow you to find the contract associated with your company', 'everwcedenred' ) ?></li>
                            <?php
                            } ?>
                            <li class="list-group-item"><?php echo __( 'Complete the form below to prepare the payment method according to your category criteria', 'everwcedenred' ) ?></li>
                            <?php if (empty($this->mid)) {
                                ?>
                            <li class="list-group-item"><?php echo __( 'As soon as your contract is validated by Edenred, the plugin will automatically validate your data and add your MID.', 'everwcedenred' ) ?></li>
                            <?php
                            } ?>
                            <?php if ($this->enabled == 'no') {
                                ?>
                                <li class="list-group-item"><?php echo __( 'Activate the plugin by checking the box "Enable Edenred payment"', 'everwcedenred' ) ?></li>
                            <?php
                            } ?>
                            <?php
                        } else { ?>
                            <li class="list-group-item"><?php echo __( 'Everything is fine, you already have configured this plugin !', 'everwcedenred' ) ?></li>
                        <?php
                        } ?>
                    </ul>
                    <p></p>
                </div>
                <?php
                if ((bool)$this->everwcedenred_check_version($this->plugin_name, $this->version) === true) {
                     echo '<div class=" col-12 alert alert-warning">
                         <p>A new version of Ever Woo Referral is available</p>
                         <p>Please check latest version at <a href="https://www.team-ever.com/mon-compte/" target="_blank">https://www.team-ever.com/mon-compte/</a></p>
                     </div>';
                }
                ?>
            </div>
        </div>
        <table class="form-table">
        <?php
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
        ?>
        </table><!--/.form-table-->
        <?php
    }

    public function payment_fields() {
        if (!$this->is_allowed_cart()) {
            return;
        }
        do_action( 'woocommerce_edenred_card_form_start', $this->id );
        $access_token = WC()->session->get( 'edenred_access_token' );
        ?>
        
        <?php
        if ( $this->enabled === 'no' ) {
            $error = WC()->session->get( 'edenred_access_error' );
            if (isset($error) && !empty($error)) {
                wc_add_notice( __( 'Edenred sandbox message : ', 'everwcedenred' ).$error, 'error' );
            }
            ?>
            <p class="woocommerce-error" role="alert">
                <?php _e( 'Beware : sandbox mode is enabled', 'everwcedenred' ); ?>
            </p>
            <?php
        }
        if ($this->sandbox == 'yes') {
            ?>
            <p class="woocommerce-error" role="alert">
                <?php _e( 'Beware : sandbox mode is enabled', 'everwcedenred' ); ?>
            </p>
            <?php
        }
        if (isset($access_token) && !empty($access_token)) {
            // Partial payment
            if ($this->partial_payment == 'yes') {
            ?>
            <div class="form-group edenred-partial-form">
                <label for="edenred_partial_payment"><?php _e( 'Partially pay for order with Edenred account', 'everwcedenred' ); ?></label>
                <br>
                <input type="number" class="form-control" id="edenred_partial_payment" name="edenred_partial_payment" aria-describedby="edenred_partial_payment_help" value="<?php if (isset($this->max_payment) && (int)$this->max_payment > 0) { echo $this->max_payment; } else { echo '0';} ?>" <?php if (isset($this->max_payment) && (int)$this->max_payment > 0) { echo 'max="'.$this->max_payment.'"'; } ?> required> <?php echo get_woocommerce_currency_symbol(); ?>
                <p>
                    <small><?php _e( 'Please enter the amount you wish to pay with Edenred.', 'everwcedenred' ); ?></small>
                </p>
                <p>
                    <small><?php _e( 'If total paid using your Edenred account is less than order total, you will have to pay the balance for your order by selecting another payment method.', 'everwcedenred' ); ?></small>
                </p>
            </div>
            <?php
            }
        ?>
        <p class="woocommerce-message" role="alert">
            <?php _e( 'You can now validate your order, your Edenred account will be used for payment', 'everwcedenred' ); ?>
        </p>
         <input type="hidden" id="edenred_access_token" name="edenred_access_token" value="<?php echo $access_token; ?>">
        <?php
        } else {
        ?>
        <p class="edenred-description text-center"><?php echo esc_attr($this->description); ?></p>
        <a href="https://edenred.team-ever.com/edenred?mid=<?php echo $this->mid; ?>&checkout_url=<?php echo $this->checkout_url; ?>&language=<?php echo strtolower(get_bloginfo( 'language' )); ?>&state=<?php echo wp_create_nonce( 'edenred-state' ); ?>&nonce=<?php echo wp_create_nonce( 'edenred-api' ); ?>&login_link=1" class="checkout-button button alt wc-forward">
            <?php _e( 'Login to Edenred account', 'everwcedenred' ); ?>
        </a>
        <?php
        }
        ?>
        <?php
        do_action( 'woocommerce_edenred_card_form_end', $this->id );
    }
    /**
     * Check admin form values
    */
    function process_admin_options() {
        parent::process_admin_options();
        if ( empty( $_POST['woocommerce_edenred_siret'] ) ) {
            WC_Admin_Settings::add_error( 'Error: Please set SIRET' );
            return false;
        }
        $mid = $this->get_edenred_mid($_POST['woocommerce_edenred_siret']);
        $this->update_option('mid', $mid);
    }

    /**
     * Session ID is fully required
    */
    // public function validate_fields(){
    //     if( empty( $_POST[ 'edenred_access_token' ])) {
    //         wc_add_notice( __( 'Edenred payment is not valid. Please connect before validate payment.', 'everwcedenred' ), 'notice' );
    //         return false;
    //     }
    //     return true;
    // }

    /**
     * Process payment
    */
    public function process_payment( $order_id ) {

        if (!$this->is_allowed_cart()) {
            wc_add_notice( __( 'Cart not valid.', 'everwcedenred' ), 'error' );
            return false;
        }
        // No token
        if( empty( $_POST[ 'edenred_access_token' ])) {
            wc_add_notice( __( 'Edenred payment is not valid. Please connect before validate payment.', 'everwcedenred' ), 'error' );
            return false;
        }
        global $woocommerce;
        $order = wc_get_order( $order_id );
        $customer_email = $order->get_billing_email();
        $access_token = WC()->session->get( 'edenred_access_token' );
        // if ($this->getbalance == 'yes') {
        //     $balance = $this->get_edenred_balance($_POST['edenred_access_token'], $customer_email);
        //     if ((float)$balance->available_amount <= 0) {
        //         wc_add_notice( __( 'You dont have enough funds on your Edenred account. Please choose another payment method.', 'everwcedenred' ), 'error' );
        //         return false;
        //     }
        //     if ($balance->available_amount < (float)WC()->cart->total) {

        //     }
        // }
        // we need it to get any order detailes
        // edenred_partial_payment
        if ($this->partial_payment == 'yes') {
            $edenred_partial_payment = $_POST['edenred_partial_payment'];
            if (!isset($edenred_partial_payment)
                || empty($edenred_partial_payment)
            ) {
                wc_add_notice( __( 'Partial payment amount not valid', 'everwcedenred' ), 'error' );
                return false;
            }
            if ((float)$this->max_payment > 0
                && (float)$_POST['edenred_partial_payment'] > (float)$this->max_payment
            ) {
                wc_add_notice( __( 'Maximum amount allowed with this payment method is overloaded', 'everwcedenred' ), 'error' );
                return false;
            }
        }
        if ($this->sandbox == 'yes') {
            $transaction = new stdClass();
            $transaction->status = 'succeeded';
            $transaction->authorized_amount = isset($edenred_partial_payment) ? $edenred_partial_payment : (float)WC()->cart->total;
            $transaction->captured_amount = isset($edenred_partial_payment) ? $edenred_partial_payment : (float)WC()->cart->total;
            $transaction->mid = 'SANDBOX MID';
            $transaction->capture_id = 'SANDBOX CAPTURE ID';
            $transaction->authorization_id = 'SANDBOX AUTHORISAZION ID';
        } else {
            // Test if transaction has been allowed by Edenred
            $transaction = $this->authorize_edenred_transaction(
                sanitize_text_field(
                    $_POST['edenred_access_token']
                ),
                $order_id,
                isset($edenred_partial_payment) ? $edenred_partial_payment : 0
            );
        }
        $allowed_states = array(
            'succeeded',
            'captured'
        );
        if (isset($transaction) && is_object($transaction)) {
            if ( !in_array($transaction->status, $allowed_states) ) {
                wc_add_notice(  __( 'Edenred payment error : ', 'everwcedenred' ).$transaction->text.' | Code : '.$transaction->code, 'error' );
                return false;
            }
            // Do not validate order on partial payment
            if ((float)$transaction->authorized_amount < (float)WC()->cart->total
                || (float)$transaction->captured_amount < (float)WC()->cart->total
            ) {
                $left_to_pay = (float)WC()->cart->total - (float)$transaction->authorized_amount;
                // Save information on WC session
                WC()->session->set( 'edenred_authorized_amount', (float)$transaction->authorized_amount );
                WC()->session->set( 'edenred_captured_amount', (float)$transaction->captured_amount );
                WC()->session->set( 'edenred_left_to_pay', (float)$left_to_pay );
                WC()->session->set( 'edenred_mid', sanitize_text_field($transaction->mid) );
                WC()->session->set( 'edenred_capture_id', sanitize_text_field($transaction->capture_id) );
                WC()->session->set( 'edenred_authorization_id', sanitize_text_field($transaction->authorization_id) );

                wc_add_notice( __( 'You can now validate your order using another payment method', 'everwcedenred' ), 'success' );
                // Drop order to avoid double orders on admin
                wp_delete_post($order_id, true);
                // return false to allow customer select another payment method
                return false;
            }
        } else {
            wc_add_notice( __( 'Edenred transaction not allowed', 'everwcedenred' ), 'error' );
            return false;
        }
        // From here we consider cart total has been paid using Edenred
        // some notes to customer (replace false with true to make it public)
        if ($this->sandbox == 'yes') {
            $order->add_order_note( __( 'Sandbox Edenred order.', 'everwcedenred' ), false );
            $order->update_meta_data( '_is_sandbox', 1 );
        }
        $order->add_order_note( __( 'Payment received using Edenred.', 'everwcedenred' ), false );
        // Required order meta datas
        $order->update_meta_data( '_is_edenred', 1 );
        $order->update_meta_data( '_edenred_captured_amount', sanitize_text_field($transaction->captured_amount) );
        $order->update_meta_data( '_edenred_mid', sanitize_text_field($transaction->mid) );
        $order->update_meta_data( '_edenred_capture_id', sanitize_text_field($transaction->capture_id) );
        $order->update_meta_data( '_edenred_authorization_id', sanitize_text_field($transaction->authorization_id) );
        $order->update_meta_data( '_edenred_access_token', sanitize_text_field( WC()->session->get( 'edenred_access_token' ) ) );
        $order->save();
        // we received the payment
        $order->payment_complete();
        $order->reduce_order_stock();

        // Empty cart
        $woocommerce->cart->empty_cart();

        // Empty session
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

        // Redirect to the thank you page
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url( $order )
        );
    }
    /*
     * In case you need a webhook, like PayPal IPN etc
     */
    public function webhook() {

        $order = wc_get_order( $_GET['id'] );
        $order->payment_complete();
        $order->reduce_order_stock();
        // some notes to customer (replace false with true to make it public)
        $order->add_order_note( 'Payment received using Edenred.', false );

        update_option('webhook_debug', $_GET);
    }

    /**
     * Get all product categories in WooCommerce
     * @return array for multiselect or only select
    */
    private function get_product_categories_array() {
        if ( !is_admin()) {
            return;
        }
        $args = array(
            'taxonomy'   => 'product_cat',
            'number'     => 0,
            'hide_empty' => false,
        );
        $product_categories = get_terms($args);
        $categories_array = array();
        foreach ($product_categories as $product_category) {
            $categories_array[$product_category->term_id] = $product_category->name;
        }
        return $categories_array;
    }

    /**
     * Check if cart is allowed depending on products and allowed categories
     * @return bool
    */
    private function is_allowed_cart(){
        if ( is_admin() || ! is_checkout()) {
            return;
        }
        if ($this->only_logged === 'yes'
            && !is_user_logged_in()
        ) {
            return;
        }
        $allowed = true;
        foreach ( WC()->cart->get_cart_contents() as $key => $values ) {
            $terms = get_the_terms( $values['product_id'], 'product_cat' );
            if (!is_array($this->allowed_categories)) {
                $this->allowed_categories = array($this->allowed_categories);
            }
            foreach ( $terms as $term ) {
                if ( !in_array( $term->term_id, $this->allowed_categories ) ) {
                    $allowed = false;
                    break;
                }
            }
        }
        return $allowed;
    }
    /**
     * Unset payment methods if at least one product is not allowed
     * @param array
     * @return array of allowed payments
     * @link https://www.businessbloomer.com/woocommerce-disable-payment-method-for-specific-category/
    */
    public function edenred_unset_gateway( $available_gateways ) {
        if ( is_admin() || ! is_checkout()) {
            return $available_gateways;
        }
        if ($this->only_logged === 'yes'
            && !is_user_logged_in()
        ) {
            unset( $available_gateways[$this->id] );
            return $available_gateways;
        }
        if (!$this->login_link || empty($this->login_link)) {
            unset( $available_gateways[$this->id] );
            return $available_gateways;
        }
        if (!$this->mid || empty($this->mid)) {
            unset( $available_gateways[$this->id] );
            return $available_gateways;
        }
        if (!$this->siret || empty($this->siret)) {
            unset( $available_gateways[$this->id] );
            return $available_gateways;
        }
        if (WC()->session->get( 'edenred_paid_amount' )) {
            unset( $available_gateways[$this->id] );
            return $available_gateways;
        }
        // Unset payment method if is sandbow, only show for super admin users
        if ($this->enabled === 'no' && !is_super_admin()) {
            unset( $available_gateways[$this->id] );
            return $available_gateways;
        }
        $unset = false;
        foreach ( WC()->cart->get_cart_contents() as $key => $values ) {
            $terms = get_the_terms( $values['product_id'], 'product_cat' );
            if (!is_array($this->allowed_categories)) {
                $this->allowed_categories = array($this->allowed_categories);
            }
            foreach ( $terms as $term ) {
                if ( !in_array( $term->term_id, $this->allowed_categories ) ) {
                    $unset = true;
                    break;
                }
            }
        }
        if ( $unset == true ) {
            unset( $available_gateways[$this->id] );
        }
        return $available_gateways;
    }

    private function get_edenred_balance($access_token, $email) {
        if (!$this->mid || empty($this->mid)) {
            return false;
        }
        // set post fields
        $post = [
            'mid' => sanitize_text_field($this->mid),
            'get_balance' => 1,
            'access_token' => $access_token,
            'email' => $email
        ];

        $curl = curl_init($this->edenred_validation_url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
        // execute!
        $balance = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        // close the connection, release resources used
        curl_close($curl);
        if ($httpCode != 200) {
          return false;
        }
        return json_decode($balance);
    }

    /**
     * Authorize payment
     * @param token, int order id
     * @return obj or false
     * @see https://documenter.getpostman.com/view/10405248/TVewaQQX#872651b9-8d3e-4a89-8ddc-815e1c1335d7
    */
    private function authorize_edenred_transaction($access_token, $order_id, $edenred_partial_payment = 0) {
        if (!$this->mid || empty($this->mid)) {
            return false;
        }
        if ($this->partial_payment == 'yes'
            && $edenred_partial_payment > 0
        ) {
            $amount = (float)$edenred_partial_payment;
        } else {
            $amount = (float)WC()->cart->total;
        }
        // set post fields
        $post = [
            'mid' => sanitize_text_field($this->mid),
            'authorize_transaction' => 1,
            'cart_total' => $amount,
            'access_token' => $access_token,
            'order_id' => (int)$order_id
        ];

        $curl = curl_init($this->edenred_validation_url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
        // execute!
        $transaction = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        // close the connection, release resources used
        curl_close($curl);
        if ($httpCode != 200) {
          return false;
        }
        return json_decode($transaction);
    }

    /**
     * Get Edenred MID on Team Ever webserver
     * @param string/int company siret
     * @return string/int mid, false if not found or not valid
    */
    private function get_edenred_login_link() {
        if (!$this->mid || empty($this->mid)) {
            return false;
        }
        // set post fields
        $post = [
            'mid' => sanitize_text_field($this->mid),
            'get_login_link' => 1,
            'checkout_url' => $this->checkout_url,
            'language' => strtolower(get_bloginfo( 'language' )),
            'state' => wp_create_nonce( 'edenred-state' ),
            'nonce' => wp_create_nonce( 'edenred-api' )
        ];

        $curl = curl_init($this->edenred_validation_url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
        // execute!
        $login_link = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        // close the connection, release resources used
        curl_close($curl);
        if ($httpCode != 200) {
          return false;
        }
        return esc_url_raw($login_link);
    }

    /**
     * Get Edenred MID on Team Ever webserver
     * @param string/int company siret
     * @return string/int mid, false if not found or not valid
    */
    private function get_edenred_mid($siret) {
        if (!$this->siret || empty($this->siret)) {
            return;
        }
        // set post fields
        $post = [
            'siret' => $siret,
        ];

        $curl = curl_init($this->edenred_validation_url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
        // execute!
        $mid = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        // close the connection, release resources used
        curl_close($curl);
        if ($httpCode != 200) {
            $this->update_option('mid', '');
            $this->update_option('enabled', 'no');
            return;
        }
        // $this->update_option('enabled', 'yes');
        $this->update_option('mid', $mid);
        return $mid;
    }

    /**
     * Process a refund if supported.
     *
     * @param  int    $order_id Order ID.
     * @param  float  $amount Refund amount.
     * @param  string $reason Refund reason.
     * @return bool|WP_Error
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        $order = wc_get_order( $order_id );
        $is_sandbox = get_post_meta( $order_id, '_is_sandbox', true );
        if ($is_sandbox && !empty($is_sandbox)) {
            $refunded = true;
        } else {
            $refunded = $this->refund_edenred_order($amount, $order_id);
        }
        if ((bool)$refunded === true) {
            if (isset($reason) && !empty($reason)) {
                $order->add_order_note( $reason, false );
            }
            // Use of wc_create_refund
            $refund = wc_create_refund( array(
                'amount'         => $amount,
                'reason'         => $reason,
                'order_id'       => $order_id,
                'line_items'     => array(),
                'refund_payment' => true,
                'restock_items'  => true
            ) );
            $order->add_order_note( __( 'Payment refunded using Edenred.', 'everwcedenred' ), false );

            return true;
        }
        return false;
    }

    // refundEdenredOrder
    private function refund_edenred_order($amount, $order_id) {
        if (!$this->mid || empty($this->mid)) {
            return false;
        }

        $order = wc_get_order( $order_id );
        // set post fields
        $post = [
            'mid' => sanitize_text_field($this->mid),
            'refund_transaction' => 1,
            'authorization_id' => $order->get_meta( '_edenred_authorization_id', true ),
            'amount' => (float)$amount
        ];

        $curl = curl_init($this->edenred_validation_url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
        // execute!
        $refund = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        // close the connection, release resources used
        curl_close($curl);
        if ($httpCode != 200) {
          return false;
        }
        return $refund;
    }    

    /*
    * Check latest version of plugin
    */
    private function everwcedenred_check_version($module, $version)
    {
        $upgrade_link = 'https://upgrade.team-ever.com/upgrade.php?module='
        .$module
        .'&version='
        .$version;
        $handle = curl_init($upgrade_link);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        $module_version = curl_exec($handle);
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);
        if ($httpCode != 200) {
          return false;
        }

        if ($module_version && $module_version > $version) {
            if (version_compare( $module_version, $version, '<' ) >= 0) {
              return true;
            }
        }
        return false;
    }

    private function edenred_get_random_string($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * Get Edenred order list
     * @param bool export for CSV file, bool export_all for rendereing all orders using edenred
     * @return array of orders or CSV file
    */
    private function get_edenred_orders($export = false, $export_all = false) {
        $filename = 'edenred_orders.csv';
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
            if ((bool)$export_all === false
                && strtotime(date('Y-m-d')) != strtotime($order->get_date_created()->date('Y-m-d'))
            ) {
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
        if ((bool)$export === false ) {
            return $orders_list;
        }
        ob_end_clean();
        $fh = @fopen( 'php://output', 'w' );
        fprintf( $fh, chr(0xEF) . chr(0xBB) . chr(0xBF) );
        header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
        header( 'Content-Description: File Transfer' );
        header( 'Content-type: text/csv' );
        header( "Content-Disposition: attachment; filename={$filename}" );
        header( 'Expires: 0' );
        header( 'Pragma: public' );
        fputcsv( $fh, $header_row, ';' );
        foreach ( $orders_list as $data_row ) {
            // die(var_dump($data_row));
            fputcsv( $fh, $data_row, ';' );
        }
        fclose($fh);
        exit();
    }
}
