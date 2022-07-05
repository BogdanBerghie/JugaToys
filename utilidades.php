<?php

function actualizarStockProductoActual()
{
    //Obtenemos objeto de producto actual
    global $product;

    actualizarStockIdProducto($product->id);
}

function actualizarStockCarrito()
{
    //Obtenemos objeto woocommerce
    global $woocommerce;

    //Obtenemos productos en carrito
    $products = $woocommerce->cart->get_cart();

    //Creamos array vacío para ids de productos
    $aIdProducto = array();
    foreach ($products as $item => $product) {
        $aIdProducto[] = $product['product_id'];
    }

    if (empty($aIdProducto)) {
        return false;
    }

    actualizarStockIdProducto($aIdProducto);
}

function actualizarStockIdProducto($aIdProducto = array())
{
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
        // $sku = get_post_meta($idProducto, '_sku', true);
        $sku = get_post_meta($idProducto, '_sku_jugatoys', true);
        $product = wc_get_product($idProducto);
        if (empty($sku)) {
            $sku = $product->get_sku();
        }
        if (!empty($sku)) {
            $aProductId_Sku[$idProducto] = $sku;
        }
    }
    
    // jugatoys_log(["Corriendo actualizarStockIdProducto aIdProducto: ", $aIdProducto]);
    jugatoys_log(["Corriendo actualizarStockIdProducto aProductId_Sku: ", $aProductId_Sku]);

    //Si no se han obtenido SKUs, devolvemos
    if (empty($aProductId_Sku)) {
        return false;
    }

    //Enviamos array de sku y product id
    actualizarStockSku($aProductId_Sku);
}

