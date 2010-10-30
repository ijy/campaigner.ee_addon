<?php

/**
 * Tests for the Campaigner model.
 *
 * @package 	Campaigner
 * @author 		Stephen Lewis <addons@experienceinternet.co.uk>
 * @copyright	Experience Internet
 */

require_once PATH_THIRD .'campaigner/classes/campaigner_settings' .EXT;
require_once PATH_THIRD .'campaigner/models/campaigner_model' .EXT;
require_once PATH_THIRD .'campaigner/tests/mocks/mock.cmbase' .EXT;

class Test_campaigner_model extends Testee_unit_test_case {
	
	/* --------------------------------------------------------------
	 * PRIVATE PROPERTIES
	 * ------------------------------------------------------------ */
	
	/**
	 * The API connector.
	 *
	 * @access	private
	 * @var		CMBase
	 */
	private $_api_connector;
	
	/**
	 * The model.
	 *
	 * @access	private
	 * @var		Campaigner_model
	 */
	private $_model;
	
	
	
	/* --------------------------------------------------------------
	 * PUBLIC METHODS
	 * ------------------------------------------------------------ */
	
	/**
	 * Runs before each test.
	 *
	 * @access	public
	 * @return	void
	 */
	public function setUp()
	{
		parent::setUp();
		
		Mock::generate('Mock_CampaignMonitor', 'CampaignMonitor');
		
		$this->_api_connector	= new CampaignMonitor();
		$this->_model 			= new Campaigner_model();
	}
	
	
	/* --------------------------------------------------------------
	 * TEST METHODS
	 * ------------------------------------------------------------ */
	
