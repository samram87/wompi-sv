<?php
class wompi_Payment_Gateway extends WC_Payment_Gateway
{
    function __construct()
    {
        global $woocommerce;
        $this->id = "wompi_payment";
        $this->method_title = __("WOMPI - El Salvador", 'wompi-payment');
        $this->method_description = __("WOMPI - El Salvador Payment Gateway Plug-in para WooCommerce", 'wompi-payment');
        $this->title = __("WOMPI - El Salvador", 'wompi-payment');
        $this->icon = apply_filters('woocommerce_wompi_icon', $woocommerce->plugin_url() . '/../wompi-el-salvador/assets/images/wompi3.png');
        $this->has_fields = true;
        $this->init_form_fields();
        $this->init_settings();
        foreach ($this->settings as $setting_key => $value)
        {
            $this->$setting_key = $value;
        }



        add_action('admin_notices', array(
            $this,
            'do_ssl_check'
        ));
        add_action('woocommerce_api_wc_gateway_wompi', array(
            $this,
            'validate_wompi_return'
        ));
        add_action('woocommerce_api_wc_webhook_wompi', array(
            $this,
            'validate_wompi_webhook'
        ));

        add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

        if (is_admin())
        {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                $this,
                'process_admin_options'
            ));
        }
    }
    public function validate_wompi_webhook()
    {
        global $woocommerce;
        $headers = getallheaders();
        if (!function_exists('write_log'))
        {
            function write_log($log)
            {
                if (true === WP_DEBUG)
                {
                    if (is_array($log) || is_object($log))
                    {
                        error_log(print_r($log, true));
                    }
                    else
                    {
                        error_log($log);
                    }
                }
            }

        }
        $entityBody = @file_get_contents('php://input');
        write_log('entra en el validate_wompi_webhook ************************************ ' . json_encode($headers) . ' ************');

        write_log('entra en el validate_wompi_webhook ************************************ BODY: ' . $entityBody . ' ************');
        $arrayResult = json_decode($entityBody);
        $order_id = $arrayResult->{'EnlacePago'}->{'IdentificadorEnlaceComercio'};
        $customer_order = new WC_Order($order_id);
        write_log('entra en el validate_wompi_webhook ********** ORDER ID: ' . json_encode($order_id) . ' *****');

        $sig = hash_hmac('sha256', $entityBody, $this->client_secret);
        $hash = $headers['Wompi_Hash'];
        update_post_meta($order_id, '_wc_order_wompi_Hash', $hash);
        update_post_meta($order_id, '_wc_order_wompi_cadena', $entityBody);
        write_log('entra en el validate_wompi_webhook ********** HASH: ' . $hash . ' *****');

        if ($sig == $hash)
        {
            write_log('entra en el validate_wompi_webhook ********** HASH VALIDO  *****');
            update_post_meta($order_id, '_wc_order_wompi_StatusHash', $sig . ' valido:');
            $customer_order->add_order_note(__('wompi pago completado WH.', 'wompi-payment'));

            $customer_order->payment_complete();
            update_post_meta($order_id, '_wc_order_wompi_transactionid', $arrayResult->{'idTransaccion'}, true);
            $woocommerce
                ->cart
                ->empty_cart();
            header('HTTP/1.1 200 OK');
        }
        else
        {
            write_log('entra en el validate_wompi_webhook ********** HASH NO VALIDO  *****');

            update_post_meta($order_id, '_wc_order_wompi_StatusHash', $sig . ' No valido:');
            $customer_order->add_order_note(__('wompi hash no valido WH.', 'wompi-payment'));
            header('HTTP/1.1 200 OK');
        }

    }

    public function validate_wompi_return()
    {
        global $woocommerce;
        $order_id = sanitize_text_field($_GET['identificadorEnlaceComercio']);
        $customer_order = new WC_Order($order_id);
        $idTransaccion = sanitize_text_field($_GET['idTransaccion']);
        $idEnlace = sanitize_text_field($_GET['idEnlace']);
        $monto = sanitize_text_field($_GET['monto']);
        $hash = sanitize_text_field($_GET['hash']);
        $cadena = $order_id . $idTransaccion . $idEnlace . $monto;
        $sig = hash_hmac('sha256', $cadena, $this->client_secret);

        $authcode = get_post_meta($order_id, '_wc_order_wompi_authcode', true);
        if ($authcode == null)
        {

            update_post_meta($order_id, '_wc_order_wompi_Hash', $hash);
            update_post_meta($order_id, '_wc_order_wompi_cadena', $cadena);

            if ($sig == $hash)
            {
                update_post_meta($order_id, '_wc_order_wompi_StatusHash', $sig . ' valido:');
                $customer_order->add_order_note(__('wompi pago completado.', 'wompi-payment'));

                $customer_order->payment_complete();
                update_post_meta($order_id, '_wc_order_wompi_transactionid', $idTransaccion, true);
                $woocommerce
                    ->cart
                    ->empty_cart();
                wp_redirect(html_entity_decode($customer_order->get_checkout_order_received_url()));

            }
            else
            {
                update_post_meta($order_id, '_wc_order_wompi_StatusHash', $sig . ' No valido:');
                $customer_order->add_order_note(__('wompi hash no valido.', 'wompi-payment'));
                home_url();
            }
        }
        else
        {
            wp_redirect(html_entity_decode($customer_order->get_checkout_order_received_url()));
        }
    }

    public function payment_scripts() {
 
        // we need JavaScript to process a token only on cart/checkout pages, right?
        if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
            return;
        }
     
        // if our payment gateway is disabled, we do not have to enqueue JS too
        if ( 'no' === $this->enabled ) {
            return;
        }

        if ( !is_ssl() ) {
            return;
        }

        wp_register_style( 'woocommerce_api_wc_gateway_wompi_css', plugins_url( 'assets/js/card.css', __FILE__ ));
        wp_enqueue_style( 'woocommerce_api_wc_gateway_wompi_css' );
     
        wp_register_script( 'woocommerce_api_wc_gateway_wompi', plugins_url( 'assets/js/card.js', __FILE__ ));
     
        wp_enqueue_script( 'woocommerce_api_wc_gateway_wompi' );

        wp_register_script( 'woocommerce_api_wc_gateway_wompi_final', plugins_url( 'assets/js/final.js', __FILE__ ),array('woocommerce_api_wc_gateway_wompi'),"151523",true);
        wp_enqueue_script( 'woocommerce_api_wc_gateway_wompi_final' );
    }

    public function payment_fields() {
        if ( !is_ssl() ) {
            echo wpautop( wp_kses_post( "Su tienda no cuenta con certificado de seguridad" ) );
            return;
        }
 
        if ( $this->description ) {
            echo wpautop( wp_kses_post( $this->description ) );
        }
     
        echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
     
        do_action( 'woocommerce_credit_card_form_start', $this->id );
                 
        echo '<div class="card-wrapper" ></div>
        <div class="clear"><br/></div>
        <div class="form-container active">
                <input placeholder="Número de Tarjeta" type="tel" name="wompi_number">
                <input placeholder="Nombre en Tarjeta" type="text" name="wompi_name">
                <input placeholder="MM/AA" type="tel" name="wompi_expiry" length="10">
                <input placeholder="CVC" type="tel" name="wompi_cvc" length="10">';
        if($this->api_permitirPagoConPuntoAgricola){
            echo '<br/><br/><label style="vertical-align: middle;" for="wompi_puntos"><input type="checkbox" id="wompi_puntos" name="wompi_puntos" style="vertical-align: middle;" value="puntos">Usar Puntos del Banco Agrícola</label><br>';
        }
        echo '</div>';
        do_action( 'woocommerce_credit_card_form_end', $this->id );
     
        echo '<div class="clear"></div></fieldset>';
     
    }


    public function validate_fields(){
        if (!function_exists('write_log'))
        {
            function write_log($log)
            {
                if (true === WP_DEBUG)
                {
                    if (is_array($log) || is_object($log))
                    {
                        error_log(print_r($log, true));
                    }
                    else
                    {
                        error_log($log);
                    }
                }
            }

        }
        write_log('Entra en validate fields ' . json_encode($_POST) );

        if($_POST["payment_method"]==="wompi_payment"){
            $ret=true;
            if( empty( $_POST[ 'wompi_number' ]) ) {
                wc_add_notice(  'Numero de Tarjeta es Requerido', 'error' );
                $ret=false;
            }
            
            if( empty( $_POST[ 'wompi_cvc' ]) ) {
                wc_add_notice(  'Numero de CVC es Requerido', 'error' );
                $ret=false;
            }
            if( empty( $_POST[ 'wompi_name' ]) ) {
                wc_add_notice(  'Nombre de Propietario de Tarjeta es Requerido', 'error' );
                $ret=false;
            }

            if( empty( $_POST[ 'wompi_expiry' ]) ) {
                wc_add_notice(  'Fecha de Vencimiento es Requerida', 'error' );
                $ret=false;
            }else if($ret){
                $fecha=$_POST[ 'wompi_expiry' ];
                if(strpos($fecha,"/")===false){
                    wc_add_notice(  'Fecha de Vencimiento no tiene el formato correcto', 'error' );
                    $ret=false;
                }else{
                    list($mes,$año)=explode('/', $fecha);
                    $mes=trim($mes);
                    $año="20".trim($año);
                    $validDate = WompiCreditCard::validDate($año, $mes);
                    if(!$validDate){
                        wc_add_notice(  'La Tarjeta esta vencida', 'error' );
                        $ret=false;
                    }else{
                        $cardNumber= str_replace(" ", "", $_POST[ 'wompi_number' ]);
                        $card = WompiCreditCard::validCreditCard($cardNumber);
                        write_log($card);
                        if($card["valid"]==1){
                            $allow= array("visaelectron","maestro","visa","mastercard");
                            $type=$card["type"];
                            if(!in_array($type,$allow)){
                                wc_add_notice(  'El tipo de la tarjeta ('.$type.') no es valido', 'error' );
                                $ret=false;    
                            }else{
                                $valid=WompiCreditCard::validCvc($_POST["wompi_cvc"], $type);
                                if(!$valid){
                                    wc_add_notice(  'El CVC no es correcto', 'error' );
                                    $ret=false;    
                                }
                            }
                        }else{
                            wc_add_notice(  'La Tarjeta no es valida', 'error' );
                            $ret=false;
                        }
                    }
                }
            }

            return $ret;
        }

        
        return true;
     
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Activar / Desactivar', 'wompi-payment') ,
                'label' => __('Activar este metodo de pago', 'wompi-payment') ,
                'type' => 'checkbox',
                'default' => 'no',
            ) ,
            'title' => array(
                'title' => __('Título', 'wompi-payment') ,
                'type' => 'text',
                'desc_tip' => __('Título de pago que el cliente verá durante el proceso de pago.', 'wompi-payment') ,
                'default' => __('Tarjeta de crédito con WOMPI', 'wompi-payment') ,
            ) ,
            'description' => array(
                'title' => __('Descripción', 'wompi-payment') ,
                'type' => 'textarea',
                'desc_tip' => __('Descripción de pago que el cliente verá durante el proceso de pago.', 'wompi-payment') ,
                'default' => __('Pague con seguridad usando su tarjeta de crédito.', 'wompi-payment') ,
                'css' => 'max-width:350px;'
            ) ,
            'TextoWompi' => array(
                'title' => __('Título del pago', 'wompi-payment') ,
                'type' => 'text',
                'desc_tip' => __('Título que aparece en la descripcion del pago en wompi.', 'wompi-payment') ,
                'default' => __('Carrito de la Compra', 'wompi-payment') ,
            ) ,
            'client_id' => array(
                'title' => __('App ID', 'wompi-payment') ,
                'type' => 'text',
                'desc_tip' => __('ID de clave de seguridad del panel de control del comerciante.', 'wompi-payment') ,
                'default' => '',
            ) ,
            'client_secret' => array(
                'title' => __('Api Secret', 'wompi-payment') ,
                'type' => 'text',
                'desc_tip' => __('ID de clave de api del panel de control del comerciante.', 'wompi-payment') ,
                'default' => '',
            ) ,
            'api_email' => array(
                'title' => __('Correo para notificar', 'wompi-payment') ,
                'type' => 'text',
                'desc_tip' => __('El correo del comercio donde se notificará los pagos.', 'wompi-payment') ,
                'default' => '',
                'description' => 'Se puede colocar más de un correo separado por comas Ejemplo: correo@gmail.com,correo2@gmail.com'
            ) ,
            'api_notifica' => array(
                'title' => __('Se notificará al cliente?', 'wompi-payment') ,
                'type' => 'select',
                'options' => array(
                    'true' => 'SI',
                    'false' => 'NO'
                ) ,
                'desc_tip' => __('Si se notificará por correo al cliente el pago.', 'wompi-payment') ,
                'default' => 'true'
            ) ,
            'api_edit_monto' => array(
                'title' => __('El monto es editable?', 'wompi-payment') ,
                'type' => 'select',
                'options' => array(
                    'false' => 'NO',
                    'true' => 'SI'
                ) ,
                'desc_tip' => __('Activar en caso de permitir editar el monto de la compra en Wompi Ejemplo: Donaciones', 'wompi-payment') ,
                'default' => 'false'
            ) ,
            'api_permitirTarjetaCreditoDebido' => array(
                'title' => __('Permitir Tarjeta Crédito/Débido', 'wompi-payment') ,
                'type' => 'select',
                'options' => array(
                    'true' => 'SI',
                    'false' => 'NO'
                ) ,
                'desc_tip' => __('Permitir cobrar con tarjeta de Crédito/Débido', 'wompi-payment') ,
                'default' => 'true'
            ) ,
            'api_permitirPagoConPuntoAgricola' => array(
                'title' => __('Permitir pago con puntos Agrícola', 'wompi-payment') ,
                'type' => 'select',
                'options' => array(
                    'true' => 'SI',
                    'false' => 'NO'
                ) ,
                'desc_tip' => __('Permitir cobrar con puntos Agrícola', 'wompi-payment') ,
                'default' => 'true'
            ) ,
        );
    }

    public function process_payment($order_id)
    {

        if (!function_exists('write_log'))
        {
            function write_log($log)
            {
                if (true === WP_DEBUG)
                {
                    if (is_array($log) || is_object($log))
                    {
                        error_log(print_r($log, true));
                    }
                    else
                    {
                        error_log($log);
                    }
                }
            }

        }
        write_log('Entra en process_payment ' . json_encode($_POST) );
        global $woocommerce;
        $customer_order = new WC_Order($order_id);

        $client_id = $this->client_id;
        $client_secret = $this->client_secret;
        $postBody = array(
            'grant_type' => 'client_credentials',
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'audience' => 'wompi_api',
        );

        $response = wp_remote_post('https://id.wompi.sv/connect/token', array(
            'method' => 'POST',
            'body' => http_build_query($postBody) ,
            'timeout' => 90,
            'sslverify' => false,
        ));

        if (is_wp_error($response))
        {
            $error_message = $response->get_error_message();
            echo "error: " . $error_message;
        }
        else
        {

            
            $body = wp_remote_retrieve_body($response);
            $arrayResult = json_decode($body);
            write_log('El response fue correcto ' . $body );
            $token = $arrayResult->{'access_token'};

            $order = wc_get_order($order_id);
            //Sending the card numbers
            $billing_email  = $order->get_billing_email();


            $url_redi = $this->get_return_url($order);
            $configuracion = array(
                "emailsNotificacion" => $this->api_email,
                "urlWebhook" => home_url() . '/?wc-api=WC_webhook_Wompi',
                "notificarTransaccionCliente" => $this->api_edit_monto
            );
            $fecha=$_POST["wompi_expiry"];
            list($mes,$año)=explode('/', $fecha);
            $mes=trim($mes);
            $año="20".trim($año);
            $cardNumber= str_replace(" ", "", $_POST[ 'wompi_number' ]);
            $tarjeta = array(
                "numeroTarjeta" => $cardNumber,
                "cvv" => $_POST["wompi_cvc"],
                "mesVencimiento" => $mes,
                "anioVencimiento" => $año
            );

            $formaPago="PagoNormal";
            if($_POST["wompi_puntos"]=="puntos"){
                $formaPago="Puntos";
            }
            $payload_data = array(
                "tarjetaCreditoDebido" => $tarjeta,
                "monto" => method_exists($customer_order, 'get_total') ? $customer_order->get_total() : $customer_order->order_total,
                "emailCliente" => $billing_email,
                "nombreCliente" => $_POST["wompi_name"],
                "formaPago" => $formaPago,
                "configuracion" => $configuracion
            );
            $args = array(
                'body' => wp_json_encode($payload_data) ,
                'timeout' => '90',
                'blocking' => true,
                'headers' => array(
                    "Authorization" => 'Bearer ' . $token,
                    "content-type" => 'application/json'
                ) ,
            );
            $response = wp_remote_post('https://api.wompi.sv/TransaccionCompra', $args);
            if (is_wp_error($response))
            {
                $error_message = $response->get_error_message();
                echo "error: " . $error_message;
            }
            else
            {
                $body = wp_remote_retrieve_body($response);
                $arrayResult = json_decode($body);

                write_log($arrayResult);

                $idTransaccion=$arrayResult->{'idTransaccion'};
                $esReal=$arrayResult->{'esReal'};
                $esAprobada=$arrayResult->{'esAprobada'};
                $codigoAutorizacion=$arrayResult->{'codigoAutorizacion'};
                $mensaje=$arrayResult->{'mensaje'};
                $formaPago=$arrayResult->{'formaPago'};
                if($esAprobada){
                    update_post_meta($order_id, '_wc_order_wompi_Hash', $codigoAutorizacion);
                    update_post_meta($order_id, '_wc_order_wompi_cadena', $entityBody);
                    update_post_meta($order_id, '_wc_order_wompi_StatusHash', $idTransaccion . ' valido:');
                    $customer_order->add_order_note(__('wompi pago completado WH.', 'wompi-payment'));
                    $customer_order->payment_complete();


                    return array(
                        'result'   => 'success',
                        'redirect' => $this->get_return_url( $order ),
                    );
                }else{
                    if($formaPago=="Puntos" && $mensaje=="FONDOS INSUFICIENTES"){
                        $mensaje="PUNTOS INSUFICIENTES";    
                    }
                    wc_add_notice(  'Transaccion no procesada: '.$mensaje, 'error' );
                    return false;
                }
                    
                
            }
        }

    }

}

add_action('woocommerce_admin_order_data_after_billing_address', 'show_WOMPI_info', 10, 1);
function show_WOMPI_info($order)
{
    $order_id = method_exists($order, 'get_id') ? $order->get_id() : $order->id;
    echo '<p><strong>' . __('WOMPI Transaction Id') . ':</strong> ' . get_post_meta($order_id, '_wc_order_wompi_transactionid', true) . '</p>';
}
?>