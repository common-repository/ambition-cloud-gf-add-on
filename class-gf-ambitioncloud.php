<?php

// don't load directly
if (!defined('ABSPATH')) {
    die();
}

GFForms::include_feed_addon_framework();

/**
 * Gravity Forms AmbitionCloud Add-On.
 *
 * @since     1.0
 * @package   GravityForms
 * @author    Micheal Muldoon
 * @copyright Copyright (c) 2021, Micheal Muldoon
 */
class GFAmbitionCloud extends GFFeedAddOn
{

    /**
     * Contains an instance of this class, if available.
     *
     * @since  1.0
     * @access private
     * @var    object $_instance If available, contains an instance of this class.
     */
    private static $_instance = null;

    /**
     * Defines the version of the AmbitionCloud Add-On.
     *
     * @since  1.0
     * @access protected
     * @var    string $_version Contains the version, defined from ambitioncloud.php
     */
    protected $_version = GF_AMBITIONCLOUD_VERSION;

    /**
     * Defines the minimum Gravity Forms version required.
     *
     * @since  1.0
     * @access protected
     * @var    string $_min_gravityforms_version The minimum version required.
     */
    protected $_min_gravityforms_version = '2.5.16';

    /**
     * Defines the plugin slug.
     *
     * @since  1.0
     * @access protected
     * @var    string $_slug The slug used for this plugin.
     */
    protected $_slug = 'ambitioncloud';

    /**
     * Defines the main plugin file.
     *
     * @since  1.0
     * @access protected
     * @var    string $_path The path to the main plugin file, relative to the plugins folder.
     */
    protected $_path = 'ambitioncloud/ambitioncloud.php';

    /**
     * Defines the full path to this class file.
     *
     * @since  1.0
     * @access protected
     * @var    string $_full_path The full path.
     */
    protected $_full_path = __FILE__;

    /**
     * Defines the URL where this Add-On can be found.
     *
     * @since  1.0
     * @access protected
     * @var    string The URL of the Add-On.
     */
    protected $_url = 'http://www.ambitioncloud.com.au';

    /**
     * Defines the title of this Add-On.
     *
     * @since  1.0
     * @access protected
     * @var    string $_title The title of the Add-On.
     */
    protected $_title = 'Gravity Forms Ambition Cloud Add-On';

    /**
     * Defines the short title of the Add-On.
     *
     * @since  1.0
     * @access protected
     * @var    string $_short_title The short title.
     */
    protected $_short_title = 'Ambition Cloud';

    /**
     * Defines if Add-On should use Gravity Forms servers for update data.
     *
     * @since  1.0
     * @access protected
     * @var    bool
     */
    protected $_enable_rg_autoupgrade = true;
    /**
     * Defines the capability needed to access the Add-On settings page.
     *
     * @since  1.0
     * @access protected
     * @var    string $_capabilities_settings_page The capability needed to access the Add-On settings page.
     */
    protected $_capabilities_settings_page = 'gravityforms_ambitioncloud';

    /**
     * Defines the capability needed to access the Add-On form settings page.
     *
     * @since  1.0
     * @access protected
     * @var    string $_capabilities_form_settings The capability needed to access the Add-On form settings page.
     */
    protected $_capabilities_form_settings = 'gravityforms_ambitioncloud';

    /**
     * Defines the capability needed to uninstall the Add-On.
     *
     * @since  1.0
     * @access protected
     * @var    string $_capabilities_uninstall The capability needed to uninstall the Add-On.
     */
    protected $_capabilities_uninstall = 'gravityforms_ambitioncloud_uninstall';

    /**
     * Defines the capabilities needed for the Post Creation Add-On
     *
     * @since  1.0
     * @access protected
     * @var    array $_capabilities The capabilities needed for the Add-On
     */
    protected $_capabilities = array('gravityforms_ambitioncloud', 'gravityforms_ambitioncloud_uninstall');

    /**
     * Stores an instance of the AmbitionCloud API library, if initialized.
     *
     * @since  1.0
     * @access protected
     * @var    object $api If initialized, an instance of the AmbitionCloud API library.
     */
    protected $api = null;

    /**
     * New AmbitionCloud fields that need to be created when saving feed.
     *
     * @since  1.0
     * @access protected
     * @var    object $api When saving feed, new AmbitionCloud fields that need to be created.
     */
    protected $_new_custom_fields = array();

    protected $teams_enabled    = false;
    protected $default_team     = false;
    protected $default_referrer = false;

    /**
     * Get an instance of this class.
     *
     * @return GFAmbitionCloud
     */
    public static function get_instance()
    {

        if (null === self::$_instance) {
            self::$_instance = new GFAmbitionCloud();
        }

        return self::$_instance;

    }

    // # FEED PROCESSING -----------------------------------------------------------------------------------------------

