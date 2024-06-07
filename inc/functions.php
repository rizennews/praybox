<?php
/* PLUGIN FUNCTIONS */
function clean($string){
    return strip_tags(nl2br($string),"<br>");
}

function howManyFlags($req_id){
    global $wpdb;
    $flags = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM {$wpdb->prefix}pb_flags WHERE request_id = %d", $req_id));
    return $flags;
}

function howManyPrayers($req_id){
    global $wpdb;
    $prayers = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM {$wpdb->prefix}pb_prayedfor WHERE request_id = %d", $req_id));
    return $prayers;
}

function isIPBanned($ip){
    global $wpdb;
    $result = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM {$wpdb->prefix}pb_banned_ips WHERE ip_address = %s", $ip));
    return ($result == 0) ? "pass" : "fail";
}

function prePgphOutput($input){
    $reporder = array("\r\n", "\n", "\r");
    $badwords = array("fuck","shit","cunt","penis","bastard");

    $step1 = str_replace($reporder, "||", $input);
    $step2 = str_replace($badwords, "[omitted]", $step1);
    $step3 = stripslashes($step2);
    $output = str_replace("||", "<br />", $step3);

    return $output;
}

function isRequestActive($authcode){
    global $wpdb;
    $wpdb->get_results($wpdb->prepare("SELECT id FROM {$wpdb->prefix}pb_requests WHERE authcode = %s AND active = 1", $authcode));
    return ($wpdb->num_rows == 0) ? "no" : "yes";
}

function rand_chars() {
    $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $s = '';
    $z = strlen($a) - 1;
    for ($i = 0; $i != 12; $s .= $a[rand(0, $z)], $i++);
    return $s;
}

function isDuplicate($fname, $lname, $email, $title, $ipaddy){
    global $wpdb;
    $result = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM {$wpdb->prefix}pb_requests WHERE first_name = %s AND last_name = %s AND email = %s AND title = %s AND ip_address = %s", $fname, $lname, $email, $title, $ipaddy));
    return ($result == 0) ? "pass" : "fail";
}

function getRequestList($status){
    global $wpdb;
    $querycond = "";
    switch($status){
        case "pending":
            $querycond = "WHERE active = 0 AND closed = 0";
            break;
        case "active":
            $querycond = "WHERE active = 1";
            break;
        case "closed":
            $querycond = "WHERE active = 2";
            break;
        case "archived":
            $querycond = "WHERE active = 3";
            break;
    }

    $requests = $wpdb->get_results("SELECT id, first_name, last_name, email, title, body, ip_address, submitted FROM {$wpdb->prefix}pb_requests $querycond ORDER BY submitted DESC");

    $output = "";
    if($requests){
        foreach($requests as $req){
            $req_id = $req->id;
            $first_name = stripslashes($req->first_name);
            $last_name = stripslashes($req->last_name);
            $email = $req->email;
            $title = stripslashes($req->title);
            $body = prePgphOutput($req->body);
            $ip = $req->ip_address;
            $submitted = date("m-d-y", strtotime($req->submitted));
            $num_prayers = howManyPrayers($req_id);

            $output .= "<tr><td>$req_id</td><td>$first_name $last_name<br />$email</td><td><strong>$title</strong><br />$body</td><td>$ip</td><td>$submitted</td><td>$num_prayers</td><td>";

            switch($status){
                case "pending":
                    $output .= "<form method='post'><input type='hidden' name='action' value='approve_request' /><input type='hidden' name='pb_request_id' value='$req_id' /><input type='submit' class='button-secondary' value='".PB_ADMIN_APPROVE."' /></form>";
                    $output .= "<form method='post'><input type='hidden' name='action' value='edit_request' /><input type='hidden' name='pb_request_id' value='$req_id' /><input type='submit' class='button-secondary' value='".PB_ADMIN_EDIT."' /></form>";
                    $output .= "<form method='post'><input type='hidden' name='action' value='remove_request' /><input type='hidden' name='pb_request_id' value='$req_id' /><input type='submit' class='button-secondary' value='".PB_ADMIN_DELETE."' /></form>";
                    $output .= "<form method='post'><input type='hidden' name='action' value='remove_ban' /><input type='hidden' name='pb_ip_address' value='$ip' /><input type='hidden' name='pb_request_id' value='$req_id' /><input type='submit' class='button-secondary' value='".PB_ADMIN_BAN."' /></form>";
                    break;
                case "active":
                    $output .= "<form method='post'><input type='hidden' name='action' value='remove_request' /><input type='hidden' name='pb_request_id' value='$req_id' /><input type='submit' class='button-secondary' value='".PB_ADMIN_REMOVE."' /></form>";
                    $output .= "<form method='post'><input type='hidden' name='action' value='close_request' /><input type='hidden' name='pb_request_id' value='$req_id' /><input type='submit' class='button-secondary' value='".PB_ADMIN_CLOSE."' /></form>";
                    $output .= "<form method='post'><input type='hidden' name='action' value='remove_ban' /><input type='hidden' name='pb_ip_address' value='$ip' /><input type='hidden' name='pb_request_id' value='$req_id' /><input type='submit' class='button-secondary' value='".PB_ADMIN_BAN."' /></form>";
                    break;
                case "closed":
                    $output .= "<form method='post'><input type='hidden' name='action' value='remove_request' /><input type='hidden' name='pb_request_id' value='$req_id' /><input type='submit' class='button-secondary' value='".PB_ADMIN_REMOVE."' /></form>";
                    $output .= "<form method='post'><input type='hidden' name='action' value='reopen_request' /><input type='hidden' name='pb_request_id' value='$req_id' /><input type='submit' class='button-secondary' value='".PB_ADMIN_REOPEN."' /></form>";
                    break;
            }
            $output .= "</td></tr>";
        }
    } else {
        $output = "<tr><td colspan='7'>".PB_ADMIN_CURRENTLY." $status ".PB_ADMIN_PRAYER_REQ.".</td></tr>";
    }
    return $output;
}

