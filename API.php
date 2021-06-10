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
    // curl_setopt($tuCurl, CURLOPT_SSL_VERIFYPEER, 0);

    // curl_setopt($this->ch, CURLOPT_NOBODY, TRUE); // remove body
  }

  public function productInfo($arrayIds = array(), $updated_from = false){

    $url = $this->options['url'] . '/productinfo';
    $data = array();

    if (!empty($arrayIds)) {
      if (is_array($arrayIds)) {
        $data['products-id'] = $arrayIds;
      }else{
        $data['products-id'] = array($arrayIds);
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

  public function ticketInsert($orderId, $typeCharge, $lines = array()){

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

    return $this->post($url, $data);
    
  }

  private function post($url, $params){
    curl_setopt($this->ch, CURLOPT_URL, $url);

    $credenciales = array(
      'user' => $this->options['usuario'],
      'password' => $this->options['pw']
    );

    $data = array_merge($credenciales, $params);
    $jsonData = json_encode($data);

    curl_setopt($this->ch, CURLOPT_POST, true);
    curl_setopt($this->ch, CURLOPT_POSTFIELDS, $jsonData);

    $respuesta = curl_exec($this->ch);
    $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
    curl_close($this->ch);

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