    /**
     * Process the feed, subscribe the user to the list.
     *
     * @param array $feed The feed object to be processed.
     * @param array $entry The entry object currently being processed.
     * @param array $form The form object currently being processed.
     *
     * @return bool|void
     */
    public function process_feed($feed, $entry, $form)
    {

        $this->log_debug(__METHOD__ . '(): Processing feed.');

        /* If API instance is not initialized, exit. */
        if (!$this->initialize_api()) {

            $this->log_error(__METHOD__ . '(): Failed to set up the API.');

            return;

        }

        /* Setup mapped fields array. */
        $mapped_fields = $this->get_field_map_fields($feed, 'fields');

        /* Setup contact array. */
        $contact = array(
            'email' => $this->get_field_value($form, $entry, rgar($mapped_fields, 'email')),
        );

        /* If the email address is invalid or empty, exit. */
        if (GFCommon::is_invalid_or_empty_email($contact['email'])) {
            $this->log_error(__METHOD__ . '(): Aborting. Invalid email address: ' . sanitize_email(rgar($contact, 'email')));

            return;
        }

        /**
         * Prevent empty form fields from erasing values already stored in Ambition Cloud
         * when updating an existing subscriber.
         *
         * @since 1.5
         *
         * @param bool  $override If blank fields should override values already stored in Ambition Cloud
         * @param array $form     The form object.
         * @param array $entry    The entry object.
         * @param array $feed     The feed object.
         */
        $override_empty_fields = gf_apply_filters('gform_ambitioncloud_override_empty_fields', array($form['id']), true, $form, $entry, $feed);

        /* Assiging properties that have been mapped */
        $properties = array('first_name', 'last_name', 'phone');
        foreach ($properties as $property) {
            $field_value = $this->get_field_value($form, $entry, rgar($mapped_fields, $property));
            $is_mapped   = !rgempty($property, $mapped_fields);

            /* Only send fields that are mapped. Also, make sure blank values are ok to override existing data */
            if ($is_mapped && ($override_empty_fields || !empty($field_value))) {
                $contact[$property] = $field_value;
            }
        }

        /* Prepare tags. */
        if (rgars($feed, 'meta/tags')) {

            $tags            = GFCommon::replace_variables($feed['meta']['tags'], $form, $entry, false, false, false, 'text');
            $tags            = array_map('trim', explode(',', $tags));
            $contact['tags'] = gf_apply_filters('gform_ambitioncloud_tags', $form['id'], $tags, $feed, $entry, $form);

        }

        /* Add list to contact array. */
        $list_id                = $feed['meta']['list'];
        $contact['form_id']     = $list_id;
        $contact['referrer_id'] = $this->default_referrer ? $this->default_referrer : rgars($feed, 'meta/referrer_id');
        $contact['team_id']     = $this->default_team ? $this->default_team : rgars($feed, 'meta/team_id');

        /* Add custom fields to contact array. */
        $custom_fields = rgars($feed, 'meta/custom_fields');
        if (is_array($custom_fields)) {
            foreach ($feed['meta']['custom_fields'] as $custom_field) {

                if (rgblank($custom_field['key']) || rgblank($custom_field['value'])) {
                    continue;
                }

                if ('gf_custom' === $custom_field['key']) {

                    $perstag             = trim($custom_field['custom_key']); // Set shortcut name to custom key
                    $perstag             = str_replace(' ', '_', $perstag); // Remove all spaces
                    $perstag             = preg_replace('([^\w\d])', '', $perstag); // Strip all custom characters
                    $custom_field['key'] = strtolower($perstag); // Set to lowercase

                    $field_value = $this->get_field_value($form, $entry, $custom_field['value']);

                    if (rgblank($field_value) && !$override_empty_fields) {
                        continue;
                    }

                    $contact_key = $custom_field['key'];

                    //If contact is already set, don't override it with fields that are hidden by conditional logic
                    $is_hidden = GFFormsModel::is_field_hidden($form, GFFormsModel::get_field($form, $custom_field['value']), array(), $entry);
                    if (isset($contact['custom_fields'][$contact_key]) && $is_hidden) {
                        continue;
                    }

                    $contact['custom_fields'][$contact_key] = $field_value;

                } else {

                    $field_value = $this->get_field_value($form, $entry, $custom_field['value']);

                    if (rgblank($field_value) && !$override_empty_fields) {
                        continue;
                    }

                    $contact_key = $custom_field['key'];

                    //If contact is already set, don't override it with fields that are hidden by conditional logic
                    $is_hidden = GFFormsModel::is_field_hidden($form, GFFormsModel::get_field($form, $custom_field['value']), array(), $entry);
                    if (isset($contact[$contact_key]) && $is_hidden) {
                        continue;
                    }

                    $contact[$contact_key] = $field_value;
                }

            }
        }

        /* Add note. */
        if (rgars($feed, 'meta/note')) {

            $note = GFCommon::replace_variables($feed['meta']['note'], $form, $entry, false, false, false, 'text');

            $this->log_debug(__METHOD__ . "():" . print_r($note, true));

            $contact['note'] = $note;
        }

        /* Add address fields to contact array. */
        $address_fields = rgars($feed, 'meta/address_fields');
        if (is_array($address_fields)) {
            foreach ($feed['meta']['address_fields'] as $address_field) {

                if (rgblank($address_field['key']) || rgblank($address_field['value'])) {
                    continue;
                }

                $field_value = $this->get_field_value($form, $entry, $address_field['value']);

                if (rgblank($field_value) && !$override_empty_fields) {
                    continue;
                }

                $perstag = trim($address_field['key']); // Set shortcut name to custom key
                $perstag = str_replace(' ', '_', $perstag); // Remove all spaces
                $perstag = preg_replace('([^\w\d])', '', $perstag); // Strip all custom characters
                $perstag = strtolower($perstag); // Set to lowercase

                $contact_key = $perstag;

                //If contact is already set, don't override it with fields that are hidden by conditional logic
                $is_hidden = GFFormsModel::is_field_hidden($form, GFFormsModel::get_field($form, $address_field['value']), array(), $entry);
                if (isset($contact[$contact_key]) && $is_hidden) {
                    continue;
                }

                $contact['address'][$contact_key] = $field_value;

            }
        }

        /* Add aai fields to contact array. */
        $aai_fields = rgars($feed, 'meta/aai_fields');
        if (is_array($aai_fields)) {
            foreach ($feed['meta']['aai_fields'] as $aai_field) {

                if (rgblank($aai_field['key']) || rgblank($aai_field['value'])) {
                    continue;
                }

                $field_value = $this->get_field_value($form, $entry, $aai_field['value']);

                if (rgblank($field_value) && !$override_empty_fields) {
                    continue;
                }

                $perstag = trim($aai_field['key']); // Set shortcut name to custom key
                $perstag = str_replace(' ', '_', $perstag); // Remove all spaces
                $perstag = preg_replace('([^\w\d])', '', $perstag); // Strip all custom characters
                $perstag = strtolower($perstag); // Set to lowercase

                $contact_key = $perstag;

                //If contact is already set, don't override it with fields that are hidden by conditional logic
                $is_hidden = GFFormsModel::is_field_hidden($form, GFFormsModel::get_field($form, $aai_field['value']), array(), $entry);
                if (isset($contact[$contact_key]) && $is_hidden) {
                    continue;
                }

                $contact['aai'][$contact_key] = $field_value;

            }
        }

        /* Add custom fields to contact array. */
        $utm_fields = rgars($feed, 'meta/utm_fields');
        if (is_array($utm_fields)) {
            foreach ($feed['meta']['utm_fields'] as $utm_field) {

                if (rgblank($utm_field['key']) || rgblank($utm_field['value'])) {
                    continue;
                }

                $field_value = $this->get_field_value($form, $entry, $utm_field['value']);

                if (rgblank($field_value) && !$override_empty_fields) {
                    continue;
                }

                $perstag = trim($utm_field['key']); // Set shortcut name to custom key
                $perstag = str_replace(' ', '_', $perstag); // Remove all spaces
                $perstag = preg_replace('([^\w\d])', '', $perstag); // Strip all custom characters
                $perstag = strtolower($perstag); // Set to lowercase

                $contact_key = $perstag;

                //If contact is already set, don't override it with fields that are hidden by conditional logic
                $is_hidden = GFFormsModel::is_field_hidden($form, GFFormsModel::get_field($form, $utm_field['value']), array(), $entry);
                if (isset($contact[$contact_key]) && $is_hidden) {
                    continue;
                }

                $contact[$contact_key] = $field_value;

            }
        }

        if (1 == $feed['meta']['fast_track'] && !$feed['meta']['redirectform']) {
            $contact['fast_track'] = $feed['meta']['fast_track'];
        }

        $contact['redirect_form'] = $feed['meta']['redirectform'];

        /**
         * Allows the contact properties to be overridden before the contact_sync request is sent to the API.
         *
         * @param array $contact The contact properties.
         * @param array $entry The entry currently being processed.
         * @param array $form The form object the current entry was created from.
         * @param array $feed The feed which is currently being processed.
         *
         * @since 1.3.5
         */
        $contact = apply_filters('gform_ambitioncloud_contact_pre_sync', $contact, $entry, $form, $feed);
        $contact = apply_filters('gform_ambitioncloud_contact_pre_sync_' . $form['id'], $contact, $entry, $form, $feed);

        /* Sync the lead. */

        $this->log_debug(__METHOD__ . '(): Lead payload => ' . print_r($feed, true));
        $this->log_debug(__METHOD__ . '(): Contact payload => ' . print_r($contact, true));

        $sync_contact = $this->api->add_lead($contact);

        $this->log_debug(__METHOD__ . "(): response => " . print_r($sync_contact, true));

        if ($sync_contact['result_code'] == 1) {

            $this->add_note($entry['id'], esc_html__('Lead Successfully added!'), 'success');
            GFAPI::update_entry_field($entry['id'], 'app_id', $sync_contact['app_id']);
            GFAPI::update_entry_field($entry['id'], 'ref_id', $sync_contact['ref_id']);
            GFAPI::update_entry_field($entry['id'], 'fast_track_link', $sync_contact['fast_track_link']);

            // reload entry
            $entry = $entry = \GFAPI::get_entry($entry['id']);

            $this->log_debug(__METHOD__ . "(): {$contact['email']} has been added; {$sync_contact['result_message']}.");
            return true;

        } else {

            $this->add_note($entry['id'], esc_html__('Lead not added!'), 'error');
            $this->log_error(__METHOD__ . "(): {$contact['email']} was not added; {$sync_contact['result_message']}");

            return false;

        }

    }

    // # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------

    /**
     * Plugin starting point. Handles hooks, loading of language files and PayPal delayed payment support.
     */
    public function init()
    {
        parent::init();

        add_filter('gform_entry_detail_meta_boxes', array($this, 'register_meta_box'), 10, 3);
        add_filter('gform_entry_meta', array($this, 'ambition_entry_meta'), 10, 2);
        add_filter('gform_custom_merge_tags', array($this, 'ambition_merge_tags'), 10, 4);
        add_filter('gform_merge_tag_data', array($this, 'ambition_merge_tag_data'), 10, 4);
        add_shortcode('fasttrack', array($this, 'fast_track_button'));

    }

    public function ambition_entry_meta($entry_meta, $form_id)
    {
        $entry_meta['app_id'] = array(
            'label'             => 'App ID',
            'is_numeric'        => false,
            'is_default_column' => true,
        );
        $entry_meta['fast_track_link'] = array(
            'label'             => 'Fast Track Link',
            'is_numeric'        => false,
            'is_default_column' => true,
        );

        return $entry_meta;
    }

    public function ambition_merge_tags($merge_tags, $form_id, $fields, $element_id)
    {

        $merge_tags[] = array('label' => 'Application ID', 'tag' => '{ambition:app_id}');
        $merge_tags[] = array('label' => 'Reference ID', 'tag' => '{ambition:ref_id}');
        $merge_tags[] = array('label' => 'Fast Track Link', 'tag' => '{ambition:fast_track_link}');

        return $merge_tags;

    }

    public function ambition_merge_tag_data($data, $text, $form, $entry)
    {

        $entry            = \GFAPI::get_entry($entry['id']);
        $data['ambition'] = array(
            'app_id'          => rgar($entry, 'app_id'),
            'ref_id'          => rgar($entry, 'ref_id'),
            'fast_track_link' => rgar($entry, 'fast_track_link'),
        );
        return $data;

    }

    /**
     * Add the meta box to the entry detail page.
     *
     * @param array $meta_boxes The properties for the meta boxes.
     * @param array $entry The entry currently being viewed/edited.
     * @param array $form The form object used to process the current entry.
     *
     * @return array
     */
    public function register_meta_box($meta_boxes, $entry, $form)
    {
        if ($this->get_active_feeds($form['id']) && $this->initialize_api()) {
            $meta_boxes[$this->_slug] = array(
                'title'    => $this->get_short_title(),
                'callback' => array($this, 'add_details_meta_box'),
                'context'  => 'side',
            );
        }

        return $meta_boxes;
    }

    /**
     * The callback used to echo the content to the meta box.
     *
     * @param array $args An array containing the form and entry objects.
     */
    public function add_details_meta_box($args)
    {
        $settings = $this->get_saved_plugin_settings();
        $form     = $args['form'];
        $entry    = $args['entry'];

        $html   = '';
        $action = $this->_slug . '_process_feeds';

        $lead_id = rgar($entry, 'app_id');

        if (empty($lead_id) && rgpost('action') == $action) {
            check_admin_referer('gforms_save_entry', 'gforms_save_entry');

            $entry = $this->maybe_process_feed($entry, $form);

            // Retrieve the lead id from the updated entry.
            $lead_id = rgar($entry, 'app_id');

            $html .= esc_html__('Lead Processed.', 'ambitioncloud') . '</br></br>';
        }

        if (empty($lead_id)) {

            // Add the 'Process Feeds' button.
            $html .= sprintf('<input type="submit" value="%s" class="button" onclick="jQuery(\'#action\').val(\'%s\');" />', esc_attr__('Process Lead', 'ambitioncloud'), $action);

        } else {

            // Display the lead ID.
            $html .= esc_html__('Lead ID', 'ambitioncloud') . ': ' . $lead_id . '</br></br>';
            $html .= sprintf('<a href="%s" target="_blank" class="button"/>%s</a>', untrailingslashit(sanitize_url($settings['api_url'])) . '/admin/applications/' . $lead_id, esc_attr__('Open Lead', 'ambitioncloud'));

        }

        echo esc_html($html);
    }