// $aProductId_Sku: array(productId => sku)
function actualizarStockSku($aProductId_Sku = array())
{

    //Si no llega aProductId_Sku, devolvemos
    if (empty($aProductId_Sku)) {
        return false;
    }

    jugatoys_log(["Corriendo actualizarStockSku: ", $aProductId_Sku]);

    //Iniciamos API
    $api = new JugaToysAPI();

    //Consultamos contra la API
    $skus = array_values($aProductId_Sku);
    
    $numeroActualizacion = get_option('jugatoys_numero_actualizacion');

    // v1.4.2 - Cambiamos llamada de productInfo a stockPrice
    $productInfo = $api->stockPrice($skus);

    if (empty($productInfo)) {
        // Si no hay productInfo, es que la API no ha dado una respuesta correcta.
        // A fin de evitar que se quede en bucle consultando el stock del mismo producto, marcamos numero_actualizacion para que no se vuelva a consultar
        foreach ($aProductId_Sku as $idProducto => $sku) {
            update_post_meta($idProducto, '_jugatoys_numero_actualizacion', $numeroActualizacion);
        }
        return false;
    }

    //Verificamos si la respuesta es correcta
    if ($productInfo->Result == "OK") {

        //Verificamos que haya datos
        if (!empty($productInfo->Data)) {
            // jugatoys_log(["productInfo response data: ",$productInfo->Data]);

            //Si solo devuelve un dato, quizá devuelva el objeto directamente en vez de array. Confirmamos, y convertimos en array si es preciso para que encaje en lógica
            if (!is_array($productInfo->Data)) {
                $productInfo->Data = array($productInfo->Data);
            }

            //Recorremos todos los productos devueltos
            foreach ($productInfo->Data as $key => $pData) {
                if (!empty($pData->Sku_Provider)) {
                    //Confirmamos que hayamos solicitado el SKU.
                    $idProducto = array_search($pData->Sku_Provider, $aProductId_Sku);
                    if ($idProducto !== false) {
                        //Si coincide el SKU, actualizamos stock
                        $stock = $pData->Stock;
                        //BOGDAN v1.3.4 - A peticion del cliente se configura que tambien actualice el prescio y el titulo de articulos al interactuar con ellos.
                        $PVP = $pData->PVP;
                        //BOGDAN v1.3.9 - Los artículos con un stock negativo los mostrará como Stock = 0.
                        $product = wc_get_product($idProducto);
                        if($stock<=0){
                            jugatoys_log("Stock menor que 0 en SKU: ". $pData->Sku_Provider. " Stock se pone a 0");
                            wc_update_product_stock($idProducto, 0, 'set');
                            
                            // V. 1.4.4 - Si el stock de un producto es <=0 establecemos como borrador
                            // V. 1.4.4 - Fix - No pasamos stock <= 0 a borradores
                            // $product->set_status('draft');
                            // $product->save();
                        }else{
                            wc_update_product_stock($idProducto, $stock, 'set');

                            // V. 1.4.4 - Si el stock de un producto es >0 quitamos borrador
                            $product->set_status('publish');
                            $product->save();
                        } 
                        //BOGDAN v1.3.6 - A peticion de Ander. No quiere que se actualice el nombre.
                        // $Product_Name = $pData->Product_Name;
                        // $productIdBySKU = wc_get_product_id_by_sku($pData->Sku_Provider);
                        // wp_update_post([
                            // 'ID' => $productIdBySKU,
                            // 'post_title' => $Product_Name,
                        // ]);
                        //BOGDAN - v1.2.6

                        // wc_update_product_stock($idProducto, $stock, 'set');

                        // Verificamos si tenemos que sincronizar precio
                        $sincronizarPrecio = get_post_meta($idProducto, 'jugatoys_sincronizarPrecio', true);
                        if(!empty($sincronizarPrecio)){
                            update_post_meta($idProducto, '_price', $PVP);
                        }
                        //BOGDAN v1.3.6
                        jugatoys_log([
                            "SKU de articulo seleccionado: ". $pData->Sku_Provider,
                            //BOGDAN v1.3.6 - A peticion de Ander. No quiere que se actualice el nombre.
                            //"Descripcion actualizado: ". $Product_Name,
                            //BOGDAN - v1.2.6
                            "precio actualizado ". $sincronizarPrecio . ": ". $PVP,
                            "Stock actualizado: ". $stock
                                ]);
                        //BOGDAN v1.3.6
                        //BOGDAN
                    }
                }
            }

            foreach ($aProductId_Sku as $idProducto => $sku) {
                update_post_meta($idProducto, '_jugatoys_numero_actualizacion', $numeroActualizacion);
            }
        } else {
            //Si llega data pero llega vacío, establecemos los productos como actualizados
            foreach ($aProductId_Sku as $postId => $sku) {
                update_post_meta($postId, '_jugatoys_numero_actualizacion', $numeroActualizacion);
            }
        }
    } else {

        // foreach ($aProductId_Sku as $postId => $sku) {
        //   update_post_meta($postId, '_jugatoys_numero_actualizacion', $numeroActualizacion );
        // }
        //En caso de respuesta incorrecta, marcamos consultamos indifivucalmente producto
        //SI tiene más de un elemento, puede que solo esté fallando uno de los elementos, por tanto consultamos todos.
        if (count($aProductId_Sku) > 1) {
            foreach ($aProductId_Sku as $key => $value) {
                $aNuevoProductId_Sku = array($key => $value);
                actualizarStockSku($aNuevoProductId_Sku);
            }
        } else {
            //Si tiene un elemento, asumimos que está incorrecto y actualiamos la opción numero actualización
            foreach ($aProductId_Sku as $postId => $sku) {
                update_post_meta($postId, '_jugatoys_numero_actualizacion', $numeroActualizacion);
            }
        }
    }
}

