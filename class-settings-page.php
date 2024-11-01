<?php

class User_Magic_Settings
{
    public function user_magic_add_admin_menu()
    {
        add_options_page(
            'User Magic Settings',
            'User Magic',
            'manage_options',
            'user-magic',
            array( $this, 'settings_page' )
        );
    }
    
    public function user_magic_settings_init()
    {
        register_setting( 'user-magic', 'user_magic_settings' );
        add_settings_section(
            'user-magic_section_general',
            '',
            array( $this, 'user_magic_settings_general_section_callback' ),
            'user-magic'
        );
        add_settings_field(
            'contact-list-send_email',
            __( 'Send an email notify when a contact is added via the public form', 'contact-list' ),
            array( $this, 'checkbox_render' ),
            'contact-list',
            'contact-list_section_general',
            array(
            'label_for'  => 'contact-list-send_email',
            'field_name' => 'send_email',
        )
        );
        add_settings_field(
            'contact-list-recipient_email',
            __( 'Notification recipient email', 'contact-list' ),
            array( $this, 'input_render' ),
            'contact-list',
            'contact-list_section_general',
            array(
            'label_for'   => 'contact-list-recipient_email',
            'field_name'  => 'recipient_email',
            'placeholder' => '',
        )
        );
    }
    
    public function input_render( $args )
    {
        
        if ( $args['field_name'] ) {
            $options = get_option( 'contact_list_settings' );
            ?>    
      <input type="text" class="input-field" id="contact-list-<?php 
            echo  $args['field_name'] ;
            ?>" name="contact_list_settings[<?php 
            echo  $args['field_name'] ;
            ?>]" value="<?php 
            echo  ( isset( $options[$args['field_name']] ) ? $options[$args['field_name']] : '' ) ;
            ?>" placeholder="<?php 
            echo  ( $args['placeholder'] ? $args['placeholder'] : '' ) ;
            ?>">
      <?php 
        }
    
    }
    
    public function checkbox_render( $args )
    {
        
        if ( $args['field_name'] ) {
            $options = get_option( 'contact_list_settings' );
            ?>    
      <input type="checkbox" id="contact-list-<?php 
            echo  $args['field_name'] ;
            ?>" name="contact_list_settings[<?php 
            echo  $args['field_name'] ;
            ?>]" <?php 
            echo  ( isset( $options[$args['field_name']] ) ? 'checked="checked"' : '' ) ;
            ?>>
      
      <?php 
            
            if ( $args['field_name'] == 'send_email' ) {
                ?>
        <div class="email-info">
          <b><?php 
                echo  __( 'Note:' ) ;
                ?></b> <?php 
                echo  __( 'By activating this you agree that the email sending is handled by the plugin developers own server and using <a href="https://www.mailgun.com" target="_blank">Mailgun</a>. The server is a DigitalOcean Droplet hosted in the EU. This method was chosen to ensure reliable mail delivery.', 'contact-list' ) ;
                ?>
        </div>
      <?php 
            }
            
            ?>
      <?php 
        }
    
    }
    
    public function user_magic_settings_general_section_callback()
    {
        echo  proFeatureSettingsMarkup() ;
    }
    
    public function settings_page()
    {
        ?>

    <form action="options.php" method="post" class="user-magic-settings-form">

      <div class="wrap fs-section fs-full-size-wrapper">
        <h2 class="nav-tab-wrapper">
          <a href="#" class="nav-tab fs-tab nav-tab-active home">Settings</a>
        </h2>
        <!-- Plugin settings go here -->

        <div class="user-magic-settings-container">
          <p><?php 
        echo  __( 'Currently there are no additional settings. For more information on the plugin please visit', 'user-magic' ) ;
        ?> <a href="https://www.usermagicpro.com" target="_blank">usermagicpro.com</a>.</p>
        </div>

        <?php 
        
        if ( 0 ) {
            ?>
          <h1><?php 
            echo  __( 'Settings', 'user-magic' ) ;
            ?></h1>
    
          <?php 
            settings_fields( 'user-magic' );
            ?>
          <?php 
            do_settings_sections( 'user-magic' );
            ?>  
          
          <?php 
            submit_button();
            ?>
        <?php 
        }
        
        ?>

      </div>
      
    </form>
    <?php 
    }

}