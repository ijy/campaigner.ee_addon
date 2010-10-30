<?php if ( ! defined('BASEPATH')) exit('Direct script access is not permitted.');

/**
 * Automatically add your EE members to Campaign Monitor mailing lists.
 * 
 * @author			: Stephen Lewis <addons@experienceinternet.co.uk>
 * @copyright		: Experience Internet
 * @package			: Campaigner
 */

require_once PATH_THIRD .'campaigner/classes/campaigner_api_error' .EXT;
require_once PATH_THIRD .'campaigner/libraries/CMBase' .EXT;

class Campaigner_ext {
	
	/* --------------------------------------------------------------
	 * PRIVATE PROPERTIES
	 * ------------------------------------------------------------ */
	
	/**
	 * ExpressionEngine object reference.
	 *
	 * @access	private
	 * @var		object
	 */
	private $_ee;
	
	
	/* --------------------------------------------------------------
	 * PUBLIC PROPERTIES
	 * ------------------------------------------------------------ */
	
	/**
	 * Description.
	 *
	 * @access	public
	 * @var		string
	 */
	public $description;
	
	/**
	 * Documentation URL.
	 *
	 * @access	public
	 * @var		string
	 */
	public $docs_url;
	
	/**
	 * Extension name.
	 *
	 * @access	public
	 * @var		string
	 */
	public $name;
	
	/**
	 * Settings.
	 *
	 * @access	public
	 * @var		array
	 */
	public $settings = array();
	
	/**
	 * Does this extension have a settings screen?
	 *
	 * @access	public
	 * @var		string
	 */
	public $settings_exist = 'y';
	
	/**
	 * Version.
	 *
	 * @access	public
	 * @var		string
	 */
	public $version;
	
	
	
	/* --------------------------------------------------------------
	 * PUBLIC METHODS
	 * ------------------------------------------------------------ */