//Función que se llama dos veces al día para comprobar nuevos productos
function comprobarTodosProductos()
{
    jugatoys_log("Corriendo comprobarTodosProductos");

    //Checkeamos si se ha lanzado alguna vez o es la primera.
    $fechaUltimaComprobacionProductos = get_option("jugatoys_fechaUltimaComprobacionProductos");
    if ($fechaUltimaComprobacionProductos == false) {
        $fechaUltimaComprobacionProductos = "2000-07-01";
    }

    $api = new JugaToysAPI();
    $productInfo = $api->productInfo(array(), $fechaUltimaComprobacionProductos);

    $productosInsertados = 0;
    $productosPasados = 0;

    if ($productInfo) {
        if ($productInfo->Result == "OK") {
            $productos = $productInfo->Data;
            foreach ($productos as $key => $producto) {

                // TODO: Test, quitar despues
//                if ($productosInsertados > 0 || $productosPasados > 5) {
//                    wp_die();
//                }
//                if (empty($producto->UrlImage)) {
//                    if ($producto->Stock == 0 || $producto->PVP == 0) {
//                        $productosPasados++;
//                        continue;
//                    }
//                }
                // /TODO

                $producto->Sku = $producto->Sku_Provider;
                $idProducto = existeSKU($producto->Sku);
                // BOGDAN v1.3.9 - Ya no se dará de alta los artículos sin stock
                // if ($producto->Stock <= 0) {
                //     jugatoys_log(["NO STOCK SE IGNORA" , $producto]);
                //     $productosPasados++;
                //     continue;
                // }
                
                if (!$idProducto) {
                    jugatoys_log("ERROR - SKU no localizado: " . $producto->Sku);
                    if (altaProducto((array)$producto)) {
                        $productosInsertados++;
                        jugatoys_log($producto);
                    }
                } else {
                    // V. 1.4.4 - Desactivamos actualización de stock. Solo actualizará desde stockPrice
                    // jugatoys_log("ENCONTRADO - " . $producto->Sku. " CON EAN: ". $producto->EAN);
                    // update_post_meta($idProducto, '_sku_jugatoys', $producto->Sku_Provider);
                    
                    $EAN_tratado = conseguirUnSoloEAN($producto->EAN);
                    update_post_meta($idProducto, '_alg_ean', $EAN_tratado);
                }
            }

            update_option("jugatoys_fechaUltimaComprobacionProductos", Date("Y-m-d H:i", time()));
        }
    }

    return $productosInsertados;
}

//Función que se llama desde cron recursivamente
function actualizarStockProductos()
{

    //Obtenemos número de actualización actual
    $numeroActualizacion = get_option('jugatoys_numero_actualizacion');
    if (!$numeroActualizacion) {
        $numeroActualizacion = 1;
        update_option('jugatoys_numero_actualizacion', $numeroActualizacion);
    }

    jugatoys_log("Nº " . $numeroActualizacion . " - Corriendo actualizarStockProductos");

    //Activamos flag indicando que se están actualizando productos
    update_option("jugatoys_actualizandoStockProductos", 1);

    $opciones = get_option('jugatoys_settings');

    //Obtenemos los productos con la configuración establecida
    $args = array(
        "numberposts" => $opciones['sincronizaciones_diarias_numero_productos'],
        "post_type" => "product",
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => '_sku',
                'compare' => 'EXISTS',
            ),
            array(
                'relation' => 'OR',
                array(
                    'key' => '_jugatoys_numero_actualizacion',
                    'value' => $numeroActualizacion,
                    'type' => 'numeric',
                    'compare' => '<',
                ),
                array(
                    'key' => '_jugatoys_numero_actualizacion',
                    'compare' => 'NOT EXISTS',
                ),
            ),
        ),
    );

    $productos = get_posts($args);

    //Si hay productos para actualizar, tratamos
    if (!empty($productos)) {
        //BOGDAN V.1.4.1 - Antes de actualizar el stock se hará una comprobación para dar de alta un artículo que en un primer momento se obvio por falta de stock
        // comprobarTodosProductos();
        //BOGDAN V.1.4.1

        $aIdProdcutos = array();

        foreach ($productos as $key => $producto) {
            $aIdProdcutos[] = $producto->ID;
        }

        // jugatoys_log("Nº " . $numeroActualizacion . " - Obtenidos: ");
        // jugatoys_log($productos);

        //Actualizamos productos
        actualizarStockIdProducto($aIdProdcutos);

        //Quitamos flag de actualización
        update_option("jugatoys_actualizandoStockProductos", 0);
        
        //Hacemos que función sea recursiva
        wp_schedule_single_event(time(), "jugatoys_actualizar_stock_productos");
    } else {
        jugatoys_log("Nº " . $numeroActualizacion . " - Finalizando actualizarStockProductos");

        //Quitamos flag de actualización
        update_option("jugatoys_actualizandoStockProductos", 0);

        //Actualizamos numero de actualizacion
        $numeroActualizacion++;
        update_option('jugatoys_numero_actualizacion', $numeroActualizacion);
    }
}

