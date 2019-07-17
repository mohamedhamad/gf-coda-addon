<?php
// don't load directly
if ( ! defined( 'ABSPATH' ) ) exit;
GFForms::include_feed_addon_framework();

class GravityFormsCodaFeedAddOn extends GFFeedAddOn
{

    protected $_version = GF_CODA_VERSION;
	protected $_min_gravityforms_version = '2.4.8.5';
	protected $_slug = GF_CODA_SLUG;
	protected $_path = GF_CODA_PATH;
	protected $_full_path = __FILE__;
	protected $_title = 'GravityForms Coda.io AddOn';
	protected $_short_title = 'Coda.io';
	
	private static $_instance = null;
	public $api = null;
	
	/**
	 * Defines the capabilities needed for the Mailchimp Add-On
	 *
	 * @since  3.0
	 * @access protected
	 * @var    array $_capabilities The capabilities needed for the Add-On
	 */
	protected $_capabilities = array( 'gravityforms_coda', 'gravityforms_coda_uninstall' );

	/**
	 * Defines the capability needed to access the Add-On settings page.
	 *
	 * @since  3.0
	 * @access protected
	 * @var    string $_capabilities_settings_page The capability needed to access the Add-On settings page.
	 */
	protected $_capabilities_settings_page = 'gravityforms_coda';

	/**
	 * Defines the capability needed to access the Add-On form settings page.
	 *
	 * @since  3.0
	 * @access protected
	 * @var    string $_capabilities_form_settings The capability needed to access the Add-On form settings page.
	 */
	protected $_capabilities_form_settings = 'gravityforms_coda';
   
	/**
	 * Defines the Coda list field tag name.
	 *
	 * @since  3.7
	 * @access protected
	 * @var    string $merge_var_name The MailChimp list field tag name; used by gform_mailchimp_field_value.
	 */
	protected $merge_var_name = '';
	protected $merge_fields = array();
	
	/**
	 * Get an instance of this class.
	 *
	 * @return GravityFormsCodaFeedAddOn
	 */
	public static function get_instance()
	{
		if ( self::$_instance == null )
		{
			self::$_instance = new self;
		}
		return self::$_instance;
	}
	
	/**
	 * Autoload the required libraries.
	 *
	 * @since  4.0
	 * @access public
	 *
	 * @uses GFAddOn::is_gravityforms_supported()
	 */
	public function pre_init() {

		parent::pre_init();

		if ( $this->is_gravityforms_supported() ) {

			// Load the MailChimp API library.
			if ( ! class_exists( 'CodaPHP' ) ) {
				require_once ('vendor/autoload.php');
			}

		}

	}
	
    
    /**
	 * Plugin starting point. Handles hooks, loading of language files and PayPal delayed payment support.
	 */
	public function init()
	{
        parent::init();
        add_action( 'gform_pre_submission',   array($this, 'gform_pre_submission'));
		add_filter( 'gform_confirmation',     array($this, 'gform_confirmation') , 10, 4 );
    }


	/**
	 * Remove unneeded settings.
	 *
	 * @since  4.0
	 * @access public
	 */
	public function uninstall() {

		parent::uninstall();

		GFCache::delete( 'coda_plugin_settings' );
		delete_option( 'gf_coda_settings' );
		delete_option( 'gf_coda_version' );

	}

	/**
	 * Register needed styles.
	 *
	 * @since  4.0
	 * @access public
	 *
	 * @return array
	 */
	public function styles() {

		$styles = array(
			array(
				'handle'  => $this->_slug . '_form_settings',
				'src'     => $this->get_base_url() . '/css/style.css',
				'version' => $this->_version,
				'enqueue' => array(
					array( 'admin_page' => array( 'form_settings' ) ),
				),
			),
		);

		return array_merge( parent::styles(), $styles );

	}

