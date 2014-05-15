<?php

class LafayetteCourseBlock implements CourseBlockInterface {

  /**
   * Parse a term code and return human readable identifier.
   */
  public function parseTermCode($code) {
    $year = substr($code, 0, 4);
    $term = substr($code, 4, 2);
    // Year codes for terms don't display intuitively.
    $year = $year + 1;

    switch ($term) {
      case '10':
        // Except for the fall.
        $year = $year - 1;
        $term = 'Fall';
        break;

      case '20':
        $term = 'Interim';
        break;

      case '30':
        $term = 'Spring';
        break;

      case '40':
        $term = 'Summer I';
        break;

      case '50':
        $term = 'Summer II';
        break;

    }

    $term = $term . ' ' . $year;

    return $term;
  }

  /**
   * Calculate the current term.
   */
  public function calculateCurrentTerm() {
    $time = date('Ymd');
    $year = substr($time, 0, 4);

    // Term codes are based on academic year.
    $year = $year - 1;

    $compare = substr($time, 4);
    if ((int)$compare < 122) {
      $term = $year . '20';
    }
    elseif ((int)$compare < 522) {
      $term = $year . '30';
    }
    elseif ((int)$compare < 701) {
      $term = $year . '40';
    }
    elseif ((int)$compare < 815) {
      $term = $year . '50';
    }
    else {
      // So we adjust everything except the fall.
      $year = $year + 1;
      $term = $year . '10';
    }
    return $term;
  }

  /**
   * Provide additional settings.
   */
  public function additionalSettings() {
    $form['shibIDP'] = array(
      '#type' => 'textfield',
      '#title' => t('Shibboleth IDP String'),
      '#description' => t('The Shibboleth IDP string to use for webservice authentication.'),
      '#size' => 40,
      '#maxlength' => 255,
      '#required' => TRUE,
    );

    return $form;
  }

  /**
   * Generate course links.
   */
  public function createCourseLink($course_id, $settings) {
    // Do some formatting stuff.
    $site_url = $settings['siteURL'];
    $auth_url = str_replace('http://', 'https://', $site_url);
    $shib_idp = $settings['shibIDP'];
    $link_url = $auth_url . '/alt?providerID=' . $shib_idp . '&target=' . $site_url . '/course/view.php?id=' . $course_id;

    return $link_url;

  }

  /**
   * Generate default term code.
   */
  public function defaultTermCode($current_term, $term_array) {
    // Figure out default term here.
    $terms = array();
    foreach ($term_array as $code => $title) {
      $terms[] = $code;
    }
    if (in_array($current_term, $terms)) {
      $term_code = $current_term;
    }
    else {
      $terms[] = $current_term;
      sort($terms);
      if ($terms[count($terms) - 1] == $current_term) {
        if (count($terms) != 1) {
          $term_code = $terms[count($terms) - 2];
        }
        else {
          $term_code = $current_term;
        }
      }
      else {
        $term_code = $terms[array_search($current_term, $terms) + 1];
      }
    }

    return $term_code;
  }

  /**
   * Generate the link back to the Moodle site.
   */
  public function createSiteLink($settings) {
    $site_url = $settings['siteURL'];
    $auth_url = str_replace('http://', 'https://', $site_url);
    $shib_idp = $settings['shibIDP'];
    return $auth_url . '/alt?providerID=' . $shib_idp . '&target=' . $site_url;
  }

}