	/**
	 * Class constructor.
	 *
	 * @access	public
	 * @param	array 		$settings		Previously-saved extension settings.
	 * @return	void
	 */
	public function __construct($settings = array())
	{
		$this->_ee =& get_instance();
		
		// Load the model.
		$this->_ee->load->add_package_path(PATH_THIRD .'campaigner/');
		$this->_ee->load->model('campaigner_model');
		
		// Shortcut.
		$model = $this->_ee->campaigner_model;
		
		// Load the language file.
		$this->_ee->lang->loadfile('campaigner');
		
		// Set the instance properties.
		$this->description	= $this->_ee->lang->line('extension_description');
		$this->docs_url		= 'http://experienceinternet.co.uk/software/campaigner/';
		$this->name			= $this->_ee->lang->line('extension_name');
		$this->settings		= $settings;
		$this->version		= $model->get_package_version();
		
		// Is the extension installed?
		if ($model->get_installed_extension_version())
		{
			// Load the settings from the database, and update them with any input data.
			$this->settings = $model->update_extension_settings_from_input($model->get_extension_settings());
			
			// If the API key has been set, initialise the API connector.
			if ($this->settings->get_api_key())
			{
				$model->set_api_connector(new CampaignMonitor($this->settings->get_api_key()));
			}
		}
	}
	
	
	/**
	 * Activates the extension.
	 *
	 * @access	public
	 * @return	void
	 */
	public function activate_extension()
	{
		$this->_ee->campaigner_model->activate_extension();
	}
	
	
	/**
	 * Disables the extension.
	 *
	 * @access	public
	 * @return	void
	 */
	public function disable_extension()
	{
		$this->_ee->campaigner_model->disable_extension();
	}
	
	
	/**
	 * Displays the 'settings' page.
	 *
	 * @access	public
	 * @return	string
	 */
	public function display_settings()
	{
		// If this isn't an AJAX request, just display the "base" settings form.
		if ( ! isset($_SERVER['HTTP_X_REQUESTED_WITH']) OR strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest')
		{
			return $this->display_settings_base();
		}
		
		// Handle AJAX requests.
		switch (strtolower($this->_ee->input->get('request')))
		{
			case 'get_clients':
				$this->_ee->output->send_ajax_response($this->display_settings_clients());
				break;
				
			case 'get_mailing_lists':
				$this->_ee->output->send_ajax_response($this->display_settings_mailing_lists());
				break;
			
			default:
				// Unknown request. Do nothing.
				break;
		}
	}
	
	
	/**
	 * Displays the "base" settings form.
	 *
	 * @access	public
	 * @return	string
	 */
	public function display_settings_base()
	{
		// Shortcuts.
		$cp		= $this->_ee->cp;
		$lang	= $this->_ee->lang;
		$model	= $this->_ee->campaigner_model;
		
		$lower_package_name = strtolower($model->get_package_name());
		
		// View variables.
		$view_vars = array(
			'action_url'		=> 'C=addons_extensions' .AMP .'M=save_extension_settings',
			'cp_page_title'		=> $lang->line('extension_name'),
			'hidden_fields'		=> array('file' => $lower_package_name),
			'settings'			=> $this->settings		// Loaded in the constructor.
		);
		
		// Theme URL.
		$theme_url = $model->get_theme_url();
		
		// Add the CSS.
		$cp->add_to_foot('<link media="screen, projection" rel="stylesheet" type="text/css" href="' .$theme_url .'css/cp.css" />');

		// Load the JavaScript library, and set a shortcut.
		$this->_ee->load->library('javascript');
		$js = $this->_ee->javascript;
		
		$cp->add_to_foot('<script type="text/javascript" src="' .$theme_url .'js/cp.js"></script>');

		// JavaScript globals.
		$js->set_global('campaigner.lang', array(
				'missingApiKey' 	=> $lang->line('msg_missing_api_key'),
				'missingClientId'	=> $lang->line('msg_missing_client_id')
		));
		
		// $js->set_global('campaigner.memberFields', $js->generate_json($member_fields->to_array()));

		$js->set_global('campaigner.ajaxUrl',
			str_replace(AMP, '&', BASE) .'&C=addons_extensions&M=extension_settings&file=' .$lower_package_name
		);

		// Compile the JavaScript.
		$js->compile();
		
		// Load the view.
		return $this->_ee->load->view('settings', $view_vars, TRUE);
	}
	
	
	/**
	 * Displays the "clients" settings form fragment.
	 *
	 * @access	public
	 * @return	string
	 */
	public function display_settings_clients()
	{
		try
		{
			$view_vars = array(
				'clients'	=> $this->_ee->campaigner_model->get_clients_from_api(),
				'settings'	=> $this->settings
			);
			
			$view_name = '_clients';
		}
		catch (Exception $e)
		{
			// Something went wrong with the API call.
			$view_vars = array('api_error' => new Campaigner_api_error(array(
				'code'		=> $e->getCode(),
				'message'	=> $e->getMessage()
			)));
			
			$view_name = '_clients_api_error';
		}
		
		return $this->_ee->load->view($view_name, $view_vars, TRUE);
	}
	
	
	/**
	 * Displays the "mailing lists" settings form fragment.
	 *
	 * @access	public
	 * @return	string
	 */
	public function display_settings_mailing_lists()
	{
		try
		{
			$view_vars = array(
				'mailing_lists'	=> $this->_ee->campaigner_model->get_mailing_lists_from_api($this->settings->get_client_id()),
				'member_fields'	=> $this->_ee->campaigner_model->get_member_fields(),
				'settings'		=> $this->settings
			);
		
			$view_name = '_mailing_lists';
		}
		catch (Exception $e)
		{
			$view_vars = array('api_error' => new Campaigner_api_error(array(
					'code'		=> $e->getCode(),
					'message'	=> $e->getMessage()
			)));
			
			$view_name = '_mailing_lists_api_error';
		}
		
		return $this->_ee->load->view($view_name, $view_vars, TRUE);
	}
	
	
	/**
	 * Saves the extension settings.
	 *
	 * @access	public
	 * @return	void
	 */
	public function save_settings()
	{
		// Save the settings.
		try
		{
			$this->_ee->campaigner_model->save_extension_settings($this->settings);
			$this->_ee->session->set_flashdata('message_success', $this->_ee->lang->line('msg_settings_saved'));
		}
		catch (Exception $e)
		{
			$this->_ee->session->set_flashdata(
				'message_failure',
				$this->_ee->lang->line('msg_settings_not_saved') .' (' .$e->getMessage() .')'
			);
		}
	}
	
	
	/**
	 * Displays the extension settings form.
	 *
	 * @access	public
	 * @return	string
	 */
	public function settings_form()
	{
		// Load our glamorous assistants.
		$this->_ee->load->helper('form');
		$this->_ee->load->library('table');
		
		// Define the navigation.
		$base_url = BASE .AMP .'C=addons_extensions' .AMP .'M=extension_settings' .AMP .'file=campaigner' .AMP .'tab=';
		
		$this->_ee->cp->set_right_nav(array(
			'nav_settings'	=> $base_url .'settings',
			'nav_errors'	=> $base_url .'errors',
			'nav_help'		=> $base_url .'help'
		));
		
		switch ($this->_ee->input->get('tab'))
		{
			case 'errors':
				return $this->display_errors();
				break;
				
			case 'help':
				return $this->display_help();
				break;
			
			case 'settings':
			default:
				return $this->display_settings();
				break;
		}
	}
	
	
	/**
	 * Updates the extension.
	 *
	 * @access	public
	 * @param	string		$current_version	The current version.
	 * @return	bool
	 */
	public function update_extension($current_version = '')
	{
		return $this->_ee->campaigner_model->update_extension($current_version, $this->version);
	}
	
	
	
	/* --------------------------------------------------------------
	 * HOOK HANDLERS
	 * ------------------------------------------------------------ */

	/**
	 * Handles the `example_hook` hook.
	 *
	 * @see		http://expressionengine.com/developers/extension_hooks/on_example_hook/
	 * @access	public
	 * @param 	string 		$example_data		Data passed to the hook handler.
	 * @return	void
	 */
	public function on_example_hook($example_data = '')
	{
		// Check for previous handlers.
		$example_data = $this->_ee->extensions->last_call
			? $this->_ee->extensions->last_call
			: $example_data;
	}
	
}

/* End of file		: ext.campaigner.php */
/* File location	: third_party/campaigner/ext.campaigner.php */