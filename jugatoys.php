<?php 
/*
Plugin Name: JugaToys
Plugin URI: https://serinfor.net
Description: Plugin para guardar datos de presupuestos automáticos. 
Version: 1.4.1
Author: Jon Alain Hinojosa & Bogdan Berghie
Author URI: https://serinfor.net
License: GPL2
*/

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

    // TODO: Revisando esta parte, creo que faltaba el código de la siguiente línea. Lo añado comentado.
    // $actualizandoStockProductos = get_option("jugatoys_actualizandoStockProductos");

    if (!$actualizandoStockProductos) {
      jugatoys_log("Valor de actualizandoStockProductos " . $actualizandoStockProductos);
      //wp_schedule_single_event(time(), "jugatoys_actualizar_stock_productos");
      actualizarStockProductos();
    }else{
      jugatoys_log("No ha inicado actualizarStockProductos. jugatoys_actualizandoStockProductos = ".$actualizandoStockProductos." Para funcionar poner a 0 ");
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
  //woocommerce_new_order       woocommerce_payment_complete
  add_action( 'woocommerce_order_status_processing', 'jugatoys_action_pago_realizado', 10, 1 ); 


  //TODO: Test, quitar despues
  //add_action( 'woocommerce_checkout_order_processed', 'jugatoys_action_pago_realizado', 10, 1 ); 
  
  // Añadimos cron para notificar posibles ventas sin notificar por pérdidas de conexión 
  function jugatoys_cron_venta_no_notificada() {
    // Verificamos que el servidor esté activo
    jugatoys_log("Comprobando si el servidor está activo");
    $api = new JugaToysAPI();
    $ping = $api->ping();
    jugatoys_log("Comprobando si el servidor está activo. Resultado: ".$ping);

    // Si el servidor está activo, comprobamos si hay ventas sin notificar
    if($ping){
      // Confirmamos flag que no se estén notificando ventas actualmente
      $notificandoVentas = get_option("jugatoys_notificandoVentas");
      if (!$notificandoVentas) {
        wp_schedule_single_event(time(), "jugatoys_notificarVentasNoNotificadas_action");
      }
    }
  }
  add_action( 'jugatoys_notificarVentasNoNotificadas_action', 'jugatoys_notificarVentasNoNotificadas' );
  add_action( 'jugatoys_cron_venta_no_notificada_action', 'jugatoys_cron_venta_no_notificada' );

  if (! wp_next_scheduled ( 'jugatoys_cron_venta_no_notificada_action')) {
    wp_schedule_event( time(), 'hourly', 'jugatoys_cron_venta_no_notificada_action' );
  }
  

  // Añadimos callback para botón de sincronizar productos
  function jugatoys_sincronizarProductos() { 
    // Verificamos nonce
    if(wp_verify_nonce( $_POST['nonce'], 'jugatoys_sincronizarProductos' )){
      comprobarTodosProductos(); 
    }else{
      http_response_code(400);die();
    }
  };
  add_action('wp_ajax_jugatoys_sincronizarProductos', 'jugatoys_sincronizarProductos');

  // Añadimos script para gestionar funcionalidad
  function jugatoys_admin_scripts($hook) {
    // Solo si es admin   
    if($hook == "settings_page_jugatoys-ajustes"){
      wp_enqueue_script( 'jugatoys-admin', plugins_url(). '/jugatoys/js/jugatoys-admin.js', array(), filemtime( __DIR__. "/js/jugatoys-admin.js" ), true );
      wp_localize_script('jugatoys-admin', 'ajax_var', array(
          'url' => admin_url('admin-ajax.php'),
          'nonce' => wp_create_nonce('jugatoys_sincronizarProductos')
      ));
    }
  }
  add_action( 'admin_enqueue_scripts', 'jugatoys_admin_scripts' );

  // Añadimos check a los productos para controlar la sincronización del precio de los productos de JugaToys
  add_action('woocommerce_product_options_general_product_data', 'woocommerce_product_custom_fields');
  // Following code Saves  WooCommerce Product Custom Fields
  add_action('woocommerce_process_product_meta', 'woocommerce_product_custom_fields_save');

  function woocommerce_product_custom_fields()
  {
      echo '<div class=" product_custom_field ">';
      woocommerce_wp_checkbox( 
        array( 
          'id'            => 'jugatoys_sincronizarPrecio', 
          'label'       => __('Jugatoys - Sincronizar precio:', 'jugatoys'),
          )
        );
      echo '</div>';
  }

  function woocommerce_product_custom_fields_save($post_id)
  {
      $jugatoysSincronizarPrecio = $_POST['jugatoys_sincronizarPrecio'];
      update_post_meta($post_id, 'jugatoys_sincronizarPrecio', $jugatoysSincronizarPrecio);
  }

}

// Función que se correrá únicamente una vez al activar el plugin
function jugatoys_activate(){
  //Sumo un número a numero actualizaciones para que empiece a actualizar los artículos desde el principio 
  $numeroActualizaciones=get_option("jugatoys_numero_actualizacion");
  jugatoys_log("Se suma 1 a jugatoys_numero_actualizacion: ". $numeroActualizaciones . " --> ". ($numeroActualizaciones+1));
  update_option("jugatoys_numero_actualizacion", $numeroActualizaciones+1);
	//Define jugatoys_actualizandoStockProductos a falso para evitar que se quede a true y no vuelva a actualizar stock
  update_option("jugatoys_actualizandoStockProductos", 0);
  jugatoys_log("jugatoys_actualizandoStockProductos se define a 0");
  //Creamos cron que correrá dos veces al día para consultar productos nuevos. Servirá también para hacer la carga inicial
  if (! wp_next_scheduled ( 'jugatoys_nuevos_productos_cron')) {
    wp_schedule_event( time(), 'twicedaily', 'jugatoys_nuevos_productos_cron' );
  }
}

register_activation_hook( __FILE__, 'jugatoys_activate' );

// Función que se correrá únicamente una vez al desactivar el plugin
function jugatoys_deactivate(){
  desactivar_cron("jugatoys_nuevos_productos_cron");
}
register_deactivation_hook( __FILE__, 'jugatoys_deactivate' );
