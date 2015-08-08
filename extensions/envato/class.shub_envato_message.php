<?php

class shub_message extends SupportHub_message{

    protected $network = 'envato';

	public function __construct($envato_account = false, $envato_item = false, $shub_message_id = false){
		$this->envato_account = $envato_account;
		$this->envato_item = $envato_item;
		$this->load($shub_message_id);
	}

	/* @var $envato_item shub_envato_item */
	private $envato_item= false;
	/* @var $envato_account shub_envato_account */
	private $envato_account = false;
	private $shub_message_id = false; // the current user id in our system.
    private $details = array();

    public $json_fields = array('data','comments');

	private function reset(){
		$this->shub_message_id = false;
		$this->details = array(
			'shub_message_id' => '',
			'shub_envato_item_id' => '',
			'shub_account_id' => '',
			'envato_id' => '',
			'title' => '',
			'summary' => '',
			'last_active' => '',
			'comments' => '',
			'type' => '',
			'link' => '',
			'data' => '',
			'status' => '',
			'user_id' => '',
			'shub_user_id' => 0,
		);
		foreach($this->details as $key=>$val){
			$this->{$key} = '';
		}
	}

	public function create_new(){
		$this->reset();
		$this->shub_message_id = shub_update_insert('shub_message_id',false,'shub_message',array(
            'title' => '',
        ));
		$this->load($this->shub_message_id);
	}

	public function load_by_envato_id($envato_id, $message_data, $type, $debug = false){

		switch($type){
			case 'item_comment':
				$existing = shub_get_single('shub_message', 'envato_id', $envato_id);
				if($existing){
					// load it up.
					$this->load($existing['shub_message_id']);
				}
				if($message_data && isset($message_data['id']) && $message_data['id'] == $envato_id){
					if(!$existing){
						$this->create_new();
					}
					$this->update('shub_account_id',$this->envato_account->get('shub_account_id'));
					$this->update('shub_envato_item_id',$this->envato_item->get('shub_envato_item_id'));
					$comments = $message_data['conversation'];
					$this->update('title',$comments[0]['content']);
					$this->update('summary',$comments[count($comments)-1]['content']);
					$this->update('last_active',strtotime($message_data['last_comment_at']));
					$this->update('type',$type);
					$this->update('data',$message_data);
					$this->update('link',$message_data['url'] . '/' . $message_data['id']);
					$this->update('envato_id', $envato_id);
                    if($this->get('status')!=_shub_MESSAGE_STATUS_HIDDEN) $this->update('status', _shub_MESSAGE_STATUS_UNANSWERED);
					$this->update('comments',$comments);

					// create/update a user entry for this comments.
				    $shub_user_id = 0;
					$first_comment = current($comments);
				    if(!empty($first_comment['username'])) {
					    $comment_user = new SupportHubUser_Envato();
					    $res = $comment_user->load_by( 'user_username', $first_comment['username']);
					    if(!$res){
						    $comment_user -> create_new();
						    if(!$comment_user->get('user_username'))$comment_user -> update('user_username', $first_comment['username']);
						    $comment_user -> update_user_data(array(
							    'image' => $first_comment['profile_image_url'],
							    'envato' => $first_comment,
						    ));
					    }
					    $shub_user_id = $comment_user->get('shub_user_id');
				    }
					$this->update('shub_user_id', $shub_user_id);


					return $existing;
				}
				break;

		}

	}

