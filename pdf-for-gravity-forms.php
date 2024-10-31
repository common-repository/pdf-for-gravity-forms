<?php
/**
 * Plugin Name: PDF for Gravity Forms + Drag And Drop Template Builder
 * Description:  Gravity Forms PDF is a helpful tool that helps you build and customize the PDF Templates for Gravity Forms.
 * Plugin URI: https://add-ons.org/plugin/gravity-form-pdf-generator-attachment/
 * Version: 4.1.1
 * Requires PHP: 5.6
 * Author: add-ons.org
 * Author URI: https://add-ons.org/
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
define( 'BUIDER_PDF_GF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BUIDER_PDF_GF_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
if(!class_exists('Yeepdf_Creator_Builder')) {
    require 'vendor/autoload.php';
    if(!defined('YEEPDF_CREATOR_BUILDER_PATH')) {
        define( 'YEEPDF_CREATOR_BUILDER_PATH', plugin_dir_path( __FILE__ ) );
    }
    if(!defined('YEEPDF_CREATOR_BUILDER_URL')) {
        define( 'YEEPDF_CREATOR_BUILDER_URL', plugin_dir_url( __FILE__ ) );
    }
    class Yeepdf_Creator_Builder {
        function __construct(){
            $dir = new RecursiveDirectoryIterator(YEEPDF_CREATOR_BUILDER_PATH."backend");
            $ite = new RecursiveIteratorIterator($dir);
            $files = new RegexIterator($ite, "/\.php/", RegexIterator::MATCH);
            foreach ($files as $file) {
                if (!$file->isDir()){
                    require_once $file->getPathname();
                }
            }
            include_once YEEPDF_CREATOR_BUILDER_PATH."libs/phpqrcode.php";
            include_once YEEPDF_CREATOR_BUILDER_PATH."frontend/index.php";
        }
    }
    new Yeepdf_Creator_Builder;
}
class Yeepdf_Creator_Gravity_Forms_Builder { 
    function __construct(){
        include BUIDER_PDF_GF_PLUGIN_PATH."gravityforms/gfcommon_style.php";
        include BUIDER_PDF_GF_PLUGIN_PATH."gravityforms/index.php";
        include BUIDER_PDF_GF_PLUGIN_PATH."gravityforms/pdf_table.php";
        //include BUIDER_PDF_GF_PLUGIN_PATH."gravityforms/gravityview.php";
        add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array($this,'add_link') );
        register_activation_hook( __FILE__, array($this,'activation') );
        include BUIDER_PDF_GF_PLUGIN_PATH."superaddons/check_purchase_code.php";
        new Superaddons_Check_Purchase_Code( 
            array(
                "plugin" => "pdf-for-gravity-forms/pdf-for-gravity-forms.php",
                "id"=>"1536",
                "pro"=>"https://add-ons.org/plugin/gravity-form-pdf-generator-attachment/",
                "plugin_name"=> "PDF Creator for Gravity Forms",
                "document"=>"https://pdf.add-ons.org/document/"
            )
        );
    }
    function add_link( $actions ) {
        $actions[] = '<a target="_blank" href="https://pdf.add-ons.org/document/" target="_blank">'.esc_html__( "Document", "pdf-for-gravityforms" ).'</a>';
        $actions[] = '<a target="_blank" href="https://add-ons.org/supports/" target="_blank">'.esc_html__( "Supports", "pdf-for-gravityforms" ).'</a>';
        return $actions;
    }
    function activation() {
        global $wpdb;
        //install data
        include_once(ABSPATH.'wp-admin/includes/plugin.php');
        $pdf_creator = $wpdb->prefix.'gf_form_pdf_creator';
        if( $wpdb->get_var("SHOW TABLES LIKE '$pdf_creator'") != $pdf_creator ) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $pdf_creator (
                id INT NOT NULL AUTO_INCREMENT,
                form_id INT NULL,
                name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
                template LONGTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
                notifications VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
                filename VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
                password VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
                conditional_logic INT NULL,
                conditional_logic_datas VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
                PRIMARY KEY (id)
            ) $charset_collate;";
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
            $data = file_get_contents(BUIDER_PDF_GF_PLUGIN_PATH."gravityforms/form-import.json");
            $my_template = array(
            'post_title'    => "Gravity Forms Default",
            'post_content'  => "",
            'post_status'   => 'publish',
            'post_type'     => 'yeepdf'
            );
            $id_template = wp_insert_post( $my_template );
            add_post_meta($id_template,"data_email",$data);      
            add_post_meta($id_template,"_builder_pdf_settings_font_family",'dejavu sans');
        }
    }
}
new Yeepdf_Creator_Gravity_Forms_Builder;
if(!class_exists('Superaddons_List_Addons')) {  
    include BUIDER_PDF_GF_PLUGIN_PATH."add-ons.php"; 
}