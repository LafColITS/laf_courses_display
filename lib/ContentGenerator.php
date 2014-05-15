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

    // Option to follow redirects.
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

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
    $moodle_base_url = str_replace('http://', 'https://', $settings['siteURL']);
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
   * Return an array of terms from the course list.
   */
  private static function generateTermList($courseBlock, $formatted) {
    $terms = array();
    foreach ($formatted as $courseInfo) {
      if (!in_array($courseInfo['term'], $terms)) {
        $terms[$courseInfo['term']] = self::call($courseBlock, 'parseTermCode', $courseInfo['term']);
      }
    }
    return $terms;
  }

  /**
   * Split course list into active and inactive and reformat info array.
   */
  private static function formatCourseInfo($courseBlock, $formatted, $settings) {
    $courses = array();
    foreach ($formatted as $courseInfo) {
      if ($courseInfo['visible'] == 1) {
        $courses['active'][] = array(
          'href' => self::call($courseBlock, 'createCourseLink', $courseInfo['id'], $settings),
          'title' => $courseInfo['fullname'],
          'attributes' => array('target' => '_blank'),
          'term' => $courseInfo['term'],
        );
      }
      else if ($courseInfo['visible'] == 0) {
        $courses['inactive'][] = array(
          'href' => self::call($courseBlock, 'createCourseLink', $courseInfo['id'], $settings),
          'title' => $courseInfo['fullname'],
          'attribues' => array('target' => '_blank'),
          'term' => $courseInfo['term'],
        );
      }
    }
    return $courses;
  }

  /**
   * Generate content to display in block.
   */
  public static function generateBlockContent($instance_id, $courseBlock, $formatted, $settings, $selected_term) {

    if ($settings['usetermcode']) {
      $terms = self::generateTermList($courseBlock, $formatted);
    }

    $courses = self::formatCourseInfo($courseBlock, $formatted, $settings);

    if (!empty($courses['active'])) {
      if ($settings['usetermcode']) {
        $sorted = self::termSelect($courseBlock, $courses['active'], $selected_term, $terms);
        $default_term = $sorted['term'];
        $variables['links'] = $sorted['courses'];
      }
      else {
        $variables['links'] = $courses['active'];
      }
      uasort($variables['links'], array('self', 'compareLinksAlphabetical'));
      $variables['attributes'] = array();
    }

    if (!empty($courses['inactive'])) {
      if ($settings['usetermcode']) {
        $sorted = self::termSelect($courseBlock, $courses['inactive'], $selected_term, $terms);
        $default_term = $sorted['term'];
        $inactive['links'] = $sorted['courses'];
      }
      else {
        $inactive['links'] = $courses['inactive'];
      }
      uasort($inactive['links'], array('self', 'compareLinksAlphabetical'));
      $inactive['attributes'] = array();
    }

    $options = array(
      'instanceid' => $instance_id,
      'usetermcode' => $settings['usetermcode'],
      'dropdown' => !$selected_term,
      'terms' => isset($terms) ? $terms : array(),
      'defaultterm' => isset($default_term) ? $default_term : '',
      'activecontent' => isset($variables) ? $variables : array(),
      'inactivecontent' => isset($inactive) ? $inactive : array(),
    );

    return self::formatContent($options);
  }

  /**
   * Comparison function for sorting links arrays.
   */
  private static function compareLinksAlphabetical($a, $b) {
    // We are given two arrays, and want to compare their 'title' fields.
    return strnatcasecmp($a['title'], $b['title']);
  }

  /**
   * Add javascript necessary for block.
   */
  private static function addBlockJs() {
    drupal_add_js('misc/ajax.js');
    drupal_add_library('system', 'drupal.ajax');
    drupal_add_library('system', 'jquery.form');

    $js = "jQuery(document).ready(function(){";
    $js .= "jQuery('#dropdown').css('display', 'block');";
    $js .= "});";

    drupal_add_js($js, 'inline');
  }

  /**
   * Format content for display.
   */
  private static function formatContent($options) {
    // Setup options variables.
    foreach ($options as $key => $value) {
      $$key = $value;
    }
    // Add necessary Javascript.
    self::addBlockJs();

    // Generate html from course arrays.
    $content = '';
    if ($usetermcode == 1 && $dropdown == 1 && !empty($terms)) {
      $content .= drupal_render(drupal_get_form('get_term_form', $terms, $defaultterm, $instanceid));
    }
    $content .= "<div id='ajax_content_response_$instanceid' class='item-list'>";
    if (!empty($activecontent['links'])) {
      $content .= theme('links', $activecontent);
    }
    if (!empty($inactivecontent['links'])) {
      $inactiveprocessed = theme('links', $inactivecontent);
      $handle = t('Inactive Courses');
      $content .= theme('ctools_collapsible',
        array(
          'handle' => $handle,
          'content' => $inactiveprocessed,
          'collapsed' => TRUE,
        ));
    }
    $content .= "</div>";
    return $content;
  }

}
