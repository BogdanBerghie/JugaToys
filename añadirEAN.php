<?php 

/** 
 * Adding Custom EAN Meta Field
 * Save meta data to DB
 */
// add EAN input field
add_action('woocommerce_product_options_inventory_product_data','jugatoys_product_field', 10, 1 );
function jugatoys_product_field(){
   global $woocommerce, $post;
   $product = new WC_Product(get_the_ID());
   echo '<div id="ean_attr" class="options_group">';
   //add EAN field for simple product
   woocommerce_wp_text_input( 
    array(  
      'id'      => '_ean',
      'label'         => __( 'EAN', 'jugatoys' ), 
      'placeholder'   => '01234567891231',
      'desc_tip'    => 'true',
      'description'   => __( 'Introduce el EAN', 'jugatoys' )
    )
  );
   echo '</div>';
}
// save simple product EAN
add_action('woocommerce_process_product_meta','jugatoys_product_save');
function jugatoys_product_save($post_id){
   $ean_post = $_POST['_ean'];
   // save the ean
   if(isset($ean_post)){
      update_post_meta($post_id,'_ean', esc_attr($ean_post));
   }
   // remove if EAN meta is empty
   $ean_data = get_post_meta($post_id,'_ean', true);
   if (empty($ean_data)){
      delete_post_meta($post_id,'_ean', '');
   }
}



// Add Variation EAN Meta Field
add_action( 'woocommerce_product_after_variable_attributes', 'jugatoys_product_settings_fields', 10, 3 );
function jugatoys_product_settings_fields( $loop, $variation_data, $variation ) {
  // Text Field
  woocommerce_wp_text_input( 
    array( 
      'id'          => '_ean[' . $variation->ID . ']', 
      'label'         => __( 'EAN', 'jugatoys' ), 
      'placeholder'   => '01234567891231',
      'desc_tip'    => 'true',
      'description'   => __( 'Introduce el EAN', 'jugatoys' ),
      'value'       => get_post_meta( $variation->ID, '_ean', true )
    )
  );  
}

// Save Variation EAN Meta Field Settings
add_action( 'woocommerce_save_product_variation', 'jugatoys_product_save_variation_settings_fields', 10, 2 );
function jugatoys_product_save_variation_settings_fields( $post_id ) {
  
  $ean_post = $_POST['_ean'][ $post_id ];
  // save the ean
  if(isset($ean_post)){
    update_post_meta($post_id,'_ean', esc_attr($ean_post));
  }
  // remove if EAN meta is empty
  $ean_data = get_post_meta($post_id,'_ean', true);
  if (empty($ean_data)){
    delete_post_meta($post_id,'_ean', '');
  }
  
}

 ?>