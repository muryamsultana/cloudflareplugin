<?php


/**
 * Made by RashFlash
 * Dec 10th 2015
 */
class cloudflare_api {

    //Timeout for the API requests in seconds
    const TIMEOUT = 50;
    //Interval values for Stats
    const INTERVAL_365_DAYS = 10;
    const INTERVAL_30_DAYS = 20;
    const INTERVAL_7_DAYS = 30;
    const INTERVAL_DAY = 40;
    const INTERVAL_24_HOURS = 100;
    const INTERVAL_12_HOURS = 110;
    const INTERVAL_6_HOURS = 120;

    public function getCloudFlareLog($email, $api, $zone_id, $st_date, $ed_date) {
        $st_date = strtotime($st_date);
        $ed_date = strtotime($ed_date);
        $url = "https://api.cloudflare.com/client/v4/zones/$zone_id/logs/requests?start=$st_date&end=$ed_date"; //$st_date&end=$ed_date
//        var_dump($url);
        $log_date = $st_date . "_" . $ed_date;
        $result = $this->curlGetRequest($url, $email, $api, $log_date,1000);
        return $result;
    }

    public function createPageRuleForZone($email, $api, $zone_id, $page_url, $rule_id = "security_level", $rule_value = "off") {
        // related to ticket => TAIL-2019 https://tailopez.atlassian.net/browse/TAIL-2019
        $fields = array(
            "targets" => array(
                array(
                    "target" => "url",
                    "constraint" => array("operator" => "matches", "value" => $page_url)
                )
            ),
            "actions" => array(
                array(
                    "id" => $rule_id,
                    "value" => $rule_value
                )
            ),
            "status" => 'active'
        );

        $fields = json_encode($fields);
        $fields = $this->makeDataCompatible($fields);

        $result = $this->curlPostRequest("https://api.cloudflare.com/client/v4/zones/$zone_id/pagerules", $email, $api, $fields);
        return $result;
    }

    public function makeDataCompatible($str) {
        $str = str_replace('\/', "/", $str);
        return $str;
    }

    public function getPageRulesList($email, $api, $zone_id, $status = 'active') {
        $result = $this->curlGetRequest("https://api.cloudflare.com/client/v4/zones/$zone_id/pagerules?status=$status", $email, $api);
        return $result;
    }