function existeSKU($sku)
{
    $skuDeJugaToys = $sku;

    //Primero probamos con el SKU completo (10000-SKU)
    $product_id = wc_get_product_id_by_sku($sku);
    if ($product_id) {
        jugatoys_log(["SKU COMPLETO: ". $sku]);
        return $product_id;
    }

    // Probamos quitando el codigo del proveedor: 10000-SKU CASO 0
    $pos = strpos($sku, '-');
    if ($pos !== false) {
        $sku = substr($sku, $pos + 1);
    }
    $product_id = wc_get_product_id_by_sku($sku);
    if ($product_id) {

        //BOGDAN v1.3.5 - Como ha encontrado el articulo sin el codigo proveedor vamos a actualizar el SKU para que sean iguales en página web tpv
        
        jugatoys_log(["SKU SIN COD. PROVEEDOR. NUEVO SKU: ". $skuDeJugaToys. " REMPLAZARA A: ". $sku]);
        update_post_meta($product_id, '_sku', $skuDeJugaToys);

        //BOGDAN v1.3.5
        return $product_id;
    }

    //si no se ha encontrado, tratamos caso 1
    // 2671 API =>  WP 02671
    $nuevo_sku = intval($sku);
    $product_id = wc_get_product_id_by_sku($nuevo_sku);
    if ($product_id) {
        jugatoys_log("SKU CASUISTICA 1: ");
        return $product_id;
    }

    //si no se ha encontrado, tratamos caso 2
    // 500TT API => WP TT500
    $i = 0;
    for ($i = 0; $i < strlen($sku); $i++) {
        if (!is_numeric($sku[$i])) {
            break;
        }
    }

    //Si hay alguna letra iniciamos comprobaciones
    if ($i != strlen($sku)) {
        $numeros_sku = substr($sku, 0, $i);
        $letras_sku = substr($sku, $i);
        $product_id = wc_get_product_id_by_sku($letras_sku . $numeros_sku);
        if ($product_id) {
            return $product_id;
        }

        //si no se ha encontrado, tratamos caso 2
        // 1PIRRITX API => WP PIRRITX-1
        $product_id = wc_get_product_id_by_sku($letras_sku . '-' . $numeros_sku);
        if ($product_id) {
            jugatoys_log("SKU CASUISTICA 2: ");
            return $product_id;
        }
    }

    return false;
}

// function existeSKU($sku){
//   global $wpdb;
//   $product_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku ) );
//   return $product_id;
// }

function altaProducto($producto)
{
    if (empty($producto['Product_Name']) || empty($producto['Sku_Provider'])) {
        return false;
    }

    jugatoys_log("Corriendo altaProducto: " . $producto['Product_Name']);

    try {
        $new_simple_product = new WC_Product_Simple();

        $new_simple_product->set_name($producto['Product_Name']);
        $new_simple_product->set_sku($producto['Sku']);
        $new_simple_product->set_stock($producto['Stock']);
        $new_simple_product->set_stock_quantity($producto['Stock']);
        $new_simple_product->set_stock_status("instock");
        $new_simple_product->set_price($producto['PVP']);
        $new_simple_product->set_regular_price($producto['PVP']);
        $new_simple_product->set_manage_stock("yes");
        $new_simple_product->set_catalog_visibility('visible');
        $new_simple_product->set_downloadable('no');
        $new_simple_product->set_virtual('no');

        if (!empty($producto['UrlImage'])) {
            jugatoys_log("altaProducto - Obteniendo imagen:");

            $options = get_option('jugatoys_settings');
            $puerto = $options['puerto'];
            $url = parse_url($options['url']);
            $url['port'] = $puerto;

            $urlImagen = $url['scheme'] . "://" . $url['host'] .":". $url['port'] . str_replace("/TPV", "", $url['path']) . $producto['UrlImage'];
            $nombreImagen = basename($producto['UrlImage']);

            jugatoys_log("URL de la IMAGEN:". $urlImagen);
            
            $attach_id = descargarImagen($new_simple_product->get_id(), $urlImagen, $nombreImagen);
            $new_simple_product->set_image_id($attach_id);

            jugatoys_log("altaProducto - Imagen guardada: " . $attach_id);
        }

        $new_simple_product->set_status("draft");

        $new_simple_product->save();

        //Guardamos EAN
        if (!empty($producto['EAN'])) {
            update_post_meta($new_simple_product->get_id(), '_ean', $producto['EAN']);

            $EAN_tratado = conseguirUnSoloEAN($producto['EAN']);

            update_post_meta($new_simple_product->get_id(), '_alg_ean', $EAN_tratado);
        }

        //Guardamos marca
        if (!empty($producto["Brand_Supplier_Name"])) {
            wp_set_object_terms($new_simple_product->get_id(), $producto["Brand_Supplier_Name"], "pwb-brand");
        }

        //Guardamos sku de jugatoys
        if (!empty($producto['Sku_Provider'])) {
            update_post_meta($new_simple_product->get_id(), '_sku_jugatoys', $producto['Sku_Provider']);
        }

        $numeroActualizacion = get_option('jugatoys_numero_actualizacion');
        update_post_meta($new_simple_product->get_id(), '_jugatoys_numero_actualizacion', $numeroActualizacion);

        // Opción de sincronización de precio por defecto activa
        update_post_meta($new_simple_product->get_id(), 'jugatoys_sincronizarPrecio', "yes");

        jugatoys_log("altaProducto - OK: " . $new_simple_product->get_id());

        return $new_simple_product->get_id();
    } catch (Exception $ex) {
        jugatoys_log("----------------------------------------------------");
        jugatoys_log("Error intentando añadir producto");
        jugatoys_log($ex->getMessage());
        jugatoys_log($producto);
        jugatoys_log("----------------------------------------------------\r\n");

        return false;
    }
}

