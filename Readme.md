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
### V. 1.4.3 - Alain - 17/06/2022
+ Quitamos discriminación de stock al dar de alta productos. Cambiamos llamada a la API de la función actualizarStockSku. Llamaba productInfo y ahora llama a stockPrice
+ Cambiamos llamada a la API de la función actualizarStockSku. Llamaba productInfo y ahora llama a stockPrice

# Descripción
### V. 1.4.2 - Alain - 17/06/2022
+ Actualizamos funciones para obtener datos de cliente.
+ Fix al añadir datos de cliente al notificar venta
+ Quitamos campos null de los datos de cliente al notificar venta

### V. 1.4.1 - Bogdan - 30/05/2022
Ahora al actualizar el stock hace una comprobacion para dar de alta artículos que en un principio hayan sido discriminados por no tener stock.

```php 
function actualizarStockProductos()
{
  ...
  //BOGDAN V.1.4.1 - Antes de actualizar el stock se hará una comprobación para dar de alta un artículo que en un primer momento se obvio por falta de stock
  comprobarTodosProductos();
  //BOGDAN V.1.4.1
```

### V. 1.4.0 - Alain - 25/05/2022
+ Ahora cuando se pierda y se recupere la conexión entre página y TPV se hará una comprobación para actualizar todas las ventas que se hayan realizdo en el *apagón*

+ En las llamadas al TPV ahora se incluye la fecha del momento en el que se realiza la llamada para que el tpv responda con todos los artículos que hayan sufrido alguna modificación (ej.: suma o resta de stock).

+ Ahora con la notificación de una compra se pasará también los datos del cliente para que en caso de que se hayan facilitado todos los campos necesarios se dará de alta el cliente en en TPV. 
> En caso de que el cliente ya exista en el TPV no se dará de alta.

+ Se añade un check en todos los artículos para especificar si se debe actualizar o no el precio del artículo.

+ Se añade un nuevo botón para desencadenar la funcinalidad de buscar nuevos artículos en la pantalla de configuración del pluguin.

### Versión: 1.3.9 - Bogdan - 05/01/2022.
#### Jugatoys.php

+ Se cambian todas las referencias de **jugatoys_actualizarStockProductos** para que en vez de definirse a **true/false** pasará a definirse a **1/0**.

> Parece ser que cuando guardas un false en la BBDD deja el parametro en blanco. Ha habído un error que impedía que se actualizarán de forma recursiva los stocks de artículos **function cron_events_actualizar_stock_productos()**.

+ De momento queda deshabilitada la funcionalidad que se poniese en espera *function cron_events_actualizar_stock_productos()* en caso de que ya se esté actualizando los stocks de los artículos.

> Ahora en *function cron_events_actualizar_stock_productos()* comprueba el valor de **jugatoys_actualizarStockProductos** de la BBDD antes de actualizar stoks:
>+ Si el valor de **jugatoys_actualizarStockProductos** es **1** quiere decir que ya se está realizando una actualización de datos por lo que no hará nada y se saltará dicha actualización.
> + Si el valor de **jugatoys_actualizarStockProductos** es **0** quiere decir que no se está realizando ninguna actualización de Stock, por lo que se llamará a **actualizarStockProductos();** y comenzará a actualizar el stock de los artículos.
>>Nota: Al llamar a **actualizarStockProductos();** lo primero que se hará será poner **jugatoys_actualizarStockProductos** a **1** para evitar la posibilidad de que empiece una actualización con una en marcha. Al terminar de actualizar el stock de los artículos **jugatoys_actualizarStockProductos** se define a **0**

```php
  function cron_events_actualizar_stock_productos() {
    //Confirmamos flag que no se estén actualizando productos actualmente
    if (!$actualizandoStockProductos) {
      jugatoys_log("Valor de actualizandoStockProductos " . $actualizandoStockProductos);
      //wp_schedule_single_event(time(), "jugatoys_actualizar_stock_productos");
      actualizarStockProductos();
    }else{
      jugatoys_log("No ha inicado actualizarStockProductos. jugatoys_actualizandoStockProductos = ".$actualizandoStockProductos." Para funcionar poner a 0 ");
    }   
  }
```

+ Para solucionar un error que deshabilitaba la funcionalidad de actualizar el stock de forma recursiva desde hoy al encender el pluguin la variable **jugatoys_actualizarStockProductos** se define a 0 para habilitar la actualización de artículos de forma automatica.

