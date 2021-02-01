<?php
/*
Plugin Name: WOMPI ON SITE - El Salvador
Plugin URI: https://getavante.com/plugins
Description: Plugin WooCommerce para integrar la pasarela de pago Wompi El Salvador directamente en la pagina de checkout
Version: 1.0
Author: Avante - El Salvador 
Author URI: https://getavante.com
*/

  // Payment Gateway with WooCommerce infinitechsv
  add_action( 'plugins_loaded', 'WOMPI_payment_init', 0 );

  function WOMPI_payment_init() {
    include_once( 'wompi-creditcard.php' );

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
    include_once( 'wc-WOMPI-payment.php' );

    
    add_filter( 'woocommerce_payment_gateways', 'add_WOMPI_payment_gateway' );
    function add_WOMPI_payment_gateway( $methods ) {
      
      $methods[] = 'WOMPI_Payment_Gateway';
      return $methods;
    }
  }


  add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'WOMPI_payment_action_links' );
  function WOMPI_payment_action_links( $links ) {
    $plugin_links = array(
      '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'WOMPI-payment' ) . '</a>',
    );

    return array_merge( $plugin_links, $links );
  }