    /**
     * Return the stylesheets which should be enqueued.
     *
     * @return array
     */
    public function styles()
    {

        $min    = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG || isset($_GET['gform_debug']) ? '' : '.min';
        $styles = array(
            array(
                'handle'  => 'gform_ambitioncloud_form_settings_css',
                'src'     => $this->get_base_url() . "/css/form_settings{$min}.css",
                'version' => $this->_version,
                'enqueue' => array(
                    array('admin_page' => array('form_settings')),
                ),
            ),
        );

        return array_merge(parent::styles(), $styles);

    }

    /**
     * Return the plugin's icon for the plugin/form settings menu.
     *
     * @since 1.8
     *
     * @return string
     */
    public function get_menu_icon()
    {

        return file_get_contents($this->get_base_path() . '/images/menu-icon.svg');

    }

    public function fast_track_button($atts = array(), $content = null)
    {

        $fast_track = sanitize_url(rgars($_GET, 'fast-track'));
        $signature  = sanitize_text_field(rgars($_GET, 'signature'));

        if ($fast_track) {
            extract(shortcode_atts(array(
                'link' => '#',
            ), $atts));

            return '<a href="' . sanitize_url($fast_track . '&signature=' . $signature) . '" target="_blank" class="btn">' . $content . '</a>';
        }

        return;

    }

    // # PLUGIN SETTINGS -----------------------------------------------------------------------------------------------

    /**
     * Configures the settings which should be rendered on the add-on settings tab.
     *
     * @return array
     */
    public function plugin_settings_fields()
    {
        if (rgar($_POST, 'gform-settings-save')) {
            if (rgar($_POST, '_gform_setting_defaultform') == 'gf_custom') {
                $this->initialize_api();
                $form_name          = sanitize_text_field(rgar($_POST, '_gform_setting_defaultform_custom'));
                $ac_sources_updated = $this->api->add_form($form_name);
                unset($_POST['_gform_setting_defaultform_custom']);
                unset($_POST['_gform_setting_defaultform']);
            }
        }

        return array(
            array(
                'title'       => '',
                'description' => $this->plugin_settings_description(),
                'fields'      => array(
                    array(
                        'name'              => 'api_url',
                        'label'             => esc_html__('Tenant Url', 'ambitioncloud'),
                        'type'              => 'text',
                        'class'             => 'medium',
                        'feedback_callback' => array($this, 'initialize_api'),
                    ),
                    array(
                        'name'              => 'api_key',
                        'label'             => esc_html__('Tenant Key', 'ambitioncloud'),
                        'type'              => 'text',
                        'class'             => 'large',
                        'feedback_callback' => array($this, 'initialize_api'),
                    ),
                    array(
                        'type'     => 'save',
                        'messages' => array(
                            'success' => esc_html__('Ambition Cloud settings have been updated.', 'ambitioncloud'),
                        ),
                    ),
                ),
            ),
            array(
                'title'       => 'Forms available on Ambition Cloud',
                'description' => 'View available forms to push leads to or create a new form.',
                'fields'      => array(
                    array(
                        'label'   => esc_html__('Ambition Cloud Form', 'ambitioncloud'),
                        'type'    => 'select_custom',
                        'choices' => $this->sources_for_feed_setting(),
                        'name'    => 'defaultform',
                    ),
                ),
            ),
        );

    }

    /**
     * Prepare plugin settings description.
     *
     * @return string
     */
    public function plugin_settings_description()
    {

        $description = '<p>';
        $description .= sprintf(
            esc_html__('Use Gravity Forms to collect customer information and automatically add it as a lead to your Ambition Cloud Tenancy. If you don\'t have an Ambition Cloud account, you can %1$ssign up for one here.%2$s', 'ambitioncloud'),
            '<a href="http://www.ambitioncloud.com.au/" target="_blank">', '</a>'
        );
        $description .= '</p>';

        if (!$this->initialize_api()) {

            $description .= '<p>';
            $description .= esc_html__('Gravity Forms Ambition Cloud Add-On requires your Tenant Url and Tenant Key, which can be found in the Settings tab on the account dashboard.', 'ambitioncloud');
            $description .= '</p>';

        }

        return $description;

    }

    // ------- Feed page -------

    /**
     * Prevent feeds being listed or created if the api key isn't valid.
     *
     * @return bool
     */
    public function can_create_feed()
    {

        return $this->initialize_api();

    }

    /**
     * Enable feed duplication.
     *
     * @access public
     * @return bool
     */
    public function can_duplicate_feed($id)
    {

        return true;

    }

    /**
     * If the api keys are invalid or empty return the appropriate message.
     *
     * @return string
     */
    public function configure_addon_message()
    {

        $settings_label = sprintf(esc_html__('%s Settings', 'gravityforms'), $this->get_short_title());
        $settings_link  = sprintf('<a href="%s">%s</a>', sanitize_url($this->get_plugin_settings_url()), $settings_label);

        if (is_null($this->initialize_api())) {

            return sprintf(esc_html__('To get started, please configure your %s.', 'gravityforms'), $settings_link);
        }

        return sprintf(esc_html__('Please make sure you have entered valid Tenant credentials on the %s page.', 'ambitioncloud'), $settings_link);

    }

    /**
     * Configures which columns should be displayed on the feed list page.
     *
     * @return array
     */
    public function feed_list_columns()
    {

        return array(
            'feed_name' => esc_html__('Name', 'ambitioncloud'),
            'list'      => esc_html__('Ambition Cloud Form', 'ambitioncloud'),
        );

    }

    /**
     * Returns the value to be displayed in the AmbitionCloud Source column.
     *
     * @param array $feed The feed being included in the feed list.
     *
     * @return string
     */
    public function get_column_value_list($feed)
    {

        /* If AmbitionCloud instance is not initialized, return campaign ID. */
        if (!$this->initialize_api()) {
            return $feed['meta']['list'];
        }

        /* Get form and return name */
        $forms    = $this->api->get_sources();
        $formName = null;

        foreach( $forms as $index => $form) {
            if ($form['id'] == $feed['meta']['list']) {
                $formName = $form['name'];
            }
            break;
        }   
        
        return $formName ? $formName : $feed['meta']['list'];
    }

