<?php

class TermlessCourseBlock implements CourseBlockInterface {

  /**
   * Parse a term code and return human readable identifier.
   */
  public function parseTermCode($code) {
    return $code;
  }

  /**
   * Calculate the current term.
   */
  public function calculateCurrentTerm() {
    return '';
  }

  /**
   * Provide additional settings.
   */
  public function additionalSettings() {
    $form['usetermcode'] = array(
      '#type' => 'value',
      '#value' => 0,
    );

    return $form;
  }

  /**
   * Generate course links.
   */
  public function createCourseLink($course_id, $settings) {
    $site_url = $settings['siteURL'];
    $link_url = $site_url . '/course/view.php?id=' . $course_id;
    return $link_url;

  }

  /**
   * Generate default term code.
   */
  public function defaultTermCode($current_term, $term_array) {
    return '';
  }

  /**
   * Generate the link back to the Moodle site.
   */
  public function createSiteLink($settings) {
    return $site_url;
  }

}
