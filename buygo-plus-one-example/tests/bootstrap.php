<?php
/**
 * Bootstrap for WordPress integration tests
 *
 * This file loads WordPress before running tests that need the full WordPress environment
 */

// Prevent errors from different WordPress versions
error_reporting(E_ALL);
ini_set('display_errors', '1');

// This would load WordPress if needed
// For now, we use unit tests which don't require full WordPress
require_once __DIR__ . '/bootstrap-unit.php';
