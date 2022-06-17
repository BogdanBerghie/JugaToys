<?php 

class JugaToysAPI{

  private $options;

  private $ch;

  /**
   * Start up
  */
  public function __construct()
  {
    $this->options = get_option( 'jugatoys_settings' );
    if (!$this->options) {
      throw new Exception(__("Es necesario introducir los ajustes del TPV","jugatoys"), 1);
    }

    $this->ch = curl_init();
    curl_setopt($this->ch, CURLOPT_HEADER, TRUE);
    curl_setopt($this->ch, CURLOPT_PORT , $this->options['puerto']);
    curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($this->ch, CURLOPT_TIMEOUT, $this->options['timeout']);
    curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 0);


    // curl_setopt($this->ch, CURLOPT_NOBODY, TRUE); // remove body
  }

  // Función para verificar la conexión del servidor
  public function ping(){
    $url = $this->options['url'];
    curl_setopt($this->ch, CURLOPT_URL, $url);
    $result = curl_exec($this->ch);
    $httpcode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
    curl_close($this->ch);
    if ($httpcode == 200) {
      return true;
    }else{
      return false;
    }
  }

  public function productInfo($arrayIds = array(), $updated_from = false){

    $url = $this->options['url'] . '/productinfo';
    $data = array();

    if (!empty($arrayIds)) {
      if (is_array($arrayIds)) {
        $data['products_id'] = $arrayIds;
      }else{
        $data['products_id'] = array($arrayIds);
      }
    }

    if (!empty($updated_from)) {
      $data['updated_from'] = $updated_from;
    }

    return $this->post($url, $data);
    
  }

  public function stockPrice($arrayIds = array(), $updated_from = false){

    $url = $this->options['url'] . '/stockprice';
    $data = array();

    if (is_array($arrayIds) && !empty($arrayIds)) {
      $data['producto_id'] = $arrayIds;
    }

    if (!empty($updated_from)) {
      $data['updated_from'] = $updated_from;
    }

    return $this->post($url, $data);
    
  }

  public function ticketInsert($orderId, $typeCharge, $lines = array(), $client = array()){

    $url = $this->options['url'] . '/ticketinsert';
    $data = array(
      'Ticket' => array(
        'OrderID' => absint($orderId),
        'Type_Charge' => $typeCharge,
        'Ticket_Lines' => array()
      )
    );

    if (is_array($lines) && !empty($lines)) {
      $data['Ticket']['Ticket_Lines'] = $lines;
    }

    if(!empty($client)){
      $data['Ticket']['Client'] = $client;
    }
    
    return $this->post($url, $data);
    
  }

  private function post($url, $params){
    curl_setopt($this->ch, CURLOPT_URL, $url);

    $credenciales = array(
      'user' => $this->options['usuario'],
      'password' => $this->options['pw']
    );

    if (!empty($params)) {
      $data = array_merge($credenciales, $params);
    }
    $jsonData = json_encode($data, JSON_UNESCAPED_SLASHES );

    curl_setopt($this->ch, CURLOPT_POST, true);
    curl_setopt($this->ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($this->ch, CURLOPT_HTTPHEADER,
    array(
        'Content-Type:application/json',
        'Content-Length: ' . strlen($jsonData)
    ));

    jugatoys_log("-------------------------------");
    jugatoys_log("Petición POST");
    jugatoys_log(["url", $url]);
    jugatoys_log(["jsonData", $jsonData]);

    $respuesta = curl_exec($this->ch);
    if ($respuesta === false) {
      jugatoys_log(["curl_error", curl_error($this->ch)]);
    }else{
      $header_size = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
      $header = substr($respuesta, 0, $header_size);
      $respuesta = substr($respuesta, $header_size);
    }
    
    $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
    curl_close($this->ch);

    jugatoys_log(["httpCode", $httpCode]);
    if($respuesta){
      jugatoys_log(["Result", json_decode($respuesta)->Result]);
    }
    jugatoys_log("-------------------------------");

    if($httpCode < 400)
    {
        return json_decode($respuesta);
    }
    else
    {
        return FALSE;
    }

  }

}


?>