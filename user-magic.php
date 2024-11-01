<?php

/**
 * @link              https://anssilaitila.fi
 * @since             1.0.0
 * @package           User_Magic
 *
 * @wordpress-plugin
 * Plugin Name:       User Magic
 * Description:       User management improvements: last login timestamp, login counter and send email to users.
 * Version:           1.0.7
 * Author:            Anssi Laitila
 * Author URI:        https://anssilaitila.fi
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       user-magic
 * Domain Path:       /languages
 */

if ( !function_exists( 'um_fs' ) ) {
    // Create a helper function for easy SDK access.
    function um_fs()
    {
        global  $um_fs ;
        
        if ( !isset( $um_fs ) ) {
            // Include Freemius SDK.
            require_once dirname( __FILE__ ) . '/freemius/start.php';
            $um_fs = fs_dynamic_init( array(
                'id'             => '5153',
                'slug'           => 'user-magic',
                'type'           => 'plugin',
                'public_key'     => 'pk_0f8b2524b80587e68204db81e3ef8',
                'is_premium'     => false,
                'premium_suffix' => 'Pro',
                'has_addons'     => false,
                'has_paid_plans' => true,
                'trial'          => array(
                'days'               => 14,
                'is_require_payment' => false,
            ),
                'menu'           => array(
                'slug'    => 'user-magic',
                'contact' => false,
                'parent'  => array(
                'slug' => 'options-general.php',
            ),
            ),
                'is_live'        => true,
            ) );
        }
        
        return $um_fs;
    }
    
    // Init Freemius.
    um_fs();
    // Signal that SDK was initiated.
    do_action( 'um_fs_loaded' );
}

function um_fs_custom_connect_message( $message, $user_first_name )
{
    return sprintf( __( 'Hey %1$s' ) . ',<br>' . __( 'never miss an important update -- opt-in to our security and feature updates notifications, and non-sensitive diagnostic tracking with freemius.com.', 'user-magic' ), $user_first_name );
}

um_fs()->add_filter(
    'connect_message',
    'um_fs_custom_connect_message',
    10,
    6
);
function um_fs_custom_connect_message_on_update(
    $message,
    $user_first_name,
    $plugin_title,
    $user_login,
    $site_link,
    $freemius_link
)
{
    return sprintf(
        __( 'Hey %1$s' ) . ',<br>' . __( 'Please help us improve %2$s! If you opt-in, some data about your usage of %2$s will be sent to %5$s. If you skip this, that\'s okay! %2$s will still work just fine.', 'user-magic' ),
        $user_first_name,
        '<b>' . $plugin_title . '</b>',
        '<b>' . $user_login . '</b>',
        $site_link,
        $freemius_link
    );
}

um_fs()->add_filter(
    'connect_message_on_update',
    'um_fs_custom_connect_message_on_update',
    10,
    6
);
define( 'USER_MAGIC_VERSION', '1.0.7' );
require 'inc/user-category-taxonomy.php';
require_once 'class-settings-page.php';
class User_Magic
{
    public function __construct()
    {
        add_action( 'init', array( &$this, 'init' ) );
    }
    