// Función sustituida por otra versión más correcta, que hace uso de la clase producto de WC. Más correcta en cuanto a posibles cambios, cediendo la lógica al producto, en vez de introducirlo manualmente como está realizado abajo. Lo dejo para que te sirva de info.
// function altaProducto($producto){

//   $post_id = wp_insert_post( array(
//     'post_title' => $producto['Product_Name'],
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

//     $attach_id = descargarImagen($post_id, $producto['Image'], $producto['Product_Name']);
//     update_post_meta( $post_id, '_thumbnail_id', $attach_id );

//     update_post_meta( $post_id, "_manage_stock", "yes");
//     update_post_meta( $post_id, '_visibility', 'visible' );
//     update_post_meta( $post_id, '_downloadable', 'no' );
//     update_post_meta( $post_id, '_virtual', 'no' );

//   }

//   return $post_id;

// }

function descargarImagen($post_id, $url, $titulo)
{
    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents($url);
    $filename = basename($url);

    if (wp_mkdir_p($upload_dir['path'])) {
        $file = $upload_dir['path'] . '/' . $filename;
    } else {
        $file = $upload_dir['basedir'] . '/' . $filename;
    }

    file_put_contents($file, $image_data);

    $wp_filetype = wp_check_filetype($filename, null);
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => $titulo,
        'post_content' => '',
        'post_status' => 'inherit',
    );
    $attach_id = wp_insert_attachment($attachment, $file, $post_id);
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attach_data = wp_generate_attachment_metadata($attach_id, $file);
    wp_update_attachment_metadata($attach_id, $attach_data);

    set_post_thumbnail($post_id, $attach_id);

    return $attach_id;
}