	public function test_get_package_name()
	{
		$this->assertEqual(
			strtolower($this->_model->get_package_name()),
			'campaigner'
		);
		
		$this->assertNotEqual(
			strtolower($this->_model->get_package_name()),
			'wibble'
		);
	}
	
	
	public function test_get_package_version()
	{
		$this->assertPattern(
			'/^[0-9abcdehlprtv\.]+$/i',
			$this->_model->get_package_version()
		);
	}
	
	
	public function test_get_extension_class()
	{
		$this->assertEqual(
			strtolower($this->_model->get_extension_class()),
			'campaigner_ext'
		);
		
		$this->assertNotEqual(
			strtolower($this->_model->get_extension_class()),
			'campaigner'
		);
	}
	
	
	public function test_get_site_id()
	{
		$config = $this->_ee->config;
		$site_id = '10';
		
		$config->expectOnce('item', array('site_id'));
		$config->setReturnValue('item', $site_id, array('site_id'));
		
		$this->assertIdentical($site_id, $this->_model->get_site_id());
	}
	
	
	public function test_activate_extension__create_settings_table()
	{
		$db 	= $this->_ee->db;
		$dbf	= $this->_ee->dbforge;
		$loader	= $this->_ee->load;
		
		/**
		 * Create the settings table.
		 * - site_id
		 * - api_key
		 * - client_id
		 */
		
		$fields = array(
			'site_id'	=> array(
				'constraint'		=> 5,
				'type'				=> 'int',
				'unsigned'			=> TRUE
			),
			'api_key'	=> array(
				'constraint'		=> 50,
				'type'				=> 'varchar'
			),
			'client_id'	=> array(
				'constraint'		=> 50,
				'type'				=> 'varchar'
			)
		);
		
		$loader->expectOnce('dbforge', array());
		
		$dbf->expectAt(0, 'add_field', array($fields));
		$dbf->expectAt(0, 'add_key', array('site_id', TRUE));
		$dbf->expectAt(0, 'create_table', array('campaigner_settings'));
		
		// Tests for the _total_ call count.
		$dbf->expectCallCount('add_field', 2);
		$dbf->expectCallCount('add_key', 2);
		$dbf->expectCallCount('create_table', 2);
		
		$this->_model->activate_extension();
	}
	
	
	public function test_activate_extension__create_mailing_lists_table()
	{
		$db		= $this->_ee->db;
		$dbf	= $this->_ee->dbforge;
		$loader	= $this->_ee->load;
		
		/**
		 * Create the mailing lists table.
		 * - list_id
		 * - site_id
		 * - custom_fields
		 * 		serialised array: array($merge_variable => $member_field_id) --> simplest solution, for now.
		 * - trigger_field_id
		 * - trigger_field_value
		 */
		
		$fields = array(
			'list_id' => array(
				'constraint'	=> 50,
				'type'			=> 'varchar'
			),
			'site_id' => array(
				'constraint'	=> 5,
				'type'			=> 'int',
				'unsigned'		=> TRUE
			),
			'custom_fields' => array(
				'type'			=> 'text'
			),
			'trigger_field' => array(
				'constraint'	=> 50,
				'type'			=> 'varchar',
				'unsigned'		=> TRUE
			),
			'trigger_value' => array(
				'constraint'	=> 255,
				'type'			=> 'varchar'
			)
		);
		
		$loader->expectOnce('dbforge', array());
		
		$dbf->expectAt(1, 'add_field', array($fields));
		$dbf->expectAt(1, 'add_key', array('list_id', TRUE));
		$dbf->expectAt(1, 'create_table', array('campaigner_mailing_lists'));
		
		$this->_model->activate_extension();
	}
	
	
	public function test_activate_extension__register_extension_hooks()
	{
		$db = $this->_ee->db;
		
		/**
		 * Register the extension hooks:
		 * - cp_members_validate_members
		 * - member_member_register
		 * - member_register_validate_members
		 * - user_edit_end
		 * - user_register_end
		 */
		
		$class = $this->_model->get_extension_class();
		$version = $this->_model->get_package_version();
		
		$hooks = array(
			'cp_members_validate_members',
			'member_member_register',
			'member_register_validate_members',
			'user_edit_end',
			'user_register_end'
		);
		
		for ($list_count = 0; $list_count < count($hooks); $list_count++)
		{
			$data = array(
				'class'		=> $class,
				'enabled'	=> 'y',
				'hook'		=> $hooks[$list_count],
				'method'	=> 'on_' .$hooks[$list_count],
				'priority'	=> 10,
				'settings'	=> '',
				'version'	=> $version
			);
			
			$db->expectAt($list_count, 'insert', array('extensions', $data));
		}
		
		$db->expectCallCount('insert', count($hooks));
		$this->_model->activate_extension();
	}
	
	
	public function test_disable_extension()
	{
		$db = $this->_ee->db;
		$dbf = $this->_ee->dbforge;
		
		/**
		 * 1. Delete the extension hooks.
		 * 2. Drop the settings table.
		 * 3. Drop the mailing lists table.
		 */
		
		$db->expectOnce('delete', array('extensions', array('class' => $this->_model->get_extension_class())));
		
		$dbf->expectCallCount('drop_table', 2);
		$dbf->expectAt(0, 'drop_table', array('campaigner_settings'));
		$dbf->expectAt(1, 'drop_table', array('campaigner_mailing_lists'));
		
		$this->_model->disable_extension();
	}
	
	
	public function test_update_extension__update()
	{
		$db = $this->_ee->db;
		
		$installed_version	= '1.0.0';
		$package_version	= '1.1.0';
		
		// Update the extension version number in the database.
		$data = array('version' => $package_version);
		$criteria = array('class' => $this->_model->get_extension_class());
		
		$db->expectOnce('update', array('extensions', $data, $criteria));
		
		$this->assertIdentical(NULL, $this->_model->update_extension($installed_version, $package_version));
	}
	
	
	public function test_update_extension__no_update()
	{
		$installed_version	= '1.0.0';
		$package_version	= '1.0.0';
		
		$this->assertIdentical(FALSE, $this->_model->update_extension($installed_version, $package_version));
	}
	
	
	public function test_update_extension__not_installed()
	{
		$installed_version	= '';
		$package_version	= '1.0.0';
		
		$this->assertIdentical(FALSE, $this->_model->update_extension($installed_version, $package_version));
	}
	
	
	public function test_get_theme_url__no_slash()
	{
		// Dummy values.
		$theme_url 		= '/path/to/themes';
		$package_url	= $theme_url .'/third_party/' .strtolower($this->_model->get_package_name()) .'/';
		
		// Expectations.
		$this->_ee->config->expectOnce('item', array('theme_folder_url'));
		
		// Return values.
		$this->_ee->config->setReturnValue('item', $theme_url, array('theme_folder_url'));
		
		// Tests.
		$this->assertIdentical($package_url, $this->_model->get_theme_url());
	}
	
	
	
	/* --------------------------------------------------------------
	 * DATABASE TESTS
	 * ------------------------------------------------------------ */
	