    /**
     * Configures the settings which should be rendered on the feed edit page.
     *
     * @return array The feed settings.
     */
    public function feed_settings_fields()
    {
        $settings = $this->get_plugin_settings();

        /* Build fields array. */
        $fields = array(
            array(
                'name'          => 'feed_name',
                'label'         => esc_html__('Feed Name', 'ambitioncloud'),
                'type'          => 'text',
                'required'      => true,
                'default_value' => $this->get_default_feed_name(),
                'class'         => 'medium',
                'tooltip'       => '<h6>' . esc_html__('Name', 'ambitioncloud') . '</h6>' . esc_html__('Enter a feed name to uniquely identify this setup.', 'ambitioncloud'),
            ),
            array(
                'name'     => 'list',
                'label'    => esc_html__('Ambition Cloud Form', 'ambitioncloud'),
                'type'     => 'select',
                'required' => true,
                'choices'  => $this->sources_for_feed_setting(),
                'onchange' => "jQuery(this).parents('form').submit();",
                'tooltip'  => '<h6>' . esc_html__('Ambition Cloud Source', 'ambitioncloud') . '</h6>' . esc_html__('Select which Ambition Cloud form this feed will add leads to.', 'ambitioncloud'),
            ),
            array(
                'name'       => 'fields',
                'label'      => esc_html__('Map Fields', 'ambitioncloud'),
                'type'       => 'field_map',
                'dependency' => 'list',
                'field_map'  => $this->fields_for_feed_mapping(),
                'tooltip'    => '<h6>' . esc_html__('Map Fields', 'ambitioncloud') . '</h6>' . esc_html__('Select which Gravity Form fields pair with their respective Ambition Cloud fields.', 'ambitioncloud'),
            ),
            array(
                'name'          => 'custom_fields',
                'label'         => '',
                'type'          => 'dynamic_field_map',
                'dependency'    => 'list',
                'field_map'     => $this->custom_fields_for_feed_setting(),
                'save_callback' => array($this, 'create_new_custom_fields'),
            ),
            array(
                'name'       => 'address_fields',
                'label'      => esc_html__('Address Fields', 'ambitioncloud'),
                'type'       => 'dynamic_field_map',
                'field_type' => 'hidden',
                'limit'      => 12,
                'dependency' => 'list',
                'field_map'  => $this->address_fields_for_feed_setting(),
            ),
            array(
                'name'       => 'aai_fields',
                'label'      => esc_html__('AAI Fields', 'ambitioncloud'),
                'type'       => 'dynamic_field_map',
                'field_type' => 'hidden',
                'limit'      => 14,
                'dependency' => 'list',
                'field_map'  => $this->aai_fields_for_feed_setting(),
            ),
            array(
                'name'       => 'utm_fields',
                'label'      => esc_html__('UTM Fields', 'ambitioncloud'),
                'type'       => 'dynamic_field_map',
                'field_type' => 'hidden',
                'limit'      => 5,
                'dependency' => 'list',
                'field_map'  => $this->utm_fields_for_feed_setting(),
            ),
            array(
                'name'       => 'note',
                'type'       => 'textarea',
                'label'      => esc_html__('Note', 'ambitioncloud'),
                'dependency' => 'list',
                'class'      => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
            ),
        );

        if (!$this->default_referrer) {
            $fields[] = array(
                'name'       => 'referrer_id',
                'type'       => 'text',
                'label'      => esc_html__('Referrer ID', 'ambitioncloud'),
                'dependency' => 'list',
                'tooltip'    => '<h6>' . esc_html__('Referrer ID', 'ambitioncloud') . '</h6>' . sprintf(esc_html__('Visit your tenancy referrer portal to locate the referrer id, visit %syour Tenant Portal%s', 'ambitioncloud'), '<a href="' . untrailingslashit(sanitize_url($settings['api_url'])) . '/admin/referrers" target="_blank">', '</a>'),
            );
        }

        if ($this->teams_enabled && !$this->default_team) {
            $fields[] = array(
                'name'       => 'team_id',
                'type'       => 'text',
                'label'      => esc_html__('Team ID', 'ambitioncloud'),
                'dependency' => 'list',
                'tooltip'    => '<h6>' . esc_html__('Team ID', 'ambitioncloud') . '</h6>' . sprintf(esc_html__('Visit your tenancy team portal to locate the desired team id, visit %syour Tenant Portal%s', 'ambitioncloud'), '<a href="' . untrailingslashit(sanitize_url($settings['api_url'])) . '/admin/settings/team" target="_blank">', '</a>'),
            );
        }

        /* Add feed condition and options fields. */
        $fields[] = array(
            'name'       => 'feed_condition',
            'label'      => esc_html__('Conditional Logic', 'ambitioncloud'),
            'type'       => 'feed_condition',
            'dependency' => 'list',
            'tooltip'    => '<h6>' . esc_html__('Conditional Logic', 'ambitioncloud') . '</h6>' . esc_html__('When conditional logic is enabled, form submissions will only be exported to AmbitionCloud when the condition is met. When disabled, all form submissions will be exported.', 'ambitioncloud'),
        );

        // $fields[] = array(
        //     'name'       => 'options',
        //     'label'      => esc_html__('Options', 'ambitioncloud'),
        //     'type'       => 'checkbox',
        //     'dependency' => 'list',
        //     'choices'    => array(
        //         array(
        //             'name'          => 'fast_track',
        //             'label'         => esc_html__('Fast Track Follow-up (this option will be depreciated in favour of below)', 'ambitioncloud'),
        //             'default_value' => 1,
        //             'tooltip'       => '<h6>' . esc_html__('Fast Track', 'ambitioncloud') . '</h6>' . esc_html__('When the fast track option is enabled, Ambition Cloud will automatically forward your lead to the fast track form on your tenancy.', 'ambitioncloud'),
        //         ),
        //     ),
        // );

        $fields[] = array(
            'label'   => esc_html__('Ambition Cloud Redirect / Fast Track Forms', 'ambitioncloud'),
            'type'    => 'select',
            'choices' => $this->redirect_forms_feed_setting(),
            'name'    => 'redirectform',
            'tooltip' => '<h6>' . esc_html__('Redirect / Fast Track Forms', 'ambitioncloud') . '</h6>' . esc_html__('When a form is selected here, Ambition Cloud will automatically forward your lead to the selected form on your tenancy.', 'ambitioncloud'),
        );

        return array(
            array(
                'title'  => '',
                'fields' => $fields,
            ),
        );

    }

    /**
     * Renders and initializes a dynamic field map field based on the $field array whose choices are populated by the fields to be mapped.
     * (Forked to force reload of field map options.)
     *
     * @since  Unknown
     *
     * @param array $field Field array containing the configuration options of this field.
     * @param bool  $echo  Determines if field contents should automatically be displayed. Defaults to true.
     *
     * @return string
     */
    public function settings_dynamic_field_map($field, $echo = true)
    {

        // Refresh field map.
        if ('custom_fields' === $field['name'] && $this->is_postback()) {
            $field['field_map'] = $this->custom_fields_for_feed_setting();
        }

        return parent::settings_dynamic_field_map($field, $echo);
    }