function notificarVenta($orderId)
{
    // Notificar la venta a jugatoys
    $lineas = array();
    $order = wc_get_order($orderId);

    // Obtenemos líneas
    foreach ($order->get_items() as $item_id => $item) {
        $producto = $item->get_product();
        $pvp_string = $producto->get_price();
        $item_data = $item->get_data();
        $idProducto = $item->get_product_id();
        $sku = get_post_meta($idProducto, '_sku_jugatoys', true);
        if (empty($sku)) {
            $producto->get_sku();
        }
        $lineas[] = (object) array(
            "Sku_Provider" => $sku,
            "Quantity" => $item->get_quantity(),
            "PVP" => floatval($pvp_string),
            "IVA" => ($item_data['tax_class']) ? $item_data['tax_class'] : 21,
        );
    }

    // Obtenemos cliente
    $idUsuario = $order->get_customer_id();
    $usuario = get_user_by('id', $idUsuario);

    // TODO: Verificar que se obtienen los datos correctamente
    // Datos de usuario
    $aDatosUsuario = (object) array(
        "CIF" => (!empty($usuario->user_nif)) ? $usuario->user_nif : null,
        "email" => $usuario->user_email,
        "Name" => $usuario->first_name . " " . $usuario->last_name,
        // "Mobile" => $usuario->billing_phone,
        // "Address" => $usuario->billing_address_1,
        // "City" => $usuario->billing_city,
        // "PostalCode" => $usuario->billing_postcode,
        // "Country" => $usuario->billing_country,
        "Mobile" => null,
        "Address" => null,
        "City" => null,
        "PostalCode" => null,
        "Country" => null,
        "Phone" => null
    );
    // Datos de cliente
    $_billing_nif = get_post_meta($orderId, '_billing_nif', true);
    $aDatosBilling = (object) array(
        "CIF" => (!empty($_billing_nif)) ? $_billing_nif : null,
        "email" => $order->get_billing_email(),
        "Name" => $order->get_billing_first_name() . " " . $order->get_billing_last_name(),
        "Mobile" => $order->get_billing_phone(),
        "Address" => $order->get_billing_address_1() . " - " . $order->get_billing_address_2(),
        "City" => $order->get_billing_city(),
        "PostalCode" => $order->get_billing_postcode(),
        "Country" => $order->get_billing_country(),
        "Phone" => null
    );
    $_shipping_nif = get_post_meta($orderId, '_shipping_nif', true);
    $_shipping_email = get_post_meta($orderId, '_shipping_email', true);
    $aDatosShipping = (object) array(
        "CIF" => (!empty($_shipping_nif)) ? $_shipping_nif : null,
        "email" => (!empty($_shipping_email)) ? $_shipping_email : null,
        "Name" => $order->get_shipping_first_name() . " " . $order->get_shipping_last_name(),
        "Mobile" => $order->get_shipping_phone(),
        "Address" => $order->get_shipping_address_1() . " - " . $order->get_shipping_address_2(),
        "City" => $order->get_shipping_city(),
        "PostalCode" => $order->get_shipping_postcode(),
        "Country" => $order->get_shipping_country(),
        "Phone" => null
    );
    // Cruzamos dato dando prioridad al de billing (parte de wc)
    $aDatosFinales = (object) array(
        "CIF" => (!empty($aDatosBilling->CIF) ? $aDatosBilling->CIF : $aDatosUsuario->CIF),
        "email" => (!empty($aDatosBilling->email) ? $aDatosBilling->email : $aDatosUsuario->email),
        "Name" => (!empty($aDatosBilling->Name) ? $aDatosBilling->Name : $aDatosUsuario->Name),
        "Mobile" => (!empty($aDatosBilling->Mobile) ? $aDatosBilling->Mobile : $aDatosUsuario->Mobile),
        "Address" => (!empty($aDatosBilling->Address) ? $aDatosBilling->Address : $aDatosUsuario->Address),
        "City" => (!empty($aDatosBilling->City) ? $aDatosBilling->City : $aDatosUsuario->City),
        "PostalCode" => (!empty($aDatosBilling->PostalCode) ? $aDatosBilling->PostalCode : $aDatosUsuario->PostalCode),
        "Country" => (!empty($aDatosBilling->Country) ? $aDatosBilling->Country : $aDatosUsuario->Country),
        "Phone" => (!empty($aDatosBilling->Phone) ? $aDatosBilling->Phone : $aDatosUsuario->Phone),
    );
    $aDatosFinales = (object) array(
        "CIF" => (!empty($aDatosFinales->CIF) ? $aDatosFinales->CIF : $aDatosShipping->CIF),
        "email" => (!empty($aDatosFinales->email) ? $aDatosFinales->email : $aDatosShipping->email),
        "Name" => (!empty($aDatosFinales->Name) ? $aDatosFinales->Name : $aDatosShipping->Name),
        "Mobile" => (!empty($aDatosFinales->Mobile) ? $aDatosFinales->Mobile : $aDatosShipping->Mobile),
        "Address" => (!empty($aDatosFinales->Address) ? $aDatosFinales->Address : $aDatosShipping->Address),
        "City" => (!empty($aDatosFinales->City) ? $aDatosFinales->City : $aDatosShipping->City),
        "PostalCode" => (!empty($aDatosFinales->PostalCode) ? $aDatosFinales->PostalCode : $aDatosShipping->PostalCode),
        "Country" => (!empty($aDatosFinales->Country) ? $aDatosFinales->Country : $aDatosShipping->Country),
        "Phone" => (!empty($aDatosFinales->Phone) ? $aDatosFinales->Phone : $aDatosShipping->Phone),
    );

    // Opcional - Quitamos campos null
    foreach ($aDatosFinales as $key => $value) {
        if (is_null($value)) {
            unset($aDatosFinales->$key);
        }
    }


    //TEST TODO
    jugatoys_log("----------------------------------------------------");
    jugatoys_log("Realizando notificar venta");
    jugatoys_log($lineas);

    // var_dump($lineas);
    
    $api = new JugaToysAPI();

    // $respuesta = $api->ticketInsert($orderId, 'TPV', $lineas);
    $respuesta = $api->ticketInsert($orderId, 'V', $lineas, $aDatosFinales);

    // Si no hay respuesta correcta, marcamos flag de venta no notificada
    if (!$respuesta) {
        update_post_meta($orderId, 'jugatoys_ventaNoNotificada', true);        
    }

    // var_dump($respuesta);
    return $respuesta;

}