    public function init()
    {
        $plugin_settings = new User_Magic_Settings();
        add_action(
            'manage_users_custom_column',
            array( $this, 'custom_user_columns_data' ),
            10,
            3
        );
        add_action( 'plugins_loaded', array( $this, 'user_magic_load_textdomain' ) );
        add_action(
            'wp_login',
            array( $this, 'user_login' ),
            10,
            2
        );
        add_action( 'profile_update', array( $this, 'user_magic_profile_update' ) );
        add_action(
            'password_reset',
            array( $this, 'user_magic_password_reset' ),
            10,
            2
        );
        add_action( 'admin_menu', array( $this, 'update_user_metadata' ) );
        add_action( 'pre_get_posts', array( $this, 'user_magic_custom_orderby' ) );
        add_action( 'admin_menu', array( $this, 'register_send_email_page' ) );
        add_action( 'admin_menu', array( $this, 'register_mail_log_page' ) );
        add_action( 'admin_menu', array( $this, 'add_settings_link' ) );
        add_action( 'wp_ajax_um_send_mail', array( $this, 'um_send_mail' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_menu', array( $this, 'update_db_check_um' ) );
        add_action( 'admin_menu', array( $plugin_settings, 'user_magic_add_admin_menu' ) );
        add_action( 'admin_init', array( $plugin_settings, 'user_magic_settings_init' ) );
        add_filter( 'manage_users_columns', array( $this, 'custom_user_columns' ) );
        add_filter( 'manage_users_sortable_columns', array( $this, 'set_custom_user_magic_sortable_columns' ) );
    }
    
    public static function custom_user_columns( $columns )
    {
        $columns['last_login'] = __( 'Last login', 'user-magic' );
        $columns['total_logins'] = __( 'Total logins', 'user-magic' );
        $columns['last_password_change'] = __( 'Last password change', 'user-magic' );
        return $columns;
    }
    
    public static function custom_user_columns_data( $value, $column_name, $user_id )
    {
        
        if ( $column_name == 'last_login' ) {
            $last_login = get_user_meta( $user_id, '_user_magic_last_login', true );
            return '<span style="white-space: nowrap;">' . $last_login . '</span>';
        } else {
            
            if ( $column_name == 'total_logins' ) {
                $total_logins = get_user_meta( $user_id, '_user_magic_total_logins', true );
                return $total_logins;
            } else {
                
                if ( $column_name == 'last_password_change' ) {
                    $last_password_change = get_user_meta( $user_id, '_user_magic_last_password_change', true );
                    return '<span style="white-space: nowrap;">' . $last_password_change . '</span>';
                }
            
            }
        
        }
    
    }
    
    public function add_settings_link()
    {
        global  $submenu ;
        $permalink = './options-general.php?page=user-magic';
        $submenu['users.php'][] = array( __( 'Settings', 'user-magic' ), 'manage_options', $permalink );
    }
    
    public function enqueue_styles()
    {
        wp_enqueue_style(
            'user-magic',
            plugin_dir_url( __FILE__ ) . 'user-magic-admin.css',
            array(),
            USER_MAGIC_VERSION,
            'all'
        );
    }
    
    public function enqueue_scripts()
    {
        wp_enqueue_script(
            'user-magic',
            plugin_dir_url( __FILE__ ) . 'user-magic-admin.js',
            array( 'jquery' ),
            USER_MAGIC_VERSION,
            false
        );
    }
    
    public static function user_magic_password_reset( $user, $new_pass )
    {
        $timestamp = current_time( 'mysql' );
        update_user_meta( $user->ID, '_user_magic_last_password_change', $timestamp );
        update_user_meta( $user->ID, '_user_magic_last_password_change_int', strtotime( $timestamp ) );
    }
    
    public static function user_login( $user_login, $user )
    {
        $total_logins = (int) get_user_meta( $user->ID, '_user_magic_total_logins', true );
        update_user_meta( $user->ID, '_user_magic_total_logins', $total_logins + 1 );
        $timestamp = current_time( 'mysql' );
        update_user_meta( $user->ID, '_user_magic_last_login', $timestamp );
        update_user_meta( $user->ID, '_user_magic_last_login_int', strtotime( $timestamp ) );
    }
    
    public static function user_magic_profile_update( $user_id )
    {
        if ( !isset( $_POST['pass1'] ) || $_POST['pass1'] == '' ) {
            return;
        }
        update_user_meta( $user_id, '_user_magic_last_password_change', current_time( 'mysql' ) );
    }
    
    public function user_magic_load_textdomain()
    {
        load_plugin_textdomain( 'user-magic' );
    }
    
    public function set_custom_user_magic_sortable_columns( $columns )
    {
        $columns['last_login'] = 'last_login';
        $columns['total_logins'] = 'total_logins';
        $columns['last_password_change'] = 'last_password_change';
        return $columns;
    }
    
    public function user_magic_custom_orderby( $query )
    {
        if ( !is_admin() ) {
            return;
        }
        $orderby = $query->get( 'orderby' );
        $order = ( $query->get( 'order' ) == 'asc' ? 'ASC' : 'DESC' );
        
        if ( $orderby == 'last_login' ) {
            $query->set( 'meta_query', array(
                'relation'         => 'AND',
                'last_name_clause' => array(
                'key'     => '_user_magic_last_login_int',
                'compare' => 'EXISTS',
            ),
            ) );
            $query->set( 'orderby', array(
                'last_login_clause' => $order,
                'title'             => $order,
            ) );
        } elseif ( $orderby == 'total_logins' ) {
            $query->set( 'meta_query', array(
                'relation'         => 'AND',
                'last_name_clause' => array(
                'key'     => '_user_magic_total_logins',
                'compare' => 'EXISTS',
            ),
            ) );
            $query->set( 'orderby', array(
                'total_logins_clause' => $order,
                'title'               => $order,
            ) );
        } elseif ( $orderby == 'last_password_change' ) {
            $query->set( 'meta_query', array(
                'relation'         => 'AND',
                'last_name_clause' => array(
                'key'     => '_user_magic_last_password_change_int',
                'compare' => 'EXISTS',
            ),
            ) );
            $query->set( 'orderby', array(
                'last_password_change_clause' => $order,
                'title'                       => $order,
            ) );
        }
    
    }
    
    public function update_user_metadata()
    {
        
        if ( !get_option( '_user_magic_db_ver' ) ) {
            $users = get_users( array(
                'fields' => array( 'ID' ),
            ) );
            foreach ( $users as $user ) {
                if ( $last_password_change = get_user_meta( $user->ID, '_user_magic_last_password_change', true ) ) {
                    update_user_meta( $user->ID, '_user_magic_last_password_change_int', strtotime( $last_password_change ) );
                }
                if ( $last_login = get_user_meta( $user->ID, '_user_magic_last_login', true ) ) {
                    update_user_meta( $user->ID, '_user_magic_last_login_int', strtotime( $last_login ) );
                }
            }
            add_option(
                '_user_magic_db_ver',
                '2',
                '',
                'yes'
            );
        }
    
    }
    
    public function register_send_email_page()
    {
        add_submenu_page(
            'users.php',
            __( 'Send email to users', 'user-magic' ),
            __( 'Send email', 'user-magic' ),
            'manage_options',
            'user-magic-send-email',
            [ $this, 'register_send_email_page_callback' ]
        );
    }
    
    public function proFeatureMarkup()
    {
        $html = '';
        $html .= '<div class="pro-feature">';
        $html .= '<span>' . __( 'This feature is available in the Pro version.', 'user-magic' ) . '</span>';
        $html .= '<a href="' . get_admin_url() . 'options-general.php?page=user-magic-pricing">' . __( 'Upgrade here', 'user-magic' ) . '</a>';
        $html .= '</div>';
        return $html;
    }
    
    public function register_send_email_page_callback()
    {
        $term_id = ( isset( $_GET['group_id'] ) ? $_GET['group_id'] : 0 );
        $user_query = new WP_User_Query( array(
            'number' => 1000,
        ) );
        $recipient_emails = [];
        if ( !empty($user_query->get_results()) ) {
            foreach ( $user_query->get_results() as $user ) {
                
                if ( isset( $user->user_email ) && sanitize_email( $user->user_email ) ) {
                    $meta_values = get_user_meta( $user->ID, '_um_user_category', true );
                    
                    if ( $term_id && in_array( $term_id, $meta_values ) ) {
                        $recipient_emails[] = $user->user_email;
                    } elseif ( !$term_id ) {
                        $recipient_emails[] = $user->user_email;
                    }
                
                }
            
            }
        }
        wp_reset_postdata();
        ?>
    
    <div class="wrap">

      <form method="post" class="user-magic-send-email-form" action="" target="send_email">

          <h1><?php 
        echo  __( 'Send email to users', 'user-magic' ) ;
        ?></h1>

          <div class="email-info">
            <b><?php 
        echo  __( 'Note:' ) ;
        ?></b> <?php 
        echo  __( 'The emails are sent by the plugin developers own server and using <a href="https://www.mailgun.com" target="_blank">Mailgun</a>. The server is a DigitalOcean Droplet hosted in the EU. This method was chosen to ensure reliable mail delivery.', 'user-magic' ) ;
        ?>
          </div>

          <?php 
        ?>
              <?php 
        echo  $this->proFeatureMarkup() ;
        ?>
          <?php 
        ?>
    
          <div class="sender-info"><?php 
        echo  __( 'The sender email of the message is', 'user-magic' ) ;
        ?> <b>no-reply@usermagicpro.com</b>.</div>
    
          <label>
            <span><?php 
        echo  __( 'Subject', 'user-magic' ) ;
        ?></span>
            <input name="subject" value="" />
          </label>

          <?php 
        $user_id = get_current_user_id();
        ?>
          <?php 
        $user = get_userdata( $user_id );
        ?>
          
          <label>
            <span><?php 
        echo  __( 'Sender name', 'user-magic' ) ;
        ?></span>
            <input name="sender_name" value="<?php 
        echo  $user->first_name ;
        ?> <?php 
        echo  $user->last_name ;
        ?>" />
          </label>

          <label>
            <span><?php 
        echo  __( 'Reply-to', 'user-magic' ) ;
        ?></span>
            <input name="reply_to" value="<?php 
        echo  $user->user_email ;
        ?>" />
          </label>
    
          <label>
            <span><?php 
        echo  __( 'Message', 'user-magic' ) ;
        ?></span>
            <textarea name="body"></textarea>
          </label>

          <div>
              <span class="restrict-recipients-title"><?php 
        echo  __( 'Restrict recipients to specific group', 'user-magic' ) ;
        ?></span>

              <?php 
        $taxonomies = get_terms( array(
            'taxonomy'   => 'um_user_category',
            'hide_empty' => false,
        ) );
        $output = '';
        if ( !empty($taxonomies) ) {
            foreach ( $taxonomies as $category ) {
                
                if ( $category->parent == 0 ) {
                    $output .= '<label class="user-magic-category"><input type="radio" name="_um_groups[]" value="' . esc_attr( $category->term_id ) . '" onclick="document.location.href=\'./users.php?page=user-magic-send-email&group_id=\' + this.value;" ' . (( isset( $_GET['group_id'] ) && $_GET['group_id'] == $category->term_id ? 'checked' : '' )) . ' /> <span class="user-magic-checkbox-title">' . esc_attr( $category->name ) . '</span></label>';
                    foreach ( $taxonomies as $subcategory ) {
                        if ( $subcategory->parent == $category->term_id ) {
                            $output .= '<label class="user-magic-subcategory"><input type="radio" name="_um_groups[]" value="' . esc_attr( $subcategory->term_id ) . '" onclick="document.location.href=\'./users.php?page=user-magic-send-email&group_id=\' + this.value;" ' . (( isset( $_GET['group_id'] ) && $_GET['group_id'] == $subcategory->term_id ? 'checked' : '' )) . ' /> <span class="user-magic-checkbox-title">' . esc_html( $subcategory->name ) . '</span></label>';
                        }
                    }
                }
            
            }
        }
        echo  '<div class="user-magic-restrict-to-groups">' ;
        echo  $output ;
        echo  '</div>' ;
        ?>

          </div>

          <span class="recipients-title"><?php 
        echo  __( 'Recipients', 'user-magic' ) ;
        ?> (<?php 
        echo  __( 'total of', 'user-magic' ) ;
        ?> <?php 
        echo  sizeof( $recipient_emails ) ;
        ?> <?php 
        echo  __( 'users with email addresses', 'user-magic' ) ;
        ?>)</span>
          <div><?php 
        echo  implode( ", ", $recipient_emails ) ;
        ?></div>
          <input name="recipient_emails" type="hidden" value="<?php 
        echo  implode( ",", $recipient_emails ) ;
        ?>" />

          <?php 
        ?>
            <input type="submit" class="submit-form" value="<?php 
        echo  __( 'Send', 'user-magic' ) ;
        ?>" disabled />
          <?php 
        ?>
    
          <hr class="style-one" />
          
      </form>

    </div>
    <?php 
    }
    
    public function update_db_check_um()
    {
        $installed_version = get_site_option( 'user_magic_version' );
        
        if ( $installed_version != USER_MAGIC_VERSION ) {
            global  $wpdb ;
            $charset_collate = $wpdb->get_charset_collate();
            $table_name = $wpdb->prefix . 'um_sent_mail_log';
            $wpdb->query( "CREATE TABLE IF NOT EXISTS " . $table_name . " (\n    \t  id              BIGINT(20) NOT NULL auto_increment,\n    \t  msg_id          VARCHAR(255) NOT NULL,\n    \t  sender_email    VARCHAR(255) NOT NULL,\n    \t  sender_name     VARCHAR(255) NOT NULL,\n    \t  recipient_email VARCHAR(255) NOT NULL,\n    \t  reply_to        VARCHAR(255) NOT NULL,\n    \t  msg_type        VARCHAR(255) NOT NULL,\n    \t  subject         VARCHAR(255) NOT NULL,\n    \t  response        VARCHAR(255) NOT NULL,\n    \t  mail_cnt        MEDIUMINT NOT NULL,\n    \t  report          TEXT NOT NULL,\n    \t  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n    \t  PRIMARY KEY (id)\n    \t) " . $charset_collate . ";" );
            update_option( 'user_magic_version', USER_MAGIC_VERSION );
        }
    
    }
    
    public function register_mail_log_page()
    {
        add_submenu_page(
            'users.php',
            __( 'Sent email', 'user-magic' ),
            __( 'Mail log', 'user-magic' ),
            'manage_options',
            'user-magic-mail-log',
            [ $this, 'register_mail_log_page_callback' ]
        );
    }
    
    public function register_mail_log_page_callback()
    {
        ?>
    
    <div class="wrap">

          <h1><?php 
        echo  __( 'Log of sent mail', 'user-magic' ) ;
        ?></h1>

          <?php 
        
        if ( isset( $_GET['mail_id'] ) ) {
            ?>

            <?php 
            $mail_id = (int) $_GET['mail_id'];
            global  $wpdb ;
            $table_name = $wpdb->prefix . "um_sent_mail_log";
            $msg = $wpdb->get_results( "SELECT * FROM {$table_name} WHERE id = " . $mail_id );
            ?>
            
            <a href="javascript:history.go(-1)">&lt;&lt; Back</a>
            
            <?php 
            foreach ( $msg as $row ) {
                ?>

              <table class="user-magic-mail-log-msg-details">
              <tr>
                <td><?php 
                echo  __( 'Message sent', 'user-magic' ) ;
                ?></td>
                <td><?php 
                echo  $row->created_at ;
                ?></td>
              </tr>
              <tr>
                <td><?php 
                echo  __( 'Sender email', 'user-magic' ) ;
                ?></td>
                <td><?php 
                echo  $row->sender_email ;
                ?></td>
              </tr>
              <tr>
                <td><?php 
                echo  __( 'Sender name', 'user-magic' ) ;
                ?></td>
                <td><?php 
                echo  $row->sender_name ;
                ?></td>
              </tr>
              <tr>
                <td><?php 
                echo  __( 'Reply-to', 'user-magic' ) ;
                ?></td>
                <td><?php 
                echo  $row->reply_to ;
                ?></td>
              </tr>
              <tr>
                <td><?php 
                echo  __( 'Subject', 'user-magic' ) ;
                ?></td>
                <td><?php 
                echo  $row->subject ;
                ?></td>
              </tr>
              <tr>
                <td><?php 
                echo  __( 'Message count', 'user-magic' ) ;
                ?></td>
                <td><?php 
                echo  $row->mail_cnt ;
                ?></td>
              </tr>
              </table>
              
              <h3><?php 
                echo  __( 'Mail was sent to the following recipients:', 'user-magic' ) ;
                ?></h3>
              
              <div class="user-magic-mail-log-recipients-container">
                <?php 
                echo  $row->report ;
                ?>
              </div>

            <?php 
            }
            ?>

          <?php 
        } else {
            ?>
          
            <?php 
            global  $wpdb ;
            $table_name = $wpdb->prefix . 'um_sent_mail_log';
            $msg = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT 200" );
            ?>
            
            <table class="user-magic-mail-log">
            <tr>
              <th><?php 
            echo  __( 'Date', 'user-magic' ) ;
            ?></th>
              <th><?php 
            echo  __( 'Sender email', 'user-magic' ) ;
            ?></th>
              <th><?php 
            echo  __( 'Sender name', 'user-magic' ) ;
            ?></th>
              <th><?php 
            echo  __( 'Reply-to', 'user-magic' ) ;
            ?></th>
              <th><?php 
            echo  __( 'Subject', 'user-magic' ) ;
            ?></th>
              <th><?php 
            echo  __( 'Messages sent', 'user-magic' ) ;
            ?></th>
              <th><?php 
            echo  __( 'Report', 'user-magic' ) ;
            ?></th>
            </tr>

            <?php 
            
            if ( sizeof( $msg ) > 0 ) {
                ?>
              <?php 
                foreach ( $msg as $row ) {
                    ?>
                <tr>
                  <td>
                    <?php 
                    echo  $row->created_at ;
                    ?>
                  </td>
                  <td>
                    <?php 
                    echo  $row->sender_email ;
                    ?>
                  </td>
                  <td>
                    <?php 
                    echo  $row->sender_name ;
                    ?>
                  </td>
                  <td>
                    <?php 
                    
                    if ( isset( $row->reply_to ) ) {
                        ?>
                      <?php 
                        echo  $row->reply_to ;
                        ?>
                    <?php 
                    }
                    
                    ?>
                  </td>
                  <td>
                    <?php 
                    echo  $row->subject ;
                    ?>
                  </td>
                  <td>
                    <?php 
                    echo  $row->mail_cnt ;
                    ?>
                  </td>
                  <td>
                    <a href="./users.php?page=user-magic-mail-log&mail_id=<?php 
                    echo  $row->id ;
                    ?>">Open &raquo;</a>
                  </td>
                </tr>
              <?php 
                }
                ?>
            <?php 
            } else {
                ?>
              <tr>
                <td colspan="7">
                  <?php 
                echo  __( 'No mail sent yet.', 'user-magic' ) ;
                ?>
                </td>
              </tr>
            <?php 
            }
            
            ?>
            
            </table>
            
          <?php 
        }
        
        ?>

          <?php 
        ?>

            <?php 
        echo  $this->proFeatureMarkup() ;
        ?>
            
          <?php 
        ?>

    </div>
    <?php 
    }
    
    public function um_send_mail()
    {
        global  $wpdb ;
        $wpdb->insert( 'wp_um_sent_mail_log', array(
            'subject'      => $_POST['subject'],
            'sender_name'  => $_POST['sender_name'],
            'reply_to'     => $_POST['reply_to'],
            'report'       => $_POST['report'],
            'sender_email' => $_POST['sender_email'],
            'mail_cnt'     => $_POST['mail_cnt'],
        ) );
        wp_die();
    }

}
new User_Magic();