    public function isPageRuleExist($email, $api, $zone_id, $url, $parameter = "security_level", $param_value = "off") {
        $res = $this->getPageRulesList($email, $api, $zone_id);
        if (isset($res->result)) {
            $cf_data = $res->result;
            foreach ($cf_data as $item) {
                $targets = $item->targets;
                $actions = $item->actions;
                foreach ($targets as $tg) {
                    $constraint = $tg->constraint;
                    if (isset($constraint)) {
                        $pos = strpos($url, $constraint->value);
                        $pos2 = strpos($constraint->value, $url);
                        if ($pos !== FALSE || $pos2 !== FALSE) {
                            $action_matched = $this->getMatchedActions($actions, $parameter, $param_value);
                            if ($action_matched) {
                                return $item;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    public function getMatchedActions($actions, $parameter, $param_value) {
        foreach ($actions as $item) {
            $id = $item->id;

            if ($id === $parameter && $item->value === $param_value) {
                return $item;
            }
        }
        return false;
    }
    
    public function getWafPackageforZone($email, $api, $zone_id){
        $result = $this->curlGetRequest("https://api.cloudflare.com/client/v4/zones/$zone_id/firewall/waf/packages", $email, $api);
        return $result;
    }
    public function getWafPackageInfoforZone($email, $api, $zone_id,$pid){
        $result = $this->curlGetRequest("https://api.cloudflare.com/client/v4/zones/$zone_id/firewall/waf/packages/$pid/groups", $email, $api);
        return $result;
    }
    public function updateWafPAckageforZone($email, $api, $zone_id, $package_id, $rule_id, $mode) {
        $fields = array(
            "mode" => $mode
        );
        $fields = json_encode($fields);

        $result = $this->curlPatchRequest("https://api.cloudflare.com/client/v4/zones/$zone_id/firewall/waf/packages/$package_id/groups/$rule_id", $email, $api, $fields);

        return $result;
    }
    
    public function updateWafZoneRule($email, $api, $zone_id, $package_id, $rule_id, $mode) {
        $fields = array(
            "mode" => $mode
        );
        $fields = json_encode($fields);

        $result = $this->curlPatchRequest("https://api.cloudflare.com/client/v4/zones/$zone_id/firewall/waf/packages/$package_id/rules/$rule_id", $email, $api, $fields);

        return $result;
    }
    
     public function updateZoneSecurityLevel($email, $api, $zone_id, $value) {
        $fields = array(
            "value" => $value
        );
        $fields = json_encode($fields);        
        $result = $this->curlPatchRequest("https://api.cloudflare.com/client/v4/zones/$zone_id/settings/security_level", $email, $api, $fields);

        return $result;
    }

    public function getZoneList($email, $api) {
        $result = $this->curlGetRequest("https://api.cloudflare.com/client/v4/zones?per_page=50", $email, $api);
        return $result;
    }

    public function getWhitelistedIpsList($email, $api_key, $zone_id, $access_rule_mode, $page) {
        $res = $this->getZoneAccessRules($email, $api_key, $zone_id, $access_rule_mode, $page);
        $ip_list = [];
        if (isset($res)) {
            if (isset($res->result)) {
                $data = $res->result;

                foreach ($data as $item) {

                    $target = $item->configuration->target;
                    $ip = $item->configuration->value;

                    if ($target === 'ip') {
                        $created_on = $item->created_on;
                        $modified_on = $item->modified_on;
                        $notes = $item->notes;
                        $id = $item->id;
                        $scope_type = $item->scope->type;
                        $scope_name = $item->scope->name;

                        array_push($ip_list, array(
                            'ip' => $ip,
                            'notes' => $notes,
                            'created_on' => $created_on,
                            'modified_on' => $modified_on,
                            'scope_type' => $scope_type,
                            'scope_name' => $scope_name,
                            'access_id' => $id
                        ));
                    }
                }
            }
        }

        return $ip_list;
    }

    public function getWhitelistedIpsList_Organization($email, $api_key, $org_id, $access_rule_mode, $page) {
        $res = $this->getOrganizationAccessRules($email, $api_key, $org_id, $access_rule_mode, $page);
        $ip_list = [];
        if (isset($res)) {
            if (isset($res->result)) {
                $data = $res->result;

                foreach ($data as $item) {

                    $target = $item->configuration->target;
                    $ip = $item->configuration->value;

                    if ($target === 'ip') {
                        $created_on = $item->created_on;
                        $modified_on = $item->modified_on;
                        $notes = $item->notes;
                        $id = $item->id;
                        $scope_type = $item->scope->type;
                        $scope_name = $item->scope->name;

                        array_push($ip_list, array(
                            'ip' => $ip,
                            'notes' => $notes,
                            'created_on' => $created_on,
                            'modified_on' => $modified_on,
                            'scope_type' => $scope_type,
                            'scope_name' => $scope_name,
                            'access_id' => $id
                        ));
                    }
                }
            }
        }

        return $ip_list;
    }

    public function getAllIpsList_org($email, $api_key, $organization_id, $access_rule_mode) {
        $res = $this->getOrganizationAccessRules($email, $api_key, $organization_id, $access_rule_mode);
        $_list = $this->getWhitelistedIpsList_Organization($email, $api_key, $organization_id, $access_rule_mode, 1);

        if (isset($res->result_info)) {
            $total_pages = $res->result_info->total_pages;
            $curr_page = $res->result_info->page;
            $curr_page++;

            for ($i = $curr_page; $i <= $total_pages; $i++) {                
                $data = $this->getWhitelistedIpsList_Organization($email, $api_key, $organization_id, $access_rule_mode, $i);
                $_list = array_merge($_list, $data);
            }
        }

        return $_list;
    }

    public function getFireWallEvents($email, $api, $zone_id, $next_page_id = null, $per_page = 50) {
        if ($next_page_id) {
            $query = "next_page_id='$next_page_id'&per_page=$per_page";
        } else {
            $query = "per_page=$per_page";
        }
        $result = $this->curlGetRequest("https://api.cloudflare.com/client/v4/zones/$zone_id/firewall/events?$query", $email, $api);
        return $result;
    }
    
    public function getFireWallEvents_org($email, $api, $org_id, $next_page_id = null, $per_page = 50) {
        if ($next_page_id) {
            $query = "next_page_id='$next_page_id'&per_page=$per_page";
        } else {
            $query = "per_page=$per_page";
        }
        $result = $this->curlGetRequest("https://api.cloudflare.com/client/v4/organizations/$org_id/firewall/events?$query", $email, $api);
        return $result;
    }

    public function getUserDetail($email, $api) {
        $result = $this->curlGetRequest("https://api.cloudflare.com/client/v4/user", $email, $api);
        return $result;
    }

    public function getZoneAccessRules($email, $api, $zone_id, $mode, $page = 1, $per_page = 50, $target = "ip") {

        $result = $this->curlGetRequest("https://api.cloudflare.com/client/v4/zones/$zone_id/firewall/access_rules/rules?scope_type=zone&mode=$mode&configuration_target=$target&page=$page&per_page=$per_page", $email, $api);
        return $result;
    }

    public function getOrganizationAccessRules($email, $api, $org_id, $mode, $page = 1, $per_page = 50, $target = "ip") {

        $result = $this->curlGetRequest("https://api.cloudflare.com/client/v4/organizations/$org_id/firewall/access_rules/rules?mode=$mode&configuration_target=$target&page=$page&per_page=$per_page", $email, $api);
        return $result;
    }

    public function deleteZoneAccessRule($email, $api, $zone_id, $id) {
        $json = json_encode(array(
            "cascade" => "none"
        ));

        $result = $this->curlDeleteRequest("https://api.cloudflare.com/client/v4/zones/$zone_id/firewall/access_rules/rules/$id", $email, $api, $json);
        return $result;
    }

    public function deleteOrganizationAccessRule($email, $api, $organization_id, $id) {
        $json = json_encode(array(
            "cascade" => "none"
        ));

        $result = $this->curlDeleteRequest("https://api.cloudflare.com/client/v4/organizations/$organization_id/firewall/access_rules/rules/$id", $email, $api, $json);
        return $result;
    }

    public function whitelistIP($email, $api, $ip, $zone_id, $notes) {

        $fields = array(
            "mode" => "whitelist",
            "configuration" => (array("target" => "ip", "value" => $ip)),
            "notes" => $notes
        );
//        $fields = http_build_query($fields);
        $fields = json_encode($fields);
        //  return json_decode($fields);
        //$result = $this->curlPostRequest("https://api.cloudflare.com/client/v4/user/firewall/access_rules/rules", $email, $api, $fields);            
        $result = $this->curlPostRequest("https://api.cloudflare.com/client/v4/zones/$zone_id/firewall/access_rules/rules", $email, $api, $fields);
        return $result;
    }

    public function whitelistIP_organization($email, $api, $ip, $organization_id, $notes) {

        $fields = array(
            "mode" => "whitelist",
            "configuration" => (array("target" => "ip", "value" => $ip)),
            "notes" => $notes
        );
//        $fields = http_build_query($fields);
        $fields = json_encode($fields);
        //  return json_decode($fields);
        //$result = $this->curlPostRequest("https://api.cloudflare.com/client/v4/user/firewall/access_rules/rules", $email, $api, $fields);            
        $result = $this->curlPostRequest("https://api.cloudflare.com/client/v4/organizations/$organization_id/firewall/access_rules/rules", $email, $api, $fields);
        return $result;
    }
    
     public function getIPWhitlistRule($email, $api, $ip, $organization_id, $notes) {
        $result = $this->curlGetRequest("https://api.cloudflare.com/client/v4/organizations/$organization_id/firewall/access_rules/rules?&mode=whitelist&configuration.target=ip&configuration.value=$ip", $email, $api, $fields);
        return $result;
    }

    public function blockIP($email, $api, $ip, $zone_id, $notes) {

        $fields = array(
            "mode" => "block",
            "configuration" => (array("target" => "ip", "value" => $ip)),
            "notes" => $notes
        );
        $fields = json_encode($fields);
        $result = $this->curlPostRequest("https://api.cloudflare.com/client/v4/zones/$zone_id/firewall/access_rules/rules", $email, $api, $fields);
        return $result;
    }

    public function blockIP_organization($email, $api, $ip, $organization_id, $notes) {

        $fields = array(
            "mode" => "block",
            "configuration" => (array("target" => "ip", "value" => $ip)),
            "notes" => $notes
        );
        $fields = json_encode($fields);
        $result = $this->curlPostRequest("https://api.cloudflare.com/client/v4/organizations/$organization_id/firewall/access_rules/rules", $email, $api, $fields);
        return $result;
    }

    public function purgeFiles($email, $api, $files, $zone_id) {
        $json = json_encode(array(
            'files' => $files
        ));

        $result = $this->curlDeleteRequest("https://api.cloudflare.com/client/v4/zones/$zone_id/purge_cache", $email, $api, $json);
        return $result;
    }

    public function purgeAll($email, $api, $zone_id) {
        $json = json_encode(array(
            'purge_everything' => true
        ));

        $result = $this->curlDeleteRequest("https://api.cloudflare.com/client/v4/zones/$zone_id/purge_cache", $email, $api, $json);
        return $result;
    }

    public function getZonesList($email, $api, $domain) {
        $result = $this->curlGetRequest("https://api.cloudflare.com/client/v4/zones?name=$domain", $email, $api);
        return $result;
    }
    public function getOrganizationDetail($email, $api, $id) {
        $result = $this->curlGetRequest("https://api.cloudflare.com/client/v4/organizations/$id", $email, $api);
        return $result;
    }

    public function getUserOrganizationDetail($email, $api, $id) {
        $result = $this->curlGetRequest("https://api.cloudflare.com/client/v4/user/organizations/$id", $email, $api);
        return $result;
    }

    public function getDevelopmentMode($email, $api, $zone_id) {
        $result = $this->curlGetRequest("https://api.cloudflare.com/client/v4/zones/$zone_id/settings/development_mode", $email, $api);
        return $result;
    }

    public function curlGetRequest($url, $email, $api_key, $log = false,$timeout=null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        
        if(!$timeout){
            $timeout=self::TIMEOUT;
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        $headers = array(
            'X-Auth-Email: ' . $email,
            'X-Auth-Key: ' . $api_key,
            'Content-Type: application/json',
        );

        if ($log) {
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
            curl_setopt($ch, CURLOPT_ENCODING, ''); // to decode zip data           

            $headers = array(
                'X-Auth-Email: ' . $email,
                'X-Auth-Key: ' . $api_key,
                'Accept-encoding: gzip'
//                'Content-Type: application/json'
            );
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);

        if ($log) {
            if (!$result) {
                $tmp_arr = json_encode(array(
                    'error' => curl_error($ch),
                    'errorno' => curl_errno($ch),
                    'url' => $url
                ));
                $result = $tmp_arr;
            } else {
//                $file_nm = 'admin/downloads/cloudflare_logs/cflog_' . $log . '.txt';
//                $filename = dirname(__DIR__) . "/" . $file_nm;
//                file_put_contents($filename, $result);
//
//                $tmp_arr = json_encode(array(
//                    'success' => true,
//                    'date' => $log,
//                    'file' => $file_nm,
//                    'content' => $result
//                ));
//                $result = $tmp_arr;

                 $filename = dirname(__DIR__) . '/admin/downloads/cf_log_file.gz';
                $gzfile = $filename;
                $fp = gzopen($gzfile, 'w9');
                gzwrite($fp, $result);
                gzclose($fp);
                
                $result=json_encode(array(
                    'success' => true,
                    'date' => $log,
                    'file' => 'admin/downloads/cf_log_file.gz'
                ));
                
            }
        }



        curl_close($ch);
        return json_decode($result);
    }

    public function curlDeleteRequest($url, $email, $api_key, $json) {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = array(
            'X-Auth-Email: ' . $email,
            'X-Auth-Key: ' . $api_key,
            'Content-Type: application/json'
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result);
    }

    public function curlPatchRequest($url, $email, $api_key, $json) {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = array(
            'X-Auth-Email: ' . $email,
            'X-Auth-Key: ' . $api_key,
            'Content-Type: application/json'
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result);
    }

    public function curlPostRequest($url, $email, $api_key, $fields) {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //    curl_setopt($ch, CURLINFO_HEADER_OUT , true);

        $headers = array(
            'X-Auth-Email: ' . $email,
            'X-Auth-Key: ' . $api_key,
            'Content-Type: application/json'
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
//        var_dump($result);
//        var_dump(curl_getinfo($ch));
//        var_dump($headers);
//        var_dump($fields);
        //   $result=curl_getinfo($ch);
        //  return $result;
        curl_close($ch);
        return json_decode($result);
    }

    public function gzCompressFile($file_name, $source_data) {
        $gzfile = $file_name;
// Open the gz file (w9 is the highest compression)
        $fp = gzopen($gzfile, 'w9');
        gzwrite($fp, $source_data);
// Close the gz file and we're done
        gzclose($fp);
    }

    public function insertWhitlistLog($ip, $notes, $sender_email, $email_content, $email_subject, $folder, $request_status = 'whitlist', $current_status = 'whitelist', $comments = "", $expiry_after_hours = 24, $identifier = null) {

        $date = date('Y-m-d H:i:s');
        $expiry_ts = date("Y-m-d H:i:s", strtotime('+' . $expiry_after_hours . ' hours'));
        $sql = "insert into cloudflare_whitelist_requests (`ip`,`notes`,`sender_email`,`email_content`,`email_subject`,`request_status`,`current_status`,`created_ts`,`updated_ts`,`comments`,`expiry_ts`,`folder`,`access_id`)"
                . "VALUES('$ip','$notes','$sender_email','$email_content','$email_subject','$request_status','$current_status','$date','$date','$comments','$expiry_ts','$folder','$identifier')";
        db_res($sql);
    }

    public function insertAutoBlockIP($ip, $access_id, $hits, $min_hits_rule, $min_sec_rule, $action_taken, $expiry_after_hours, $cf_type = 'waf', $cf_action = 'block', $notes = "") {
        
        if($this->is_IPAlreadyAutoBlock($ip)){
            return true;
        }
        $date = date('Y-m-d H:i:s');
        $expiry_ts = date("Y-m-d H:i:s", strtotime('+' . $expiry_after_hours . ' hours'));
        $sql = "insert into cloudflare_autoblock_ip (`ip`,`hits`,`cf_type`,`cf_action`,`min_hits_rule`,`min_seconds_rule`,`action_taken`,`created_ts`,`updated_ts`,`comments`,`expiry_ts`,`access_id`)"
                . "VALUES('$ip','$hits','$cf_type','$cf_action','$min_hits_rule','$min_sec_rule','$action_taken','$date','$date','$notes','$expiry_ts','$access_id')";
        db_res($sql);
    }
    
    function updateAutoBlock_IP($id) {
        $date = date('Y-m-d H:i:s');        
        $query = "update cloudflare_autoblock_ip set action_taken='expired',updated_ts='$date' where id='$id'";
        db_res($query);
    }

    public function is_IPAlreadyAutoBlock($ip) {
        $query = "select * from cloudflare_autoblock_ip where ip ='$ip' and action_taken='blocked'";
        $res = db_arr($query);
        if (isset($res)) {
            if (isset($res['id'])) {
                return true;
            }
        }
        return false;
    }

    public function insertAdminPageRule($firewall_id, $action, $uri, $type) {
        $action = secure_string($action);
        $firewall_id = secure_string($firewall_id);
        $uri = secure_string($uri);
        $type = secure_string($type);
        $action_taken = 'pending';

        $date = date('Y-m-d H:i:s');

        $sql = "insert into cloudflare_adminurl_pagerules (`url`, `action`, `type`, `firewall_id`,`action_taken`,`created_ts`)"
                . "VALUES('$uri','$action','$type','$firewall_id','$action_taken','$date')";
        db_res($sql);
    }

    function isPageRuleExistInDB($url, $status = 'pending') {
        $query = "select * from cloudflare_adminurl_pagerules where url ='$url' and action_taken='$status'";
        $res = db_arr($query);
        if (isset($res)) {
            if (isset($res['id'])) {
                return true;
            }
        }

        return false;
    }

    function getPageRulefromDB($status = 'pending', $start = 0, $end = 100) {
        $limit = " Limit $start,$end";
        $query = "select * from cloudflare_adminurl_pagerules where action_taken='$status' order by created_ts $limit";
        $res = fill_assoc_array(db_res($query));
        return $res;
    }

    function updatePageRulefromDB($id, $rule, $admin, $rule_value) {
        $date = date('Y-m-d H:i:s');
        $update_remarks = "$rule=$rule_value";
        $query = "update cloudflare_adminurl_pagerules set action_taken='$rule',updated_ts='$date',admin='$admin',update_remarks='$update_remarks' where id='$id'";
        db_res($query);
    }

    public function insertFirewallEvent($event_id, $action, $host, $country, $ip, $method, $occured_at, $rule_message, $uri, $user_agent, $type, $rule_id, $rule_description, $triggered_rule_ids, $remarks = "", $other = "") {
        $action = secure_string($action);
        $host = secure_string($host);
        $country = secure_string($country);
        $ip = secure_string($ip);
        $method = secure_string($method);
        $rule_message = secure_string($rule_message);
        $uri = secure_string($uri);
        $user_agent = secure_string($user_agent);
        $type = secure_string($type);
        $triggered_rule_ids = secure_string($triggered_rule_ids);
        $rule_description = secure_string($rule_description);
        $rule_id = secure_string($rule_id);


        $date = date('Y-m-d H:i:s');

        $occured_at_ts = $this->convertCFTimeToTimeStamp($occured_at);

        $final_data = [];
        $final_data['event_id'] = $event_id;
        $final_data['action'] = $action;
        $final_data['host'] = $host;
        $final_data['country'] = $country;
        $final_data['ip'] = $ip;
        $final_data['method'] = $method;
        $final_data['occured_at'] = $occured_at;
        $final_data['occured_at_ts'] = $occured_at_ts;
        $final_data['rule_message'] = $rule_message;
        $final_data['uri'] = $uri;
        $final_data['user_agent'] = $user_agent;
        $final_data['type'] = $type;
        $final_data['other'] = $other;
        $final_data['remarks'] = $remarks;
        $final_data['created_ts'] = $date;
        $final_data['rule_id'] = $rule_id;
        $final_data['triggered_rule_ids'] = $triggered_rule_ids;
        $final_data['rule_description'] = $rule_description;

        $newly_created_id = new_query_insert('cloudflare_firewall_events', $final_data);
        return $newly_created_id;

//        $sql = "insert into cloudflare_firewall_events (`event_id`, `action`, `host`, `country`, `ip`, `method`, `occured_at`, `occured_at_ts`, `rule_message`, `uri`, `user_agent`,`type`, `other`, `remarks`, `created_ts`,`rule_id`,`triggered_rule_ids`,`rule_description`)"
//                . "VALUES('$event_id','$action','$host','$country','$ip','$method','$occured_at','$occured_at_ts','$rule_message','$uri','$user_agent','$type','$other','$remarks','$date','$rule_id','$triggered_rule_ids','$rule_description')";
//        db_res($sql);
    }

    public function convertCFTimeToTimeStamp($cf_time) {

//        $strto = strtotime($cf_time);
//        $convert_ts = date('Y-m-d H:i:s', $strto);
//        return $convert_ts;


        $date = explode("T", $cf_time);
        $time = explode("Z", $date[1]);

        $time_2 = explode(":", $time[0]);
        $time_1 = round(floatval($time_2[2]), 3);
        $final_dt = $date[0] . "T" . $time_2[0] . ":" . $time_2[1] . ":" . $time_1;
        $strto = strtotime($final_dt);
        $convert_ts = date('Y-m-d H:i:s', $strto);
        return $convert_ts;

//
//        //$time_1=  explode(":", $time[0]);
//        //$time_2=  substr($time_1[2],2);
//
//        $final_dt = $date[0] . " " . $time[0];
//
//        $str = strtotime($final_dt);
//        $convert_ts = date('Y-m-d H:i:s', $str);
//        //$convert_ts=$convert_ts.$time_2;
//        return $convert_ts;
    }

    public function validateBody($body, $stream, $mess) {
        $body = imap_fetchbody($stream, $mess, 1);
        $body = strip_tags($body);
        $body = explode(" ", $body);
        $body = $body[0];
        $body = explode(PHP_EOL, $body);
        $body = $body[0];
        $body = trim(preg_replace('/\s\s+/', ' ', $body));

        return $body;
    }

}