function jugatoys_log($msg)
{
    $errorPath = ini_get('error_log');
    $errorPath = str_replace('\\', '/', $errorPath);
    $log_file = str_replace('php_error.log', 'jugatoys.log', $errorPath);
    error_log("\r\n" . Date("Y-m-d H:i:s") . " - " . print_r($msg, true), 3, $log_file);
}

function jugatoys_configuracion_default()
{
    $bGuardar = false;
    $opciones = get_option('jugatoys_settings');
    // Desactivamos opción para establecer número de actualizaciones de stock diarias. Será cada 5 minutos
    // if (empty($opciones['sincronizaciones_diarias'])) {
    //     $opciones['sincronizaciones_diarias'] = 60/5 * 24; // 5 minutos
    //     $bGuardar = true;
    // }
    if (empty($opciones['sincronizaciones_diarias_numero_productos'])) {
        $opciones['sincronizaciones_diarias_numero_productos'] = 20;
        $bGuardar = true;
    }
    if ($bGuardar) {
        update_option("jugatoys_settings", $opciones);
    }
    // Verificamos si se ha establecido por defecto a true la sincronización del precio, que por lógica es necesario. Únicamente correrá una vez, o cuando se borre la opción
    if(empty($opciones['jugatoys_establecidoSincronizacionStockDefault'])){

        // Establecemos la opción a true en todos los productos
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids',
            "numberposts" => -1
        );
        $productos = get_posts($args);
        foreach ($productos as $producto) {
            update_post_meta($producto, 'jugatoys_sincronizarPrecio', "yes");
        }

        $opciones['jugatoys_establecidoSincronizacionStockDefault'] = true;
        update_option("jugatoys_settings", $opciones);
    }
}

function desactivar_cron($cron_name)
{
    $timestamp = wp_next_scheduled($cron_name);
    wp_unschedule_event($timestamp, $cron_name);
}

function conseguirUnSoloEAN($EANs)
{
    $pos = strpos($EANs, ',');

    if ($pos == true) {
        $EAN = substr($EANs, 0, $pos);
        return $EAN;
    }
    return $EANs;
}

// Ajax de prueba
// https://ametsjuguetesydisfraces.es/wp-admin/admin-ajax.php?action=pruebaAPI
add_action('wp_ajax_pruebaAPI', 'pruebaAPI');
// add_action( 'wp_ajax_nopriv_pruebaAPI', 'pruebaAPI' );