	public function test_get_installed_extension_version__installed()
	{
		$db = $this->_ee->db;
		
		// Dummy values.
		$criteria	= array('class' => $this->_model->get_extension_class());
		$limit		= 1;
		$table 		= 'extensions';
		$version	= '1.1.0';
		
		$db_result			= $this->_get_mock('db_query');
		$db_row				= new stdClass();
		$db_row->version 	= $version;
		
		// Expectations.
		$db->expectOnce('select', array('version'));
		$db->expectOnce('get_where', array($table, $criteria, $limit));
		$db_result->expectOnce('num_rows');
		$db_result->expectOnce('row');
		
		// Return values.
		$db->setReturnReference('get_where', $db_result);
		$db_result->setReturnValue('num_rows', 1);
		$db_result->setReturnValue('row', $db_row);
		
		// Tests.
		$this->assertIdentical($version, $this->_model->get_installed_extension_version());
	}
	
	
	public function test_get_installed_extension_version__not_installed()
	{
		$db = $this->_ee->db;
		
		// Dummy values.
		$db_result	= $this->_get_mock('db_query');
		
		// Expectations.
		$db_result->expectNever('row');
		
		// Return values.
		$db->setReturnReference('select', $db);
		$db->setReturnReference('get_where', $db_result);
		$db_result->setReturnValue('num_rows', 0);
		
		// Tests.
		$this->assertIdentical('', $this->_model->get_installed_extension_version());
	}
	
	
	public function test_get_settings_from_db__success()
	{
		$config		= $this->_ee->config;
		$db			= $this->_ee->db;
		
		$site_id	= '10';
		$api_key	= 'api_key';
		$client_id	= 'client_id';
		
		// Return the site ID.
		$config->expectOnce('item', array('site_id'));
		$config->setReturnValue('item', $site_id, array('site_id'));
		
		// Settings db row.
		$db_row = array(
			'site_id'	=> $site_id,
			'api_key'	=> $api_key,
			'client_id'	=> $client_id
		);
		
		$db_query = $this->_get_mock('db_query');
		
		// Return values.
		$db_query->setReturnValue('num_rows', 1);
		$db_query->setReturnValue('row_array', $db_row);
		$db->setReturnReference('get_where', $db_query);
		
		// Expectations.
		$db_query->expectOnce('num_rows');
		$db_query->expectOnce('row_array');
		$db->expectOnce('get_where', array('campaigner_settings', array('site_id' => $site_id), 1));
		
		// Create the settings object.
		$settings = new Campaigner_settings($db_row);
		
		// Run the test.
		$this->assertIdentical($settings, $this->_model->get_settings_from_db());
	}
	
	
	public function test_get_settings_from_db__no_settings()
	{
		$db = $this->_ee->db;
		$db_query = $this->_get_mock('db_query');
		
		// Return values.
		$db_query->setReturnValue('num_rows', 0);
		$db->setReturnReference('get_where', $db_query);
		
		// Expectations.
		$db_query->expectNever('row_array');
		
		// Run the test.
		$this->assertIdentical(new Campaigner_settings(), $this->_model->get_settings_from_db());
	}
	
	
	public function test_get_mailing_lists_from_db__success()
	{
		$db			= $this->_ee->db;
		$db_query 	= $this->_get_mock('db_query');
		$site_id 	= '10';
		
		// Site ID.
		$this->_ee->config->setReturnValue('item', $site_id, array('site_id'));
		
		// Custom fields.
		$custom_fields_data	= array();
		$custom_fields 		= array();
		
		for ($list_count = 0; $list_count < 10; $list_count++)
		{
			$data = array('field_id' => 'm_field_id_' .$list_count, 'id' => 'merge_var_id_' .$list_count);
			
			$custom_fields_data[] 	= $data;
			$custom_fields[] 		= new Campaigner_custom_field($data);
		}
		
		$custom_fields_data = serialize($custom_fields_data);
		
		// Rows / mailing lists.
		$db_rows 		= array();
		$mailing_lists 	= array();
		
		for ($list_count = 0; $list_count < 10; $list_count++)
		{
			$data = array(
				'site_id'		=> $site_id,
				'custom_fields'	=> $custom_fields_data,
				'list_id'		=> 'list_id_' .$list_count,
				'trigger_field'	=> 'm_field_id_' .$list_count,
				'trigger_value'	=> 'trigger_value_' .$list_count
			);
			
			$db_rows[] = $data;
			
			$data['custom_fields']	= $custom_fields;
			$mailing_lists[]		= new Campaigner_mailing_list($data);
		}
		
		// Return values.
		$db_query->setReturnValue('num_rows', count($db_rows));
		$db_query->setReturnValue('result_array', $db_rows);
		$db->setReturnReference('get_where', $db_query);
		
		// Expectations.
		$db_query->expectOnce('result_array');
		$db->expectOnce('get_where', array('campaigner_mailing_lists', array('site_id' => $site_id)));
		
		// Run the test.
		$this->assertIdentical($mailing_lists, $this->_model->get_mailing_lists_from_db());
	}
	
	
	public function test_get_mailing_lists_from_db__no_mailing_lists()
	{
		$db = $this->_ee->db;
		$db_query = $this->_get_mock('db_query');
		
		// Retun values.
		$db_query->setReturnValue('result_array', array());
		$db->setReturnReference('get_where', $db_query);
		
		// Run the test.
		$this->assertIdentical(array(), $this->_model->get_mailing_lists_from_db());
	}
	
	
	public function test_get_mailing_lists_from_db__no_custom_fields()
	{
		$db			= $this->_ee->db;
		$db_query 	= $this->_get_mock('db_query');
		$site_id 	= '10';
		
		// Rows / mailing lists.
		$db_rows = array();
		$mailing_lists = array();
		
		for ($list_count = 0; $list_count < 10; $list_count++)
		{
			$data = array(
				'site_id'		=> $site_id,
				'custom_fields'	=> NULL,
				'list_id'		=> 'list_id_' .$list_count,
				'trigger_field'	=> 'm_field_id_' .$list_count,
				'trigger_value'	=> 'trigger_value_' .$list_count
			);
			
			$db_rows[] = $data;
			
			unset($data['custom_fields']);
			$mailing_lists[] = new Campaigner_mailing_list($data);
		}
		
		// Return values.
		$db_query->setReturnValue('num_rows', count($db_rows));
		$db_query->setReturnValue('result_array', $db_rows);
		$db->setReturnReference('get_where', $db_query);
		
		// Run the test.
		$this->assertIdentical($mailing_lists, $this->_model->get_mailing_lists_from_db());
	}
	
	
	public function test_save_settings_to_db__success()
	{
		$config		= $this->_ee->config;
		$db			= $this->_ee->db;
		$site_id	= '10';
		
		// Settings.
		$settings = new Campaigner_settings(array(
			'api_key'	=> 'API key',
			'client_id'	=> 'Client ID'
		));
		
		$settings_data = $settings->to_array();
		unset($settings_data['mailing_lists']);
		$settings_data = array_merge(array('site_id' => $site_id), $settings_data);
		
		// Return values.
		$config->setReturnValue('item', $site_id, array('site_id'));
		$db->setReturnValue('affected_rows', 1);
		
		// Expectations.
		$config->expectOnce('item', array('site_id'));
		$db->expectOnce('delete', array('campaigner_settings', array('site_id' => $site_id)));
		$db->expectOnce('insert', array('campaigner_settings', $settings_data));
		
		// Run the test.
		$this->assertIdentical(TRUE, $this->_model->save_settings_to_db($settings));
	}
	
	
	public function test_save_settings_to_db__failure()
	{
		$this->_ee->db->setReturnValue('affected_rows', 0);
		$this->assertIdentical(FALSE, $this->_model->save_settings_to_db(new Campaigner_settings()));
	}
	
	
	public function test_save_mailing_lists_to_db__success()
	{
		$config		= $this->_ee->config;
		$db 		= $this->_ee->db;
		$site_id	= '10';
		
		// Merge variables.
		for ($list_count = 0; $list_count < 10; $list_count++)
		{
			$custom_fields[] = new Campaigner_custom_field(array(
				'cm_key'			=> 'cm_key_' .$list_count,
				'member_field_id'	=> 'm_field_id_' .$list_count
			));
		}
		
		// Mailing lists.
		for ($list_count = 0; $list_count < 10; $list_count++)
		{
			$mailing_lists[] = new Campaigner_mailing_list(array(
				'custom_fields'	=> $custom_fields,
				'list_id'		=> 'list_id_' .$list_count,
				'trigger_field'	=> 'm_field_id_' .$list_count,
				'trigger_value'	=> 'trigger_value_' .$list_count
			));
		}
		
		// Settings.
		$settings = new Campaigner_settings(array('mailing_lists' => $mailing_lists));
		
		// Return values.
		$config->setReturnValue('item', $site_id, array('site_id'));
		$db->setReturnValue('affected_rows', 1);
		
		// Expectations.
		$config->expectOnce('item', array('site_id'));
		$db->expectOnce('delete', array('campaigner_mailing_lists', array('site_id' => $site_id)));
		$db->expectCallCount('insert', count($mailing_lists));
		
		for ($list_count = 0; $list_count < count($mailing_lists); $list_count++)
		{
			$data					= $mailing_lists[$list_count]->to_array();
			$data['custom_fields']	= serialize($data['custom_fields']);
			$data					= array_merge(array('site_id' => $site_id), $data);
			
			$db->expectAt($list_count, 'insert', array('campaigner_mailing_lists', $data));
		}
		
		// Run the test.
		$this->assertIdentical(TRUE, $this->_model->save_mailing_lists_to_db($settings));
	}
	
	
	public function test_save_mailing_lists_to_db__failure()
	{
		$config		= $this->_ee->config;
		$db 		= $this->_ee->db;
		$site_id	= '10';
		
		// Settings.
		$settings = new Campaigner_settings(array('mailing_lists' => array(new Campaigner_mailing_list())));
		
		// Return values.
		$config->setReturnValue('item', $site_id, array('site_id'));
		$db->setReturnValue('affected_rows', 0);
		
		// Expectations.
		$db->expectCallCount('delete', 2, array('campaigner_mailing_lists', array('site_id' => $site_id)));
		
		// Run the test.
		$this->assertIdentical(FALSE, $this->_model->save_mailing_lists_to_db($settings));
	}
	
	
	public function test_save_extension_settings__settings_error()
	{
		$db		= $this->_ee->db;
		$lang	= $this->_ee->lang;
		$error	= 'Settings not saved';
		
		// Return values.
		$db->setReturnValue('affected_rows', 0);
		$lang->setReturnValue('line', $error);
		
		// Run the test.
		try
		{
			$this->_model->save_extension_settings(new Campaigner_settings());
			$this->fail();
		}
		catch (Exception $e)
		{
			$e->getMessage() == $error
				? $this->pass()
				: $this->fail();
		}
	}
	
	
	public function test_save_extension_settings__mailing_lists_error()
	{
		$db		= $this->_ee->db;
		$config	= $this->_ee->config;
		$lang	= $this->_ee->lang;
		$error	= 'Mailing lists not saved';
		
		// Return values.
		$db->setReturnValueAt(0, 'affected_rows', 1);
		$db->setReturnValue('affected_rows', 0);
		$lang->setReturnValue('line', $error);
		
		// Settings.
		$settings = new Campaigner_settings(array('mailing_lists' => array(new Campaigner_mailing_list())));
		
		// Run the test.
		try
		{
			$this->_model->save_extension_settings($settings);
			$this->fail();
		}
		catch (Exception $e)
		{
			$e->getMessage() == $error
				? $this->pass()
				: $this->fail();
		}
	}
	
	
	
