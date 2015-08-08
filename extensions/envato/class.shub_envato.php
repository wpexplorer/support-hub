<?php

class shub_envato extends SupportHub_extension {


	public function init(){
		if(isset($_GET[_SHUB_ENVATO_OAUTH_DOING_FLAG]) && strlen($_GET[_SHUB_ENVATO_OAUTH_DOING_FLAG]) > 0){
			// we're doing an oauth callback, grab the code and redirect back to the login url.
			if(!headers_sent() && !session_id()){
				session_start();
			}
			if(!empty($_SESSION['shub_oauth_doing_envato'])){
				$_SESSION['shub_oauth_doing_envato']['code'] = isset($_GET['code']) ? $_GET['code'] : false;
				header("Location: ".$_SESSION['shub_oauth_doing_envato']['url']);
				exit;
			}
			echo "Oauth failed, please go back and try again.";
			exit;
		}
        parent::init();

	}

	public function page_assets($from_master=false){
		if(!$from_master)SupportHub::getInstance()->inbox_assets();

		wp_register_style( 'support-hub-envato-css', plugins_url('extensions/envato/shub_envato.css',_DTBAKER_SUPPORT_HUB_CORE_FILE_), array(), '1.0.0' );
		wp_enqueue_style( 'support-hub-envato-css' );
		wp_register_script( 'support-hub-envato', plugins_url('extensions/envato/shub_envato.js',_DTBAKER_SUPPORT_HUB_CORE_FILE_), array( 'jquery' ), '1.0.0' );
		wp_enqueue_script( 'support-hub-envato' );

	}

	public function settings_page(){
		include( dirname(__FILE__) . '/envato_settings.php');
	}


	public function compose_to(){
		$accounts = $this->get_accounts();
	    if(!count($accounts)){
		    _e('No accounts configured', 'support_hub');
	    }
		foreach ( $accounts as $account ) {
			$envato_account = new shub_envato_account( $account['shub_account_id'] );
			echo '<div class="envato_compose_account_select">' .
				     '<input type="checkbox" name="compose_envato_id[' . $account['shub_account_id'] . '][share]" value="1"> ' .
				     ($envato_account->get_picture() ? '<img src="'.$envato_account->get_picture().'">' : '' ) .
				     '<span>' . htmlspecialchars( $envato_account->get( 'envato_name' ) ) . ' (status update)</span>' .
				     '</div>';
			/*echo '<div class="envato_compose_account_select">' .
				     '<input type="checkbox" name="compose_envato_id[' . $account['shub_account_id'] . '][blog]" value="1"> ' .
				     ($envato_account->get_picture() ? '<img src="'.$envato_account->get_picture().'">' : '' ) .
				     '<span>' . htmlspecialchars( $envato_account->get( 'envato_name' ) ) . ' (blog post)</span>' .
				     '</div>';*/
			$items            = $envato_account->get( 'items' );
			foreach ( $items as $envato_item_id => $item ) {
				echo '<div class="envato_compose_account_select">' .
				     '<input type="checkbox" name="compose_envato_id[' . $account['shub_account_id'] . '][' . $envato_item_id . ']" value="1"> ' .
				     ($envato_account->get_picture() ? '<img src="'.$envato_account->get_picture().'">' : '' ) .
				     '<span>' . htmlspecialchars( $item->get( 'item_name' ) ) . ' (item)</span>' .
				     '</div>';
			}
		}

	}
	public function compose_message($defaults){
		?>
		<textarea name="envato_message" rows="6" cols="50" id="envato_compose_message"><?php echo isset($defaults['envato_message']) ? esc_attr($defaults['envato_message']) : '';?></textarea>
		<?php
	}