    /**
     * Prepare AmbitionCloud lists for feed field
     *
     * @return array
     */
    public function sources_for_feed_setting()
    {
        global $_gaddon_posted_settings;
        $_gaddon_posted_settings = $this->get_posted_settings();

        $sources = array(
            array(
                'label' => esc_html__('Select Form', 'ambitioncloud'),
                'value' => '',
            ),
        );

        /* If AmbitionCloud API credentials are invalid, return the sources array. */
        if (!$this->initialize_api()) {
            return $sources;
        }

        $list_id = $this->get_setting('list');

        $this->log_debug("List ID:" . print_r($list_id, true));

        $current_settings = $this->get_current_settings();
        if ($current_settings) {
            if ($current_settings['list'] == 'gf_custom') {
                if ($_gaddon_posted_settings && rgar($_gaddon_posted_settings, 'list_custom')) {
                    $form_name          = $_gaddon_posted_settings['list_custom'];
                    $ac_sources_updated = $this->api->add_form($form_name);

                    $current_settings['list'] = $ac_sources_updated;
                    unset($_gaddon_posted_settings['list_custom']);
                    unset($_gaddon_posted_settings['list_custom']);
                }
            }
        }

        /* Get available Ambition Cloud sources. */
        $ac_sources = $this->api->get_sources();

        /* Add AmbitionCloud sources to array and return it. */
        foreach ($ac_sources as $source) {

            if (!is_array($source)) {
                continue;
            }

            $sources[] = array(
                'label' => $source['name'],
                'value' => $source['id'],
            );

        }

        return $sources;

    }

    /**
     * Prepare fields for feed field mapping.
     *
     * @return array
     */
    public function fields_for_feed_mapping()
    {

        $email_field = array(
            'name'     => 'email',
            'label'    => esc_html__('Email Address', 'ambitioncloud'),
            'required' => true,
            'tooltip'  => '<h6>' . esc_html__('Email Field Mapping', 'ambitioncloud') . '</h6>' . sprintf(esc_html__('Only email and hidden fields are available to be mapped. To add support for other field types, visit %sour documentation site%s', 'ambitioncloud'), '<a href="https://api.fintelligence.com.au/category/ambitioncloud/">', '</a>'),
        );

        /**
         * Allows the list of supported fields types for the email field map to be changed.
         * Return an array of field types or true (to allow all field types)
         *
         * @since 1.5
         *
         * @param array|bool $field_types Array of field types or "true" for all field types.
         */
        $field_types = apply_filters('gform_ambitioncloud_supported_field_types_email_map', array('email', 'hidden'));

        if ($field_types !== true & is_array($field_types)) {
            $email_field['field_type'] = $field_types;
        }

        return array(
            $email_field,
            array(
                'name'     => 'first_name',
                'label'    => esc_html__('First Name', 'ambitioncloud'),
                'required' => true,
            ),
            array(
                'name'     => 'middle_name',
                'label'    => esc_html__('Middle Name', 'ambitioncloud'),
                'required' => false,
            ),
            array(
                'name'     => 'last_name',
                'label'    => esc_html__('Last Name', 'ambitioncloud'),
                'required' => true,
            ),
            array(
                'name'     => 'phone',
                'label'    => esc_html__('Phone Number', 'ambitioncloud'),
                'required' => true,
            ),
        );

    }

    /**
     * Prepare address fields for feed field mapping.
     *
     * @return array
     */
    public function address_fields_for_feed_setting()
    {

        $fields = array();

        $address = [
            [
                'name'  => 'unit_number',
                'label' => esc_html__('Unit Number', 'ambitioncloud'),
            ],
            [
                'name'  => 'street_number',
                'label' => esc_html__('Street Number', 'ambitioncloud'),
            ],
            [
                'name'  => 'route',
                'label' => esc_html__('Route', 'ambitioncloud'),
            ],
            [
                'name'  => 'locality',
                'label' => esc_html__('Locality', 'ambitioncloud'),
            ],
            [
                'name'  => 'administrative_area_level_1',
                'label' => esc_html__('Admin Area Level 1', 'ambitioncloud'),
            ],
            [
                'name'  => 'administrative_area_level_2',
                'label' => esc_html__('Admin Area Level 2', 'ambitioncloud'),
            ],
            [
                'name'  => 'postal_code',
                'label' => esc_html__('Postcode', 'ambitioncloud'),
            ],
            [
                'name'  => 'country',
                'label' => esc_html__('Country', 'ambitioncloud'),
            ],
            [
                'name'  => 'residential_status_id',
                'label' => esc_html__('Residential Status Id', 'ambitioncloud'),
            ],
            [
                'name'  => 'is_current',
                'label' => esc_html__('Ic Current', 'ambitioncloud'),
            ],
            [
                'name'  => 'years',
                'label' => esc_html__('Years', 'ambitioncloud'),
            ],
            [
                'name'  => 'months',
                'label' => esc_html__('Months', 'ambitioncloud'),
            ],
        ];

        foreach ($address as $index => $source) {
            $fields[] = [
                'name'     => esc_html__($source['name'], 'ambitioncloud'),
                'label'    => esc_html__($source['label'], 'ambitioncloud'),
                'required' => false,
            ];
        }

        return $fields;

    }