//función de pruebas que se llama desde HOST/wp-admin/admin-ajax.php?action=pruebaAPI
function pruebaAPI()
{
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
    echo "<pre>";

    jugatoys_log("prueba");
    wp_die();
    // notificarVenta(42610);//42219
    notificarVenta(45892);

    wp_die();

    $orderId = 42610;//42219;
    
    $lineas = array();
    $order = wc_get_order($orderId);
    var_dump($order);
    var_dump($order->get_items());
    foreach ($order->get_items() as $item_key => $item) {
        $producto = $item->get_product();
        $item_data = $item->get_data();
        $idProducto = $item->get_product_id();
        $sku = get_post_meta($idProducto, '_sku_jugatoys', true);
        if (empty($sku)) {
            $producto->get_sku();
        }
        $lineas[] = (object) array(
            "Sku_Provider" => $sku,
            "Quantity" => $item->get_quantity(),
            "PVP" => floatval($producto->get_price()),
            "IVA" => ($item_data['tax_class']) ? $item_data['tax_class'] : 21,
        );
    }

    //TEST TODO
    var_dump($lineas);


    wp_die();

    comprobarTodosProductos();
    wp_die();

    // var_dump(wp_set_object_terms(2920, "Fabricante prueba", "pwb-brand"));

    // wp_die();
    //actualizarStockProductos();
    // var_dump(["jugatoys_actualizandoStockProductos", get_option("jugatoys_actualizandoStockProductos")]);
    // var_dump(update_option("jugatoys_actualizandoStockProductos",false));

    // var_dump(wp_schedule_single_event(time(), "jugatoys_actualizar_stock_productos")); //actualizarStockProductos
    echo time();
    echo '<pre>';
    print_r(_get_cron_array());
    echo '</pre>';

    wp_die();

    var_dump(["jugatoys_fechaUltimaComprobacionProductos", get_option("jugatoys_fechaUltimaComprobacionProductos")]);
    var_dump(comprobarTodosProductos());

    wp_die();

    $producto = array(
        "Product_Name" => "test desde funcion altaProducto",
        "PVP" => 288,
        "Stock" => 10,
        "UrlImage" => "https://destinonegocio.com/wp-content/uploads/2018/09/ciclo-de-vida-de-un-producto-1030x687.jpg",
        "Sku_Provider" => "288-288-7",
        "Brand_Supplier_Name" => "Prueba fabricante",

    );
    var_dump(altaProducto($producto));

    wp_die();

    $api = new JugaToysAPI();

    //Consultamos contra la API
    $productInfo = $api->productInfo(array(), "14/06/2021 10:00");

    var_dump($productInfo);

    // echo time();
    // echo '<pre>'; print_r( _get_cron_array() ); echo '</pre>';

    wp_die();

    var_dump(existeSKU("288-288-2"));

    wp_die();

    $producto = array(
        "Product_Name" => "test desde funcion altaProducto",
        "PVP" => 288,
        "Stock" => 10,
        "UrlImage" => "https://destinonegocio.com/wp-content/uploads/2018/09/ciclo-de-vida-de-un-producto-1030x687.jpg",
        "Sku_Provider" => "288-288-4",

    );
    var_dump(altaProducto($producto));

    actualizarStockProductos();
    wp_die();

    echo time();
    echo '<pre>';
    print_r(_get_cron_array());
    echo '</pre>';

    wp_die();

    $producto = array(
        "Product_Name" => "test desde funcion altaProducto",
        "PVP" => 288,
        "Stock" => 10,
        "UrlImage" => "https://destinonegocio.com/wp-content/uploads/2018/09/ciclo-de-vida-de-un-producto-1030x687.jpg",
        "Sku_Provider" => "288-288-2",

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

// Función lanzada desde cron para verificar si hay ventas que notificar
function jugatoys_notificarVentasNoNotificadas()
{
    // Activamos flag de notificación de ventas
    update_option("jugatoys_notificandoVentas", true);

    // Obtenemos todas las ventas que no hayan sido notificadas mediante la opcion jugatoys_ventaNoNotificada
    $orders = get_posts(array(
        'post_type' => 'shop_order',
        // 'post_status' => 'wc-processing',
        'meta_query' => array(
            array(
                'key' => 'jugatoys_ventaNoNotificada',
                'value' => '1',
                'compare' => '='
            )
            ),
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'ASC',
    ));

    // Notificamos las ventas
    foreach ($orders as $order) {
        $orderId = $order->ID;
        $order = wc_get_order($orderId);
        $order->save();
        if(notificarVenta($orderId)){
            // Si la notificación se ha realizado correctamente, eliminamos la opcion jugatoys_ventaNoNotificada
            $order->update_meta_data('jugatoys_ventaNoNotificada', 0);
        }
    }

    // Desactivamos flag de notificación de ventas
    update_option("jugatoys_notificandoVentas", false);
}
