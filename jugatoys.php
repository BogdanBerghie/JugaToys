<?php 
/*
Plugin Name: JugaToys
Plugin URI: https://serinfor.net
Description: Plugin para guardar datos de presupuestos automáticos
Version: 1.0
Author: Jon Alain Hinojosa & Bogdan 
Author URI: https://serinfor.net
License: GPL2
*/

// Disculpa, no sé tu apellido. Añádelo arriba.

include_once("API.php");
include_once("opciones.php");

include_once("utilidades.php");

defined('ABSPATH') or die("Error de ruta.");

add_action( 'init', 'configuracionInicial', 0 );

function configuracionInicial(){

  //Añadimos EAN
  include_once("añadirEAN.php");

  //Checkeamos valores por defecto de la configuración
  jugatoys_configuracion_default();

  // crear cron para sincronización de stock
  //IDEA: Esta sincronización de stock de todos los productos se debe lanzar X veces diariamente (configurable en las opciones). Se deberían de ir consultando productos de X en X (¿Convendría que fuese configurable en opciones?). 
  //Una forma de realizar esto sería añadiendo un meta dato a cada producto, que indique la fecha de última actualización. En una función que será recursiva, consultar X número de productos en función a la fecha de actualización. Si hay productos, establecer cron para dentro de X tiempo. Si no hay más productos (ya ha corrido suficientes veces, y se han actualizado todos), establecer el cron para el próximo día.

  //Creamos intervalo de cron en función a lo que ha establecido el usuario
  add_filter( 'cron_schedules', 'jugatoys_cron_interval' );
  function jugatoys_cron_interval( $schedules ) { 
    $opciones = get_option( 'jugatoys_settings' );
    $schedules['jugatoys_interval'] = array(
        'interval' => (3600*24)/$opciones['sincronizaciones_diarias'],
        'display'  => esc_html__( $opciones['sincronizaciones_diarias']. ' veces al día' ), );
    return $schedules;
  }    

  //Función que se correra X veces al día, según lo que se haya establecido en opciones
  function cron_events_actualizar_stock_productos() {
    //Confirmamos flag que no se estén actualizando productos actualmente
    $actualizandoStockProductos = get_option("jugatoys_actualizandoStockProductos");
    if (!$actualizandoStockProductos) {
      wp_schedule_single_event(time(), "jugatoys_actualizar_stock_productos");
    }
  }
  add_action( 'jugatoys_actualizar_stock_productos', 'actualizarStockProductos' );
  add_action( 'jugatoys_actualizar_stock_productos_cron', 'cron_events_actualizar_stock_productos' );


  if (! wp_next_scheduled ( 'jugatoys_actualizar_stock_productos_cron')) {
    wp_schedule_event( time(), 'jugatoys_interval', 'jugatoys_actualizar_stock_productos_cron' );
  }

  add_action('update_option_jugatoys_settings', 'jugatoys_settings_actualizados', 10, 3);
  function jugatoys_settings_actualizados($old, $new, $name){
    if ($old['sincronizaciones_diarias'] != $new['sincronizaciones_diarias']) {
      desactivar_cron("jugatoys_actualizar_stock_productos_cron");
    }    
  }

  // crear cron para consultar si hay nuevos productos a partir de la última fecha de consulta (guardar como opción)
  //IDEA: Cron diario como el anterior, pero esta vez consultando si hay productos nuevos a partir de X fecha. Entiendo que no haría falta recursividad
  function cron_events_nuevos_productos() {
    comprobarTodosProductos();
  }
  add_action( 'jugatoys_nuevos_productos_cron', 'cron_events_nuevos_productos' );
   


  //Añadimos hoook/acción de consulta de stock de producto individual al entrar a la página individual
  // https://www.businessbloomer.com/woocommerce-visual-hook-guide-single-product-page/
  function jugatoys_action_actualizar_stock_single( $params ) { 
    actualizarStockProductoActual();
  }; 
  add_action( 'woocommerce_before_single_product', 'jugatoys_action_actualizar_stock_single', 10, 1 ); 

  //Añadimos hoook/acción de consulta de stock de producto individual al entrar al checkout
  // https://www.businessbloomer.com/woocommerce-visual-hook-guide-cart-page/
  function jugatoys_action_actualizar_stock_multiple( $params ) { 
    actualizarStockCarrito();
  }; 
  add_action( 'woocommerce_before_cart', 'jugatoys_action_actualizar_stock_multiple', 10, 1 ); 


  // Añadir hoook/acción para notificar la venta a jugatoys
  function jugatoys_action_pago_realizado( $orderId ) { 
    notificarVenta($orderId); 
  }; 
  // En duda, otro posible hook
  // woocommerce_payment_complete_reduce_order_stock
  // https://woocommerce.github.io/code-reference/hooks/hooks.html
  add_action( 'woocommerce_payment_complete', 'jugatoys_action_pago_realizado', 10, 1 ); 


  //TODO: Test, quitar despues
  //add_action( 'woocommerce_checkout_order_processed', 'jugatoys_action_pago_realizado', 10, 1 ); 
  




}

// Función que se correrá únicamente una vez al activar el plugin
function jugatoys_activate(){

  //Creamos cron que correrá dos veces al día para consultar productos nuevos. Servirá también para hacer la carga inicial
  if (! wp_next_scheduled ( 'jugatoys_nuevos_productos_cron')) {
    wp_schedule_event( time(), 'twicedaily', 'jugatoys_nuevos_productos_cron' );//
  }

}

register_activation_hook( __FILE__, 'jugatoys_activate' );

// Función que se correrá únicamente una vez al desactivar el plugin
function jugatoys_deactivate(){
  desactivar_cron("jugatoys_nuevos_productos_cron");
}
register_deactivation_hook( __FILE__, 'jugatoys_deactivate' );

?>