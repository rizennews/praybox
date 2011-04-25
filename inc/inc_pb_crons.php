<?php
function gd_pb_crons(){
	wp_schedule_event(time(),'hourly','prayer_gap');
	wp_schedule_event(time(),'daily','daily_emails');
}

function do_gap_check() {
	global $wpdb;
	$gap=get_option('pb_send_notify_hours');
	$gap_email=get_option('pb_send_notify_email');

	if($gap!=0){
		
	$prayedfor_results=$wpdb->get_results("SELECT request_id FROM wp_pb_prayedfor GROUP BY request_id");
	foreach($prayedfor_results as $prayedfor_result){
		$prayedfor_ids[]=$prayedfor_result->request_id;
	}
	$requests_results=$wpdb->get_results("SELECT id FROM wp_pb_requests WHERE active='1'");
	foreach($requests_results as $requests_result){
		$request_ids[]=$requests_result->id;
	}

	$unprayed_ids=array_diff($request_ids,$prayedfor_ids);
	$gap_time=strtotime("- ".$gap." hours");
	
	foreach($unprayed_ids as $upid){
		$unprayed_details=$wpdb->get_row("SELECT title,submitted FROM wp_pb_requests WHERE id='$upid'");
		$title=stripslashes($unprayed_details->title);
		$submitted=$unprayed_details->submitted;
		if($submitted<$gap_time){
			$unprayed_gap_items.="- ".$title."\n";
		}
	}
	
		if($unprayed_gap_items!=""){
		   	$email_message.="This is an automated message from your PrayBox WordPress plugin. Prayer requests have been submitted with the following titles and have not been prayed for in the time you have designated:\n\n";
		   	$email_message.=$unprayed_gap_items;
		   	$email_message.="\nYou will continue to receive these alerts every hour until these items have been prayed for or are removed. If you would like to stop receiving these messages, set your Initial Prayer Gap setting to 0.";
		   	
		   	wp_mail($gap_email,'Prayer Request Plugin Gap Alert',$email_message);
		}	
	}	
}

function send_daily_emails() {
	// do something every day
	global $wpdb;
		
	$onedayago=strtotime("-1 day");
	
	$daily_prayers=$wpdb->get_results("SELECT request_id FROM wp_pb_prayedfor WHERE date>'$onedayago' GROUP BY request_id");
	
	foreach($daily_prayers as $prayer){
		$request_id=$prayer->request_id;
		
		$num_prayers=$wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM wp_pb_prayedfor WHERE prayedfor_date>'$onedayago' AND request_id='$request_id';"));
	
		$prayer_request=$wpdb->get_row("SELECT first_name,last_name,email,title,notify,authcode FROM wp_pb_requests WHERE id='$request_id'");
		
		$first_name=$prayer_request->first_name;
		$last_name=$prayer_request->last_name;
		$email=$prayer_request->email;
		$title=$prayer_request->title;
		$notify=$prayer_request->notify;
		$authcode=$prayer_request->authcode;

		if($notify==1){
	
			$management_url=site_url()."/?page_id=".get_option('pb_management_page')."&pbid=$authcode";
			
		   	$email_from=get_option('pb_reply_to_email');
		   	$email_subject=get_option('pb_email_subject');
		   	$email_message=get_option('pb_email_prefix');
		   	$email_message.="\n\nYour prayer request titled \"$title\" has been lifted up $num_prayers times in the past 24 hours. If you would like to edit your prayer request or submit a praise report for an answered prayer, click here: $management_url\n\nYou will receive an email at the end of each day that your prayer request is lifted up to the Lord letting you know how many times you were prayed for that day.\n\n";
		   	$email_message.=get_option('pb_email_suffix');
			$headers = 'Reply-To: '.$email_from . \\"\r\n\\";
		   	
		   	wp_mail($email,$email_subject,$email_message,$headers);
	
		sleep(1);	
		}		
	}
}