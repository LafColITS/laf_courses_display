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
    cache_set("laf_courses_display:$instance_id:$uid", $formatted, 'cache', $expire);

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

  /**
   * Call methods on classes implementing CourseBlockInterface.
   */
  public static function call(CourseBlockInterface $courseBlock, $method) {
    $args = func_get_args();
    // Remove the CourseBlock object and the method name from the args list to
    // be passed.
    unset($args[0]);
    unset($args[1]);
    return call_user_func_array(array($courseBlock, $method), $args);
  }

  /**
   * Return just the courses for the selected term.
   */
  public static function termSelect(CourseBlockInterface $courseBlock, $courses, $term_code, $term_array) {
    if ($term_code == 0) {
      $current_term = self::call($courseBlock, 'calculateCurrentTerm');
      $term_code = self::call($courseBlock, 'defaultTermCode', $current_term, $term_array);
    }

    $return_array = array(
      'courses' => array(),
      'term' => $term_code,
    );

    foreach ($courses as $element) {
      if (isset($element['term']) && $element['term'] == $term_code) {
        $return_array['courses'][] = $element;
      }
    }
    return $return_array;
  }

  /**
   * Generate content to display in block.
   */
  public static function generate_block_content($instance_id, $courseBlock, $formatted, $settings, $selected_term) {
    drupal_add_js('misc/ajax.js');
    drupal_add_library('system', 'drupal.ajax');
    drupal_add_library('system', 'jquery.form');

    $js = "jQuery(document).ready(function(){";
    $js .= "jQuery('#dropdown').css('display', 'block');";
    $js .= "});";

    drupal_add_js($js, 'inline');

    // Renderable output.
    $content = '';

    // For default content.
    $variables = array();

    // For inactive content.
    $inactive = array();

    // For all content.
    $links = array();

    // For course terms.
    $drop_links = array();

    // For looping through $links.
    $i = 0;

    // For looping through $drop_links.
    $j = 0;

    // Determine whether to render drop-down menu.
    $drop_down_flag = 0;

    if (!$selected_term) {
      $drop_down_flag = 1;
    }
    foreach ($formatted as $link_info) {
      $links[$i]['href'] = self::call($courseBlock, 'createCourseLink', $site_url, $link_info['id'], $settings);
      $links[$i]['title'] = html_entity_decode($link_info['fullname'], ENT_QUOTES);
      $links[$i]['attributes'] = array('target' => '_blank');
      // If there is a term field specified, generate the link string.
      if (!empty($link_info['term'])) {
        $links[$i]['term'] = $link_info['term'];
        $k = 1;
        $test_link = self::call($courseBlock, 'parseTermCode', $link_info['term']);
        foreach ($drop_links as $comp_link) {
          $k = 1;
          if (strcmp($test_link, $comp_link['title']) == 0) {
            $k = 0;
            break;
          }
        }
        if ($k == 1) {
          $drop_links[$j]['title'] = $test_link;
          $drop_links[$j]['code'] = $link_info['term'];
          $j = $j + 1;
        }
      }
      // TODO: Fix data problem when only visible course has no term code. Hack fix on line 625
      if ($link_info['visible'] == 1) {
        $variables['links'][] = $links[$i];
      }
      else {
        $inactive['links'][] = $links[$i];
      }
      $i = $i + 1;
    }
    if ($check = !empty($variables)) {
      if ($use_term_code) {
        $sorted = self::termSelect($courseBlock, $variables['links'], $selected_term, $drop_links);
        $default_term = $sorted['term'];
        $variables['links'] = $sorted['courses'];
      }
      uasort($variables['links'], 'compare_links_alphabetical');
      $variables['attributes'] = array();
      if ($use_term_code == 1 && $drop_down_flag == 1 && !empty($drop_links)) {
        $content .= drupal_render(drupal_get_form('get_term_form', $drop_links, $default_term, $instance_id));
      }
      $content .= "<div id='ajax_content_response_$instance_id' class='item-list'>";
      if ($use_term_code) {
        $term_display = self::call($courseBlock, 'parseTermCode', $default_term);
      }
      $content .= theme('links', $variables);
    }
    if (!empty($inactive)) {
      if ($use_term_code) {
        $sorted = self::termSelect($courseBlock, $inactive['links'], $selected_term, $drop_links);
        $default_term = $sorted['term'];
        $inactive['links'] = $sorted['courses'];
      }
      uasort($inactive['links'], array(self, 'compare_links_alphabetical'));
      $inactive['attributes'] = array();
      $inactive_content = theme('links', $inactive);
    }

    // Collapsible div.
    if (!empty($inactive['links'])) {
      $handle = t('Inactive Courses');
      $content .= theme('ctools_collapsible',
        array(
          'handle' => $handle,
          'content' => $inactive_content,
          'collapsed' => TRUE,
        ));
    }
    if ($check) {
      $content .= "</div>";
    }
    return $content;
  }


  /**
   * Comparison function for sorting links arrays.
   */
  public static function compare_links_alphabetical($a, $b) {
    // We are given two arrays, and want to compare their 'title' fields.
    return strnatcasecmp($a['title'], $b['title']);
  }


}