+ Para resetear el orden de actualización de stock ahora la variable  **jugatoys_numero_actualizacion** tomará el valor de **(jugatoys_numero_actualizacion + 1)**. Esto puede servir para resetear el orden que tiene el plugin de cara a actualizar los artículos. 

> Ej: jugatoys_numero_actualizacion = 1, al apagar y encender el pluguin jugatoys_numero_actualizacion pasara a ser 2.

```php
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

```

#### Utilidades.php

+ Ahora en la función **function actualizarStockSku($aProductId_Sku = array())** antes de actualizar el stock de un artículo comprueba si el stock es menor a 0, en ese caso definirá el stock a 0

> Ej: stock = -1 a la página web llegará como 0.

```php
//BOGDAN v1.3.9 - Los artículos con un stock negativo los mostrará como Stock = 0.
if($stock<0){
    jugatoys_log("Stock menor que 0 en SKU: ". $pData->Sku_Provider. " Stock se pone a 0");
    wc_update_product_stock($idProducto, 0, 'set');
}else{
    wc_update_product_stock($idProducto, $stock, 'set');
} 
```


+ Ya no se dará de alta los artículos sin stock. 

```php
// BOGDAN v1.3.9 - Ya no se dará de alta los artículos sin stock
if ($producto->Stock <= 0) {
    jugatoys_log(["NO STOCK SE IGNORA" , $producto]);
    $productosPasados++;
    continue;
}
```

### Versión: 1.3.8 - Bogdan - 18/10/2021.
+ Se cambia el HOOK usado en el Action de notificar venta de woocommerce_new_order --> woocommerce_payment_complete para solucionar un problema que se daba al trabajar con $OrderId.

+ Se añaden varios cambios para corregir el EAN que se estaba definiendo de una forma no esperada por woocommerce. Ahora cuando se vaya a  definier la metaKey _ean se duplicará el valor para guardarlo en _alg_ean.

+ Se crea la funcion conseguirUnSoloEAN(); en utilidades para quedarnos con un solo EAN para el metadato _alg_ean en los casos en los que vengan varios EANs 

### Versión: 1.3.7 - Bogdan - 07/10/2021.
La url que se estaba formando para las imagenes de los artículos nuevos no tenian el puerto puesto y eso impedia descargar las imagenes de los artículos. 

Se añade el puerto.

### Versión: 1.3.6 - Alain 
Se reestructura al forma de dar de alta artículos nuevos. 
Había artículos en la página web que no existian en el TPV y al intentar sobreescribir el SKU de la página web con el SKU de un artículo (del TPV) que no existia se abortaba el proceso.

Ahora al comprobar si existe o no un artículo de la página web en el TPV pueden ocurrir estas 3 situaciones: 
+ 1. EXISTEN en ambos sitios, por lo tanto actualizará el SKU de la página web por el sku del TPV
+ 2. EXISTE en la **página web** pero **NO** en el **TPV**, NO hará nada.
+ 3. EXISTE en el **TPV** pero **NO** en la página web. Se dará de alta como un artículo **NUEVO** 

### Versión: 1.3.5
Al encontrar un SKU al quitar el código proveedor actualizará el SKU del artículo por el SKU que ha mandado el TPV. Esto se hace por que se da por hecho que el código artículo está bien pero el Cod. proveedor esta mal. (Codigo proveedor ejemplos : **10000**-70100, **33**-40123, **16**-12345, etc.)

### Versión: 1.3.4
Ahroa al interactuar con un producto se actualizá el **título**, el **precio** y el Stock.

### Versión: 1.3.2

Solución de error que impedía cargar textos al encontrar SKUs

### Versión: 1.3.2

Ahora quedará de que forma se encuentra un **SKU** en el log

### Versión: 1.3.1

Antes de dividir el codigo **SKU** comprueba si existe articulos con el sku de jugatoys

### Versión:1.3

Separa el **CÓDIGO** del **PROVEEDOR** del **CÓDIGO** del **ARTÍCULO**. 

> Si un articulo entra como: 10000-70151 el conector guarda el **código del articulo** (70151) como **SKU** y crea el *metadato* **_sku_jugatoys** en el que se guarda el SKU completo (10000-70151) para luego poder hacer las operacioenes en las que se necesite el SKU completo. 

Prueba 3 casuisticas comunes antes de dar de alta artículos nuevos y si no hay coincidencias no crea el producto.

+ **utilidades.php** función: $idProducto = existeSKU($producto->Sku);
