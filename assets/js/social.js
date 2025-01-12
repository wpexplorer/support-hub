ucm = typeof ucm == 'undefined' ? {} : ucm;
ucm.social = {

    modal_url: '',
    init: function(){
        var t = this;
        var menu_count = jQuery('#shub_menu_outbox_count');
        if(menu_count.get(0) && !menu_count.data('count')){
            //menu_count.parents('li').first().hide();
        }
        jQuery('.support_hub_date_field').datepicker({ dateFormat: 'yy-mm-dd' });
        jQuery('.support_hub_time_field').timepicker();
        jQuery('body').delegate('.shub_modal','click',function() {
            ucm.social.open_modal(jQuery(this).attr('href'), jQuery(this).data('modaltitle'), jQuery(this).data());
            return false;
        }).delegate('.shub_request_extra', 'click', function(){
            var $f = jQuery(this).parents('.message_edit_form').first();
            // find out how far away this button is from the parent form
            // move our 'request extra' popover this far down as well so it kinda lines up nicely for long messages.
            if($f.data('requesting_extra')){
                $f.data('requesting_extra',false);
                $f.find('.message_content').css('opacity',1);
                $f.find('.message_request_extra').hide();
            }else{
                $f.data('requesting_extra',true);
                $f.find('.message_content').css('opacity',0.4);
                var pos = jQuery(this).position();
                var $e = $f.find('.message_request_extra');
                $e.show().css('top',Math.max(20, pos.top - jQuery($e).height() - (jQuery($e).height()/2)));

            }
            return false;
        }).delegate('.shub_request_extra_generate', 'click', function(){
            var $f = jQuery(this).parents('.message_edit_form').first();
            $f.find('.extra_details_message').text('');
            // send a message with these extra details.
            var postdata = jQuery(this).data();
            var ids = [];
            $f.find('.request_extra:checked').each(function(){
                ids.push(jQuery(this).data('extra-id'));
            });
            postdata.extra_ids = ids;
            postdata.action = 'support_hub_request_extra_details';
            postdata.wp_nonce = support_hub.wp_nonce;
            jQuery.ajax({
                url: ajaxurl,
                method: 'POST',
                data: postdata,
                dataType: 'json',
                success: function(r){
                    if(r && typeof r.redirect != 'undefined'){
                        window.location = r.redirect;
                    }else if(r && typeof r.message != 'undefined'){
                        // got a successful message response, paste that into the next available 'reply' box on the window.
                        $f.find('.shub_message_reply textarea').val(r.message);
                        setTimeout(function(){$f.find('.shub_message_reply textarea').keyup();},100);
                        $f.find('.shub_request_extra').first().click(); // swap back to message screen.
                    }else{
                        $f.find('.extra_details_message').text("Unknown error, please try again: "+r);
                    }
                }
            });
            return false;
        }).delegate('.shub_message_reply textarea','keyup',function(){
            var a = this;
            if (!jQuery(a).prop('scrollTop')) {
                do {
                    var b = jQuery(a).prop('scrollHeight');
                    var h = jQuery(a).height();
                    jQuery(a).height(h - 5);
                }
                while (b && (b != jQuery(a).prop('scrollHeight')));
            }
            jQuery(a).height(jQuery(a).prop('scrollHeight') + 10);
        }).delegate('.shub_send_message_reply_button','click',function(){
            // send a message!
            var pt = jQuery(this).parents('.shub_message_reply_box').first();
            var txt = pt.find('textarea');
            var message = txt.val();
            if(message.length > 0){
                //txt[0].disabled = true;
                // show a loading message in place of the box..
                var post_data = {
                    action: 'support_hub_send-message-reply',
                    wp_nonce: support_hub.wp_nonce,
                    message: message,
                    form_auth_key: ucm.form_auth_key
                };
                var button_post = jQuery(this).data('post');
                for(var i in button_post){
                    if(button_post.hasOwnProperty(i)){
                        post_data[i] = button_post[i];
                    }
                }
                // add any additioal reply options to this.
                pt.find('[data-reply="yes"]').each(function(){
                    if(jQuery(this).attr('type') == 'checkbox'){
                        post_data[jQuery(this).attr('name')] = this.checked ? jQuery(this).val() : false;
                    }else{
                        post_data[jQuery(this).attr('name')] = jQuery(this).val();
                    }
                });
                jQuery.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: post_data,
                    dataType: 'json',
                    success: function(r){
                        if(r && typeof r.redirect != 'undefined') {
                            window.location = r.redirect;
                        }else if(r && typeof r.error != 'undefined' && r.error){
                            pt.html("Error: "+ r.message);
                        }else if(r && typeof r.shub_outbox_id != 'undefined' && r.shub_outbox_id){
                            // successfully queued the message reply for sending.
                            // slide up this window and show a "queued" message, similar to archiving a message.
                            // this is for when we are in "inline" view and not when the message has been opened in a popup.

                            // work out if we are in a popup.
                            var test = jQuery(pt).parents('.shub_extension_message').first();
                            if(test.length){
                                // we are inline. slide this up and go for it.

                                (function(){
                                    var element = jQuery(pt).parents('.shub_extension_message').first();
                                    var element_action = element.prev('.shub_extension_message_action').first();
                                    element_action.find('.action_content').html('Sending message...');
                                    t.queue_watch.add(r.shub_outbox_id, element_action, function(){
                                        // successfully sent
                                        element_action.find('.action_content').html('Message Sent!');
                                    }, function(){
                                        // failed to send
                                        element_action.find('.action_content').html('FAILED TO SEND MESSAGE');
                                    });
                                    if(element.is('div')){
                                        element.slideUp();
                                        element_action.slideDown();
                                    }else{
                                        element.hide();
                                        element_action.show();
                                    }
                                })();
                            }else{
                                // we are in popup. have to close modal and find this message on the page to see if we can slide it up.
                                // otherwise it will just go away with
                                var message_row = jQuery('[data-message-id='+post_data['message-id']+'].shub_extension_message');
                                ucm.social.close_modal();
                                if(message_row.length){
                                    (function(){
                                        var element = message_row; //jQuery(pt).parents('.shub_extension_message').first();
                                        var element_action = element.prev('.shub_extension_message_action').first();
                                        element_action.find('.action_content').html('Sending message...');
                                        t.queue_watch.add(r.shub_outbox_id, element_action, function(){
                                            // successfully sent
                                            element_action.find('.action_content').html('Message Sent!');
                                        }, function(){
                                            // failed to send
                                            element_action.find('.action_content').html('FAILED TO SEND MESSAGE');
                                        });
                                        if(element.is('div')){
                                            element.slideUp();
                                            element_action.slideDown();
                                        }else{
                                            element.hide();
                                            element_action.show();
                                        }
                                    })();
                                }else{
                                    // cant find it on the screen, must have opened a related message.
                                    // do the queue watch anyway so we can update the outbox number
                                    t.queue_watch.add(r.shub_outbox_id, false);
                                }
                            }

                        }else if(r && typeof r.message != 'undefined' && r.message.length > 0){
                            pt.html("Info: "+ r.message);
                        }else{
                            pt.html("Unknown error, please check logs or try reconnecting in settings. "+r);
                        }
                    }
                });
                pt.html('Sending...');
                pt.find('.shub_message_actions').hide();
            }
            return false;
        }).delegate('.shub_message_action','click',function(){
            // action a message (archive / unarchive)
            var post_data = {
                action: 'support_hub_' + jQuery(this).data('action'),
                wp_nonce: support_hub.wp_nonce,
                form_auth_key: ucm.form_auth_key
            };
            var button_post = jQuery(this).data('post');
            for(var i in button_post){
                if(button_post.hasOwnProperty(i)){
                    post_data[i] = button_post[i];
                }
            }
            jQuery.ajax({
                url: ajaxurl,
                method: 'POST',
                data: post_data,
                dataType: 'script',
                success: function(r){
                    ucm.social.close_modal();
                }
            });
            return false;
        }).delegate('.swap_layout_type','click',function(){
            jQuery('#layout_type').val(jQuery(this).data('layout-type')).parents('form').get(0).submit();
            return false;
        }).delegate('.shub_view_full_message_sidebar','click',function(){
            var $s = jQuery(this).parents('section').first();
            $s.find('nav').hide();
            $s.find('header,aside').show();
            return false;
        });
    },
    close_modal: function(){
        tb_remove();
    },
    open_modal: function(url, title, data){
        url = ajaxurl + '?action=support_hub_modal&wp_nonce=' + support_hub.wp_nonce;
        for(var i in data){
            if(data.hasOwnProperty(i) && i != 'modaltitle'){
                url += '&' + i + '=' + data[i];
            }
        }
        url += '&width=' + Math.min(800,(jQuery(window).width()-400));
        url += '&height=' + (jQuery(window).height()-200);
        tb_show(title, url );
    },

    queue_watch: {
        queue: [],
        add: function(shub_outbox_id, element, success_callback, fail_callback){
            this.queue.push(
                {
                    shub_outbox_id: shub_outbox_id,
                    element: element,
                    success_callback: success_callback,
                    fail_callback: fail_callback
                }
            );
            this.watch();
        },
        watching: false,
        watch: function(){
            var t = this;
            if(t.watching)return;
            t.watching = true;
            // update the menu UI
            var queue_length = 0;
            for(var x = 0; x < t.queue.length; x++) {
                if (typeof t.queue[x] != 'undefined') {
                    queue_length++;
                }
            }
            jQuery('#shub_menu_outbox_count').text(queue_length); // (t.queue.length); //.parents('li').first().show();
            var post_data = {
                action: 'support_hub_queue-watch',
                wp_nonce: support_hub.wp_nonce,
                form_auth_key: ucm.form_auth_key
            };
            jQuery.ajax({
                url: ajaxurl,
                method: 'POST',
                data: post_data,
                dataType: 'json',
                success: function(r){
                    for(var x = 0; x < t.queue.length; x++){
                        if(typeof t.queue[x] != 'undefined') {
                            // find this shub_outbox_id in the queue response from server.
                            var found = false;
                            if (r && typeof r.outbox_ids != 'undefined') {
                                for (var i in r.outbox_ids) {
                                    if (r.outbox_ids.hasOwnProperty(i) && typeof r.outbox_ids[i] != 'undefined') {
                                        if (r.outbox_ids[i].shub_outbox_id && t.queue[x].shub_outbox_id == r.outbox_ids[i].shub_outbox_id) {
                                            found = true;
                                            // has it errored?
                                            if (r.outbox_ids[i].status == 2) {
                                                if (typeof t.queue[x].fail_callback == 'function') {
                                                    t.queue[x].fail_callback();
                                                    delete(t.queue[x]);
                                                }
                                            }
                                            break;
                                        }
                                    }
                                }
                            }
                            if (!found) {
                                // it's no longer in the queue! yay!
                                // fire off complete callback
                                if (typeof t.queue[x].success_callback == 'function') {
                                    t.queue[x].success_callback();
                                    delete(t.queue[x]);
                                }
                            }
                        }
                    }
                    var has_pending = false;
                    if (r && typeof r.outbox_ids != 'undefined') {
                        for (var i in r.outbox_ids) {
                            if (r.outbox_ids.hasOwnProperty(i) && typeof r.outbox_ids[i] != 'undefined' && typeof r.outbox_ids[i].status != 'undefined' && (parseInt(r.outbox_ids[i].status) == 0  ||  parseInt(r.outbox_ids[i].status) == 1)) {
                                // we have a pending queue to send!
                                has_pending = true;
                            }
                        }
                    }

                    t.watching = false;
                    if(has_pending) {
                        setTimeout(function () {
                            t.watch();
                        }, 2000);
                    }
                },
                error: function(){
                    t.watching = false;
                }
            });

        }
    }

};