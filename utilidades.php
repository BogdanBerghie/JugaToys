<?php 

function actualizarStockProductoActual(){
  //Obtenemos objeto de producto actual
  global $product;
  
  actualizarStockIdProducto($product->id);
}

function actualizarStockCarrito(){
  //Obtenemos objeto woocommerce
  global $woocommerce;

  //Obtenemos productos en carrito
  $products = $woocommerce->cart->get_cart();

  //Creamos array vacío para ids de productos
  $aIdProducto = array();
  foreach($products as $item => $product) { 
    $aIdProducto[] = $product['product_id'];
  }
  
  if (empty($aIdProducto)) {
    return false;
  }

  actualizarStockIdProducto($aIdProducto);
}


function actualizarStockIdProducto($aIdProducto = array()){

  if (empty($aIdProducto)) {
    return false;
  }

  if (!is_array($aIdProducto)) {
    $aIdProducto = array($aIdProducto);
  }

  //Preparamos array 
  $aProductId_Sku = array();

  foreach ($aIdProducto as $key => $idProducto) {
    //Obtenemos SKU del producto, para consultar contra la API
    $sku = get_post_meta($idProducto, '_sku', true);
    if (!empty($sku)) {
      $aProductId_Sku[$idProducto] = $sku;
    }
  }

  //Si no se han obtenido SKUs, devolvemos
  if (empty($aProductId_Sku)) {
    return false;
  }

  //Enviamos array de sku y product id
  actualizarStockSku($aProductId_Sku);
  
}

// $aProductId_Sku: array(productId => sku)
function actualizarStockSku($aProductId_Sku = array()){

  //Si no llega aProductId_Sku, devolvemos
  if (empty($aProductId_Sku)) {
    return false;
  }

  //Todo: VERIFICAR QUE FUNCIONA  

  //Iniciamos API
  $api = new JugaToysAPI();

  //Consultamos contra la API
  $skus = array_values($aProductId_Sku);
  $productInfo = $api->productInfo($skus);

  if (empty($productInfo)) {
    return false;
  }

  //Verificamos si la respuesta es correcta
  if ($productInfo->Result == "OK") {

    //Verificamos que haya datos
    if (!empty($productInfo->Data)) {

      //Si solo devuelve un dato, quizá devuelva el objeto directamente en vez de array. Confirmamos, y convertimos en array si es preciso para que encaje en lógica
      if (!is_array($productInfo->Data)) {
        $productInfo->Data = array($productInfo->Data);
      }

      //Recorremos todos los productos devueltos
      foreach ($productInfo->Data as $key => $pData) {

        if (!empty($pData->Sku_Provider) ) {
          //Confirmamos que hayamos solicitado el SKU
          $idProducto = array_search($pData->Sku_Provider, $aProductId_Sku);
          if ($idProducto !== false) {
            //Si coincide el SKU, actualizamos stock
            $stock = absint($pData->Stock);
            wc_update_product_stock( $idProducto,  $stock , 'set' );
            update_post_meta( $idProducto, '_jugatoys_ultima_actualizacion', time() );
          }

        }

      }

    }
  }
}

//Función que se llama dos veces al día para comprobar nuevos productos
function comprobarTodosProductos(){

  //Checkeamos si se ha lanzado alguna vez o es la primera. 
  $fechaUltimaComprobacionProductos = get_option("jugatoys_fechaUltimaComprobacionProductos");
  
  $api = new JugaToysAPI();
  $productInfo = $api->productInfo(array(), $fechaUltimaComprobacionProductos);

  if ($productInfo->result == "OK") {

    $productos = $productInfo->Data;
    foreach ($productos as $key => $producto) {
      $sku = $producto->Sku_Provider;
      if (!existeSKU($sku)) {
        altaProducto($producto);
      }
    }
    
    update_option("jugatoys_fechaUltimaComprobacionProductos", Date( "Y-m-d H:i", time() ));
  
  }

}

