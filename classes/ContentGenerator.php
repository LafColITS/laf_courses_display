<?php

class ContentGenerator {

  /**
   * Accesses the webservice and stores/replaces results in the database.
   */
  public static function getData($instance_id, $uid, $request_url) {
    
    // Check if the cache is good.
    if ($cache = cache_get("laf_courses_display:$instance_id:$uid", 'cache')) {
      if (REQUEST_TIME < $cache->expire) {
        return $cache->data;
      }
    }

    // Now we get the data from our curl request.
    $ch = curl_init($request_url);

    // Option to receive data as string directly.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    // Option to set timeout for non-responsive requests to n seconds.
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    $json_result = curl_exec($ch);
    curl_close($ch);

    // Check for no response.
    if (!$json_result) {
      return array();
    }
    // We now format the response as an associative array to return.
    $formatted = json_decode($json_result, TRUE);

    // Check for moodle response error.
    if (empty($formatted) || array_key_exists('exception', $formatted)) {
      return array();
    }

    // Store the data in the cache.
    $expire = REQUEST_TIME + (24*60*60);
    cache_set("laf_courses_display:$instance_id:$uid", $formatted, 'cache', $expire;

    return $formatted;
  }

  /**
   * Formats curl request urls.
   */
  public static function formatCurlRequest($instance_id, $user_mail, $settings) {
    $moodle_base_url = $settings['siteURL'];
    $webservice_token = $settings['webservicetoken'];
    $webservice_function_name = $settings['functionname'];
    $params = array('username' => $user_mail);

    $url = $moodle_base_url . '/webservice/rest/server.php?wstoken=' . $webservice_token . '&wsfunction=' . $webservice_function_name . '&moodlewsrestformat=json&' . http_build_query($params);
    return $url;
  }

}
