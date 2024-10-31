<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
class Superaddons_Gravity_Forms_Pdf_List_Table extends WP_List_Table{
    private $notifications = null;
    public function set_notifications($notifications){
        $this->notifications = $notifications;
    }
    protected function display_tablenav( $which ) {
    }
    public function prepare_items($upcoming =false){
        global $wpdb; 
        $table_name = $wpdb->prefix."gf_form_pdf_creator";
        
        $hidden = array();
        $columns = $this->get_columns();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->process_bulk_action();
        $form_id = "";
        if(isset($_GET["id"])){
            $form_id = sanitize_text_field($_GET["id"]);
        }
        $data= $wpdb->get_results("SELECT * FROM $table_name WHERE form_id = $form_id", ARRAY_A);
        $total_items = count($data);
        $currentPage = $this->get_pagenum();
        $per_page = 50;
        $paged = isset($_REQUEST['paged']) ? max(0, intval($_REQUEST['paged'] - 1) * $per_page) : 0;
        $orderby = (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], array_keys($this->get_sortable_columns()))) ? $_REQUEST['orderby'] : 'id';
        $order = (isset($_REQUEST['order']) && in_array($_REQUEST['order'], array('asc', 'desc'))) ? $_REQUEST['order'] : 'asc';
        $this->set_pagination_args(
            array(
                'total_items' => $total_items,
                'per_page'    => $per_page,
                'total_pages' => ceil($total_items / $per_page),
            )
        );

        $this->items      = $data;
    }
    public function no_items() {
        esc_html_e( 'This form does not have any PDFs.', 'pdf-for-gravityforms' );
    }
    function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="id[]" value="%s" />',
            $item['id']
        );
    }
    public function get_columns()
    {
        $columns = array(
            "name"=>esc_html__("Name","pdf-for-gravityforms"),
            "template"=>esc_html__("Template","pdf-for-gravityforms"),
            "notifications"=>esc_html__("Notifications","pdf-for-gravityforms"));
        return $columns;
    }
    function get_bulk_actions(){
        $actions = array(
            'delete' => 'Delete'
        );
        return $actions;
    }
    function process_bulk_action(){
        
    }
    function column_template($item){
        return $item["name"];  
    }
    function column_notifications($item){
        $notification_names = array();
        if($item["notifications"] != ""){
            $nt = json_decode($item['notifications'],true);
            foreach( $this->notifications as $key=> $notification ){
                if ( is_array($nt) && in_array( $notification['id'], $nt, true ) ) {
                    $notification_names[] = $notification['name'];
                }
            }
            return esc_html( implode( ', ', $notification_names ) );
        }else{
            return "No notification";
        }
        return $item["notifications"];  
    }
    function column_name($item){
        $page="";
        if(isset($_GET["id"])){
            $form_id = sanitize_text_field($_GET["id"]);
        }
        $edit_link = admin_url( 'admin.php?page=gf_edit_forms&view=settings&subview=pdf_creator_form_settings&id='.$form_id.'&pdf_id='.$item['id']);
        $delete_link = admin_url( 'admin.php?page=gf_edit_forms&view=settings&subview=pdf_creator_form_settings&id='.$form_id.'&pdf_id='.$item['id']."&action=delete&nonce=".wp_create_nonce("gf_pdf_save_settings"));
        $actions = array(
            'edit' => sprintf('<a href="%s">%s</a>',$edit_link, __('Edit', 'pdf-for-gravityforms')),
            'delete' => sprintf('<a class="check-delete" href="%s">%s</a>', $delete_link, __('Delete', 'pdf-for-gravityforms')),
        );
        if(isset($item['name'])){
            return sprintf('%s %s',
                $item['name'],
                $this->row_actions($actions)
            );
        }
    }
    /**
     * Define which columns are hidden
     *
     * @return Array
     */
    public function get_hidden_columns()
    {
        return array();
    }
    public function get_sortable_columns()
    {
        return array();
    }
    public function column_default( $item, $column_name ){
        $columns = apply_filters("manage_gf_pdfs_custom_column",$column_name, $item["id"],$item);
        return $columns;
    }
}