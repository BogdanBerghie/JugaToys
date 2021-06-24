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

  //XXXXX - Todo: añadir opción oculto / gestionar opción (woocommerce?) para ocultar ciertos productos
  //Se gestiona mediante visibilidad de posts


  //Todo: crear cron para sincronización de stock
  //IDEA: Esta sincronización de stock de todos los productos se debe lanzar X veces diariamente (configurable en las opciones). Se deberían de ir consultando productos de X en X (¿Convendría que fuese configurable en opciones?). 
  //Una forma de realizar esto sería añadiendo un meta dato a cada producto, que indique la fecha de última actualización. En una función que será recursiva, consultar X número de productos en función a la fecha de actualización. Si hay productos, establecer cron para dentro de X tiempo. Si no hay más productos (ya ha corrido suficientes veces, y se han actualizado todos), establecer el cron para el próximo día.

  //Todo: crear cron para consultar si hay nuevos productos a partir de la última fecha de consulta (guardar como opción)
  //IDEA: Cron diario como el anterior, pero esta vez consultando si hay productos nuevos a partir de X fecha. Entiendo que no haría falta recursividad

  function cron_events_actualizar_productos() {
      comprobarTodosProductos();
    }
  add_action( 'actualizar_productos_cron', 'cron_events_actualizar_productos' );

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


  // Todo: Añadir hoook/acción para notificar la venta a jugatoys
  function jugatoys_action_pago_realizado( $orderId ) { 
    notificarVenta($orderId); // Todo en utilidades.php
  }; 
  // En duda, otro posible hook
  // woocommerce_payment_complete_reduce_order_stock
  // https://woocommerce.github.io/code-reference/hooks/hooks.html
  add_action( 'woocommerce_payment_complete', 'jugatoys_action_pago_realizado', 10, 1 ); 


}

// Función que se correrá únicamente una vez al activar el plugin
function jugatoys_activate(){
  // Todo: Sincronizar todos los productos: nombre, descripción, imagen, precio y stock
  // IDEA: Únicamente debería hacerse una vez, después siempre se actualizará en base a la última fecha de actualización (así se reduce la carga de las consultas). Utilizar action de wp al activar el plugin
  comprobarTodosProductos(); // Todo en utilidades.php
}

register_activation_hook( __FILE__, 'jugatoys_activate' );


?>