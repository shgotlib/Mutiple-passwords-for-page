<?php
/**
 * Plugin name: FF mutiple passwords for page
 * Description: Choose multiple passwords for each page, base on your users. each user can get one or more passwords for the protected page.
 * Version: 1.0.0
 * Author URI: fatfish.co.il
 */

if(!defined('ABSPATH')) die();

class FF_Multiple_passwords {
    private $prefix = 'extra_pass_';
    private $field_id = 'all_passwords';

    public function init() {

        add_action( 'cmb2_admin_init', array($this, 'extra_pass_field') );
        add_action( 'admin_enqueue_scripts', array($this, 'style_admin_pass_box'), 100 );

        add_filter( 'the_password_form', array($this, 'multy_pass_form') );
    }

    function extra_pass_field() {

        if(!function_exists('new_cmb2_box')) {
            return;
        }
        
        $extra_pass = new_cmb2_box( array(
            'id'            => $this->prefix . 'group_field',
            'title'         => __('Passwords by users', 'extra_pass'),
            'object_types'  => array( 'page' ), 
            'context'    => 'side',
            'priority'   => 'high',
            
        ) );

        // The field for passwords and users
        $users_pass = $extra_pass->add_field( array(
            'id'            => $this->prefix . $this->field_id,
            'description'   => __( 'enter here password for each user', 'extra_pass' ),
            'type'          => 'group',
            'options'       => array(
                'group_title'   => __( 'Entry {#}', 'extra_pass' ),
                'add_button'    => __( 'Add Another Password', 'extra_pass' ),
                'remove_button' => __( 'Remove Passsword', 'extra_pass' ),
                'sortable'      => false,
            ),
        ) );
    
        $extra_pass->add_group_field( $users_pass, array(
            'name'  => __('User', 'extra_pass'),
            'id'    => $this->prefix . 'user',
            'type'  => 'select',
            'options_cb'    => array($this, 'users_list'),
        ) );
        $extra_pass->add_group_field( $users_pass, array(
            'name'  => __('Password', 'extra_pass'),
            'id'    => $this->prefix . 'password',
            'type'  => 'text',
        ) );
    }
    
    
    function users_list() {
        $users_list = array();
        $users = get_users( array(
            'role'      => 'administrator',
            'orderby'   => 'nicename',
        ) );
        if(!is_array($users)) {
            return $users_list;
        }
        foreach($users as $user) {
            $users_list[$user->ID] = $user->user_nicename;
        }
        return $users_list;
    }
    
    function post_password_required( $post = null ) {
        $required_pass = true;
        $post = get_post($post);
    
        if ( empty( $post->post_password ) )
                return false;
    
        if ( ! isset( $_COOKIE['wp-postpass_' . COOKIEHASH] ) )
            return true;
        if( ! class_exists( 'PasswordHash' ) ) {
            require_once ABSPATH . WPINC . '/class-phpass.php';
        }
        $hasher = new PasswordHash( 8, true );
    
        $hash = wp_unslash( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );
        if ( 0 !== strpos( $hash, '$P$B' ) )
            return true;
    
        // Check the current password
        if( $hasher->CheckPassword( $post->post_password, $hash ) )
            return false;
    
        // Fetch extra passwords
        $extra_passwords = get_post_meta( $post->ID, $this->prefix.$this->field_id, true );
        if( ! $extra_passwords ) 
            return true;
    
        // Check these extra passwords
        foreach( $extra_passwords as $password ) {
            $user_password = trim( $password[$this->prefix.'password'] );
            $user_id = trim( $password[$this->prefix.'user'] );
            
            if( ! empty( $user_password ) && $hasher->CheckPassword( $user_password, $hash ) ) {
                if(get_current_user_id() == (string) $user_id) {
                    $required_pass = false;
                }
            }              
        }
        return $required_pass;
    }
    
    function multy_pass_form( $output ) {
        if( ! is_page() || ! in_the_loop() || did_action( 'the_password_form' ) )
            return $output;
    
        $post = get_post();
    
        // Display password form if none of the passwords matches:  
        if( $this->post_password_required( $post ) )
            return $output;
    
        // Get the current password
        $password = $post->post_password;
    
        // Temporary remove it
        $post->post_password = '';
    
        // Fetch the content
        $content = get_the_content();
    
        // Set the password back
        $post->post_password = $password;
    
        return $content;
    }
    
    function style_admin_pass_box() {
        ?>
        <style>
            <?php echo '#'.$this->prefix.$this->field_id.'_repeat'; ?> .cmb-repeat-group-field {
                float: left;
                width: 48%;
                margin: 0 1%;
            }
        </style>
        <?php
    }
}

$muly_pass = new FF_Multiple_passwords();
$muly_pass->init();
