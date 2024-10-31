<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
class Superaddons_Gravity_Formsview{
	function __construct(){
		add_filter( 'gravityview_field_entry_link', array($this,"pdf_download_entries_link"),10,4 );
		add_filter("gravityview_entry_default_fields",array($this,"gravityview_add_pdf_template"));
		//add_action("gravityview_table_cells_before",array($this,"gravityview_table_cells_before"));
	}
	function gravityview_add_pdf_template($entry_default_fields){
		$entry_default_fields['pdf_template']	= array(
			'label'	=> __('PDF Download', 'pdf-for-gravityforms'),
			'type'	=> 'pdf_template',
			'desc'	=> __('PDF Download link.', 'pdf-for-gravityforms'),
		);
		return $entry_default_fields;
	}
	function gravityview_table_cells_before($a){
		//var_dump($a);
	}
	function pdf_download_entries_link( $link, $href, $entry, $field_settings  ){
		if( isset($field_settings["entry_link_pdf_download"]) && $field_settings["entry_link_pdf_download"] == 1 ){
			$upload_dir = wp_upload_dir();
		    $path_main = $upload_dir['baseurl'] . '/pdfs/';
			$link_download_full ="";
			$link_download = gform_get_meta($entry_id,"pdf_links");
			if( $link_download != ""){ 
				$link_download_data = json_decode($link_download,true);	
				foreach( $link_download_data as $name ){
					$link_download_full .= '<a target="_blank" href="'.$this->cover_link_dowwnload($path_main.$name).'" download >'.$field_settings["entry_link_text"].'</a>';
				} 
				return $link_download_full;
			}else{
				return null;
			}
		}
    	return $link;
	}	
}