    /**
     * Prepare aai fields for feed field mapping.
     *
     * @return array
     */
    public function aai_fields_for_feed_setting()
    {

        $fields = array();

        $aai = [

            [
                'name'  => 'title',
                'label' => esc_html__('Title', 'ambitioncloud'),
            ],
            [
                'name'  => 'residential_status',
                'label' => esc_html__('Residential Status', 'ambitioncloud'),
            ],
            [
                'name'  => 'marital_status',
                'label' => esc_html__('Marital Status', 'ambitioncloud'),
            ],
            [
                'name'  => 'license_type',
                'label' => esc_html__('License Type', 'ambitioncloud'),
            ],
            [
                'name'  => 'license_number',
                'label' => esc_html__('License Number', 'ambitioncloud'),
            ],
            [
                'name'  => 'license_expiry',
                'label' => esc_html__('License Expiry', 'ambitioncloud'),
            ],
            [
                'name'  => 'license_state_issued',
                'label' => esc_html__('License State Issued', 'ambitioncloud'),
            ],
            [
                'name'  => 'total_monthly_income',
                'label' => esc_html__('Total Monthly Income', 'ambitioncloud'),
            ],
            [
                'name'  => 'total_monthly_expense',
                'label' => esc_html__('Total Monthly Expense', 'ambitioncloud'),
            ],
            [
                'name'  => 'total_monthly_debt',
                'label' => esc_html__('Total Monthly Debt', 'ambitioncloud'),
            ],
            [
                'name'  => 'total_monthly_rent',
                'label' => esc_html__('Total Monthly Rent', 'ambitioncloud'),
            ],
            [
                'name'  => 'is_sharing_expense',
                'label' => esc_html__('Is Sharing Expense', 'ambitioncloud'),
            ],
            [
                'name'  => 'applicant_type',
                'label' => esc_html__('Applicant Type', 'ambitioncloud'),
            ],
            [
                'name'  => 'is_sharing_dependents',
                'label' => esc_html__('Is Sharing Dependents', 'ambitioncloud'),
            ],
        ];

        foreach ($aai as $index => $source) {
            $fields[] = [
                'name'     => esc_html__($source['name'], 'ambitioncloud'),
                'label'    => esc_html__($source['label'], 'ambitioncloud'),
                'required' => false,
            ];
        }

        return $fields;

    }

    /**
     * Prepare fields for feed field mapping.
     *
     * @return array
     */
    public function utm_fields_for_feed_setting()
    {

        $fields = array();

        $utm = [
            [
                'name'  => 'campaign_name',
                'label' => esc_html__('Campaign Name', 'ambitioncloud'),
            ],
            [
                'name'  => 'campaign_medium',
                'label' => esc_html__('Campaign Medium', 'ambitioncloud'),
            ],
            [
                'name'  => 'campaign_content',
                'label' => esc_html__('Campaign Content', 'ambitioncloud'),
            ],
            [
                'name'  => 'campaign_source',
                'label' => esc_html__('Campaign Source', 'ambitioncloud'),
            ],
            [
                'name'  => 'campaign_gclid',
                'label' => esc_html__('Campaign Gclid', 'ambitioncloud'),
            ],
            [
                'name'  => 'campaign_adsetname',
                'label' => esc_html__('Campaign Adsetname', 'ambitioncloud'),
            ],
            [
                'name'  => 'campaign_adname',
                'label' => esc_html__('Campaign Adname', 'ambitioncloud'),
            ],
            [
                'name'  => 'campaign_placement',
                'label' => esc_html__('Campaign Placement', 'ambitioncloud'),
            ],
            [
                'name'  => 'campaign_keyword',
                'label' => esc_html__('Campaign Keyword', 'ambitioncloud'),
            ],
            [
                'name'  => 'campaign_matchtype',
                'label' => esc_html__('Campaign Matchtype', 'ambitioncloud'),
            ],
            [
                'name'  => 'campaign_device',
                'label' => esc_html__('Campaign Device', 'ambitioncloud'),
            ],
        ];

        foreach ($utm as $index => $source) {
            $fields[] = [
                'name'     => esc_html__($source['name'], 'ambitioncloud'),
                'label'    => esc_html__($source['label'], 'ambitioncloud'),
                'required' => false,
            ];
        }

        return $fields;

    }

    /**
     * Prepare custom fields for feed field mapping.
     *
     * @return array
     */
    public function custom_fields_for_feed_setting()
    {

        $fields = array();

        /* If AmbitionCloud API credentials are invalid, return the fields array. */
        if (!$this->initialize_api()) {
            return $fields;
        }

        /* Get available AmbitionCloud fields. */
        $ac_fields = $this->api->get_custom_fields();

        /* If no AmbitionCloud fields exist, return the fields array. */
        if (empty($ac_fields)) {
            return $fields;
        }

        /* If AmbitionCloud fields exist, add them to the fields array. */
        if ($ac_fields['result_code'] == 1) {

            foreach ($ac_fields as $field) {

                if (!is_array($field)) {
                    continue;
                }

                $fields[] = array(
                    'label' => esc_html__($field['name'], 'ambitioncloud'),
                    'value' => esc_html__($field['key'], 'ambitioncloud'),
                );

            }

        }

        if (!empty($this->_new_custom_fields)) {

            foreach ($this->_new_custom_fields as $new_field) {

                $found_custom_field = false;
                foreach ($fields as $field) {

                    if ($field['value'] == $new_field['value']) {
                        $found_custom_field = true;
                    }

                }

                if (!$found_custom_field) {
                    $fields[] = array(
                        'label' => esc_html__($new_field['label'], 'ambitioncloud'),
                        'value' => esc_html__($new_field['value'], 'ambitioncloud'),
                    );
                }

            }

        }

        if (empty($fields)) {
            return $fields;
        }

        // Add standard "Select a Custom Field" option.
        $standard_field = array(
            array(
                'label' => esc_html__('Select Ambition Field', 'ambitioncloud'),
                'value' => '',
            ),
        );
        $fields = array_merge($standard_field, $fields);

        /* Add "Add Custom Field" to array. */
        $fields[] = array(
            'label' => esc_html__('Add Custom Field', 'ambitioncloud'),
            'value' => 'gf_custom',
        );

        return $fields;

    }

    /**
     * Prepare AmbitionCloud redirection forms for feed field.
     *
     * @return array
     */
    public function redirect_forms_feed_setting()
    {

        $forms = array(
            array(
                'label' => esc_html__('Select a Form', 'ambitioncloud'),
                'value' => '',
            ),
        );

        // If AmbitionCloud API credentials are invalid, return the forms array.
        if (!$this->initialize_api()) {
            return $forms;
        }

        // Get available AmbitionCloud redirect forms.
        $ac_redirect_forms = $this->api->form_get_redirect_forms();

        // Add AmbitionCloud forms to array and return it.
        if (!empty($ac_redirect_forms)) {

            foreach ($ac_redirect_forms as $form) {

                if (!is_array($form)) {
                    continue;
                }

                $forms[] = array(
                    'label' => esc_html__($form['name'], 'ambitioncloud'),
                    'value' => esc_html__($form['id'], 'ambitioncloud'),
                );

            }

        }

        return $forms;

    }