function displayRequests($page, $permalink){
    global $wpdb;

    $url_pos = strpos($permalink, "?");
    $varprefix = ($url_pos === false) ? "?" : "&";

    $link = $permalink . $varprefix;

    $flag_thresh = get_option('pb_flag_threshhold');
    $time_condition = (get_option('pb_timeframe_display') == 0) ? "" : "AND submitted > " . strtotime("-" . get_option('pb_timeframe_display') . " days");
    $listingsperpage = get_option('pb_page_display');
    $page_condition = ($listingsperpage != 0) ? "LIMIT " . (($page - 1) * $listingsperpage) . ", " . ($page * $listingsperpage) : "";

    $this_display_qry_from = "FROM {$wpdb->prefix}pb_requests WHERE active = '1' $time_condition ORDER BY submitted DESC $page_condition";
    $total_display_qry_from = "FROM {$wpdb->prefix}pb_requests WHERE active = '1' $time_condition";

    $active_requests = $wpdb->get_results("SELECT id, title, body, submitted $this_display_qry_from");
    $num_requests = count($wpdb->get_results("SELECT id $this_display_qry_from"));
    $total_num_requests = count($wpdb->get_results("SELECT id $total_display_qry_from"));

    $req_list_output = "<div id='praybox_wrapper'>";
    $req_list_output .= "<p class='pbx-text'>" . get_option('pb_request_list_intro') . "</p>";

    if ($listingsperpage != 0) {
        $total_pages = ceil($total_num_requests / $listingsperpage);
        if ($total_pages != 1) {
            $i = 1;
            $req_list_output .= "<div class='pagination'>" . PB_ADMIN_PAGE . ": ";
            while ($i <= $total_pages) {
                $linkclass = ($page == $i) ? " class='active'" : "";
                $req_list_output .= " <a href='$link"."page=$i' $linkclass>$i</a>";
                $i++;
            }
            $req_list_output .= "</div>";
        }
    }

    $req_list_output .= "<table class='pbx-req'>";
    $req_list_output .= "<tr><th>".PB_REQ_TITLE."</th><th>".PB_REQ_NUM_PRAYERS."</th><th>".PB_REQ_SUBMITTED_ON."</th><th>&nbsp;</th>";

    $req_modals = [];

    foreach($active_requests as $a_req){
        $req_id = $a_req->id;
        $title = ($a_req->title != "") ? stripslashes($a_req->title) : "<em>" . PB_REQ_UNTITLED . "</em>";
        $body = stripslashes($a_req->body);
        $submitted = date("F j, Y", strtotime($a_req->submitted));
        $num_prayers = howManyPrayers($req_id);
        $num_flags = howManyFlags($req_id);

        $flag_ratio = ($flag_thresh != 0) ? $num_flags / $flag_thresh : 0;

        if($flag_ratio < 1){
            $req_list_output .= "<tr id='row_$req_id'><td>$title</td><td class='num-prayers'>$num_prayers</td><td>$submitted</td><td>";
            $req_list_output .= "<a href='#' req='$req_id'>" . PB_REQ_DETAILS . "</a>";
            $req_list_output .= "</td></tr>";

            $req_modals[] = "<div id='req_$req_id' class='pbx-modal' rel='$req_id'><h3 class='pbx-title'>$title</h3>"
                . "<div class='pbx-meta'><label>".PB_REQ_SUBMITTED_BY.":</label> $display_name</div>"
                . "<div class='pbx-body'><label>".PB_REQ_REQUEST.":</label> $body</div>"
                . "<div class='pbx-formfield pbx-formfield-footer'>"
                . "<button type='button' class='flag-btn flag-abuse'>".PB_FLAG_ABUSE."</button>"
                . "<button type='button' class='flag-btn flag-prayed'>".PB_FLAG_PRAYED."</button>"
                . "</div>"
                . "</div>";
        }
    }
    $req_list_output .= "</table>";

    if ($listingsperpage != 0) {
        $total_pages = ceil($total_num_requests / $listingsperpage);
        if ($total_pages != 1) {
            $i = 1;
            $req_list_output .= "<div class='pagination'>Page: ";
            while ($i <= $total_pages) {
                $linkclass = ($page == $i) ? " class='active'" : "";
                $req_list_output .= " <a href='$link"."page=$i' $linkclass>$i</a>";
                $i++;
            }
            $req_list_output .= "</div>";
        }
    }

    $req_list_output .= "</div>";

    $req_list_modals = "<div class='pbx-modal-bg'>"
        . implode("\n", $req_modals)
        . "<div id='flag-response' class='pbx-modal'></div>"
        . "<div id='prayed-for' class='pbx-modal'>".PB_THANK_YOU_PRAYER."</div>"
        . "</div>";

    return $req_list_output . $req_list_modals;
}

function getManagementUrl($authcode){
    $management_permalink = get_permalink(get_option('pb_management_page'));

    $pos = strpos($management_permalink, "?");

    $url_char = ($pos === FALSE) ? "?" : "&";

    $management_url = $management_permalink . $url_char . "pbid=" . $authcode;

    return $management_url;
}
?>