	public function compose_type($defaults){
		?>
		<input type="radio" name="envato_post_type" id="envato_post_type_normal" value="normal" checked>
		<label for="envato_post_type_normal">Normal Post</label>
		<table>
		    <tr>
			    <th class="width1">
				    Subject
			    </th>
			    <td class="">
				    <input name="envato_title" id="envato_compose_title" type="text" value="<?php echo isset($defaults['envato_title']) ? esc_attr($defaults['envato_title']) : '';?>">
				    <span class="envato-type-normal envato-type-option"></span>
			    </td>
		    </tr>
		    <tr>
			    <th class="width1">
				    Picture
			    </th>
			    <td class="">
				    <input type="text" name="envato_picture_url" value="<?php echo isset($defaults['envato_picture_url']) ? esc_attr($defaults['envato_picture_url']) : '';?>">
				    <br/><small>Full URL (eg: http://) to the picture to use for this link preview</small>
				    <span class="envato-type-normal envato-type-option"></span>
			    </td>
		    </tr>
	    </table>
		<?php
	}

	public static function format_person($data,$envato_account){
		$return = '';
		if($data && isset($data['username'])){
			$return .= '<a href="http://themeforest.net/user/' . $data['username'].'" target="_blank">';
		}
		if($data && isset($data['username'])){
			$return .= htmlspecialchars($data['username']);
		}
		if($data && isset($data['username'])){
			$return .= '</a>';
		}
		return $return;
	}

	// used in our Wp "outbox" view showing combined messages.
	public function get_message_details($shub_message_id){
		if(!$shub_message_id)return array();
		$messages = $this->load_all_messages(array('shub_message_id'=>$shub_message_id));
		// we want data for our colum outputs in the WP table:
		/*'shub_column_time'    => __( 'Date/Time', 'support_hub' ),
	    'shub_column_account' => __( 'Social Accounts', 'support_hub' ),
		'shub_column_summary'    => __( 'Summary', 'support_hub' ),
		'shub_column_links'    => __( 'Link Clicks', 'support_hub' ),
		'shub_column_stats'    => __( 'Stats', 'support_hub' ),
		'shub_column_action'    => __( 'Action', 'support_hub' ),*/
		$data = array(
			'shub_column_account' => '',
			'shub_column_summary' => '',
			'shub_column_links' => '',
		);
		$link_clicks = 0;
		foreach($messages as $message){
			$envato_message = new shub_message(false, false, $message['shub_message_id']);
			$data['message'] = $envato_message;
			$data['shub_column_account'] .= '<div><img src="'.plugins_url('extensions/envato/logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="envato_icon small"><a href="'.$envato_message->get_link().'" target="_blank">'.htmlspecialchars( $envato_message->get('envato_item') ? $envato_message->get('envato_item')->get( 'item_name' ) : 'Share' ) .'</a></div>';
			$data['shub_column_summary'] .= '<div><img src="'.plugins_url('extensions/envato/logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="envato_icon small"><a href="'.$envato_message->get_link().'" target="_blank">'.htmlspecialchars( $envato_message->get_summary() ) .'</a></div>';
			// how many link clicks does this one have?
			$sql = "SELECT count(*) AS `link_clicks` FROM ";
			$sql .= " `"._support_hub_DB_PREFIX."shub_message` m ";
			$sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_message_link` ml USING (shub_message_id) ";
			$sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_message_link_click` lc USING (shub_message_link_id) ";
			$sql .= " WHERE 1 ";
			$sql .= " AND m.shub_message_id = ".(int)$message['shub_message_id'];
			$sql .= " AND lc.shub_message_link_id IS NOT NULL ";
			$sql .= " AND lc.user_agent NOT LIKE '%Google%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%Yahoo%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%envatoexternalhit%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%Meta%' ";
			$res = shub_qa1($sql);
			$link_clicks = $res && $res['link_clicks'] ? $res['link_clicks'] : 0;
			$data['shub_column_links'] .= '<div><img src="'.plugins_url('extensions/envato/logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="envato_icon small">'. $link_clicks  .'</div>';
		}
		if(count($messages) && $link_clicks > 0){
			//$data['shub_column_links'] = '<div><img src="'.plugins_url('extensions/envato/logo.png', _DTBAKER_SUPPORTHUB_CORE_FILE_).'" class="envato_icon small">'. $link_clicks  .'</div>';
		}
		return $data;

	}



	public function init_js(){
		?>
		    ucm.social.envato.init();
		<?php
	}

	public function handle_process($process, $options = array()){
		switch($process){
			case 'send_shub_message':
				$message_count = 0;
				if(check_admin_referer( 'shub_send-message' ) && isset($options['shub_message_id']) && (int)$options['shub_message_id'] > 0 && isset($_POST['envato_message']) && !empty($_POST['envato_message'])){
					// we have a social message id, ready to send!
					// which envato accounts are we sending too?
					$envato_accounts = isset($_POST['compose_envato_id']) && is_array($_POST['compose_envato_id']) ? $_POST['compose_envato_id'] : array();
					foreach($envato_accounts as $envato_account_id => $send_items){
						$envato_account = new shub_envato_account($envato_account_id);
						if($envato_account->get('shub_account_id') == $envato_account_id){
							/* @var $available_items shub_envato_item[] */
				            $available_items = $envato_account->get('items');
							if($send_items){
							    foreach($send_items as $envato_item_id => $tf){
								    if(!$tf)continue;// shouldnt happen
								    switch($envato_item_id){
									    case 'share':
										    // doing a status update to this envato account
											$envato_message = new shub_message($envato_account, false, false);
										    $envato_message->create_new();
										    $envato_message->update('shub_envato_item_id',0);
							                $envato_message->update('shub_message_id',$options['shub_message_id']);
										    $envato_message->update('shub_account_id',$envato_account->get('shub_account_id'));
										    $envato_message->update('summary',isset($_POST['envato_message']) ? $_POST['envato_message'] : '');
										    $envato_message->update('title',isset($_POST['envato_title']) ? $_POST['envato_title'] : '');
										    $envato_message->update('link',isset($_POST['envato_link']) ? $_POST['envato_link'] : '');
										    if(isset($_POST['track_links']) && $_POST['track_links']){
												$envato_message->parse_links();
											}
										    $envato_message->update('type','share');
										    $envato_message->update('data',json_encode($_POST));
										    $envato_message->update('user_id',get_current_user_id());
										    // do we send this one now? or schedule it later.
										    $envato_message->update('status',_shub_MESSAGE_STATUS_PENDINGSEND);
										    if(isset($options['send_time']) && !empty($options['send_time'])){
											    // schedule for sending at a different time (now or in the past)
											    $envato_message->update('last_active',$options['send_time']);
										    }else{
											    // send it now.
											    $envato_message->update('last_active',0);
										    }
										    if(isset($_FILES['envato_picture']['tmp_name']) && is_uploaded_file($_FILES['envato_picture']['tmp_name'])){
											    $envato_message->add_attachment($_FILES['envato_picture']['tmp_name']);
										    }
											$now = time();
											if(!$envato_message->get('last_active') || $envato_message->get('last_active') <= $now){
												// send now! otherwise we wait for cron job..
												if($envato_message->send_queued(isset($_POST['debug']) && $_POST['debug'])){
										            $message_count ++;
												}
											}else{
										        $message_count ++;
												if(isset($_POST['debug']) && $_POST['debug']){
													echo "message will be sent in cron job after ".shub_print_date($envato_message->get('last_active'),true);
												}
											}
										    break;
									    case 'blog':
											// doing a blog post to this envato account
											// not possible through api

										    break;
									    default:
										    // posting to one of our available items:

										    // see if this is an available item.
										    if(isset($available_items[$envato_item_id])){
											    // push to db! then send.
											    $envato_message = new shub_message($envato_account, $available_items[$envato_item_id], false);
											    $envato_message->create_new();
											    $envato_message->update('shub_envato_item_id',$available_items[$envato_item_id]->get('shub_envato_item_id'));
								                $envato_message->update('shub_message_id',$options['shub_message_id']);
											    $envato_message->update('shub_account_id',$envato_account->get('shub_account_id'));
											    $envato_message->update('summary',isset($_POST['envato_message']) ? $_POST['envato_message'] : '');
											    $envato_message->update('title',isset($_POST['envato_title']) ? $_POST['envato_title'] : '');
											    if(isset($_POST['track_links']) && $_POST['track_links']){
													$envato_message->parse_links();
												}
											    $envato_message->update('type','item_post');
											    $envato_message->update('link',isset($_POST['link']) ? $_POST['link'] : '');
											    $envato_message->update('data',json_encode($_POST));
											    $envato_message->update('user_id',get_current_user_id());
											    // do we send this one now? or schedule it later.
											    $envato_message->update('status',_shub_MESSAGE_STATUS_PENDINGSEND);
											    if(isset($options['send_time']) && !empty($options['send_time'])){
												    // schedule for sending at a different time (now or in the past)
												    $envato_message->update('last_active',$options['send_time']);
											    }else{
												    // send it now.
												    $envato_message->update('last_active',0);
											    }
											    if(isset($_FILES['envato_picture']['tmp_name']) && is_uploaded_file($_FILES['envato_picture']['tmp_name'])){
												    $envato_message->add_attachment($_FILES['envato_picture']['tmp_name']);
											    }
												$now = time();
												if(!$envato_message->get('last_active') || $envato_message->get('last_active') <= $now){
													// send now! otherwise we wait for cron job..
													if($envato_message->send_queued(isset($_POST['debug']) && $_POST['debug'])){
											            $message_count ++;
													}
												}else{
											        $message_count ++;
													if(isset($_POST['debug']) && $_POST['debug']){
														echo "message will be sent in cron job after ".shub_print_date($envato_message->get('last_active'),true);
													}
												}

										    }else{
											    // log error?
										    }
								    }
							    }
						    }
						}
					}
				}
				return $message_count;
				break;
		}
        self::handle_process($process, $options);
	}


	public function get_message($envato_account = false, $envato_item = false, $shub_message_id = false){
		return new shub_message($envato_account, $envato_item, $shub_message_id);
	}

	public function handle_ajax($action, $support_hub_wp){
		switch($action){
			case 'request_extra_details':

				if(isset($_REQUEST['network']) && $_REQUEST['network'] == 'envato'){
					if (!headers_sent())header('Content-type: text/javascript');

					$debug = isset( $_POST['debug'] ) && $_POST['debug'] ? $_POST['debug'] : false;
					$response = array();
					$extra_ids = isset($_REQUEST['extra_ids']) && is_array($_REQUEST['extra_ids']) ? $_REQUEST['extra_ids']  : array();
					$account_id = isset($_REQUEST['networkAccountId']) ? (int)$_REQUEST['networkAccountId'] : (isset($_REQUEST['account-id']) ? (int)$_REQUEST['account-id'] : false);
					$message_id = isset($_REQUEST['networkMessageId']) ? (int)$_REQUEST['networkMessageId'] : (isset($_REQUEST['message-id']) ? (int)$_REQUEST['message-id'] : false);
					if(empty($extra_ids)){
						$response['message'] = 'Please request at least one Extra Detail';
					}else{

						$shub_message = new shub_message( false, false, $message_id );
						if($message_id && $shub_message->get('shub_message_id') == $message_id){
							// build the message up
							$message = SupportHubExtra::build_message(array(
								'network' => 'envato',
								'account_id' => $account_id,
								'message_id' => $message_id,
								'extra_ids' => $extra_ids,
							));
							$response['message'] = $message;
//							if($debug)ob_start();
//							$shub_message->send_reply( $shub_message->get('envato_id'), $message, $debug );
//							if($debug){
//								$response['message'] = ob_get_clean();
//							}else {
//								$response['redirect'] = 'admin.php?page=support_hub_main';
//							}
						}

					}

					echo json_encode($response);
					exit;
				}
				break;
		}
		return false;
	}


	public function run_cron( $debug = false, $cron_timeout = false, $cron_start = false){
		if($debug)echo "Starting envato Cron Job \n";
		$accounts = $this->get_accounts();

        $last_cron_task = get_option('last_support_hub_cron_envato',false);
        $cron_completed = true;
		foreach($accounts as $account){
            if($last_cron_task){
                if($last_cron_task['shub_account_id'] == $account['shub_account_id']) {
                    // we got here last time, continue off from where we left
                    SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO,'envato','Cron resuming operation from account : '.$account['shub_account_id']);
                }else{
                    // keep hunting for the cron job we were up to last time.
                    continue;
                }
            }
			$shub_envato_account = new shub_envato_account( $account['shub_account_id'] );
			$shub_envato_account->run_cron($debug);
			$items = $shub_envato_account->get('items');
			/* @var $items shub_envato_item[] */
			foreach($items as $item){

                if($last_cron_task){
                    if($last_cron_task['shub_envato_item_id'] == $item->get('shub_envato_item_id')) {
                        // we got here last time, continue on the next item.
                        $last_cron_task = false;
                        SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO,'envato','Cron resuming operation from item : '.$item->get('shub_envato_item_id'));
                    }
                    continue;
                }

                // recording where we get up to in the (sometimes very long) cron tasks.
                update_option('last_support_hub_cron_envato',array(
                    'shub_account_id' => $account['shub_account_id'],
                    'shub_envato_item_id' => $item->get('shub_envato_item_id'),
                    'time' => time(),
                ));

				$item->run_cron($debug);

                if($cron_start + $cron_timeout < time()){
                    $cron_completed = false;
                    break;
                }
			}
		}
        // finished everything successfully so we clear the last cache magiggy
        if($cron_completed){
            SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO,'envato','Cron completed successfully');
            update_option('last_support_hub_cron_envato',false);
        }
		if($debug)echo "Finished envato Cron Job \n";
	}

	public function find_other_user_details($user_hints, $current_extension, $message_object){
		$details = array(
			'messages' => array(),
			'user' => array(),
            'user_ids' => array(),
		);
        // todo: find user meta for envato purchase code details.
		if(!empty($user_hints['shub_user_id'])){
            if(!is_array($user_hints['shub_user_id']))$user_hints['shub_user_id'] = array($user_hints['shub_user_id']);
            foreach($user_hints['shub_user_id'] as $shub_user_id) {
                if((int)$shub_user_id > 0) {
                    $details['user_ids'][$shub_user_id] = $shub_user_id;
                    $shub_user = new SupportHubUser_Envato($shub_user_id);
                    $envato_username = $shub_user->get_meta('envato_username');
                    if($envato_username){
                        foreach($envato_username as $envato_username1) {
                            if (!empty($envato_username1)) {
                                // todo - display multiple.
                                $details['user']['username'] = $envato_username1;
                                $details['user']['url'] = 'http://themeforest.net/user/' . $envato_username1;
                                // see if we can find any other matching user accounts for this username
                                $other_users = new SupportHubUser_Envato();
                                $other_users->load_by('user_username',$envato_username1);
                                if($other_users->get('shub_user_id') && !in_array($other_users->get('shub_user_id'),$user_hints['shub_user_id'])){
                                    // pass these back to the calling method so we can get the correct values.
                                    $details['user_ids'][$other_users->get('shub_user_id')] = $other_users->get('shub_user_id');
                                }
                            }
                        }
                    }

                    $envato_codes = $shub_user->get_meta('envato_license_code');
                    if(is_array($envato_codes)){
                        $details['user']['codes'] = $envato_codes;
                    }
                    /*$user_data = $shub_user->get('user_data');
                    if (isset($user_data['envato_codes'])) {
                        // these come in from bbPress (and hopefully other places)
                        // array of purchase code info
                        $details['user']['codes'] = implode(', ', array_keys($user_data['envato_codes']));
                        $details['user']['products'] = array();
                        foreach ($user_data['envato_codes'] as $code => $purchase_data) {
                            print_r($purchase_data);
                            $details['user']['products'][] = $purchase_data['item_name'];
                        }
                        $details['user']['products'] = implode(', ', $details['user']['products']);
                    }*/

                    $comments = shub_get_multiple('shub_message_comment', array(
                        'shub_user_id' => $shub_user_id
                    ), 'shub_message_comment_id');
                    if (is_array($comments)) {
                        foreach ($comments as $comment) {
                            if ($current_extension == 'envato' && $message_object->get('shub_message_id') == $comment['shub_message_id']) continue;
                            if (!isset($details['messages']['envato' . $comment['shub_message_id']])) {
						$other_message = new shub_message();
						$other_message->load($comment['shub_message_id']);
                                $details['messages']['envato' . $comment['shub_message_id']] = array(
                                    'summary' => $comment['message_text'],
                                    'time' => $comment['time'],
                                    'network' => 'envato',
                                    'message_id' => $comment['shub_message_id'],
                                    'network_message_comment_id' => $comment['shub_message_comment_id'],
							'message_status' => $other_message->get('status'),
                                );
                            }
                        }
                    }
                }
            }
		}

		return $details;
	}

	public function extra_process_login($network, $account_id, $message_id, $extra_ids){
		if($network != 'envato')dir('Incorrect network in request_extra_login() - this should not happen');
		$accounts = $this->get_accounts();
		if(!isset($accounts[$account_id])){
			die('Invalid account, please report this error.');
		}
		if(false) {
			// for testing without doing a full login:
			$shub_message = new shub_message( false, false, $message_id );
			ob_start();
			$shub_message->full_message_output( false );
			return array(
				'message' => ob_get_clean(),
			);
		}

		// check if the user is already logged in via oauth.
		if(!empty($_SESSION['shub_oauth_envato']) && is_array($_SESSION['shub_oauth_envato']) && $_SESSION['shub_oauth_envato']['expires'] > time() && $_SESSION['shub_oauth_envato']['account_id'] == $account_id && $_SESSION['shub_oauth_envato']['message_id'] == $message_id){
			// user is logged in
			$shub_message = new shub_message(false, false, $message_id);
			if($shub_message->get('envato_account')->get('shub_account_id') == $account_id && $shub_message->get('shub_message_id') == $message_id){
				if(isset($_GET['done'])){
					// submission of extra data was successful, clear the token so the user has to login again
					$_SESSION['shub_oauth_envato'] = false;
				}
				ob_start();
				$shub_message->full_message_output(false);
				return array(
					'message' => ob_get_clean(),
				);

			}
		}else{
			// user isn't logged in or the token has expired. show the login url again.
			// find the account.
			if(isset($accounts[$account_id])){
				$shub_envato_account = new shub_envato_account($accounts[$account_id]['shub_account_id']);
				// found the account, pull in the API and build the url
				$api = $shub_envato_account->get_api();
				// check if we have a code from a previous redirect:
				if(!empty($_SESSION['shub_oauth_doing_envato']['code'])){
					// grab a token from the api
					$token = $api->get_authentication($_SESSION['shub_oauth_doing_envato']['code']);
					unset($_SESSION['shub_oauth_doing_envato']['code']);
					if(!empty($token) && !empty($token['access_token'])) {
						// good so far, time to check their username matches from the api
						$shub_message = new shub_message(false, false, $message_id);
						if($shub_message->get('envato_account')->get('shub_account_id') == $shub_envato_account->get('shub_account_id')){
							// grab the details from the envato message:
							$envato_comments = $shub_message->get_comments();
							$first_comment = current($envato_comments);
							if(!empty($first_comment)){
								$comment_data = @json_decode($first_comment['data'],true);
								$api_result = $api->api('v1/market/private/user/username.json', array(), false);

                                $account_data = $shub_envato_account->get('envato_data');

								if($comment_data && $api_result && !empty($api_result['username']) && !empty($comment_data['username']) && (($account_data && isset($account_data['user']['username']) && $api_result['username'] == $account_data['user']['username']) || $comment_data['username'] == $api_result['username'])){ // the dtbaker is here for debugging..
									SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_ERROR,'envato','OAuth Login Success - request extra','User '.$api_result['username'] .' has logged in to provide extra details');
									// todo: load this api result into a shub user, pull in their email address as well so we can find any links to other social networks.
									$api_result_email = $api->api('v1/market/private/user/email.json', array(), false);
									$comment_user = new SupportHubUser_Envato();
									if($api_result_email && !empty($api_result_email['email'])){
										$email = trim(strtolower($api_result_email['email']));
									    $comment_user->load_by( 'user_email', $email);
									    if(!$comment_user->get('shub_user_id')) {
										    // no existing match by email, find a match by username
										    $comment_user->load_by( 'user_username', $api_result['username']);
											if(!$comment_user->get('shub_user_id') || ($comment_user->get('user_email') && $comment_user->get('user_email') != $email)) {
												// no existing match by email or username, pump a new entry in
											    $comment_user->create_new();
										    }
									    }
										$comment_user->update( 'user_email', $email );
										$comment_user->update( 'user_username', $api_result['username'] );
									}else{
										// no email, only username
										$comment_user->load_by( 'user_username', $api_result['username']);
										if(!$comment_user->get('shub_user_id')) {
										    $comment_user->create_new();
										    $comment_user->update( 'user_username', $api_result['username'] );
									    }
									}

									$_SESSION['shub_oauth_envato']            = $token;
									$_SESSION['shub_oauth_envato']['account_id']            = $account_id;
									$_SESSION['shub_oauth_envato']['message_id']            = $message_id;
									$_SESSION['shub_oauth_envato']['expires'] = time() + $token['expires_in'];
									$_SESSION['shub_oauth_envato']['shub_user_id'] = $comment_user->get('shub_user_id');
									ob_start();
									$shub_message->full_message_output(false);
									return array(
										'message' => ob_get_clean(),
									);

								}else{
									SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_ERROR,'envato','OAuth Login Fail - Username mismatch','User '.var_export($api_result,true).' tried to login and gain access to ticket message ' .$message_id.': '.var_export($comment_data,true));
									echo "Sorry, unable to verify identity. Please submit a new support message if you require assistance. <br><br> ";
									$envato_item_data = $shub_message->get('envato_item')->get('envato_data');
									if($envato_item_data && $envato_item_data['url']) {
										echo '<a href="' . $envato_item_data['url'].'/comments' . (!empty($comment_data['id']) ? '/'.$comment_data['id'] : '') .'">Please click here to return to the Item Comment</a>';
									}
									return false;
								}
							}

						}

					}else{
						echo 'Failed to get access token, please try again and report this error.';
						print_r($token);
					}

				}else {
					$login_url                           = $api->get_authorization_url();
					$_SESSION['shub_oauth_doing_envato'] = array(
						'url' => str_replace('&done','',$_SERVER['REQUEST_URI']),
					);
					?>
					<a href="<?php echo esc_attr( $login_url );?>">Login to Envato</a>
				<?php
				}
			}
		}
		return false;
	}

	public function extra_validate_data($status, $extra, $value, $network, $account_id, $message_id){
		if(!is_string($value))return $status;
		if(!empty($status['data'])){
			$value = $status['data'];
		}
		$possible_purchase_code = strtolower(preg_replace('#([a-z0-9]{8})-?([a-z0-9]{4})-?([a-z0-9]{4})-?([a-z0-9]{4})-?([a-z0-9]{12})#','$1-$2-$3-$4-$5',$value));
        if(!empty($value) && ($extra->get('extra_name') == 'Purchase Code' || strlen($possible_purchase_code)==36)) { // should be 36
	        // great! we have a purchase code.
	        // see if it validates, if it does we return a success along with extra data that will be saved and eventually displayed
	        $shub_message = new shub_message( false, false, $message_id );
	        if(strlen($possible_purchase_code)==36) {
		        $api    = $shub_message->get( 'envato_account' )->get_api();
		        $result = $api->api( 'v1/market/private/user/verify-purchase:' . $possible_purchase_code . '.json' );
	        }else{
		        $result = false;
	        }
	        if($result && !empty($result['verify-purchase'])){
		        // valid purchase code.
		        $status['success'] = true;
		        $status['data'] = $possible_purchase_code;
		        $result['verify-purchase']['time'] = time();
		        $result['verify-purchase']['valid_purchase_code'] = true;
		        $status['extra_data'] = $result['verify-purchase'];
	        }else{
		        $status['success'] = false;
		        $status['message'] = 'Invalid purchase code, please try again.';
	        }

        }
		return $status;

	}
	public function extra_save_data($extra, $value, $network, $account_id, $message_id){
		$shub_message = new shub_message( false, false, $message_id );
		$shub_user_id = !empty($_SESSION['shub_oauth_envato']['shub_user_id']) ? $_SESSION['shub_oauth_envato']['shub_user_id'] : $shub_message->get('shub_user_id');
		if(is_array($value) && !empty($value['extra_data']['valid_purchase_code'])){
			// we're saving a previously validated (Above) purchase code.
			// create a shub user for this purchase and return success along with the purchase data to show
			$comment_user = new SupportHubUser_Envato();
		    $res = false;
		    if(!empty($value['extra_data']['buyer'])){
			    $res = $comment_user->load_by( 'user_username', $value['extra_data']['buyer']);
		    }
		    if(!$res) {
			    $comment_user->create_new();
			    $comment_user->update( 'user_username', $value['extra_data']['buyer'] );
		    }
		    $user_data = $comment_user->get('user_data');
			if(!is_array($user_data))$user_data=array();
		    if(!isset($user_data['envato_codes']))$user_data['envato_codes']=array();
			$user_data_codes = array();
			$user_data_codes[$value['data']] = $value['extra_data'];
		    $user_data['envato_codes'] = array_merge($user_data['envato_codes'], $user_data_codes);
		    $comment_user->update_user_data($user_data);
			$shub_user_id = $comment_user->get('shub_user_id');
		}

		$extra->save_and_link(
			array(
				'extra_value' => is_array($value) && !empty($value['data']) ? $value['data'] : $value,
				'extra_data' => is_array($value) && !empty($value['extra_data']) ? $value['extra_data'] : false,
			),
			$network,
			$account_id,
			$message_id,
			$shub_user_id
		);

	}
	public function extra_send_message($message, $network, $account_id, $message_id){
		// save this message in the database as a new comment.
		// set the 'private' flag so we know this comment has been added externally to the API scrape.
		$shub_message = new shub_message( false, false, $message_id );
		$existing_comments = $shub_message->get_comments();
		shub_update_insert('shub_message_comment_id',false,'shub_message_comment',array(
		    'shub_message_id' => $shub_message->get('shub_message_id'),
		    'private' => 1,
		    'message_text' => $message,
			'time' => time(),
		    'shub_user_id' => !empty($_SESSION['shub_oauth_envato']['shub_user_id']) ? $_SESSION['shub_oauth_envato']['shub_user_id'] : $shub_message->get('shub_user_id'),
	    ));
		// mark the main message as unread so it appears at the top.
		$shub_message->update('status',_shub_MESSAGE_STATUS_UNANSWERED);
		$shub_message->update('last_active',time());
		// todo: update the 'summary' to reflect this latest message?
		$shub_message->update('summary',$message);

		// todo: post a "Thanks for providing information, we will reply soon" message on Envato comment page

	}

	public function get_install_sql() {

		global $wpdb;

		$sql = <<< EOT

CREATE TABLE {$wpdb->prefix}shub_envato_item (
  shub_envato_item_id int(11) NOT NULL AUTO_INCREMENT,
  shub_account_id int(11) NOT NULL,
  shub_product_id int(11) NOT NULL DEFAULT '0',
  item_name varchar(50) NOT NULL,
  last_message int(11) NOT NULL DEFAULT '0',
  last_checked int(11) NOT NULL,
  item_id varchar(255) NOT NULL,
  envato_data text NOT NULL,
  PRIMARY KEY  shub_envato_item_id (shub_envato_item_id),
  KEY shub_account_id (shub_account_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


EOT;
		return $sql;
	}

}