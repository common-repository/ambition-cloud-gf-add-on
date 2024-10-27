<?php

class GF_AmbitionCloud_API
{

    protected $api_url;
    protected $api_key;

    public function __construct($api_url, $api_key = null)
    {
        $this->api_url          = $api_url;
        $this->api_key          = $api_key;
        $this->teams_enabled    = false;
        $this->default_team     = false;
        $this->default_referrer = false;
    }

    public function default_options()
    {

        return array(
            'api_key'    => $this->api_key,
            'api_output' => 'json',
        );

    }

    public function make_request($action, $options = array(), $method = 'GET')
    {

        /* Build request URL. */
        $request_url = untrailingslashit($this->api_url) . '/api/' . $action;

        /**
         * Allows request timeout to Ambition Cloud to be changed. Timeout is in seconds
         *
         * @since 1.5
         */
        $timeout = apply_filters('gform_ambitioncloud_request_timeout', 20);

        /* Execute request based on method. */
        switch ($method) {

            case 'POST':

                $args = array(
                    'body'    => $options,
                    'timeout' => $timeout,
                    'headers' => [
                        'x-tenant-key' => $this->api_key,
                        'referer'      => get_site_url(),
                    ],
                );
                $response = wp_remote_post($request_url, $args);
                break;

            case 'GET':
                $args     = array('timeout' => $timeout);
                $response = wp_remote_get($request_url, $args);
                break;

        }

        /* If WP_Error, die. Otherwise, return decoded JSON. */
        if (is_wp_error($response)) {

            die('Request failed. ' . $response->get_error_message());

        } else {

            return json_decode($response['body'], true);

        }

    }

    /**
     * Test the provided API credentials.
     *
     * @access public
     * @return bool
     */
    public function auth_test()
    {

        /* Setup request URL. */
        $request_url = untrailingslashit($this->api_url) . '/api';
        /* Execute request. */
        $timeout = apply_filters('gform_ambitioncloud_request_timeout', 20);

        $response = wp_remote_post(
            $request_url,
            [
                'timeout' => $timeout,
                'headers' => [
                    'x-tenant-key' => $this->api_key,
                ],
            ]
        );

        /* If invalid content type, TENANT URL is invalid. */
        if (is_wp_error($response) || strpos($response['headers']['content-type'], 'application/json') != 0 && strpos($response['headers']['content-type'], 'application/json') > 0) {
            throw new Exception('Invalid TENANT URL.' . $request_url);
        }

        /* If result code is false, API key is invalid. */
        $response['body'] = json_decode($response['body'], true);
        if ($response['body']['result_code'] == 0) {
            throw new Exception('Invalid TENANT Key.');
        }

        if ($response['body']['teams_enabled']) {
            $this->teams_enabled = true;
        }

        if ($response['body']['default_team']) {
            $this->default_team = $response['body']['default_team'];
        }

        if ($response['body']['teams_enabled']) {
            $this->default_referrer = $response['body']['default_referrer'];
        }

        return $response['body'];

    }

    /**
     * Add a new custom list field.
     *
     * @access public
     *
     * @param array $custom_field
     *
     * @return array
     */
    public function add_custom_field($custom_field)
    {

        return $this->make_request('list_field_add', $custom_field, 'POST');

    }

    /**
     * Get all custom list fields.
     *
     * @access public
     * @return array
     */
    public function get_custom_fields()
    {

        return $this->make_request('default-fields', array('ids' => 'all'), 'POST');

    }

    /**
     * Get all forms in the system.
     *
     * @access public
     * @return array
     */
    public function add_form($form_name)
    {

        return $this->make_request('add-form', array('form_name' => $form_name), 'POST');

    }

    /**
     * Get forms in the system with the ability for continuation.
     *
     * @access public
     * @return array
     */
    public function form_get_redirect_forms()
    {

        return $this->make_request('redirect-forms', array(), 'POST');

    }

    /**
     * Get specific list.
     *
     * @access public
     *
     * @param int $list_id
     *
     * @return array
     */
    public function get_list($list_id)
    {

        return $this->make_request('list_view', array('id' => $list_id));

    }

    /**
     * Get all lists in the system.
     *
     * @access public
     * @return array
     */
    public function get_lists()
    {

        return $this->make_request('list_list', array('ids' => 'all'));

    }

    /**
     * Get all sources in the system.
     *
     * @access public
     * @return array
     */
    public function get_sources()
    {

        return $this->make_request('list-sources', array(), 'POST');

    }

    /**
     * Add or edit a lead.
     *
     * @access public
     *
     * @param mixed $lead
     *
     * @return array
     */
    public function add_lead($lead)
    {

        return $this->make_request('add-lead', $lead, 'POST');

    }

    /**
     * Add note to contact.
     */
    public function add_note($contact_id, $list_id, $note)
    {

        $request = array(
            'id'     => $contact_id,
            'listid' => $list_id,
            'note'   => $note,
        );

        return $this->make_request('contact_note_add', $request, 'POST');
    }

}