    /**
     * Prepare AmbitionCloud forms for feed field.
     *
     * @return array
     */
    public function forms_for_feed_setting()
    {

        $forms = array(
            array(
                'label' => esc_html__('Select a Form', 'ambitioncloud'),
                'value' => '',
            ),
        );

        // If AmbitionCloud API credentials are invalid, return the forms array.
        if (!$this->initialize_api()) {
            return $forms;
        }

        // Get list ID.
        $list_id = $this->get_setting('list');

        // Get available AmbitionCloud forms.
        $ac_forms = $this->api->get_forms();

        // Add AmbitionCloud forms to array and return it.
        if (!empty($ac_forms)) {

            foreach ($ac_forms as $form) {

                if (!is_array($form)) {
                    continue;
                }

                if ($form['sendoptin'] == 0 || !is_array($form['lists']) || !in_array($list_id, $form['lists'])) {
                    continue;
                }

                $forms[] = array(
                    'label' => esc_html__($form['name'], 'ambitioncloud'),
                    'value' => esc_html__($form['id'], 'ambitioncloud'),
                );

            }

        }

        return $forms;

    }

    // # HELPERS -------------------------------------------------------------------------------------------------------

    /**
     * Create new AmbitionCloud custom fields.
     *
     * @since Unknown
     *
     * @return array
     */
    public function create_new_custom_fields($field = array(), $field_value = array())
    {

        global $_gaddon_posted_settings;

        // If no custom fields are set or if the API credentials are invalid, return settings.
        if (empty($field_value) || !$this->initialize_api()) {
            return $field_value;
        }

        foreach ($field_value as $index => &$field) {

            // If no custom key is set, move on.
			if ( rgblank( $field['custom_key'] ) ) {
				continue;
			}

            // Update POST field to ensure front-end display is up-to-date.
            $_gaddon_posted_settings['custom_fields'][$index]['key']        = $field['custom_key'];
            $_gaddon_posted_settings['custom_fields'][$index]['custom_key'] = '';

            // Push to new custom fields array to update the UI.
            $this->_new_custom_fields[] = array(
                'label' => esc_html__($field['custom_key'], 'ambitioncloud'),
                'value' => esc_html__($field['value'], 'ambitioncloud'),
            );


        }

        return $field_value;

    }
    public function note_avatar()
    {
        return $this->get_base_url() . "/images/icon-72x72.png";
    }

    /**
     * Checks validity of AmbitionCloud API credentials and initializes API if valid.
     *
     * @return bool|null
     */
    public function initialize_api()
    {

        if ($this->api instanceof GF_AmbitionCloud_API) {
            return true;
        }

        /* Load the AmbitionCloud API library. */
        require_once 'includes/class-gf-ambitioncloud-api.php';

        /* Get the plugin settings */
        $settings = $this->get_saved_plugin_settings();

        /* If any of the account information fields are empty, return null. */
        if (rgempty('api_url', $settings) || rgempty('api_key', $settings)) {
            return null;
        }

        $ambitioncloud = new GF_AmbitionCloud_API($settings['api_url'], $settings['api_key']);

        try {

            /* Run API test. */
            $test                   = $ambitioncloud->auth_test();
            $this->teams_enabled    = sanitize_text_field($test['teams_enabled']);
            $this->default_team     = $test['default_team'];
            $this->default_referrer = $test['default_referrer'];

            /* Log that test passed. */
            $this->log_debug(__METHOD__ . '(): TENANT credentials are valid.');

            /* Assign AmbitionCloud object to the class. */
            $this->api = $ambitioncloud;

            return true;

        } catch (Exception $e) {

            /* Log that test failed. */
            $this->log_error(__METHOD__ . '(): TENANT credentials are invalid; ' . $e->getMessage());

            return false;

        }

    }

    /**
     * Gets the saved plugin settings, either from the database or the post request.
     *
     * This is a helper method that ensures the feedback callback receives the right value if the newest values
     * are posted to the settings page.
     *
     * @since 1.9
     *
     * @return array
     */
    private function get_saved_plugin_settings()
    {
        $prefix  = $this->is_gravityforms_supported('2.5') ? '_gform_setting' : '_gaddon_setting';
        $api_url = rgpost("{$prefix}_api_url");
        $api_key = rgpost("{$prefix}_api_key");

        $settings = $this->get_plugin_settings();

        if (!$this->is_plugin_settings($this->_slug) || !($api_url && $api_key)) {
            return $settings;
        }

        $settings['api_url'] = sanitize_url($api_url);
        $settings['api_key'] = sanitize_title($api_key);
        return $settings;
    }

    /**
     * Checks if TENANT Url is valid.
     *
     * This method has been deprecated in favor of initialize_api, as that method throws an exception and logs the
     * error message if the service is unable to connect. We need an API key to validate the TENANT URL.
     *
     * @since unknown
     * @deprecated 1.9
     *
     * @param string $api_url The TENANT Url.
     *
     * @return null
     */
    public function has_valid_api_url($api_url)
    {
        _deprecated_function(__METHOD__, '1.2');
        return null;
    }

    // # TO 1.2 MIGRATION ----------------------------------------------------------------------------------------------

    /**
     * Checks if a previous version was installed and if the tags setting needs migrating from field map to input field.
     *
     * @param string $previous_version The version number of the previously installed version.
     */
    public function upgrade($previous_version)
    {

        $previous_is_pre_tags_change = !empty($previous_version) && version_compare($previous_version, '1.2', '<');

        if ($previous_is_pre_tags_change) {

            $feeds = $this->get_feeds();

            foreach ($feeds as &$feed) {
                $merge_tag = '';

                if (!empty($feed['meta']['fields_tags'])) {

                    if (is_numeric($feed['meta']['fields_tags'])) {

                        $form             = GFAPI::get_form($feed['form_id']);
                        $field            = GFFormsModel::get_field($form, $feed['meta']['fields_tags']);
                        $field_merge_tags = GFCommon::get_field_merge_tags($field);

                        if ($field->id == $feed['meta']['fields_tags']) {

                            $merge_tag = $field_merge_tags[0]['tag'];

                        } else {

                            foreach ($field_merge_tags as $field_merge_tag) {

                                if (strpos($field_merge_tag['tag'], $feed['meta']['fields_tags']) !== false) {

                                    $merge_tag = $field_merge_tag['tag'];

                                }

                            }

                        }

                    } else {

                        if ($feed['meta']['fields_tags'] == 'date_created') {

                            $merge_tag = '{date_mdy}';

                        } else {

                            $merge_tag = '{' . $feed['meta']['fields_tags'] . '}';

                        }

                    }

                }

                $feed['meta']['tags'] = $merge_tag;
                unset($feed['meta']['fields_tags']);

                $this->update_feed_meta($feed['id'], $feed['meta']);

            }

        }

    }

}
