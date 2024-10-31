<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
class Yeepdf_Creator_GravityFroms_Backend {
	private $processing = false;
	private $pdf_lists = array();
	function __construct(){
		add_filter("yeepdf_shortcodes",array($this,"add_shortcode"));
		add_action("yeepdf_head_settings",array($this,"add_head_settings"));
		add_action( 'save_post_yeepdf',array( $this, 'save_metabox' ), 10, 2 );	
		add_filter( 'gform_form_settings_menu', array($this,'add_custom_settings_tab') );
		add_action( 'gform_form_settings_page_pdf_creator_form_settings', array($this,"pdf_creator_form_settings") );
		add_filter( 'gform_entry_detail_meta_boxes',array($this,"pdf_meta_box"), 10, 3 );
        //add_action("gform_merge_tag_filter",array($this,"add_html_tag"),10,4);
        add_filter("gform_replace_merge_tags",array($this,"gform_replace_merge_tags"));
        add_shortcode('pdf_download_gf', array($this,"pdf_download_shortcode") );
        add_action( 'custom_download_entry', array($this,"pdf_download_entries") );
        add_action( 'gform_entries_first_column_actions', array($this,"modify_list_row_actions"), 10, 4 );
        add_action( 'gform_after_submission', array($this,"save_pdf"), 10, 2 );
        add_filter( 'gform_notification', array($this,'notifications_attachments'), 10, 3 );
        add_filter("superaddons_pdf_check_pro",array($this,"check_pro"));
        add_filter( 'gform_noconflict_scripts', array($this,"register_script") );
        add_filter( 'gform_noconflict_styles', array($this,"register_style") );
		add_action('admin_enqueue_scripts', array($this,"add_js"));
		add_action('wp_ajax_pdfbuilder_gf_re_generate', array($this,"pdfbuilder_gf_re_generate"));
		add_filter("yeepdf_add_libs",array($this,"yeepdf_add_libs"));
	}
	function gform_replace_merge_tags($confirmation_message){
		if (preg_match('/pdf_download_gf entry_id="(\d+)"/', $confirmation_message, $matches)) {
			$entry_id = $matches[1];
			$link_download = gform_get_meta($entry_id,"pdf_links");
			$url_download ="";
			if( $link_download != ""){
				$upload_dir = wp_upload_dir();
				$path_main = $upload_dir['baseurl'] . '/pdfs/';
				$link_download_data = json_decode($link_download,true);	
				foreach( $link_download_data as $name ){
					$url_download ='<a download="download" href="'.$this->cover_link_dowwnload($path_main.$name).'">Download PDF ';
				} 
				$confirmation_message = str_replace('|pdf_download_gf entry_id="'.$entry_id.'"|', $url_download, $confirmation_message);
			}else{
				$confirmation_message = str_replace('|pdf_download_gf entry_id="'.$entry_id.'"|', "", $confirmation_message);
			}
		}
		return $confirmation_message;
	}
	function pdfbuilder_gf_re_generate(){
		if ( isset($_POST[ 'nonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ 'nonce' ] ) ), 'pdfbuilder_gravityforms' ) ) {
			$form_id = sanitize_text_field( $_POST[ 'form_id' ]);
			$lead_id = sanitize_text_field( $_POST[ 'entry_id' ]);
			$lead = RGFormsModel::get_lead( $lead_id );
			$form = RGFormsModel::get_form_meta( $form_id );
			$this->processing_pdf($form,$lead);
		}
		die();
	}
	function add_js(){
		wp_enqueue_script('pdfbuilder_gravityforms', BUIDER_PDF_GF_PLUGIN_URL. 'gravityforms/gravityforms.js');
		wp_localize_script('pdfbuilder_gravityforms', 'pdfbuilder_gravityforms', array(
			'url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('pdfbuilder_gravityforms')
		));
	}
	function yeepdf_add_libs($add){
		if(isset($_GET["page"]) && $_GET["page"] == "gf_edit_forms"){
			$add = true;
		}
		return $add;
	}
	function register_style( $styles ) {
        $styles[] = 'yeepdf-font';
        $styles[] = 'yeepdf-momonga';
        $styles[] = 'yeepdf-main';
        return $styles;
    }
    function register_script($scripts){
        $scripts[] = 'yeepdf_main';
        $scripts[] = 'yeepdf_builder';
        $scripts[] = 'yeepdf_editor';
        $scripts[] = 'yeepdf_script';
        $scripts[] = 'pdfbuilder_gravityforms';
        return $scripts;
    }
	function check_pro($pro){
		$check = get_option( '_redmuber_item_1536');
		if($check == "ok"){
			$pro = true;
		}
		return $pro;
	}
	function save_pdf($entry, $form ){
		global $wpdb;
		$pdf_lists = $this->pdf_lists;
		if(count($pdf_lists) < 1){
			$this->processing_pdf($form,$entry);
		}
	}
	function notifications_attachments(  $notification, $form, $lead ) {
        global $wpdb;
        $notification['attachments'] = ( is_array( rgget('attachments', $notification ) ) ) ? rgget( 'attachments', $notification ) : array();
		$datas = array();
		$pdf_link = array();
		$pdf_lists = $this->pdf_lists;
		if(count($pdf_lists) < 1){
			$this->processing_pdf($form,$lead);
		}
		$pdf_settings = $this->get_pdf_settings_by_form_id($form["id"]);
		if($pdf_settings){
			$pdf_lists = $this->pdf_lists;
			foreach($pdf_settings as $setting ){
				$id = $setting["id"];
				if(  $setting["notifications"] != "" ){
					$notifications_dataa = json_decode( $setting["notifications"],true);
					if( is_array($notifications_dataa) && in_array($notification["id"],$notifications_dataa)){
						$notification['attachments'][]  = $pdf_lists[$id];
					}
				}
			}
		}
		return $notification;
	}
	function processing_pdf($form,$entry){
		global $wpdb;
		$datas = array();
		$pdf_lists = array();
		$datas_name = array();
		$form_id =$form["id"];
		$pdf_settings = $this->get_pdf_settings_by_form_id($form_id);
		if($pdf_settings){
			foreach( $form["fields"] as $field ){
	        	if( isset($field->inputs) && is_array($field->inputs) ){
	        		foreach( $field->inputs as $input_inner ){
	        			$datas_name[ $input_inner["id"]] = $field->label." (".$input_inner["label"].")";
	        		}
				}else{
					$datas_name[ $field->id] = $field->label;
				}
	        }
			foreach( $entry as $k => $v ){
	    		if( is_numeric($k)  ){
	    			$datas["{".$datas_name[$k].":".$k."}"] = $v;
	    		}else{
	    			$datas["{{".$k."}}"] = $v;
	    		}
    		}
			foreach($pdf_settings as $setting ){
				$id = $setting["id"];
				$check = $setting["conditional_logic"];
				$check_datas = $setting["conditional_logic_datas"];
				$template_id = $setting["template"];
				$name = $setting["filename"];
				$password = $setting["password"];
				if($check == 1){
					$show = Yeepdf_Create_PDF::is_logic($check_datas,$datas);
					if(!$show){
						continue;
					}
				}
				if( $name != ""  ){
					if(strpos($name, "entry_id") == false ){
						$name= GFCommon::replace_variables( $name , $form, $entry,false, false,false,"text" );
						$name = rand(1000,9999)."-".sanitize_file_name($name);
					}else{
						$name= GFCommon::replace_variables( $name , $form, $entry,false, false,false,"text" );
						$name = sanitize_file_name($name);
					}
		    	}else{
					$name = "contact";
					$name = rand(1000,9999)."-".sanitize_file_name($name);
				}
		    	if( $password != ""  ){
		    		$password= GFCommon::replace_variables( $password , $form, $entry,false, false,false,"text" );
		    	}
				$data_send_settings = array(
		    		"id_template"=> $template_id,
		    		"type"=> "html",
		    		"name"=> $name,
		    		"datas" =>$datas,
		    		"return_html" =>true,
		    	);
				$content = Yeepdf_Create_PDF::pdf_creator_preview($data_send_settings);
				$fileupload_fields = GFCommon::get_fields_by_type( $form, array( 'signature','fileupload' ) );
				if (  is_array( $fileupload_fields ) && count($fileupload_fields) > 0 ) { 
		    		foreach( $fileupload_fields as $field ) { 
		    			preg_match_all( '/{[^{]*?:(\d+(\.\d+)?)(:(.*?))?}/mi', $content, $matches, PREG_SET_ORDER );
						if ( is_array( $matches ) ) {
							foreach ( $matches as $match ) {
								if(isset($entry[$field->id])) {
									$input_id = $match[1];
									if($input_id == $field->id && $entry[$field->id] != "" ) {
										$urls =$entry[$field->id];
										$urls = explode(",",$urls);
										if( count($urls) > 0 ){
											$url = $urls[0];
											$url = preg_replace('/\[|\]|\"/', '', $url);
											$url = stripslashes($url);
										}else{
											$url = "";
										}
										$upload_path = GFFormsModel::get_upload_url_root() . 'signatures/';
										if ( $field->type == "signature" ) {
										    $url = sprintf('%s',$upload_path.$url);
										}else{
											$url = sprintf('%s',$url);
										}
										$content = str_replace( $match[0], $url, $content );
									}
								}
							}
						}
		    		}
		    	}
				$repeater = GFCommon::get_fields_by_type( $form, array( 'repeater_end' ) );
				if (  is_array( $repeater ) && count($repeater) > 0 ) {
					foreach( $repeater as $repeater_filed ) {
						$repeater_id =$repeater_filed->id;
						preg_match_all( '/{[^{]*?:('.$repeater_id.')(:(.*?))?}/mi', $content, $matches, PREG_SET_ORDER );
						if ( is_array( $matches ) ) {
							foreach ( $matches as $match ) {
								if(isset($entry[$repeater_filed->id])) {
									$data_repeater = maybe_unserialize($entry[$repeater_filed->id]);
									$html_repeater = "<div>";
									foreach($data_repeater as $field_rp){
										$html_repeater .='<ul>';
										foreach( $field_rp as $name=>$vl ){
											$type = $this->get_type($form,$name,$form_id);
											$lb = $this->get_field_label($form, $name, $type,false,$form_id);
											if( is_array($vl)){
												switch($type ){
													case "address":
														$vl_data = "";
														foreach( $vl as $k => $v ){
															$child_lb = $this->get_field_label($form, "input_".$k, $type,true);
															$vl_data .= $child_lb.": ".$v ."<br>";
														}
														$html_repeater .= '<li>'.$lb.": <br>". $vl_data."</li>";
														break;
													default:
														$vl_data = implode(", ",$vl);
														$html_repeater .= '<li>'.$lb.": ". $vl_data."</li>";
														break;
												}
											}else{
												$vl_data = $vl;
												switch($type ){ 
													case "fileupload":
														if (filter_var($vl_data, FILTER_VALIDATE_URL) === FALSE) {
															$content= array();
															$main_name = explode("__",$name);
															$main_name = explode("_",$main_name[0]);
															$main_name_id = $main_name[4];
															$vl_data = explode(",",$vl_data);
															if(isset($result[$main_name_id])){
																$data_uploads = json_decode($result[$main_name_id],true);
																foreach( $vl_data as $n ){
																	if ( version_compare( phpversion(), '7.4', '<' ) && get_magic_quotes_gpc() ) {
																		$n = stripslashes( $n );
																	}
																	$n = sanitize_file_name( $n );
																	foreach( $data_uploads as $name ){
																		$name_s = explode(".",$n);
																		$name_s = $name_s[0];
																		$re = "/".$name_s."\.|".$name_s."[\d]\./";
																		if (preg_match($re, $name) ){
																			$content[] = '<a href="'.$name.'" download>'. $n."</a> ";
																			break;
																		}
																	}
																}
															}
															$html_repeater .= '<li>'.$lb.': '.implode(" | ",$content)."</li>";
														}else{
															$html_repeater .= '<li>'.$lb.': <a href="'.$vl_data.'" download>'. $vl_data."</a></li>";
														}
														break;
													default:
														$html_repeater .= '<li>'.$lb.": ". $vl_data."</li>";
														break;
												}
											}
										}
										$html_repeater .= '</ul></br>';
									}
									$html_repeater .= "</div>";
									$content = str_replace( $match[0], $html_repeater, $content );
								}
							}
						}
					}
				}
				$content = str_replace(array("wp_builder_pdf_qrcode_new","wp_builder_pdf_barcode_new"),array("wp_builder_pdf_qrcode_1new","wp_builder_pdf_barcode_1new"),$content);
		    	$message = Yeepdf_GFCommon::replace_variables( $content, $form, $entry, false, false,false,"html" );
				$message = str_replace(array("wp_builder_pdf_qrcode_1new","wp_builder_pdf_barcode_1new"),array("wp_builder_pdf_qrcode_new","wp_builder_pdf_barcode_new"),$message);
		    	//$message = preg_replace( "/<br\/>|<br>|<br \/>|\n/", "", $message );
		    	$datas_pdf_settings["pdf_name"] = $name;
		    	$data_send_settings_download = array(
		    		"id_template"=> $template_id,
		    		"type"=> "upload",
		    		"name"=> $name,
		    		"datas" =>$datas,
		    		"html" =>$message,
		    		"password" =>$password,
		    	);
				$path =Yeepdf_Create_PDF::pdf_creator_preview($data_send_settings_download);
				$pdf_lists[$id] = $path;
				update_option( "download_link_pdf_gf", $name );
			}
			$this->pdf_lists = $pdf_lists;
			gform_update_meta( $entry["id"], 'pdf_links',json_encode($pdf_lists));
		}
	}
	function get_field_label($form, $name = '', $type = '', $child = false, $form_id ='') {
		if (is_array($form)) {
			if (!array_key_exists('fields', $form)) { return false; }
		} else { return false; }
		$name = str_replace("gform_multifile_upload_".$form_id, "input", $name);
		$names = explode("__",$name);
		$names = explode("_",$names[0]);
		$name = $names[1];
		foreach ($form['fields'] as $field_key=>$field_value) {
			if( is_array($field_value->inputs)){
				foreach( $field_value->inputs  as $children_id ){
					if( $children_id["id"] == $name ) {
						if( $child ){
							return $children_id["label"];
						}else{
							return $field_value->label;
						}
					}
				}
			}else{
				if( $field_value->id == $name ) {
					return $field_value->label;
				}
			}
		}
		return "";
	}
	function get_inputs($form, $name = '') {
		if (is_array($form)) {
			if (!array_key_exists('fields', $form)) { return false; }
		} else { return false; }
		$names = explode("__",$name);
		$names = explode("_",$names[0]);
		$name = $names[1];
		foreach ($form['fields'] as $field_key=>$field_value) {
			if( $field_value->id == $name ) { 
				if( is_array($field_value->inputs)){
					return $field_value->inputs;
				}
			}	
		}
		return false;
	}
	function get_type($form, $name = '',$form_id ="") {
		if (is_array($form)) {
			if (!array_key_exists('fields', $form)) { return false; }
		} else { return false; }
		$name = str_replace("gform_multifile_upload_".$form_id, "input", $name);
		$names = explode("__",$name);
		$names = explode("_",$names[0]);
		$name = $names[1];
		foreach ($form['fields'] as $field_key=>$field_value) {
			if( is_array($field_value->inputs)){
				foreach( $field_value->inputs  as $children_id ){
					if( $children_id["id"] == $name ) {
						return $field_value->type;
					}
				}
			}else{
				if( $field_value->id == $name ) {
					return $field_value->type;
				}
			}
		}
		return "";
	}
	function get_pdf_settings_by_form_id($form_id){
		global $wpdb;
		$table_name = $wpdb->prefix."gf_form_pdf_creator";
		$pdf_settings = $wpdb->get_results(
					        $wpdb->prepare(
						            "
						            SELECT * FROM $table_name
						            WHERE form_id = %d
						            ",
						            $form_id
						        ), 
        				ARRAY_A);
		return $pdf_settings;
	}
	function modify_list_row_actions( $form_id, $field_id, $value, $entry) {
		$link_download = gform_get_meta($entry["id"],"pdf_links");
		if( $link_download != ""){
			$upload_dir = wp_upload_dir();
		    $path_main = $upload_dir['baseurl'] . '/pdfs/';
			$link_download_data = json_decode($link_download,true);	
			foreach( $link_download_data as $name ){
				?>
				| <a target="_blank" href="<?php echo esc_url($path_main.$name) ?>"
					download><?php esc_html_e("Download PDF","pdf-for-gravityforms") ?></a>
				<?php
			} 
		}
	}
	function add_custom_settings_tab( $menu_items  ) {
	    $menu_items[] = array(
	        'name' => 'pdf_creator_form_settings',
	        'label' => esc_html__("PDF Creator","pdf-for-gravityforms"),
	        'icon' => 'dashicons-pdf'
        );
    	return $menu_items;
	}
	function pdf_creator_form_settings(){
		global $wpdb;
		$pro = Yeepdf_Settings_Builder_PDF_Backend::check_pro();
		$table_name = $wpdb->prefix."gf_form_pdf_creator";
		$form_id = isset( $_GET['id'] ) ? sanitize_text_field($_GET['id']) : false;
		$pdf_id  = isset( $_GET['pdf_id'] ) ? sanitize_text_field( $_GET['pdf_id'] ) : -1;
		//remove
		if ( isset($_REQUEST['action']) && $_REQUEST['action'] =="delete" && isset($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], "gf_pdf_save_settings")) {
			$wpdb->query(
	            "DELETE FROM $table_name
	             WHERE id = $pdf_id"     
	        );
	        $location = admin_url( 'admin.php?page=gf_edit_forms&view=settings&subview=pdf_creator_form_settings&id='.$form_id);
			wp_redirect( $location );
		}
		GFFormSettings::page_header();
			?>
		<div class="gform-settings__content">
			<div class="gform-settings-panel">
				<header class="gform-settings-panel__header">
					<h4 class="gform-settings-panel__title"><?php esc_html_e("Gravity PDF Creator","pdf-for-gravityforms") ?>
					</h4>
				</header>
				<div class="gform-settings-panel__content">
            	<?php
				if($pdf_id >= 0) {
					if($pdf_id == 0 ){
						$settings = array(
							"name"=>"",
							"template"=>"",
							"filename"=>"",
							"password"=>"",
							"conditional_logic"=>"",
							"notifications"=>"",
							"conditional_logic_datas"=>"",
						);
					}else{
						$settings = $wpdb->get_row(
							"
								SELECT *
								FROM $table_name
								WHERE id = $pdf_id
							",ARRAY_A
						);
					}
					if ( isset($_REQUEST['action']) && $_REQUEST['action'] =="gf_pdf_save_settings" && isset($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], "gf_pdf_save_settings")) {
						$form_id = sanitize_textarea_field($_POST["form_id"]);
						$id = sanitize_textarea_field($_POST["id"]);
						$logic = sanitize_textarea_field($_POST["logic"]);
						if($logic == 1){
							$pdfcreator_logic= map_deep( $_POST["yeepdf_logic"], 'sanitize_textarea_field' );
						}
						$template = sanitize_textarea_field($_POST["template_id"]);
						$notifications= map_deep( $_POST["notifications"], 'sanitize_textarea_field' );
						$atts = array(
							"form_id" => $form_id,
							"name" => sanitize_textarea_field($_POST["name"]),
							"template" => $template,
							"notifications" => json_encode($notifications),
							"filename" => sanitize_textarea_field($_POST["filename"]),
							"password" => sanitize_textarea_field($_POST["password"]),
							"conditional_logic" => sanitize_textarea_field($_POST["logic"]),
							"conditional_logic_datas" => json_encode($pdfcreator_logic)
						);
						if($id < 1){
							$wpdb->insert( 
								$table_name, 
								$atts
							);
							$id = $wpdb->insert_id;
						}else{
							//update
							$id_done = $wpdb->update( 
									$table_name, 
									$atts, 
									array( 'id' => $pdf_id
											),
								);
						}
						$location = admin_url( 'admin.php?page=gf_edit_forms&view=settings&subview=pdf_creator_form_settings&id='.$form_id.'&pdf_id='.$id);
						wp_redirect( $location );
					}
			?>
            <form method="post" class="gform_settings_form1 ">
                <input type="hidden" name="action" value="gf_pdf_save_settings" />
                <input type="hidden" name="id" value="<?php echo esc_attr($pdf_id) ?>" />
                <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id) ?>" />
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce("gf_pdf_save_settings")?>" />
                <div class="gform-settings-field gfpdf-settings-field-wrapper">
                    <div class="gform-settings-panel__title"><?php esc_html_e("Label","pdf-for-gravityforms") ?><span
                            class="gfield_required">(<?php esc_html_e("required","pdf-for-gravityforms") ?>)</span>
                    </div>
                    <div class="gform-settings-description gform-kitchen-sink">
                        <label><?php esc_html_e("Add a descriptive label to help you  differentiate between multiple PDF settings.","pdf-for-gravityforms") ?></label>
                    </div>
                    <input type="text" class="regular-text " row="10" id="gfpdf_settings[name]" name="name"
                        value="<?php echo esc_attr($settings["name"]) ?>" required>
                </div>
                <div class="gform-settings-field gfpdf-settings-field-wrapper">
                    <div class="gform-settings-panel__title"><?php esc_html_e("Template","pdf-for-gravityforms") ?><span
                            class="gfield_required">(<?php esc_html_e("required","pdf-for-gravityforms") ?>)</span>
                    </div>
                    <div class="gform-settings-description gform-kitchen-sink">
                        <select name="template_id">
                            <?php 
							$templates = array();
							$pdf_templates = get_posts(array( 'post_type' => 'yeepdf','post_status' => 'publish','numberposts'=>-1 ) );
							if($pdf_templates){
								foreach ( $pdf_templates as $post ) {
									$post_id = $post->ID;
									?>
                            <option <?php selected($settings["template"],$post_id) ?>
                                value="<?php echo esc_attr($post_id) ?>"><?php echo esc_html($post->post_title) ?>
                            </option>
                            <?php
								}
							}else{
								?>
                            <option value="-1"><?php esc_html_e("No template","pdf-for-gravityforms") ?></option>
                            <?php
							}
							?>
                        </select>
                    </div>
                </div>
                <div class="gform-settings-field gfpdf-settings-field-wrapper">
                    <div class="gform-settings-panel__title">
                        <?php esc_html_e("Notifications","pdf-for-gravityforms") ?></span>
                    </div>
                    <div class="gform-settings-description gform-kitchen-sink">
                        <label
                            for="gravityforms_pdf[name]"><?php esc_html_e("Send the PDF as an email attachment for the selected notifications. Password protect the PDF if security is a concern","pdf-for-gravityforms") ?></label>
                    </div>
                    <div class="gform-settings-description gform-kitchen-sink">
                        <?php 
								$notifications = $this->get_all_notifications($form_id); 
								$datas_notifications = array();
								if($settings["notifications"] != ""){
									$datas_notifications = json_decode($settings["notifications"],true);
								}
								if($notifications){
									foreach( $notifications as $key=> $notification ){
										$checked = "";
										if($datas_notifications != ""){
											if(in_array($key,$datas_notifications)){
												$checked = 'checked="checked"';
											}
										}
										$name = "Notifications Default";
										if($notification["name"] != ""){
											$name = $notification["name"];
										}
										?>
									<input <?php echo esc_attr($checked) ?> type="checkbox" class="regular-text "
										name="notifications[]" value="<?php echo esc_html($notification["id"]) ?>">
									<?php echo esc_html($name) ?>
									<?php
									}
								}else{
									esc_html_e("No notifications","pdf-for-gravityforms");
								}
								?>
                    </div>
                </div>
                <div class="gform-settings-field gfpdf-settings-field-wrapper">
                    <div class="gform-settings-panel__title">
                        <?php esc_html_e("Filename","pdf-for-gravityforms") ?></span>
                    </div>
                    <div class="gform-settings-description gform-kitchen-sink">
                        <label
                            for="gravityforms_pdf[name]"><?php esc_html_e("Set the filename for the generated PDF","pdf-for-gravityforms") ?></label>
                    </div>
                    <div class="gform-settings-description gform-kitchen-sink">
                        <?php
						Yeepdf_Settings_Main::add_number_seletor("filename",$settings["filename"]);
						?>
                    </div>
                </div>
                <div class="gform-settings-field gfpdf-settings-field-wrapper ">
                    <div class="gform-settings-panel__title">
                        <?php esc_html_e(" Password","pdf-for-gravityforms") ?></span>
                    </div>
                    <div class="gform-settings-description gform-kitchen-sink">
                        <label
                            for="gravityforms_pdf[name]"><?php esc_html_e("You have the option to password-protect your PDF documents","pdf-for-gravityforms") ?></label>
                    </div>
                    <?php 
						if($pro){ 
							Yeepdf_Settings_Main::add_number_seletor("password",$settings["password"]);
						}else{
							esc_html_e("Upgrade to pro version","pdf-for-gravityforms");
						}
							?>
                </div>
                <div class="gform-settings-field gfpdf-settings-field-wrapper ">
                    <?php 
						$conditional = array();
						if($settings["notifications"] != ""){
							$conditional = json_decode($settings["conditional_logic_datas"],true);
							if(!$conditional){
								$conditional = array(
									"type"=> "show",
									"logic"=> "all",
									"data"=> array()
								);
							}
						}else{
							$conditional = array(
								"type"=> "show",
								"logic"=> "all",
								"data"=> array()
							);
						}
					?>
                    <div class="gform-settings-panel__title">
                        <?php esc_html_e("Conditional Logic","pdf-for-gravityforms") ?></span>
                    </div>
                    <div class="gform-settings-description gform-kitchen-sink">
                        <label
                            for="gravityforms_pdf[name]"><?php esc_html_e("Add rules to dynamically enable or disable the PDF. When disabled, PDFs do not show up in the admin area, cannot be viewed, and will not be attached to notifications.","pdf-for-gravityforms") ?></label>
                    </div>
                    <div class="gform-settings-description gform-kitchen-sink">
                        <?php 
						$check_logic ='';
						$class_logic_container = "hidden";
						if(isset($settings["conditional_logic"]) && $settings["conditional_logic"] == 1){
							$class_logic_container="";
						}
						if($pro){  
						?>
                        <input <?php checked($settings["conditional_logic"],1) ?> value="1" type="checkbox" name="logic"
                            id="pdf_creator_conditional_logic">
                        <?php esc_html_e(" Enable conditional logic","pdf-for-gravityforms") ?>
						<?php 
						Yeepdf_Settings_Main::get_conditional_logic($conditional,$class_logic_container);
						?>
                        <?php }else{ ?>
                        <input disabled type="checkbox">
                        <?php esc_html_e(" Enable conditional logic (Upgrade to pro version)","pdf-for-gravityforms") ?>
                        <?php } ?>
                    </div>
                </div>
                <div class="submit-container-0">
					<input type="submit" name="submit" value="Save PDF" class="button primary large">
				</div>
				<style>
					.gform-settings-field .pdf-marketing-merge-tags-container {
						max-width: 350px !important;
					}
				</style>
            </form>
            <?php
				}else{
					$edit_link = admin_url( 'admin.php?page=gf_edit_forms&view=settings&subview=pdf_creator_form_settings&id='.$form_id.'&pdf_id=0');
					?>
				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
					</div>
					<div class="alignright">
						<a href="<?php echo esc_url($edit_link) ?>"
							class="button"><?php esc_html_e("Add New","pdf-for-gravityforms") ?></a>
					</div>
					<br class="clear">
				</div>
				<?php
							//show table
							$table = new Superaddons_Gravity_Forms_Pdf_List_Table();
							$notifications = $this->get_all_notifications($form_id);
							$table->set_notifications($notifications);
							$table->prepare_items();
							$table->display();
						}
						?>
			</div>
		</div>
	</div>
	<?php
	    GFFormSettings::page_footer();
	}
	function get_all_notifications($form_id){
		global $wpdb;
		$table_name = $wpdb->prefix."gf_form_meta";
		$notifications = $wpdb->get_row(
				    $wpdb->prepare(
				    "
				        SELECT *
				        FROM $table_name
				        WHERE form_id = %d
				    ",$form_id),
				    ARRAY_A
				);
		if($notifications && $notifications["notifications"] != ""){
			$notifications = json_decode($notifications["notifications"],true);
		}
		return $notifications;
	}
	function add_html_tag($value, $tag, $modifiers, $field ){
		if( $field->type == 'html' && ( $tag != 'all_fields' || in_array( 'allowHtmlFields', explode( ',', $modifiers ) ) ) ) {
			$value = $field->content;
		}
		if( $field->type == "checkbox") {
			if(  $value == "" ){
				$datas = "";
				$datas_full = array();
				foreach( $field->inputs as $vl ) {
					if( $vl["id"] == $tag ){
						$datas = $vl["label"];
						break;
					}
					$datas_full[] = $vl["label"];
				}
				if( $datas == ""){
					$datas = implode(", ", $datas_full);
				}
				$value = '<input type="checkbox"> <span> '.$datas.'</span>';
			}else{
				$value = '<input type="checkbox" checked="checked"> <span> '.$value.'</span>';
			}
		}
		return $value;
	}
	function pdf_download_shortcode($atts){
		$atts = shortcode_atts( array(
			'entry_id' => '',
		), $atts, 'pdf_download_gf' );
		
		if($atts["entry_id"] == ""){
			$upload_dir = wp_upload_dir();
			$path_main = $upload_dir['baseurl'] . '/pdfs/';
			$name = get_option("download_link_pdf_gf");
			return $path_main.$name.".pdf";
		}else{
			$link_download = gform_get_meta($entry_id,"pdf_links");
			$url_download ="";
			if( $link_download != ""){
				$upload_dir = wp_upload_dir();
				$path_main = $upload_dir['baseurl'] . '/pdfs/';
				$link_download_data = json_decode($link_download,true);	
				foreach( $link_download_data as $name ){
					$url_download ='<a download="download" href="'.$this->cover_link_dowwnload($path_main.$name).'">Download PDF ';
				} 
			}
			return $url_download;
		}
		
	}
	function cover_link_dowwnload($link){
		$links = explode("/pdfs/",$link);
		if(isset($links[2])){
			$upload_dir = wp_upload_dir();
		    $path_main = $upload_dir['baseurl'] . '/pdfs/';
			return $path_main.$links[2];
		}
		return $link;
	}
	function pdf_download_entries($entry_id ){
		$link_download = gform_get_meta($entry_id,"pdf_links");
		if( $link_download != ""){
			$upload_dir = wp_upload_dir();
			$path_main = $upload_dir['baseurl'] . '/pdfs/';
			$link_download_data = json_decode($link_download,true);	
			foreach( $link_download_data as $name ){
				?>
				<td><a download="download"
						href="<?php echo esc_url($this->cover_link_dowwnload($path_main.$name)) ?>"><?php esc_html_e("Download PDF","pdf-for-gravityforms") ?></a>
				</td>
				<?php
			} 
		}
	}
	function pdf_meta_box( $meta_boxes, $entry, $form ) {
		$meta = [
			'pdf-download-preview' => [
				'title'         => esc_html__( 'PDFs', 'pdf-for-gravityforms' ),
				'callback'      => array($this,"show_link"),
				'context'       => 'side',
				'callback_args' => [
					'form'  => $form,
					'entry' => $entry,
				],
			],
		];
		/* Ensure the PDF meta box is inserted right after the Entry box */
		return array_merge(
			array_slice( $meta_boxes, 0, 1 ),
			$meta,
			array_slice( $meta_boxes, 1 )
		);
	}
	function show_link($args){
		$form  = $args['form'];
    	$entry = $args['entry'];
    	$entry_id = $args['entry']["id"];
    	$link_download = gform_get_meta($entry_id,"pdf_links");
    	if( $link_download != ""){
    		$upload_dir = wp_upload_dir();
		    $path_main = $upload_dir['baseurl'] . '/pdfs/';
			$link_download_data = json_decode($link_download,true);	
			?>
			<h4><?php esc_html_e("Download PDF","pdf-for-gravityforms") ?></h4>
			<?php
			foreach( $link_download_data as $name ){
				?>
				<p>
					<a download
						href="<?php echo wp_nonce_url($this->cover_link_dowwnload($path_main.$name)) ?>"><?php esc_html_e("Download","pdf-for-gravityforms") ?></a>
					|
					<a target="_blank"
						href="<?php echo wp_nonce_url($this->cover_link_dowwnload($path_main.$name)) ?>"><?php esc_html_e("Preview","pdf-for-gravityforms") ?></a>
				</p>
			<?php
			} 
		}else{
			printf("<h4>%s</h4>",esc_html__("No Link download","pdf-for-gravityforms"));
		}
		$pdf_settings = $this->get_pdf_settings_by_form_id($args['entry']["form_id"]);
		if(count($pdf_settings) > 0 ){
		?>
			<a class="button gfpdf-re-generate" data-id="<?php echo esc_attr( $entry_id ) ?>"
				data-form_id="<?php echo esc_attr( $args['entry']["form_id"]) ?>"
				href="#"><?php esc_attr_e( "Re-generate PDF", "pdf-for-gravityforms" ) ?></a>
		<?php
		}
	}
	function my_action_row($form_id, $field_id, $value, $entry ){
		printf('| <a href="%s" target="_blank">%s</a>',"#","View PDF");
		printf('| <a href="%s" target="_blank">%s</a>',"#","Download PDF");
	}
	function add_head_settings($post){
		global $wpdb;
		$post_id= $post->ID;
		$table = $wpdb->prefix."gf_form";
		$data = get_post_meta( $post_id,'_yeepdf_gravity_forms',true);
		?>
		<div class="yeepdf-testting-order">
			<select name="yeepdf_gravity_forms" class="builder_pdf_woo_testing">
				<option value="-1">--- <?php esc_html_e("Gravity Forms","pdf-for-gravityforms"); ?> ---</option>
				<?php
							$forms = $wpdb->get_results("SELECT id, title FROM $table");
							if( count($forms) > 0){
								foreach ( $forms as $form ) {
									$form_id = $form->id;
									$form_title = $form->title;
									?>
				<option <?php selected($data,$form_id) ?> value="<?php echo esc_attr($form_id) ?>">
					<?php echo esc_html($form_title) ?></option>
				<?php
								}
							}else{
								printf( "<option value='0'>%s</option>",esc_html__("No Form",'pdf-for-gravityforms'));
							}
						?>
			</select>
		</div>
		<?php
    }
    function save_metabox($post_id, $post){
        if( isset($_POST['yeepdf_gravity_forms'])) {
            $id = sanitize_text_field($_POST['yeepdf_gravity_forms']);
            update_post_meta($post_id,'_yeepdf_gravity_forms',$id);
        }
    }
	function add_shortcode($shortcode) {
		$check = false;
		if(isset($_GET["page"]) && $_GET["page"] == "gf_edit_forms" && isset($_GET["subview"]) && $_GET["subview"] == "pdf_creator_form_settings"){
			$form_id = sanitize_text_field($_GET["id"]);
			$check = true;
		}else{
			if( isset($_GET["post"]) ){
				$post_id = sanitize_text_field($_GET["post"]);
				$form_id = get_post_meta( $post_id,'_yeepdf_gravity_forms',true);
				$check = true;
			}
		}
		$inner_shortcode = array(
					"{form_title}" => "Form Title",
					"{form_id}"=>"Form ID",
					"{entry_id}"=>"Entry ID",
					"{date_mdy}"=>"Date (mm/dd/yyyy)",
					"{date_dmy}"=>"Date (dd/mm/yyyy)",
					"{entry_url}"=>"Entry URL",
					"{all_fields}"=>"All Submitted Fields",
					"{all_fields_display_empty}"=>"All Submitted Fields Display Empty",
					"{pricing_fields}"=>"All Pricing Fields",
				);
		if( $check ){
				$datas_field = $this->get_shortcode_form_id($form_id);
				$shortcodes = array_merge($inner_shortcode,$datas_field);
				$shortcode["Gravity Forms"] = $shortcodes;
		}
		return $shortcode;
	}
	function get_shortcode_form_id($form_id){
		$shortcode= array();
		$fields = array();
		if($form_id && $form_id>0){
			$form = RGFormsModel::get_form_meta($form_id);
			if(is_array($form["fields"])){
	            foreach($form["fields"] as $field){
					if( $field["type"] == "checkbox"){
						if(isset($field["inputs"]) && is_array($field["inputs"])){
							$lable_main = GFCommon::get_label($field);
							foreach($field["inputs"] as $input){
								$lable = GFCommon::get_label($field, $input["id"]);
								$value = '{'.$lable_main.' ('.$lable.'):'.$input["id"].'}';
								$shortcode[$value] = $lable_main;
							}
						}
					}else{
						if(isset($field["inputs"]) && is_array($field["inputs"])){
							foreach($field["inputs"] as $input){
								$lable = GFCommon::get_label($field, $input["id"]);
								$value = '{'.$lable.':'.$input["id"].'}';
								$shortcode[$value] = $lable;
							}
						}
						else if(!rgar($field, 'displayOnly')){
								$fields[] =  array($field["id"], GFCommon::get_label($field));
								$lable = GFCommon::get_label($field);
								$value = '{'.$lable.':'.$field["id"].'}';
								$shortcode[$value] = $lable;
						}
					}
	            }
	        }
		}
		return $shortcode;
	}
}
new Yeepdf_Creator_GravityFroms_Backend;