//Función que se llama desde cron recursivamente
function actualizarStockProductos(){

  //Activamos flag indicando que se están actualizando productos
  update_option("jugatoys_actualizandoStockProductos", true);

  $opciones = get_option( 'jugatoys_settings' );

  //Obtenemos los productos con la configuración establecida
  $args = array(
    "numberposts" => $opciones['sincronizaciones_diarias_numero_productos'],
    "post_type" =>  "product",
    'meta_query' => array(
        array(
          'key'     => '_jugatoys_ultima_actualizacion',
          'value'   => time() - (intval($opciones['sincronizaciones_diarias_minutos_consulta']) * 60),
          'type'    => 'numeric',
          'compare' => '<=',
        ),
      ),
  );

  $productos = get_posts( $args );

  //Si hay productos para actualizar, tratamos
  if (!empty($productos)) {
    
    $aIdProdcutos = array();

    foreach ($productos as $key => $producto) {
      $aIdProdcutos[] = $producto->ID;
    }

    //Actualizamos productos
    actualizarStockIdProducto($aIdProdcutos);

    //Hacemos que función sea recursiva
    wp_schedule_single_event(time(), "jugatoys_actualizar_stock_productos");  
  }else{

    //Quitamos flag de actualización
    update_option("jugatoys_actualizandoStockProductos", false);

  }
  

}


function existeSKU($sku){
  $product_id = wc_get_product_id_by_sku($sku);
  return ($product_id == 0) ? false : $product_id;
}

// function existeSKU($sku){
//   global $wpdb;
//   $product_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku ) );
//   return $product_id;
// }


function altaProducto($producto){

  try {

    $new_simple_product = new WC_Product_Simple();

    $new_simple_product->set_name($producto['Provider_Name']);
    $new_simple_product->set_sku($producto['Sku_Provider']);
    $new_simple_product->set_stock($producto['Stock']);
    $new_simple_product->set_stock_quantity($producto['Stock']);
    $new_simple_product->set_stock_status("instock");
    $new_simple_product->set_price($producto['PVP']);
    $new_simple_product->set_regular_price($producto['PVP']);
    $new_simple_product->set_manage_stock("yes");
    $new_simple_product->set_catalog_visibility( 'visible' );
    $new_simple_product->set_downloadable( 'no' );
    $new_simple_product->set_virtual( 'no' );

    $attach_id = descargarImagen($new_simple_product->get_id(), $producto['Image'], $producto['Provider_Name']);
    $new_simple_product->set_image_id($attach_id);

    $new_simple_product->save();

    update_post_meta( $new_simple_product->get_id(), '_jugatoys_ultima_actualizacion', time() );

    return $new_simple_product->get_id();

  } catch (Exception $ex) {

    jugatoys_log("----------------------------------------------------");
    jugatoys_log("Intentando añadir producto");
    jugatoys_log($producto);
    jugatoys_log($ex->getMessage());
    jugatoys_log("----------------------------------------------------\r\n");

    return false;
  }

}



// Función sustituida por otra versión más correcta, que hace uso de la clase producto de WC. Más correcta en cuanto a posibles cambios, cediendo la lógica al producto, en vez de introducirlo manualmente como está realizado abajo. Lo dejo para que te sirva de info.
// function altaProducto($producto){

//   $post_id = wp_insert_post( array(
//     'post_title' => $producto['Provider_Name'],
//     'post_content' => '',
//     'post_status' => 'publish',
//     'post_type' => "product",
//   ) );

//   if ($post_id) {

//     update_post_meta( $post_id, '_price', $producto['PVP'] );
//     update_post_meta( $post_id, '_regular_price', $producto['PVP'] );
//     update_post_meta( $post_id, '_stock', $producto['Stock'] );
//     update_post_meta( $post_id, '_stock_status', 'instock');
//     update_post_meta( $post_id, '_sku', $producto['Sku_Provider'] );

//     $attach_id = descargarImagen($post_id, $producto['Image'], $producto['Provider_Name']);
//     update_post_meta( $post_id, '_thumbnail_id', $attach_id );  

//     update_post_meta( $post_id, "_manage_stock", "yes");
//     update_post_meta( $post_id, '_visibility', 'visible' );
//     update_post_meta( $post_id, '_downloadable', 'no' );
//     update_post_meta( $post_id, '_virtual', 'no' );

//   }

//   return $post_id;

// }