	// # PLUGIN SETTINGS -----------------------------------------------------------------------------------------------
	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				'description' => '<p>' .
					sprintf(
						esc_html__( 'MailChimp makes it easy to send email newsletters to your customers, manage your subscriber lists, and track campaign performance. Use Gravity Forms to collect customer information and automatically add it to your MailChimp subscriber list. If you don\'t have a MailChimp account, you can %1$ssign up for one here.%2$s', 'gravityforms-coda' ),
						'<a href="http://www.coda.io/" target="_blank">', '</a>'
					)
					. '</p>',
				'fields'      => array(
					array(
						'name'              => 'apiKey',
						'label'             => esc_html__( 'Coda.io API Key', 'gravityforms-coda' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'initialize_api' ),
					),
				),
			),
		);

	}

	// # FEED SETTINGS -------------------------------------------------------------------------------------------------

	/**
	 * Configures the settings which should be rendered on the feed edit page.
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @return array
	 */
	public function feed_settings_fields() {

		$settings = array(
			array(
				'title'  => esc_html__( 'Coda.io Feed Settings', 'gravityforms-coda' ),
				'fields' => array(
					array(
						'name'     => 'feedName',
						'label'    => esc_html__( 'Name', 'gravityforms-coda' ),
						'type'     => 'text',
						'required' => true,
						'class'    => 'medium',
						'tooltip'  => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Name', 'gravityforms-coda' ),
							esc_html__( 'Enter a feed name to uniquely identify this setup.', 'gravityforms-coda' )
						),
					),
					array(
						'name'     => 'codaDoc',
						'label'    => esc_html__( 'Coda Docs', 'gravityforms-coda' ),
						'type'     => 'coda_doc',
						'required' => true,
						'tooltip'  => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Coda Docs', 'gravityforms-coda' ),
							esc_html__( 'Select the Coda Doc you would like to add to.', 'gravityforms-coda' )
						),
					),
				),
			),
			array(
				'dependency' => 'codaDoc',
				'fields'     => array(
					array(
						'name'     => 'codaTable',
						'label'    => esc_html__( 'Doc Tables', 'gravityforms-coda' ),
						'type'     => 'coda_doc_tables',
						'required' => true,
						'tooltip'  => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Coda Doc Tables', 'gravityforms-coda' ),
							esc_html__( 'Select the Coda Doc Table you would like to add to.', 'gravityforms-coda' )
						),
					),
				),
			),
			array(
				'title'  => esc_html__( 'Doc Table Columns', 'gravityforms-coda' ),
				'dependency' => 'codaTable',
				'fields'     => array(
					array(
						'name'      => 'mappedFields',
						'label'     => esc_html__( 'Map Fields', 'gravityforms-coda' ),
						'type'      => 'field_map',
						'field_map' => $this->merge_vars_field_map(),
						'tooltip'   => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Map Fields', 'gravityforms-coda' ),
							esc_html__( 'Associate your Coda Doc Table columns to the appropriate Gravity Form fields by selecting the appropriate form field from the list.', 'gravityforms-coda' )
						),
					),
					array(
						'name'    => 'optinCondition',
						'label'   => esc_html__( 'Conditional Logic', 'gravityforms-coda' ),
						'type'    => 'feed_condition',
						'tooltip' => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Conditional Logic', 'gravityforms-coda' ),
							esc_html__( 'When conditional logic is enabled, form submissions will only be exported to Coda when the conditions are met. When disabled all form submissions will be exported.', 'gravityforms-coda' )
						),
					),
					
					array( 'type' => 'save' ),
				)
			),
		);

		// Get currently selected list.
		$doc = $this->get_setting( 'codaDoc' );
		$table = $this->get_setting( 'codaTable' );


		return $settings;

	}

	/**
	 * Return an array of MailChimp list fields which can be mapped to the Form fields/entry meta.
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @return array
	 */
	public function merge_vars_field_map() {
		// Initialize field map array.
		$field_map = array();
		$doc = $this->get_setting( 'codaDoc' );
		$table = $this->get_setting( 'codaTable' );

		// If unable to initialize API, return field map.
		if ( ! $this->initialize_api() ) {
			return $field_map;
		}
		if(empty($doc) || empty($table)){
			return $field_map;
		}

		try {
			// Get lists.
			$cols = $this->api->listColumns($doc, $table);
		} catch ( Exception $e ) {

			// Log that contact lists could not be obtained.
			$this->log_error( __METHOD__ . '(): Could not retrieve Doc; ' . $e->getMessage() );

			// Display error message.
			printf( esc_html__( 'Could not load Doc. %sError: %s', 'gravityforms-coda' ), '<br/>', $e->getMessage() );

			return;
		}

		// If no lists were found, display error message.
		if ( 0 === count($cols['items']) ) {

			// Log that no lists were found.
			$this->log_error( __METHOD__ . '(): Could not load Coda.io Doc Table Columns; no columns found.' );

			// Display error message.
			printf( esc_html__( 'Could not load Coda.io Doc Table Columns. %sError: %s', 'gravityforms-coda' ), '<br/>', esc_html__( 'No Doc Table Columns found.', 'gravityforms-coda' ) );

			return;

		}

		$this->log_debug( __METHOD__ . '(): Number of Docs: ' . count( $cols['items'] ) );

		// $options = array();
		foreach ( $cols['items'] as $col ) {
			$is_cal = isset($col['calculated']) ? $col['calculated'] : false;
			$is_displayed = isset($col['display']) ? $col['display'] : true;
			$is_creator = $col['name']=='Creator' ? true : false;

			$field_type = null;
			if( 'email'===strtolower($col['name']) ){
				$field_type = array( 'email', 'hidden' );
			}
			if(!$is_cal && !$is_creator){
				$field_map[ $col['id'] ] = array(
					'name'       => $col['id'],
					'label'      => $col['name'],
					'required'   => false,
					'field_type' => $field_type,
				);
			}
			
		}

		return $field_map;
	}

	/**
	 * Define the markup for the coda_doc type field.
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @param array $field The field properties.
	 * @param bool  $echo  Should the setting markup be echoed. Defaults to true.
	 *
	 * @return string
	 */
	public function settings_coda_doc( $field, $echo = true ) {

		// Initialize HTML string.
		$html = '';

		// If API is not initialized, return.
		if ( ! $this->initialize_api() ) {
			return $html;
		}

		try {
			// Get lists.
			$docs = $this->api->listDocs(  );
		} catch ( Exception $e ) {

			// Log that contact lists could not be obtained.
			$this->log_error( __METHOD__ . '(): Could not retrieve Doc; ' . $e->getMessage() );

			// Display error message.
			printf( esc_html__( 'Could not load Doc. %sError: %s', 'gravityforms-coda' ), '<br/>', $e->getMessage() );

			return;
		}

		// If no lists were found, display error message.
		if ( 0 === count($docs['items']) ) {

			// Log that no lists were found.
			$this->log_error( __METHOD__ . '(): Could not load Coda.io Docs; no lists found.' );

			// Display error message.
			printf( esc_html__( 'Could not load Coda.io Docs. %sError: %s', 'gravityforms-coda' ), '<br/>', esc_html__( 'No Docs found.', 'gravityforms-coda' ) );

			return;

		}

		// Log number of lists retrieved.
		$this->log_debug( __METHOD__ . '(): Number of Docs: ' . count( $docs['items'] ) );

		// Initialize select options.
		$options = array(
			array(
				'label' => esc_html__( 'Select a Document', 'gravityforms-coda' ),
				'value' => '',
			),
		);

		// Loop through Coda Docs.
		foreach ( $docs['items'] as $doc ) {

			// Add list to select options.
			$options[] = array(
				'label' => esc_html( $doc['name'] ),
				'value' => esc_attr( $doc['id'] ),
			);

		}

		// Add select field properties.
		$field['type']     = 'select';
		$field['choices']  = $options;
		$field['onchange'] = 'jQuery(this).parents("form").submit();';

		// Generate select field.
		$html = $this->settings_select( $field, false );

		if ( $echo ) {
			echo $html;
		}

		return $html;

	}

	public function settings_coda_doc_tables( $field, $echo = true ){
		// Initialize HTML string.
		$html = '';
		$doc_id = $this->get_setting( 'codaDoc' );

		// If API is not initialized, return.
		if ( ! $this->initialize_api() ) {
			return $html;
		}

		if(empty($doc_id)){
			return $html;
		}

		try {
			// Get lists.
			$tables = $this->api->listTables($doc_id);
			
		} catch ( Exception $e ) {

			// Log that contact lists could not be obtained.
			$this->log_error( __METHOD__ . '(): Could not retrieve Coda Doc Tables; ' . $e->getMessage() );

			// Display error message.
			printf( esc_html__( 'Could not load Coda Doc Tables. %sError: %s', 'gravityforms-coda' ), '<br/>', $e->getMessage() );

			return;
		}

		// If no tables were found, display error message.
		if ( 0 === count($tables['items']) ) {

			// Log that no lists were found.
			$this->log_error( __METHOD__ . '(): Could not load Coda.io Doc Tables; no tables found.' );

			// Display error message.
			printf( esc_html__( 'Could not load Coda.io Doc Tables. %sError: %s', 'gravityforms-coda' ), '<br/>', esc_html__( 'No Doc Tables found.', 'gravityforms-coda' ) );

			return;

		}

		// Log number of lists retrieved.
		$this->log_debug( __METHOD__ . '(): Number of Doc Tables: ' . count( $tables['items'] ) );

		// Initialize select options.
		$options = array(
			array(
				'label' => esc_html__( 'Select a Table', 'gravityforms-coda' ),
				'value' => '',
			),
		);

		// Loop through Coda Docs.
		foreach ( $tables['items'] as $table ) {

			// Add list to select options.
			$options[] = array(
				'label' => esc_html( $table['name'] ),
				'value' => esc_attr( $table['id'] ),
			);

		}
		// Add select field properties.
		$field['type']     = 'select';
		$field['choices']  = $options;
		$field['onchange'] = 'jQuery(this).parents("form").submit();';

		// Generate select field.
		$html = $this->settings_select( $field, false );

		if ( $echo ) {
			echo $html;
		}
		return $html;
	}

    // # FEED PROCESSING -----------------------------------------------------------------------------------------------

	/**
	 * Process the feed e.g. subscribe the user to a list.
	 *
	 * @param array $feed The feed object to be processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form The form object currently being processed.
	 *
	 * @return bool|void
	 */
	public function process_feed( $feed, $entry, $form )
	{
		$doc = $feed['meta']['codaDoc'];
		$table = $feed['meta']['codaTable'];

		// Log that we are processing feed.
		$this->log_debug( __METHOD__ . '(): Processing feed.' );

		// If unable to initialize API, log error and return.
		if ( ! $this->initialize_api() ) {
			$this->add_feed_error( esc_html__( 'Unable to process feed because API could not be initialized.', 'gravityforms-coda' ), $feed, $entry, $form );
			return $entry;
		}

		// Get field map values.
		$field_map = $this->get_field_map_fields( $feed, 'mappedFields' );

		// Loop through field map.
		$row = array();
		foreach ( $field_map as $name => $field_id ) {
			// Get field object.
			$field = GFFormsModel::get_field( $form, $field_id );

			// Get field value.
			$field_value = $this->get_field_value( $form, $entry, $field_id );

			$row[$name] = $field_value;
		}

		try {

			?>
			<pre><?php var_dump($row); ?></pre>
			<?php
			$result = $this->api->insertRows($doc, $table, $row); // Insert/updates a row in a table
			?>
			<pre><?php var_dump($result); ?></pre>
			<?php
		} catch ( Exception $e ) {

			// Log that contact lists could not be obtained.
			$this->log_error( __METHOD__ . '(): Could not insert entry in to Coda Doc Table; ' . $e->getMessage() );

			// Display error message.
			printf( esc_html__( 'Could not insert entry in to Coda Doc Table' ), '<br/>', $e->getMessage() );

			return;
		}
		

    }
    

    /***********************************************/
	/************ Gravity Forms Hooks **************/
	/***********************************************/
	public function gform_pre_submission($form)
	{

    }

    public function gform_confirmation( $confirmation, $form, $entry, $ajax )
	{

    }

    // # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------

	/**
	 * Creates a custom page for this add-on.
	 */
	// public function plugin_page()
	// {
	// 	echo 'This page appears in the Forms menu';
    // }
    
    
	

	public function initialize_api( $api_key = null ) {
		// If API is already initialized, return true.
		if ( ! is_null( $this->api ) ) {
			return true;
		}

		// Get the API key.
		if ( rgblank( $api_key ) ) {
			$api_key = $this->get_plugin_setting( 'apiKey' );
		}

		// If the API key is blank, do not run a validation check.
		if ( rgblank( $api_key ) ) {
			return null;
		}

		// $coda = new GF_MailChimp_API( $api_key );
		$coda = new CodaPHP\CodaPHP($api_key);

		try {

			// Retrieve account information.
			$coda->listDocs();

			// Assign API library to class.
			$this->api = $coda;

			// Log that authentication test passed.
			$this->log_debug( __METHOD__ . '(): Coda.io successfully authenticated.' );

			return true;

		} catch ( Exception $e ) {

			// Log that authentication test failed.
			$this->log_error( __METHOD__ . '(): Unable to authenticate with Coda; '. $e->getMessage() );

			return false;

		}
	}
    
    

    /**
	 * Configures which columns should be displayed on the feed list page.
	 *
	 * @return array
	 */
	public function feed_list_columns()
	{
		return array(
			'feedName'  => esc_html__( 'Name', 'gf-coda-feed-addon' ),
			//'mytextbox' => esc_html__( 'My Textbox', 'gf-coda-feed-addon' ),
		);
	}

	/**
	 * Format the value to be displayed in the mytextbox column.
	 *
	 * @param array $feed The feed being included in the feed list.
	 *
	 * @return string
	 */
	public function get_column_value_mytextbox( $feed )
	{
		return '<b>' . rgars( $feed, 'meta/mytextbox' ) . '</b>';
	}

	/**
	 * Prevent feeds being listed or created if an api key isn't valid.
	 *
	 * @return bool
	 */
	public function can_create_feed()
	{

		// Get the plugin settings.
		$settings = $this->get_plugin_settings();

		// Access a specific setting e.g. an api key
		$key = rgar( $settings, 'apiKey' );

		return true;
	}
}
