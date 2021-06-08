<?php

function actualizarStockProductoActual()
{
    //Obtenemos objeto de producto actual
    global $product;
  
    actualizarStockIdProducto($product->id);
}
function actualizarStockCron()
{
    
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
function actualizarStockSku($aProductId_Sku = array())
{

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
                if (!empty($pData->Sku_Provider)) {
                    //Confirmamos que hayamos solicitado el SKU
                    $idProducto = array_search($pData->Sku_Provider, $aProductId_Sku);
                    if ($idProducto !== false) {
                        //Si coincide el SKU, actualizamos stock
                        $stock = absint($pData->Stock);
                        wc_update_product_stock($idProducto, $stock, 'set');
                    }
                }
            }
        }
    }
}
