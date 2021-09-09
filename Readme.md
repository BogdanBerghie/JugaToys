# JUGATOYS 
   ___                   _                  
  |_  |                 | |                 
    | |_   _  __ _  __ _| |_ ___  _   _ ___ 
    | | | | |/ _` |/ _` | __/ _ \| | | / __|
/\__/ / |_| | (_| | (_| | || (_) | |_| \__ \
\____/ \__,_|\__, |\__,_|\__\___/ \__, |___/
              __/ |                __/ |    
             |___/                |___/     
                                                                                                        

Plugin para vincular contra el TPV de JugaToys

- Crea página de opciones para establecer datos de configuración necesarios.
- API para comunicación con JugaToys
- Añade EAN a los productos de Woocommerce
- Actualiza el stock del producto al entrar en la página individual o al acceder al carrito
- Cron dos veces al día para comprobar productos nuevos
- Cron para checkear stock configurable desde ajustes
- Notificación de venta

# Descripción
### Versión: 1.3.2

Ahora quedará de que forma se encuentra un **SKU** en el log

### Versión: 1.3.1

Antes de dividir el codigo **SKU** comprueba si existe articulos con el sku de jugatoys

### Versión:1.3

Separa el **CÓDIGO** del **PROVEEDOR** del **CÓDIGO** del **ARTÍCULO**. 

> Si un articulo entra como: 10000-70151 el conector guarda el **código del articulo** (70151) como **SKU** y crea el *metadato* **_sku_jugatoys** en el que se guarda el SKU completo (10000-70151) para luego poder hacer las operacioenes en las que se necesite el SKU completo. 

Prueba 3 casuisticas comunes antes de dar de alta artículos nuevos y si no hay coincidencias no crea el producto.

+ **utilidades.php** función: $idProducto = existeSKU($producto->Sku);