    public function load($shub_message_id = false){
	    if(!$shub_message_id)$shub_message_id = $this->shub_message_id;
	    $this->reset();
	    $this->shub_message_id = $shub_message_id;
        if($this->shub_message_id){
	        $data = shub_get_single('shub_message','shub_message_id',$this->shub_message_id);
	        foreach($this->details as $key=>$val){
		        $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
                if(in_array($key,$this->json_fields)){
                    $this->details[$key] = @json_decode($this->details[$key],true);
                    if(!is_array($this->details[$key]))$this->details[$key] = array();
                }
	        }
	        if(!is_array($this->details) || !isset($this->details['shub_message_id']) || $this->details['shub_message_id'] != $this->shub_message_id){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
	    if(!$this->envato_account && $this->get('shub_account_id')){
		    $this->envato_account = new shub_envato_account($this->get('shub_account_id'));
	    }
	    if(!$this->envato_item && $this->get('shub_envato_item_id')) {
		    $this->envato_item = new shub_envato_item($this->envato_account, $this->get('shub_envato_item_id'));
	    }
        return $this->shub_message_id;
    }

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}


    public function update($field,$value){
	    // what fields to we allow? or not allow?
	    if(in_array($field,array('shub_message_id')))return;
        if($this->shub_message_id){
            $this->{$field} = $value;
            if(in_array($field,$this->json_fields)){
                $value = json_encode($value);
            }
            shub_update_insert('shub_message_id',$this->shub_message_id,'shub_message',array(
	            $field => $value,
            ));
		    // special processing for certain fields.
		    if($field == 'comments'){
			    // we push all thsee messages into a shub_message_comment database table
			    // this is so we can do quick lookups on message ids so we dont import duplicate items from graph (ie: a reply on a message comes in as a separate item sometimes)
			    $data = is_array($value) ? $value : @json_decode($value,true);
			    if(is_array($data)) {
				    // clear previous message history.
				    $existing_messages = $this->get_comments(); //shub_get_multiple('shub_message_comment',array('shub_message_id'=>$this->shub_message_id),'shub_message_comment_id');
				    //shub_delete_from_db('shub_message_comment','shub_message_id',$this->shub_message_id);
				    $remaining_messages = $this->_update_comments( $data , $existing_messages);
				    // $remaining_messages contains any messages that no longer exist...
				    // todo: remove these? yer prolly. do a quick test on removing a message - i think the only thing is it will show the 'from' name still.
			    }
		    }
        }
    }

	public function parse_links($content = false){
		if(!$this->get('shub_message_id'))return;
		// strip out any links in the tweet and write them to the envato_message_link table.
		$url_clickable = '~
		            ([\\s(<.,;:!?])                                        # 1: Leading whitespace, or punctuation
		            (                                                      # 2: URL
		                    [\\w]{1,20}+://                                # Scheme and hier-part prefix
		                    (?=\S{1,2000}\s)                               # Limit to URLs less than about 2000 characters long
		                    [\\w\\x80-\\xff#%\\~/@\\[\\]*(+=&$-]*+         # Non-punctuation URL character
		                    (?:                                            # Unroll the Loop: Only allow puctuation URL character if followed by a non-punctuation URL character
		                            [\'.,;:!?)]                            # Punctuation URL character
		                            [\\w\\x80-\\xff#%\\~/@\\[\\]*(+=&$-]++ # Non-punctuation URL character
		                    )*
		            )
		            (\)?)                                                  # 3: Trailing closing parenthesis (for parethesis balancing post processing)
		    ~xS'; // The regex is a non-anchored pattern and does not have a single fixed starting character.
		          // Tell PCRE to spend more time optimizing since, when used on a page load, it will probably be used several times.
		if(!$content){
			$content = $this->get('summary');
			$doing_summary = true;
		}
		$summary = ' ' . $content . ' ';
		if(strlen($summary) && preg_match_all($url_clickable,$summary,$matches)){
			foreach($matches[2] as $id => $url){
				$url = trim($url);
				if(strlen($url)) {
					// wack this url into the database and replace it with our rewritten url.
					$shub_message_link_id = shub_update_insert( 'shub_message_link_id', false, 'shub_message_link', array(
						'shub_message_id' => $this->get('shub_message_id'),
						'link' => $url,
					) );
					if($shub_message_link_id) {
						$new_link = trailingslashit( get_site_url() );
						$new_link .= strpos( $new_link, '?' ) === false ? '?' : '&';
						$new_link .= _support_hub_envato_LINK_REWRITE_PREFIX . '=' . $shub_message_link_id;
						// basic hash to stop brute force.
						if(defined('AUTH_KEY')){
							$new_link .= ':'.substr(md5(AUTH_KEY.' envato link '.$shub_message_link_id),1,5);
						}
						$newsummary = trim(preg_replace('#'.preg_quote($url,'#').'#',$new_link,$summary, 1));
						if(strlen($newsummary)){// just incase.
							$summary = $newsummary;
						}
					}
				}
			}
		}
		if(isset($doing_summary) && $doing_summary){
			$this->update('summary',$summary);
		}
		return trim($summary);
	}

	private function _update_comments($data, $existing_messages){
	    if(is_array($data)){
		    $last_message_user_name = false;
		    foreach($data as $message){
			    if($message['id']){
				    // does this id exist in the db already?
				    $exists = shub_get_single('shub_message_comment',array('envato_id','shub_message_id'),array($message['id'],$this->shub_message_id));

				    // create/update a user entry for this comments.
				    $shub_user_id = 0;
				    if(!empty($message['username'])) {
					    $comment_user = new SupportHubUser_Envato();
					    $res = $comment_user->load_by( 'user_username', $message['username']);
					    if(!$res){
						    $comment_user -> create_new();
						    if(!$comment_user->get('user_username'))$comment_user -> update('user_username', $message['username']);
						    $comment_user -> update_user_data(array(
							    'image' => $message['profile_image_url'],
							    'envato' => $message,
						    ));
					    }
					    $shub_user_id = $comment_user->get('shub_user_id');
				    }

				    $shub_message_comment_id = shub_update_insert('shub_message_comment_id',$exists ? $exists['shub_message_comment_id'] : false,'shub_message_comment',array(
					    'shub_message_id' => $this->shub_message_id,
					    'envato_id' => $message['id'],
					    'time' => isset($message['created_at']) ? strtotime($message['created_at']) : 0,
					    'data' => json_encode($message),
					    'message_from' => isset($message['username']) ? json_encode(array("username"=>$message['username'],"profile_image_url"=>$message['profile_image_url'])) : '',
					    'message_to' => '',
					    'message_text' => isset($message['content']) ? $message['content'] : '',
					    'shub_user_id' => $shub_user_id,
				    ));
				    $last_message_user_name = isset($message['username']) ? $message['username'] : false;
				    if(isset($existing_messages[$shub_message_comment_id])){
					    unset($existing_messages[$shub_message_comment_id]);
				    }
				    /*if(isset($message['comments']) && is_array($message['comments'])){
					    $existing_messages = $this->_update_messages($message['comments'], $existing_messages);
				    }*/
			    }
		    }
		    if($last_message_user_name){
			    $account_user_data = $this->envato_account->get('envato_data');
			    if($account_user_data && isset($account_user_data['user']) && $last_message_user_name == $account_user_data['user']['username']){
				    // the last comment on this item was from the account owner.
				    // mark this item as resolves so it doesn;t show up in the inbox.
				    $this->update('status',_shub_MESSAGE_STATUS_ANSWERED);

			    }
		    }
	    }
		return $existing_messages;
	}

	public function delete(){
		if($this->shub_message_id) {
			shub_delete_from_db( 'shub_message', 'shub_message_id', $this->shub_message_id );
		}
	}


	public function mark_as_read(){
		if($this->shub_message_id && get_current_user_id()){
			$sql = "REPLACE INTO `"._support_hub_DB_PREFIX."shub_message_read` SET `shub_message_id` = ".(int)$this->shub_message_id.", `user_id` = ".(int)get_current_user_id().", read_time = ".(int)time();
			shub_query($sql);
		}
	}

	public function get_summary() {
		// who was the last person to contribute to this post? show their details here instead of the 'summary' box maybe?
		$title = $this->get( 'title' );
		return htmlspecialchars( strlen( $title ) > 80 ? substr( $title, 0, 80 ) . '...' : $title );
//		$summary = $this->get( 'summary' );
//	    return htmlspecialchars( strlen( $title ) > 80 ? substr( $title, 0, 80 ) . '...' : $title ) . ($summary!=$title ? '<br/>' .htmlspecialchars( strlen( $summary ) > 80 ? substr( $summary, 0, 80 ) . '...' : $summary ) : '');
	}

	private $can_reply = false;
	private function _output_block($envato_data,$level){
		if($level == 1){
			$comments = $this->get_comments();
			$comment = array_shift($comments);
		}else{
			$comments = array();
			$comment = $envato_data;
		}
//		echo '<pre>';echo $level;print_r($comment);echo '</pre>';
//		echo '<pre>';print_r($envato_data);echo '</pre>';
		$from = @json_decode($comment['message_from'],true);
		if(!$from && !empty($comment['private'])){
			// assume it's from the original message user.
			$from_user = new SupportHubUser_Envato($comment['shub_user_id']);
			$from = array(
				'profile_image_url' => $from_user->get_image(),
				'username' => $from_user->get('user_username'),
			);;
		}
		?>
		<div class="shub_message<?php echo !empty($comment['private']) ? ' shub_message_private':'';?>">
			<div class="shub_message_picture">
				<img src="<?php echo isset($from['profile_image_url']) && $from['profile_image_url'] ? $from['profile_image_url'] : plugins_url('extensions/envato/default-user.jpg',_DTBAKER_SUPPORT_HUB_CORE_FILE_);?>">
			</div>
			<div class="shub_message_header">
				<?php echo shub_envato::format_person($from, $this->envato_account); ?>
				<span><?php $time = isset($envato_data['time']) ? $envato_data['time'] : false;
				echo $time ? ' @ ' . shub_print_date($time,true) : '';

				// todo - better this! don't call on every message, load list in main loop and pass through all results.
				if ( isset( $envato_data['user_id'] ) && $envato_data['user_id'] ) {
					$user_info = get_userdata($envato_data['user_id']);
					echo ' (sent by ' . htmlspecialchars($user_info->display_name) . ')';
				}
				?>
				</span>
			</div>
			<div class="shub_message_body">
				<div>
					<?php
					echo shub_forum_text($comment['message_text']);?>
				</div>
			</div>
			<div class="shub_message_actions">
			</div>
		</div>
		<div class="shub_message_replies">
		<?php
		//if(strpos($envato_data['message'],'picture')){
			//echo '<pre>'; print_r($envato_data); echo '</pre>';
		//}
		if(count($comments)){
			// recursively print out our messages!
			//$messages = array_reverse($messages);
			foreach($comments as $comment){
				$this->_output_block($comment,$level+1);
			}
		}
		if($level <= 1) {
			if ( $this->can_reply && isset( $envato_data['id'] ) && $envato_data['id'] ) {
				$this->reply_box( $envato_data['id'], $level );
			}
		}
		?>
		</div>
		<?php
	}

	public function full_message_output($can_reply = false){
		$this->can_reply = $can_reply;
		// used in shub_envato_list.php to display the full message and its messages
		switch($this->get('type')){
			default:
				$envato_data = @json_decode($this->get('data'),true);
				$envato_data['message'] = $this->get('title');
				$envato_data['user_id'] = $this->get('user_id');
//				$envato_data['comments'] = array_reverse($this->get_comments());
				//echo '<pre>'; print_r($envato_data['comments']); echo '</pre>';
				$this->_output_block($envato_data,1);

				break;
		}
	}

	public function reply_box($envato_id,$level=1){
		if($this->envato_account && $this->shub_message_id) {
			$user_data = $this->envato_account->get('envato_data');

			?>
			<div class="shub_message shub_message_reply_box shub_message_reply_box_level<?php echo $level;?>">
				<div class="shub_message_picture">
					<img src="<?php echo isset($user_data['user'],$user_data['user']['image']) ? $user_data['user']['image'] : '#';?>">
				</div>
				<div class="shub_message_header">
					<?php echo isset($user_data['user']) ? shub_envato::format_person( $user_data['user'], $this->envato_account ) : 'Error'; ?>
				</div>
				<div class="shub_message_reply envato_message_reply">
					<textarea placeholder="Write a reply..."></textarea>
					<button data-envato-id="<?php echo htmlspecialchars($envato_id);?>" data-post="<?php echo esc_attr(json_encode(array(
						'id' => (int)$this->shub_message_id,
						'network' => 'envato',
						'envato_id' => htmlspecialchars($envato_id),
					)));?>"><?php _e('Send');?></button>
				</div>
				<div class="shub_message_actions">
					(debug) <input type="checkbox" name="debug" data-reply="yes" value="1"> <br/>
				</div>
			</div>
		<?php
		}else{
			?>
			<div class="shub_message shub_message_reply_box">
				(incorrect settings, please report this bug)
			</div>
			<?php
		}
	}

	public function get_link() {
        $envato_item = $this->get('envato_item');
        if($envato_item){
            $shub_product_id = $envato_item->get('shub_product_id');
            $envato_item_data = $envato_item->get('envato_data');
            if(!is_array($envato_item_data))$envato_item_data = array();
            if(!empty($envato_item_data['url'])) {
                return $envato_item_data['url'] .'/comments/'. $this->get('envato_id');
            }
        }
        return '#';
	}


	public function send_queued($debug = false){
		if($this->envato_account && $this->shub_message_id) {
			// send this message out to envato.
			// this is run when user is composing a new message from the UI,
			if ( $this->get( 'status' ) == _shub_MESSAGE_STATUS_SENDING )
				return; // dont double up on cron.


			switch($this->get('type')){
				case 'item_post':


					if(!$this->envato_item) {
						echo 'No envato item defined';
						return false;
					}

					$this->update( 'status', _shub_MESSAGE_STATUS_SENDING );
					$api = $this->envato_account->get_api();
					$envato_item_id = $this->envato_item->get('item_id');
					if($debug)echo "Sending a new message to envato item ID: $envato_item_id <br>\n";
					$result = false;
					$post_data = array();
					$post_data['summary'] = $this->get('summary');
					$post_data['title'] = $this->get('title');
					$now = time();
					$send_time = $this->get('last_active');
					$result = $api->api('v1/items/'.$envato_item_id.'/posts',array(),'POST',$post_data,'location');
					if($debug)echo "API Post Result: <br>\n".var_export($result,true)." <br>\n";
					if($result && preg_match('#https://api.envato.com/v1/posts/(.*)$#',$result,$matches)){
						// we have a result from the API! this should be an API call in itself:
						$new_post_id = $matches[1];
						$this->update('envato_id',$new_post_id);
						// reload this message and messages from the graph api.
						$this->load_by_envato_id($this->get('envato_id'),false,$this->get('type'),$debug, true);
					}else{
						echo 'Failed to send message. Error was: '.var_export($result,true);
						// remove from database.
						$this->delete();
						return false;
					}

					// successfully sent, mark is as answered.
					$this->update( 'status', _shub_MESSAGE_STATUS_ANSWERED );
					return true;

				default:
					if($debug)echo "Unknown post type: ".$this->get('type');
			}

		}
		return false;
	}
	public function send_queued_comment_reply($envato_message_comment_id){
        $comments = $this->get_comments();
        if(isset($comments[$envato_message_comment_id]) && !empty($comments[$envato_message_comment_id]['message_text'])){
            $api = $this->envato_account->get_api();
            $envato_item_data = $this->get('envato_item')->get('envato_data');
            if($envato_item_data && $envato_item_data['url']) {
                $api_result = $api->post_comment($envato_item_data['url'] . '/comments', $this->get('envato_id'), $comments[$envato_message_comment_id]['message_text']);
                if ($api_result) {
                    // add a placeholder in the comments table, next time the cron runs it should pick this up and fill in all the details correctly from the API
                    shub_update_insert('shub_message_comment_id', $envato_message_comment_id, 'shub_message_comment', array(
                        'envato_id' => $api_result,
                        'time' => time(),
                    ));
                    return true;
                } else {
                    echo "Failed to send comment, check debug log.";
                    return false;
                }
            }
        }
        return false;
    }
	public function queue_reply($envato_id, $message, $debug = false, $extra_data = array(), $shub_outbox_id = false){
		if($this->envato_account && $this->shub_message_id) {


			if($debug)echo "Type: ".$this->get('type')." <br>\n";
			switch($this->get('type')) {
				case 'item_comment':
					if(!$envato_id)$envato_id = $this->get('envato_id');

					if($debug)echo "Sending a reply to Envato Comment ID: $envato_id <br>\n";

					$result = false;
					// send via api
					$envato_item_data = $this->get('envato_item')->get('envato_data');
					if($envato_item_data && $envato_item_data['url']){

                        $reply_user = $this->get_reply_user();
                        // add a placeholder in the comments table, next time the cron runs it should pick this up and fill in all the details correctly from the API
                        $shub_message_comment_id = shub_update_insert('shub_message_comment_id',false,'shub_message_comment',array(
                            'shub_message_id' => $this->shub_message_id,
                            'shub_user_id' => $reply_user->get('shub_user_id'), // we get the main shub user id for sending messages from this account.
                            'shub_outbox_id' => $shub_outbox_id,
                            'envato_id' => '',
                            'time' => time(),
                            'message_text' => $message,
                            'user_id' => get_current_user_id(),
                        ));
                        $this->update('status',_shub_MESSAGE_STATUS_ANSWERED);
                        if($debug){
                            echo "Successfully added comment with id $shub_message_comment_id <br>\n";
                        }
                        return $shub_message_comment_id;


					}

					break;
			}
		}
        return false;
	}
	public function get_comments($message_data = false) {
		if($message_data){
			$messages = $message_data;
			if(!is_array($messages))$messages=array();
			usort($messages,function($a,$b){
				if(isset($a['id'])){
					return $a['id'] > $b['id'];
				}
				return strtotime($a['created_at']) > strtotime($b['created_at']);
			});
		}else{
			$messages = shub_get_multiple('shub_message_comment',array('shub_message_id'=>$this->shub_message_id),'shub_message_comment_id'); //@json_decode($this->get('comments'),true);
		}
		return $messages;
	}

	public function get_type_pretty() {
		$type = $this->get('type');
		switch($type){
			case 'item_comment':
				return 'Item Comment';
				break;
			default:
				return ucwords($type);
		}
	}

	public function get_from() {
		if($this->shub_message_id){
			$from = array();
			$messages = $this->get_comments(); //shub_get_multiple('shub_message_comment',array('shub_message_id'=>$this->shub_message_id),'shub_message_comment_id');
			foreach($messages as $message){
				if($message['message_from']){
					$data = @json_decode($message['message_from'],true);
					if(isset($data['username'])){
						$from[$data['username']] = array(
							'name' => $data['username'],
							'image' => isset($data['profile_image_url']) ? $data['profile_image_url'] : plugins_url('extensions/envato/default-user.jpg',_DTBAKER_SUPPORT_HUB_CORE_FILE_),
							'link' => 'http://themeforest.net/user/' . $data['username'],
						);
					}
				}
			}
			return $from;
		}
		return array();
	}

    public function message_sidebar_data(){

        // find if there is a product here
        $shub_product_id = $this->get_product_id();
        $product_data = array();
        $envato_item_data = array();
        $envato_item = $this->get('envato_item');
        if(!$shub_product_id && $envato_item){
            $shub_product_id = $envato_item->get('shub_product_id');
            $envato_item_data = $envato_item->get('envato_data');
            if(!is_array($envato_item_data))$envato_item_data = array();
        }
        if($shub_product_id) {
            $shub_product = new SupportHubProduct();
            $shub_product->load( $shub_product_id );
            $product_data = $shub_product->get( 'product_data' );
        }
        ?>
        <img src="<?php echo plugins_url('extensions/envato/logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_);?>" class="shub_message_account_icon">
        <?php
        if($shub_product_id && !empty($product_data['image'])) {
            ?>
            <img src="<?php echo $product_data['image'];?>" class="shub_message_account_icon">
            <?php
        }
        ?>
        <br/>


        <strong><?php _e('Account:');?></strong> <a href="<?php echo $this->get_link(); ?>" target="_blank"><?php echo htmlspecialchars( $this->get('envato_account') ? $this->get('envato_account')->get( 'envato_name' ) : 'N/A' ); ?></a> <br/>

        <strong><?php _e('Time:');?></strong> <?php echo shub_print_date( $this->get('last_active'), true ); ?>  <br/>

        <?php
        if($envato_item_data){
            ?>
            <strong><?php _e('Item:');?></strong>
            <a href="<?php echo isset( $envato_item_data['url'] ) ? $envato_item_data['url'] : $this->get_link(); ?>"
               target="_blank"><?php
                echo htmlspecialchars( $envato_item_data['item'] ); ?></a>
            <br/>
            <?php
        }

        $data = $this->get('data');
        if(!empty($data['buyer_and_author']) && $data['buyer_and_author'] && $data['buyer_and_author'] !== 'false'){
            // hmm - this doesn't seem to be a "purchased" flag.
            /*?>
            <strong>PURCHASED</strong><br/>
            <?php*/
        }
    }

    public function get_user_hints($user_hints = array()){
        $comments         = $this->get_comments();
        $first_comment = current($comments);
        if(isset($first_comment['shub_user_id']) && $first_comment['shub_user_id']){
            $user_hints['shub_user_id'][] = $first_comment['shub_user_id'];
        }
        $message_from = @json_decode($first_comment['message_from'],true);
        if($message_from && isset($message_from['username'])){ //} && $message_from['username'] != $envato_message->get('envato_account')->get( 'envato_name' )){
            // this wont work if user changes their username, oh well.
            $other_users = new SupportHubUser_Envato();
            $other_users->load_by_meta('envato_username',$message_from['username']);
            if($other_users->get('shub_user_id') && !in_array($other_users->get('shub_user_id'),$user_hints['shub_user_id'])){
                // pass these back to the calling method so we can get the correct values.
                $user_hints['shub_user_id'][] = $other_users->get('shub_user_id');
            }
        }
        return $user_hints;
    }
    public function get_user($shub_user_id){
        return new SupportHubUser_Envato($shub_user_id);
    }
    public function get_reply_user(){
        return new SupportHubUser_Envato($this->envato_account->get('shub_user_id'));
    }

	public function link_open(){
		return 'admin.php?page=support_hub_main&network=envato&message_id='.$this->shub_message_id;
	}


}