function descargarImagen($post_id, $url, $titulo){
  $upload_dir = wp_upload_dir();
  $image_data = file_get_contents($url);
  $filename = basename($url);

  if(wp_mkdir_p($upload_dir['path']))
      $file = $upload_dir['path'] . '/' . $filename;
  else
      $file = $upload_dir['basedir'] . '/' . $filename;
  file_put_contents($file, $image_data);

  $wp_filetype = wp_check_filetype($filename, null );
  $attachment = array(
      'post_mime_type' => $wp_filetype['type'],
      'post_title' => $titulo,
      'post_content' => '',
      'post_status' => 'inherit'
  );
  $attach_id = wp_insert_attachment( $attachment, $file, $post_id );
  require_once(ABSPATH . 'wp-admin/includes/image.php');
  $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
  wp_update_attachment_metadata( $attach_id, $attach_data );

  set_post_thumbnail( $post_id, $attach_id );

  return $attach_id;
}

function notificarVenta($orderId){
  // Notificar la venta a jugatoys

  $lineas = array();
  $order = wc_get_order( $orderId );
  foreach ($order->get_items() as $item_key => $item ){
    $producto = $item->get_product();
    $lineas[] = (object) array(
      "Sku_Provider" => $producto->get_sku(),
      "Quantity" => $item->get_quantity(),
      "PVP" => $producto->get_price(),
      "IVA" => ($item_data['tax_class']) ? $item_data['tax_class'] : 21
    );
  }

  $api = new JugaToysAPI();

  $respuesta = $api->ticketInsert($orderId, 'TPV', $lineas);

}

function jugatoys_log( $msg ){
  error_log( "\r\n" . Date("Y-m-d H:i:s"). " - " . print_r($msg, true), 3, __DIR__ . "/errors.log");
}


function jugatoys_configuracion_default(){
  $opciones = get_option( 'jugatoys_settings' );
  if (empty($opciones['sincronizaciones_diarias'])) {
    $opciones['sincronizaciones_diarias'] = 24; 
  }
  if (empty($opciones['sincronizaciones_diarias_numero_productos'])) {
    $opciones['sincronizaciones_diarias_numero_productos'] = 20;
  }
  if (empty($opciones['sincronizaciones_diarias_minutos_consulta'])) {
    $opciones['sincronizaciones_diarias_minutos_consulta'] = 60;
  }
}



//función de pruebas que se llama desde HOST/wp-admin/admin-ajax.php?action=pruebaAPI
function pruebaAPI(){

  ini_set('display_errors', '1');
  ini_set('display_startup_errors', '1');
  error_reporting(E_ALL);

  var_dump(existeSKU("288-288-2"));

  wp_die();


  $producto = array(
    "Provider_Name" => "test desde funcion altaProducto",
    "PVP" => 288,
    "Stock" => 10,
    "Image" => "https://destinonegocio.com/wp-content/uploads/2018/09/ciclo-de-vida-de-un-producto-1030x687.jpg",
    "Sku_Provider" => "288-288-4"

  );
  var_dump(altaProducto($producto));

  actualizarStockProductos();
  wp_die();

  echo time();
  echo '<pre>'; print_r( _get_cron_array() ); echo '</pre>';

  wp_die();


  $producto = array(
    "Provider_Name" => "test desde funcion altaProducto",
    "PVP" => 288,
    "Stock" => 10,
    "Image" => "https://destinonegocio.com/wp-content/uploads/2018/09/ciclo-de-vida-de-un-producto-1030x687.jpg",
    "Sku_Provider" => "288-288-2"

  );
  var_dump(altaProducto($producto));

  wp_die();

  descargarImagen(13, "https://i.pinimg.com/originals/70/c4/0d/70c40d993df6a85f53c811cb8d14f3b8.png");

  wp_die();

  //Iniciamos API
  $api = new JugaToysAPI();

  //Consultamos contra la API
  $productInfo = $api->productInfo(array(), "2021-07-01 10:00");

  var_dump($productInfo);
  

  // echo time();
  // echo '<pre>'; print_r( _get_cron_array() ); echo '</pre>';

  wp_die();
}



function desactivar_cron($cron_name){
  $timestamp = wp_next_scheduled( $cron_name );
  wp_unschedule_event( $timestamp, $cron_name );
}

 ?>