	/* --------------------------------------------------------------
	 * UPDATE FROM INPUT TESTS
	 * ------------------------------------------------------------ */
	
	public function test_update_basic_settings_from_input__success()
	{
		$input 		= $this->_ee->input;
		$api_key	= 'API key';
		$client_id	= 'Client ID';
		
		// Return values.
		$input->setReturnValue('get_post', $api_key, array('api_key'));
		$input->setReturnValue('get_post', $client_id, array('client_id'));
		
		// Expectations
		$input->expectCallCount('get_post', 2);
		
		// Settings.
		$old_settings = new Campaigner_settings(array('api_key' => 'old_api_key'));
		$new_settings = new Campaigner_settings(array('api_key' => $api_key, 'client_id' => $client_id));
		
		// Run the test.
		$this->assertIdentical($new_settings, $this->_model->update_basic_settings_from_input($old_settings));
	}
	
	
	public function test_update_basic_settings_from_input__invalid_input()
	{
		$input 		= $this->_ee->input;
		$api_key	= 'API key';
		$client_id	= 'Client ID';
		$invalid	= 'Wibble';
		
		// Return values.
		$input->setReturnValue('get_post', $api_key, array('api_key'));
		$input->setReturnValue('get_post', $client_id, array('client_id'));
		$input->setReturnValue('get_post', $invalid, array('invalid'));
		
		// Settings.
		$settings = new Campaigner_settings(array('api_key' => $api_key, 'client_id' => $client_id));
		
		// Run the test.
		$this->assertIdentical($settings, $this->_model->update_basic_settings_from_input(new Campaigner_settings()));
	}
	
	
	public function test_update_basic_settings_from_input__missing_input()
	{
		// Return values.
		$this->_ee->input->setReturnValue('get_post', FALSE);
		
		// Settings.
		$settings = new Campaigner_settings(array('api_key' => 'old_api_key', 'client_id' => 'old_client_id'));
		
		// Run the test.
		$this->assertIdentical($settings, $this->_model->update_basic_settings_from_input($settings));
	}
	
	
	public function test_update_mailing_list_settings_from_input__success()
	{
		// Shortcuts.
		$input = $this->_ee->input;
		
		// Dummy data.
		$cm_key			= '[CampaignMonitorKey]';
		$clean_cm_key	= sanitize_string($cm_key);
		
		$mailing_list_data = array(
			'mailing_list_id_1' => array(
				'checked'		=> 'mailing_list_id_1',
				'trigger_field'	=> 'group_id',
				'trigger_value'	=> '10',
				'custom_fields'	=> array($clean_cm_key => 'm_field_id_1')
			),
			'mailing_list_id_2' => array(
				'checked'		=> 'mailing_list_id_2',
				'trigger_field'	=> 'location',
				'trigger_value'	=> 'Cardiff'
			),
			'mailing_list_id_3' => array(
				'trigger_field'	=> '',
				'trigger_value'	=> '',
				'custom_fields'	=> array($clean_cm_key => '')
			),
			'mailing_list_id_4' => array(
				'trigger_field'	=> '',
				'trigger_value'	=> '',
				'custom_fields'	=> array($clean_cm_key => '')
			)
		);
		
		$mailing_lists = array(
			new Campaigner_mailing_list(array(
				'list_id'		=> 'mailing_list_id_1',
				'trigger_field'	=> 'group_id',
				'trigger_value'	=> '10',
				'custom_fields'	=> array(new Campaigner_custom_field(array('cm_key' => $cm_key, 'member_field_id' => 'm_field_id_1')))
			)),
			new Campaigner_mailing_list(array(
				'list_id'		=> 'mailing_list_id_2',
				'trigger_field'	=> 'location',
				'trigger_value'	=> 'Cardiff'
			))
		);
		
		$settings = new Campaigner_settings();
		$settings->set_mailing_lists($mailing_lists);
		
		// Expectations.
		$input->expectOnce('get_post', array('mailing_lists'));
		
		// Return values.
		$input->setReturnValue('get_post', $mailing_list_data, array('mailing_lists'));
		
		// Tests.
		$updated_settings = $this->_model->update_mailing_list_settings_from_input($settings);
		$this->assertIdentical($settings, $updated_settings);
		
		// Need to check the mailing lists separately. Bah.
		$updated_mailing_lists = $updated_settings->get_mailing_lists();
		$this->assertIdentical(count($mailing_lists), count($updated_mailing_lists));
		
		for ($count = 0; $count < count($mailing_lists); $count++)
		{
			$this->assertIdentical($mailing_lists[$count], $updated_mailing_lists[$count]);
		}
	}
	
	
	
