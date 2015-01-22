<?php

/**
 * @file
 * Definition of Drupal\acquia_connector\Tests\AcquiaConnectorSpiTest.
 */

namespace Drupal\acquia_connector\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\acquia_connector\Controller\SpiController;
use Drupal\Component\Serialization\Json;

/**
 * Tests the functionality of the Acquia SPI module.
 */
class AcquiaConnectorSpiTest extends WebTestBase{
  protected $strictConfigSchema = FALSE;
  protected $privileged_user;
  protected $setup_path;
  protected $credentials_path;
  protected $settings_path;
  protected $status_report_url;
  protected $acqtest_email = 'TEST_networkuser@example.com';
  protected $acqtest_pass = 'TEST_password';
  protected $acqtest_id =  'TEST_AcquiaConnectorTestID';
  protected $acqtest_key = 'TEST_AcquiaConnectorTestKey';
  protected $acqtest_expired_id = 'TEST_AcquiaConnectorTestIDExp';
  protected $acqtest_expired_key = 'TEST_AcquiaConnectorTestKeyExp';
  protected $acqtest_503_id = 'TEST_AcquiaConnectorTestID503';
  protected $acqtest_503_key = 'TEST_AcquiaConnectorTestKey503';
  protected $acqtest_error_id = 'TEST_AcquiaConnectorTestIDErr';
  protected $acqtest_error_key = 'TEST_AcquiaConnectorTestKeyErr';
  protected $platformKeys = array('php', 'webserver_type', 'webserver_version', 'apache_modules', 'php_extensions', 'php_quantum', 'database_type', 'database_version', 'system_type', 'system_version', 'mysql');
  protected $spiDataKeys = array(
    'spi_data_version',
    'site_key',
    'modules',
    'platform',
    'quantum',
    'system_status',
    'failed_logins',
    '404s',
    'watchdog_size',
    'watchdog_data',
    'last_nodes',
    'last_users',
    'extra_files',
    'ssl_login',
    'file_hashes',
    'hashes_md5',
    'hashes_sha1',
    'fileinfo',
    'distribution',
    'base_version',
    'build_data',
    'roles',
    'uid_0_present',
  );

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('acquia_connector', 'toolbar', 'devel', 'acquia_connector_test', 'node'); //@todo devel node(function getQuantum() 1101 line)

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Acquia SPI ',
      'description' => 'Test sending Acquia SPI data.',
      'group' => 'Acquia',
    );
  }

  public function setUp() {
    parent::setUp();
    //base url
    global $base_url;
    // Enable any modules required for the test
    // Create and log in our privileged user.
    $this->privileged_user = $this->drupalCreateUser(array(
      'administer site configuration',
      'access administration pages',
    ));
    $this->drupalLogin($this->privileged_user);

    // Setup variables.
    $this->credentials_path = 'admin/config/system/acquia-connector/credentials';
    $this->settings_path = 'admin/config/system/acquia-connector';
    $this->status_report_url = 'admin/reports/status';

    //local env
    \Drupal::config('acquia_connector.settings')->set('network_address', 'http://drupal-alerts.local:8083/')->save();
    \Drupal::config('acquia_connector.settings')->set('spi.server', 'http://drupal-alerts.local:8083/')->save();
    \Drupal::config('acquia_connector.settings')->set('spi.ssl_verify', FALSE)->save();
    \Drupal::config('acquia_connector.settings')->set('spi.ssl_override', TRUE)->save();
  }


  /**
   * Helper function for storing UI strings.
   */
  private function acquiaSPIStrings($id) {
    switch ($id) {
      case 'spi-status-text':
        return 'SPI data will be sent once every 30 minutes once cron is called';
      case 'spi-not-sent';
        return 'SPI data has not been sent';
      case 'spi-send-text';
        return 'manually send SPI data';
      case 'spi-data-sent':
        return 'SPI data sent';
      case 'spi-data-sent-error':
        return 'Error sending SPI data. Consult the logs for more information.';
      case 'spi-new-def':
        return 'There are new checks that will be performed on your site by the Acquia Connector';
    }
  }

  /**
   *
   */
  public function testAcquiaSPIUI() {
    $this->drupalGet($this->status_report_url);
    $this->assertNoText($this->acquiaSPIStrings('spi-status-text'), 'SPI send option does not exist when site is not connected');
    // Connect site on key and id that will error.
    $edit_fields = array(
      'acquia_identifier' => $this->acqtest_error_id,
      'acquia_key' => $this->acqtest_error_key,
    );
    $submit_button = 'Connect';
    $this->drupalPostForm($this->credentials_path, $edit_fields, $submit_button);
    // Send SPI data.
    $this->drupalGet($this->status_report_url);
    $this->assertText($this->acquiaSPIStrings('spi-status-text'), 'SPI explanation text exists');
    $this->clickLink($this->acquiaSPIStrings('spi-send-text'));
    $this->assertNoText($this->acquiaSPIStrings('spi-data-sent'), 'SPI data was not sent');
    $this->assertText($this->acquiaSPIStrings('spi-data-sent-error'), 'Page says there was an error sending data');

    // Connect site on non-error key and id.
    $this->connectSite();
    // Send SPI data.
    $this->drupalGet($this->status_report_url);
    $this->clickLink($this->acquiaSPIStrings('spi-send-text'));
    $this->assertText($this->acquiaSPIStrings('spi-data-sent'), 'SPI data was sent');
    $this->assertNoText($this->acquiaSPIStrings('spi-not-sent'), 'SPI does not say "data has not been sent"');
  }

  /**
   *
   */
  public function testAcquiaSPIDataStore() {
    $data = array(
      'foo' => 'bar',
    );
    $spi = new spiControllerTest();
    $spi->dataStoreSet(array('testdata' => $data));
    $stored_data = $spi->dataStoreGet(array('testdata'));
    $diff = array_diff($stored_data['testdata'], $data);
    $this->assertTrue(empty($diff), 'Storage can store simple array');

    $this->drupalGet('/');
     //Platform data should have been written.
    $stored = $spi->dataStoreGet(array('platform'));
    $diff = array_diff(array_keys($stored['platform']), $this->platformKeys);
    $this->assertTrue(empty($diff), 'Platform element contains expected keys');
  }

  /**
   *
   */
  public function testAcquiaSPIGet() {
    // Test spiControllerTest::get.
    $spi = new spiControllerTest();
    $spi_data = $spi->get();
    $valid = is_array($spi_data);
    $this->verbose(print_R($spi_data, TRUE));
    $this->assertTrue($valid, 'spiController::get returns an array');
    if ($valid) {
      foreach ($this->spiDataKeys as $key) {
        if (!array_key_exists($key, $spi_data)) {
          $valid = FALSE;
          break;
        }
      }
      $this->assertTrue($valid, 'Array has expected keys');
      $private_key = \Drupal::service('private_key')->get();
      $this->assertEqual(sha1($private_key), $spi_data['site_key'], 'Site key is sha1 of Drupal private key');
      $this->assertTrue(!empty($spi_data['spi_data_version']), 'SPI data version is set');
      $vars = Json::decode($spi_data['system_vars']);
      $this->assertTrue(is_array($vars), 'SPI data system_vars is a JSON-encoded array');
      $this->assertTrue(isset($vars['user_admin_role']), 'user_admin_role included in SPI data');
      $this->assertTrue(!empty($spi_data['modules']), 'Modules is not empty');
      $modules = array('status', 'name', 'version', 'package', 'core', 'project', 'filename', 'module_data');
      $diff = array_diff(array_keys($spi_data['modules'][0]), $modules);
      $this->assertTrue(empty($diff), 'Module elements have expected keys');
      $diff = array_diff(array_keys($spi_data['platform']), $this->platformKeys);
      $this->assertTrue(empty($diff), 'Platform contains expected keys');
      $this->assertTrue(isset($spi_data['platform']['php_quantum']['SERVER']), 'Global server data included in SPI data');
      $this->assertTrue(isset($spi_data['platform']['php_quantum']['SERVER']['SERVER_SOFTWARE']), 'Server software data set within global server info');
      $this->assertTrue(isset($spi_data['platform']['mysql']['Select_scan']), 'Mysql info in platform contains an expected key');
      $this->assertTrue(isset($spi_data['file_hashes']['core/includes/database.inc']), 'File hashes array contains an expected key');
      $roles = Json::decode($spi_data['roles']);
      $this->assertTrue(is_array($roles), 'Roles is an array');
      $this->assertTrue(isset($roles) && array_key_exists('anonymous', $roles), 'Roles array contains anonymous user');
      $this->assertTrue(isset($spi_data['fileinfo']['core/scripts/drupal.sh']), 'Fileinfo contains an expected key');
      $this->assertTrue(strpos($spi_data['fileinfo']['core/scripts/drupal.sh'], 'mt') === 0, 'Fileinfo element begins with expected value');
    }
  }

  /**
   * Helper function connects to valid subscription.
   */
  protected function connectSite() {
    $edit_fields = array(
      'acquia_identifier' => $this->acqtest_id,
      'acquia_key' => $this->acqtest_key,
    );
    $submit_button = 'Connect';
    $this->drupalPostForm($this->credentials_path, $edit_fields, $submit_button);
  }
}

class spiControllerTest extends SpiController{

  public function __construct(){}

  /**
   * Gather site profile information about this site.
   *
   * @param string $method
   *   Optional identifier for the method initiating request.
   *   Values could be 'cron' or 'menu callback' or 'drush'.
   *
   * @return array
   *   An associative array keyed by types of information.
   * D7: acquia_spi_get
   */
  public function get($method = '') {
    return parent::get($method);
  }

  /**
   * Put SPI data in local storage.
   *
   * @param array $data Keyed array of data to store.
   * @param int $expire Expire time or null to use default of 1 day.
   */
  public function dataStoreSet($data, $expire = NULL) {
    parent::dataStoreSet($data, $expire);
  }

  /**
   * Get SPI data out of local storage.
   *
   * @param array Array of keys to extract data for.
   *
   * @return array Stored data or false if no data is retrievable from storage.
   * D7: acquia_spi_data_store_get
   */
  public function dataStoreGet($keys) {
    return parent::dataStoreGet($keys);
  }
}