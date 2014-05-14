<?php

/**
 * Interface for course block implementations.
 */
 interface CourseBlockInterface {

  /**
   * Parse a term code and return human-readable text.
   *
   * This function takes a course term code returned and turns it into a human
   * readable term identifier for display.
   *
   * @param string $code
   *   A course term code returned by Moodle.
   *
   * @return string
   *   A human-readable string title for the course term.
   */
  public function parseTermCode($code);

  /**
   * Determine the current term code.
   *
   * This function takes no arguments and uses internal logic to determine
   * which term code should be the current default in the term picker.
   *
   * @return string
   *   A properly formatted term code.
   */
  public function calculateCurrentTerm();

  /**
   * Select default term given current term and term data.
   *
   * Determine what the default term in the term picker menu should be. This
   * function receives the terms that were returned in valid course data and
   * the value of this implementation of calculateCurrentTerm.
   *
   * @param string $current_term
   *   The current term code.
   *
   * @param array $terms
   *   An array of available term codes.
   *
   * @return string
   *   A properly formatted term code to serve as the default.
   */
  public function defaultTermCode($current_term, $terms);

  /**
   * Provide additional instance configuration settings.
   *
   * This function allows additional configuration settings to be added to the
   * configuration form for a course block instance. These settings values
   * are passed to the createCourseLink method.
   *
   * @return array
   *   A Drupal Form API array for the settings.
   */
  public function additionalSettings();

  /**
   * Provide a formatted URL to the course.
   *
   * This function generates a link back to the listed course in Moodle.
   * Additional settings provided with additionalSettings are available here
   * if needed.
   *
   * @param string $site_url
   *   The base URL of the Moodle instance.
   *
   * @param string $course_id
   *   The course id of the course being linked.
   *
   * @param array $settings
   *   The settings array of the block instance.
   *
   * @return string
   *   A URL that links to the course in Moodle.
   */
  public function createCourseLink($site_url, $course_id, $settings);

  /**
   * Provide a formatted URL to the site.
   *
   * This function generates a link back to the Moodle site. Additional
   * settings provided with additionalSettings are available here if needed.
   *
   * @param array $settings
   *   The settings array of the block instance.
   *
   * @return string
   *   A URL that links to the Moodle site.
   */
  public function createSiteLink($settings);

 }