	/* --------------------------------------------------------------
	 * API TESTS
	 * ------------------------------------------------------------ */
	
	public function test_make_api_call__api_connector_not_set()
	{
		try
		{
			$this->_model->make_api_call('METHOD', array(), 'ROOT_NODE');
			$this->fail();
		}
		catch (Exception $e)
		{
			$this->assertPattern('#api connector not set#i', $e->getMessage());
		}
	}
	
	
	public function test_get_clients_from_api__success()
	{
		// Dummy values.
		$client_id		= 'CLIENT_ID';
		$client_name	= 'CLIENT_NAME';
		$api_result 	= array('anyType' => array('Client' => array('ClientID' => $client_id, 'Name' => $client_name)));
		$clients		= array(new Campaigner_client(array('client_id' => $client_id, 'client_name' => $client_name)));
		
		// Set the API connector.
		$this->_model->set_api_connector($this->_api_connector);
		
		// Expectations.
		$this->_api_connector->expectOnce('userGetClients');
		
		// Return values.
		$this->_api_connector->setReturnValue('userGetClients', $api_result);
		
		// Tests.
		$this->assertIdentical($clients, $this->_model->get_clients_from_api());
	}
	
	
	public function test_get_clients_from_api__no_clients()
	{
		// Dummy values.
		$api_result	= array('anyType' => array('Client' => array()));
		
		// Set the API connector.
		$this->_model->set_api_connector($this->_api_connector);
		
		// Return values.
		$this->_api_connector->setReturnValue('userGetClients', $api_result);
		
		// Tests.
		$this->assertIdentical(array(), $this->_model->get_clients_from_api());
	}
	
	
	public function test_get_mailing_lists_from_api__success()
	{
		// Dummy values.
		$client_id	= 'ABC123';
		$list_id	= 'LIST_ID';
		$list_name	= 'LIST_NAME';
		
		$api_list_result = array(
			'anyType' => array(
				'List' => array(
					array('ListID' => $list_id, 'Name' => $list_name),
					array('ListID' => $list_id, 'Name' => $list_name)
				)
			)
		);
		
		$api_field_result = array('anyType' => array('ListCustomField' => array()));
		
		$lists = array(
			new Campaigner_api_mailing_list(array('id' => $list_id, 'name' => $list_name)),
			new Campaigner_api_mailing_list(array('id' => $list_id, 'name' => $list_name))
		);
		
		// Set the API connector.
		$this->_model->set_api_connector($this->_api_connector);
		
		// Expectations.
		$this->_api_connector->expectOnce('clientGetLists', array($client_id));
		$this->_api_connector->expectCallCount('listGetCustomFields', count($lists));
		
		// Return values.
		$this->_api_connector->setReturnValue('clientGetLists', $api_list_result);
		$this->_api_connector->setReturnValue('listGetCustomFields', $api_field_result);
		
		// Tests.
		$this->assertIdentical($lists, $this->_model->get_mailing_lists_from_api($client_id));
	}
	
	
	public function test_get_mailing_lists_from_api__no_mailing_lists()
	{
		// Dummy values.
		$client_id	= 'ABC123';
		$api_result	= array('anyType' => array('List' => array()));
		
		// Set the API connector.
		$this->_model->set_api_connector($this->_api_connector);
		
		// Return values.
		$this->_api_connector->setReturnValue('clientGetLists', $api_result);
		
		// Tests.
		$this->assertIdentical(array(), $this->_model->get_mailing_lists_from_api($client_id));
	}
	
	
	public function xtest_get_mailing_list_custom_fields_from_api__success()
	{
		// Dummy values.
		$list_id = 'ABC123';
		
		$api_result = array(
			'anyType' => array(
				'ListCustomField' => array(
					array(
						'DataType'		=> 'Text',
						'FieldName'		=> 'Example Text Field',
						'FieldOptions'	=> array(),
						'Key'			=> '[ExampleTextField]'
					),
					array(
						'DataType'		=> 'Number',
						'FieldName'		=> 'Example Number Field',
						'FieldOptions'	=> array(),
						'Key'			=> '[ExampleNumberField]'
					),
					array(
						'DataType'		=> 'MultiSelectOne',
						'FieldName'		=> 'Example Multi-Select One Field',
						'FieldOptions'	=> array('Red', 'Green', 'Blue'),
						'Key'			=> '[ExampleMultiSelectOneField]'
					),
					array(
						'DataType'		=> 'MultiSelectMany',
						'FieldName'		=> 'Example Multi-Select Many Field',
						'FieldOptions'	=> array('Red', 'Green', 'Blue'),
						'Key'			=> '[ExampleMultiSelectManyField]'
					)
				)
			)
		);
		
		$custom_fields = array();
		
		foreach ($api_result['anyType']['ListCustomField'] AS $custom_field_data)
		{
			$custom_fields[] = new Campaigner_api_custom_field(array(
				'type'		=> $custom_field_data['DataType'],
				'name'		=> $custom_field_data['FieldName'],
				'options'	=> $custom_field_data['FieldOptions'],
				'key'		=> $custom_field_data['Key']
			));
		}
		
		// Set the API connector.
		$this->_model->set_api_connector($this->_api_connector);
		
		// Expectations.
		$this->_api_connector->expectOnce('listGetCustomFields', array($list_id));
		
		// Return values.
		$this->_api_connector->setReturnValue('listGetCustomFields', $api_result);
		
		// Tests.
		$this->assertIdentical($custom_fields, $this->_model->get_mailing_list_custom_fields_from_api($list_id));
	}
	
	
	public function test_get_mailing_list_custom_fields_from_api__no_custom_fields()
	{
		// Dummy values.
		$list_id = 'ABC123';
		$api_result = array('anyType' => array('ListCustomField' => array()));
		
		// Set the API connector.
		$this->_model->set_api_connector($this->_api_connector);
		
		// Return values.
		$this->_api_connector->setReturnValue('listGetCustomFields', $api_result);
		
		// Tests.
		$this->assertIdentical(array(), $this->_model->get_mailing_list_custom_fields_from_api($list_id));
	}
	
	
	
