<?php 

class JugaToysSettingsPage
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_options_page(
            __('JugaToys','jugatoys'), 
            __('JugaToys','jugatoys'), 
            'manage_options', 
            'jugatoys-ajustes', 
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option( 'jugatoys_settings' );
        ?>
        <div class="wrap">
            <h1><?php _e('JugaToys - Ajustes','jugatoys') ?></h1>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'jugatoys_grupo_ajustes' );
                do_settings_sections( 'jugatoys-ajustes' );
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {        
        register_setting(
            'jugatoys_grupo_ajustes', // Option group
            'jugatoys_settings', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'ajuste_credenciales_jugatoys', // ID
            __('Credenciales TPV JugaToys','jugatoys'), // Title
            false,
            'jugatoys-ajustes' // Page
        );  

        add_settings_field(
            'usuario', // ID
            __('Usuario', 'jugatoys'), // Title 
            array( $this, 'usuario_callback' ), // Callback
            'jugatoys-ajustes', // Page
            'ajuste_credenciales_jugatoys' // Section           
        );      

        add_settings_field(
            'pw', 
            __('Contraseña','jugatoys'), 
            array( $this, 'pw_callback' ), 
            'jugatoys-ajustes', 
            'ajuste_credenciales_jugatoys'
        );    

        add_settings_section(
            'ajuste_host_jugatoys', // ID
            __('Host TPV JugaToys','jugatoys'), // Title
            false,
            'jugatoys-ajustes' // Page
        );    

        add_settings_field(
            'url', 
            __('URL','jugatoys'), 
            array( $this, 'url_callback' ), 
            'jugatoys-ajustes', 
            'ajuste_host_jugatoys'
        );     

        add_settings_field(
            'puerto', 
            __('Puerto','jugatoys'), 
            array( $this, 'puerto_callback' ), 
            'jugatoys-ajustes', 
            'ajuste_host_jugatoys'
        );      

        add_settings_field(
            'timeout', 
            __('Timeout','jugatoys'), 
            array( $this, 'timeout_callback' ), 
            'jugatoys-ajustes', 
            'ajuste_host_jugatoys'
        );

        add_settings_section(
            'ajuste_sincronizacion_jugatoys', // ID
            __('Sincronización','jugatoys'), // Title
            false,
            'jugatoys-ajustes' // Page
        );    

        add_settings_field(
            'sincronizaciones_diarias', 
            __('Número de sincronizaciones globales diarias','jugatoys'), 
            array( $this, 'sincronizaciones_diarias_callback' ), 
            'jugatoys-ajustes', 
            'ajuste_sincronizacion_jugatoys'
        );

      
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        $new_input = array();
        if( isset( $input['usuario'] ) )
            $new_input['usuario'] = sanitize_text_field( $input['usuario'] );

        if( isset( $input['pw'] ) )
            $new_input['pw'] = sanitize_text_field( $input['pw'] );

        if( isset( $input['url'] ) )
            $new_input['url'] = sanitize_text_field( $input['url'] );

        if( isset( $input['puerto'] ) )
            $new_input['puerto'] = absint( $input['puerto'] );

        if( isset( $input['timeout'] ) )
            $new_input['timeout'] = absint( $input['timeout'] );

        if( isset( $input['sincronizaciones_diarias'] ) )
            $new_input['sincronizaciones_diarias'] = sanitize_text_field( $input['sincronizaciones_diarias'] );

        return $new_input;
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function usuario_callback()
    {
        printf(
            '<input type="text" id="usuario" name="jugatoys_settings[usuario]" value="%s" />',
            isset( $this->options['usuario'] ) ? esc_attr( $this->options['usuario']) : ''
        );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function pw_callback()
    {
        printf(
            '<input type="password" id="pw" name="jugatoys_settings[pw]" value="%s" />',
            isset( $this->options['pw'] ) ? esc_attr( $this->options['pw']) : ''
        );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function url_callback()
    {
        printf(
            '<input type="text" id="url" name="jugatoys_settings[url]" value="%s" />',
            isset( $this->options['url'] ) ? esc_attr( $this->options['url']) : ''
        );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function puerto_callback()
    {
        printf(
            '<input type="text" id="puerto" name="jugatoys_settings[puerto]" value="%s" />',
            isset( $this->options['puerto'] ) ? esc_attr( $this->options['puerto']) : ''
        );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function timeout_callback()
    {
        printf(
            '<input type="text" id="timeout" name="jugatoys_settings[timeout]" value="%s" />',
            isset( $this->options['timeout'] ) ? esc_attr( $this->options['timeout']) : ''
        );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function sincronizaciones_diarias_callback()
    {
        printf(
            '<input type="text" id="sincronizaciones_diarias" name="jugatoys_settings[sincronizaciones_diarias]" value="%s" />',
            isset( $this->options['sincronizaciones_diarias'] ) ? esc_attr( $this->options['sincronizaciones_diarias']) : ''
        );
    }

}

if( is_admin() )
    $my_settings_page = new JugaToysSettingsPage();

 ?>