	/* --------------------------------------------------------------
	 * OBSOLETE TESTS
	 * --------------------------------------------------------------
	 * The methods referenced by these methods have since been made
	 * private. The tests are included here for convenience, in case
	 * they are required for future testing and debugging.
	 * ------------------------------------------------------------ */
	
	public function xtest_validate_api_response__valid()
	{
		// Dummy values.
		$api_response = array(
			'anyType' => array('List' => array('ListID' => '123456', 'Name' => 'List Name'))
		);
		
		// Tests. If no exception is thrown, we're good.
		$this->assertIdentical(TRUE, $this->_model->validate_api_response($api_response), 'List');
	}
	
	
	public function xtest_validate_api_response__missing_root_node()
	{
		// Dummy values.
		$root_node = 'ROOT_NODE';
		$api_response = array('anyType' => array('List' => array('ListID' => '123456', 'Name' => 'List Name')));
		
		// Tests.
		try
		{
			$this->_model->validate_api_response($api_response, $root_node);
			$this->fail('Expected exception when validating API response.');
		}
		catch (Exception $e)
		{
			$this->assertIdentical(0, $e->getCode());
			$this->assertPattern('/' .$root_node .'/', $e->getMessage());
		}
	}
	
	
	public function xtest_validate_api_response__invalid_structure()
	{
		// Dummy values.
		$root_node		= 'ROOT_NODE';
		$api_response	= array('anyType' => array('NodeId' => 'NODE_ID', 'NodeName' => 'NODE_NAME'));
		
		// Tests.
		try
		{
			$this->_model->validate_api_response($api_response, $root_node);
			$this->fail('Expected exception when validating API response.');
		}
		catch (Exception $e)
		{
			$this->assertIdentical(0, $e->getCode());
			$this->assertPattern('/' .$root_node .'/', $e->getMessage());
		}
	}
	
	
	public function xtest_validate_api_response__unknown_error()
	{
		// Dummy values.
		$api_response = array();
		
		// Tests.
		try
		{
			$this->_model->validate_api_response($api_response);
			$this->fail('Expected exception when validating API response.');
		}
		catch (Exception $e)
		{
			$this->assertIdentical(0, $e->getCode());
		}
	}
	
	
	public function xtest_validate_api_response__known_error()
	{
		// Dummy values.
		$error_code 	= 100;
		$error_message	= 'ERROR_MESSAGE';
		
		$api_response = array(
			'anyType' => array(
				'Code'		=> $error_code,
				'Message'	=> $error_message
			)
		);
		
		// Tests.
		try
		{
			$this->_model->validate_api_response($api_response);
		}
		catch (Exception $e)
		{
			$this->assertIdentical($error_code, $e->getCode());
			$this->assertIdentical($error_message, $e->getMessage());
		}
	}
	
	
	public function xtest_fix_api_response__no_fix_required()
	{
		// Dummy values.
		$root_node = 'Root';
		
		$api_response = array(
			'anyType' => array(
				$root_node => array(
					array('NodeId' => 'NODE_ID', 'NodeName' => 'NODE_NAME'),
					array('NodeId' => 'NODE_ID', 'NodeName' => 'NODE_NAME')
				)
			)
		);
		
		// Tests.
		$this->assertIdentical($api_response, $this->_model->fix_api_response($api_response, $root_node));
	}
	
	
	public function xtest_fix_api_response__fix_required()
	{
		// Dummy values.
		$root_node = 'Root';
		
		$api_response = array('anyType' => array($root_node => array('NodeId' => 'NODE_ID', 'NodeName' => 'NODE_NAME')));
		
		$fixed_response = array(
			'anyType' => array(
				$root_node => array(array('NodeId' => 'NODE_ID', 'NodeName' => 'NODE_NAME'))
			)
		);
		
		// Tests.
		$this->assertIdentical($fixed_response, $this->_model->fix_api_response($api_response, $root_node));
	}
	
	
	public function test_get_member_fields__success()
	{
		// Dummy values.
		$db_result	= $this->_get_mock('db_query');
		$db_rows	= array(
			array('m_field_id' => '10', 'm_field_label' => 'Name', 'm_field_list_items' => '', 'm_field_type' => 'text'),
			array('m_field_id' => '20', 'm_field_label' => 'Email', 'm_field_list_items' => '', 'm_field_type' => 'text'),
			array('m_field_id' => '30', 'm_field_label' => 'Address', 'm_field_list_items' => '', 'm_field_type' => 'textarea'),
			array('m_field_id' => '40', 'm_field_label' => 'Gender', 'm_field_list_items' => "Male\nFemale", 'm_field_type' => 'select')
		);
		
		$member_fields	= array();
		$dummy_label	= 'Label';
		
		$standard_member_fields = array(
			array('id' => 'group_id', 'label' => $dummy_label, 'options' => array(), 'type' => 'text'),
			array('id' => 'location', 'label' => $dummy_label, 'options' => array(), 'type' => 'text'),
			array('id' => 'occupation', 'label' => $dummy_label, 'options' => array(), 'type' => 'text'),
			array('id' => 'screen_name', 'label' => $dummy_label, 'options' => array(), 'type' => 'text'),
			array('id' => 'url', 'label' => $dummy_label, 'options' => array(), 'type' => 'text'),
			array('id' => 'username', 'label' => $dummy_label, 'options' => array(), 'type' => 'text')
		);
		
		foreach ($standard_member_fields AS $member_field_data)
		{
			$member_fields[] = new EI_member_field($member_field_data);
		}
		
		foreach ($db_rows AS $db_row)
		{
			$member_field = new EI_member_field();
			$member_field->populate_from_db_array($db_row);
			
			$member_fields[] = $member_field;
		}
		
		// Expectations.
		$this->_ee->db->expectOnce('select');
		$this->_ee->db->expectOnce('get', array('member_fields'));
		$db_result->expectOnce('result_array');
		
		// Return values.
		$this->_ee->db->setReturnReference('get', $db_result);
		$this->_ee->lang->setReturnValue('line', $dummy_label);
		$db_result->setReturnValue('result_array', $db_rows);
		
		// Tests.
		$this->assertIdentical($member_fields, $this->_model->get_member_fields());
	}
	
	
	public function test_get_member_fields__no_custom_member_fields()
	{
		// Dummy values.
		$db_result		= $this->_get_mock('db_query');
		$db_rows		= array();
		$member_fields	= array();
		$dummy_label	= 'Label';

		$standard_member_fields = array(
			array('id' => 'group_id', 'label' => $dummy_label, 'options' => array(), 'type' => 'text'),
			array('id' => 'location', 'label' => $dummy_label, 'options' => array(), 'type' => 'text'),
			array('id' => 'occupation', 'label' => $dummy_label, 'options' => array(), 'type' => 'text'),
			array('id' => 'screen_name', 'label' => $dummy_label, 'options' => array(), 'type' => 'text'),
			array('id' => 'url', 'label' => $dummy_label, 'options' => array(), 'type' => 'text'),
			array('id' => 'username', 'label' => $dummy_label, 'options' => array(), 'type' => 'text')
		);

		foreach ($standard_member_fields AS $member_field_data)
		{
			$member_fields[] = new EI_member_field($member_field_data);
		}

		// Return values.
		$this->_ee->db->setReturnReference('get', $db_result);
		$this->_ee->lang->setReturnValue('line', $dummy_label);
		$db_result->setReturnValue('result_array', $db_rows);

		// Tests.
		$this->assertIdentical($member_fields, $this->_model->get_member_fields());
	}
	
}


/* End of file		: test_campaigner_model.php */
/* File location	: third_party/campaigner/tests/test_campaigner_model.php */