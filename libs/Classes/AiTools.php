<?php
/**
 * Everything the assistant can look up or change.
 *
 * Reads run immediately, scoped to the caller's company and, for a portal
 * user, the caller's client. Changes are never made here: each one becomes a
 * proposal of one or more ordinary API calls, which the browser makes after
 * the person confirms. The endpoint for each change is fixed in this file and
 * never taken from the model, and the endpoint applies its own permission,
 * validation and plan checks exactly as it does for the screens.
 *
 * Endpoints save every field of a record, so changes to existing records are
 * filled in from the stored record first; a field the model leaves out keeps
 * its current value instead of being blanked.
 */
class AiTools {

    const MAX_ROWS = 300;
    const ADMIN_ROLE_ID = 1;

    const ITEM_RESULTS = array(
        'Not Assessed',
        'Implemented',
        'Partially Implemented',
        'Not Implemented',
        'Not Applicable'
    );

    const SCHEDULES = array(
        'Fixed dates',
        'Ongoing'
    );

    const DROPPED_COLUMNS = array(
        'file_key',
        'p_word',
        'reset_token',
        'reset_token_expires',
        'created_by',
        'updated_by',
        'deleted',
        'stripe_customer_id',
        'stripe_livemode',
        'stripe_invoice_id',
        'stripe_account_id',
        'stripe_payment_intent_id',
        'stripe_connect_account_id',
        'stripe_subscription_id',
        'platform_customer_id',
        'stripe_line_id',
        'stripe_coupon_id',
        'hosted_invoice_url',
        'invoice_pdf'
    );

    private $conversation_id;
    private $ai_model;
    private $clients_model;
    private $assessments_model;
    private $evidence_model;
    private $standards_model;
    private $invoices_model;
    private $subscriptions_model;
    private $dashboard_model;
    private $companies_model;
    private $users_model;

    public function __construct($conversation_id)
    {
        $this->conversation_id = (int) $conversation_id;
        $this->ai_model = new AiModel();
        $this->clients_model = new ClientsModel();
        $this->assessments_model = new AssessmentsModel();
        $this->evidence_model = new EvidenceModel();
        $this->standards_model = new StandardsModel();
        $this->invoices_model = new InvoicesModel();
        $this->subscriptions_model = new SubscriptionsModel();
        $this->dashboard_model = new DashboardModel();
        $this->companies_model = new CompaniesModel();
        $this->users_model = new UsersModel();
    }

    public function is_portal(): bool
    {
        return Session::get('user_type') === 'portal';
    }

    public function is_admin(): bool
    {
        return Session::get('user_type') === 'staff' && (int) Session::get('role_id') === self::ADMIN_ROLE_ID;
    }

    /* ----------------------------------------------------------- definitions */

    /** Tool definitions for the caller: portal, staff, or staff plus admin. */
    public function definitions(): array
    {
        $tools = array();

        foreach ($this->catalog() as $name => $tool) {

            if (!$this->in_scope($tool['scope'])) {
                continue;
            }

            $tools[] = array(
                'name' => $name,
                'description' => $tool['description'].($tool['change'] ? ' Needs the person\'s confirmation before it happens.' : ''),
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => count($tool['properties']) > 0 ? $tool['properties'] : new stdClass(),
                    'required' => $tool['required']
                )
            );
        }

        return $tools;
    }

    public function is_change(string $name): bool
    {
        $catalog = $this->catalog();

        return isset($catalog[$name]) && $catalog[$name]['change'] && $this->in_scope($catalog[$name]['scope']);
    }

    private function in_scope(string $scope): bool
    {
        if ($scope === 'portal') {
            return $this->is_portal();
        }

        if ($scope === 'staff') {
            return !$this->is_portal();
        }

        if ($scope === 'admin') {
            return $this->is_admin();
        }

        return true;
    }

    private function catalog(): array
    {
        $id = function (string $description) {
            return array(
                'type' => 'integer',
                'description' => $description
            );
        };

        $text = function (string $description) {
            return array(
                'type' => 'string',
                'description' => $description
            );
        };

        $choice = function (array $values, string $description) {
            return array(
                'type' => 'string',
                'enum' => $values,
                'description' => $description
            );
        };

        $flag = function (string $description) {
            return array(
                'type' => 'boolean',
                'description' => $description
            );
        };

        $tool = function (string $scope, bool $change, string $description, array $properties, array $required) {
            return array(
                'scope' => $scope,
                'change' => $change,
                'description' => $description,
                'properties' => $properties,
                'required' => $required
            );
        };

        $links = array(
            'type' => 'array',
            'description' => 'Pairs of evidence file and assessment control',
            'items' => array(
                'type' => 'object',
                'properties' => array(
                    'evidence_id' => $id('Evidence id'),
                    'item_id' => $id('Assessment control (item) id')
                ),
                'required' => array(
                    'evidence_id',
                    'item_id'
                )
            )
        );

        $file_to_vault = array(
            'file_name' => $text('Exact name of a file attached to, or created in, this conversation'),
            'client_id' => $id('Client whose evidence vault receives the file; a client portal user always files into their own vault'),
            'folder_id' => $id('Folder in that vault; omit for the top level'),
            'evidence_title' => $text('Title for the evidence record'),
            'description' => $text('Description for the evidence record')
        );

        $invoice_lines = array(
            'type' => 'array',
            'description' => 'Invoice lines. Amounts are in the invoice currency as decimals, e.g. "1250.00".',
            'items' => array(
                'type' => 'object',
                'properties' => array(
                    'item_description' => $text('What the line is for'),
                    'quantity' => $text('Quantity, e.g. "1" or "2.5"'),
                    'unit_amount' => $text('Price per unit, e.g. "450.00"'),
                    'discount_type' => $choice(array('None', 'Percent', 'Amount'), 'Discount kind'),
                    'discount_value' => $text('Discount percent or amount; "0" for none')
                ),
                'required' => array(
                    'item_description',
                    'quantity',
                    'unit_amount'
                )
            )
        );

        return array(

            'list_assessments' => $tool('portal', false, 'The person\'s own assessments, with progress and dates.', array(), array()),
            'list_assessment_controls' => $tool('any', false, 'Every control in an assessment with its result (and, for staff, notes and evidence count).', array(
                'assessment_id' => $id('Assessment id')
            ), array(
                'assessment_id'
            )),
            'list_evidence' => $tool('portal', false, 'Evidence files and folders shared with the person in their evidence vault.', array(), array()),
            'get_control_evidence' => $tool('any', false, 'Evidence files attached to one assessment control.', array(
                'item_id' => $id('Assessment control (item) id')
            ), array(
                'item_id'
            )),
            'list_my_invoices' => $tool('portal', false, 'The person\'s invoices and their payment state.', array(), array()),

            'get_overview' => $tool('staff', false, 'Dashboard figures: totals, assessment status, results, assessments and items needing attention.', array(), array()),
            'list_clients' => $tool('staff', false, 'All clients with contact details.', array(), array()),
            'get_client' => $tool('staff', false, 'One client with its assessments and evidence folders.', array(
                'client_id' => $id('Client id')
            ), array(
                'client_id'
            )),
            'list_all_assessments' => $tool('staff', false, 'Assessments with client, standard, status, progress and dates: all of them, or one client\'s when client_id is given.', array(
                'client_id' => $id('Client id; omit for every client')
            ), array()),
            'get_assessment_results' => $tool('staff', false, 'Result totals for an assessment, overall and by control family. Use for reports.', array(
                'assessment_id' => $id('Assessment id')
            ), array(
                'assessment_id'
            )),
            'search_evidence' => $tool('staff', false, 'Evidence files in a client\'s evidence vault, optionally filtered by words in the title, description or file name.', array(
                'client_id' => $id('Client id'),
                'term' => $text('Words to match; omit for all files')
            ), array(
                'client_id'
            )),
            'get_evidence_links' => $tool('staff', false, 'The assessment controls one evidence file is attached to.', array(
                'evidence_id' => $id('Evidence id')
            ), array(
                'evidence_id'
            )),
            'list_standards' => $tool('staff', false, 'Compliance standards available for assessments.', array(), array()),
            'list_standard_controls' => $tool('staff', false, 'The controls defined in one standard.', array(
                'standard_id' => $id('Standard id')
            ), array(
                'standard_id'
            )),
            'list_users' => $tool('admin', false, 'All user accounts in the organisation, staff and client portal.', array(), array()),
            'list_roles' => $tool('admin', false, 'The user roles and their ids.', array(), array()),
            'get_company_settings' => $tool('admin', false, 'The organisation\'s details, branding and security policy.', array(), array()),
            'list_invoices' => $tool('admin', false, 'All invoices with client, amount, due date and payment state.', array(), array()),
            'get_invoice' => $tool('admin', false, 'One invoice with its lines.', array(
                'invoice_id' => $id('Invoice id')
            ), array(
                'invoice_id'
            )),
            'list_subscriptions' => $tool('admin', false, 'Recurring client subscriptions.', array(), array()),

            'save_client' => $tool('staff', true, 'Add a client (omit client_id) or change one (give client_id and only the fields to change).', array(
                'client_id' => $id('Client id when changing an existing client'),
                'company_name' => $text('Client company name'),
                'contact_name' => $text('Main contact name'),
                'contact_title' => $text('Main contact job title'),
                'contact_email' => $text('Main contact email'),
                'contact_phone' => $text('Main contact phone'),
                'address_1' => $text('Street address'),
                'address_2' => $text('Street address, second line'),
                'city' => $text('City'),
                'state' => $text('State or region'),
                'postal_code' => $text('Postal code'),
                'country' => $text('Country'),
                'billing_name' => $text('Billing contact name'),
                'billing_title' => $text('Billing contact job title'),
                'billing_email' => $text('Billing contact email'),
                'billing_phone' => $text('Billing contact phone')
            ), array()),
            'delete_client' => $tool('staff', true, 'Delete a client.', array(
                'client_id' => $id('Client id')
            ), array(
                'client_id'
            )),
            'create_assessment' => $tool('staff', true, 'Start an assessment of a client against a standard. Fixed dates need a start and end date; an ongoing assessment has neither.', array(
                'client_id' => $id('Client id'),
                'standard_id' => $id('Standard id'),
                'assessment_name' => $text('Assessment name'),
                'schedule' => $choice(self::SCHEDULES, 'Fixed dates or Ongoing'),
                'start_date' => $text('Start date, YYYY-MM-DD; for Fixed dates'),
                'end_date' => $text('End date, YYYY-MM-DD; for Fixed dates')
            ), array(
                'client_id',
                'standard_id',
                'assessment_name',
                'schedule'
            )),
            'update_assessment' => $tool('staff', true, 'Rename an assessment or change its schedule and dates. Give only what changes; give schedule to change dates.', array(
                'assessment_id' => $id('Assessment id'),
                'assessment_name' => $text('New name'),
                'schedule' => $choice(self::SCHEDULES, 'Fixed dates or Ongoing'),
                'start_date' => $text('Start date, YYYY-MM-DD; for Fixed dates'),
                'end_date' => $text('End date, YYYY-MM-DD; for Fixed dates')
            ), array(
                'assessment_id'
            )),
            'delete_assessment' => $tool('staff', true, 'Delete an assessment.', array(
                'assessment_id' => $id('Assessment id')
            ), array(
                'assessment_id'
            )),
            'complete_assessment' => $tool('staff', true, 'Mark an assessment complete.', array(
                'assessment_id' => $id('Assessment id')
            ), array(
                'assessment_id'
            )),
            'set_control_results' => $tool('staff', true, 'Record results for one or many assessment controls in one step.', array(
                'results' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'item_id' => $id('Assessment control (item) id'),
                            'item_result' => $choice(self::ITEM_RESULTS, 'Result')
                        ),
                        'required' => array(
                            'item_id',
                            'item_result'
                        )
                    )
                )
            ), array(
                'results'
            )),
            'link_evidence' => $tool('any', true, 'Attach evidence files to assessment controls, one or many pairs in one step.', array(
                'links' => $links
            ), array(
                'links'
            )),
            'unlink_evidence' => $tool('any', true, 'Detach evidence files from assessment controls, one or many pairs in one step.', array(
                'links' => $links
            ), array(
                'links'
            )),
            'save_file_to_evidence' => $tool('any', true, 'File a document attached to this conversation, or a report you created, into a client\'s evidence vault.', $file_to_vault, array(
                'file_name',
                'evidence_title'
            )),
            'create_folder' => $tool('staff', true, 'Create a folder in a client\'s evidence vault.', array(
                'client_id' => $id('Client id'),
                'folder_name' => $text('Folder name'),
                'parent_id' => $id('Parent folder id; omit for the top level')
            ), array(
                'client_id',
                'folder_name'
            )),
            'rename_folder' => $tool('staff', true, 'Rename an evidence folder.', array(
                'folder_id' => $id('Folder id'),
                'folder_name' => $text('New name')
            ), array(
                'folder_id',
                'folder_name'
            )),
            'delete_folder' => $tool('staff', true, 'Delete an evidence folder.', array(
                'folder_id' => $id('Folder id')
            ), array(
                'folder_id'
            )),
            'move_evidence' => $tool('staff', true, 'Move an evidence file to another folder in the same client\'s vault.', array(
                'evidence_id' => $id('Evidence id'),
                'folder_id' => $id('Destination folder id; 0 for the top level')
            ), array(
                'evidence_id',
                'folder_id'
            )),
            'update_evidence' => $tool('staff', true, 'Change an evidence file\'s title or description.', array(
                'evidence_id' => $id('Evidence id'),
                'evidence_title' => $text('New title'),
                'description' => $text('New description')
            ), array(
                'evidence_id'
            )),
            'set_evidence_private' => $tool('staff', true, 'Make an evidence file private to staff, or share it with the client.', array(
                'evidence_id' => $id('Evidence id'),
                'private' => $flag('true to hide it from the client, false to share it')
            ), array(
                'evidence_id',
                'private'
            )),
            'delete_evidence' => $tool('any', true, 'Delete an evidence file. Clients can only delete files they uploaded that are not attached to a control.', array(
                'evidence_id' => $id('Evidence id')
            ), array(
                'evidence_id'
            )),
            'save_standard' => $tool('staff', true, 'Add a standard (omit standard_id) or change one (give standard_id and only the fields to change).', array(
                'standard_id' => $id('Standard id when changing'),
                'standard_name' => $text('Name'),
                'short_code' => $text('Short code, e.g. ISO27001'),
                'version' => $text('Version'),
                'description' => $text('Description'),
                'standard_status' => $choice(array('Active', 'Archived'), 'Status')
            ), array()),
            'duplicate_standard' => $tool('staff', true, 'Copy a standard with all its controls under a new name.', array(
                'standard_id' => $id('Standard to copy'),
                'standard_name' => $text('Name of the copy'),
                'short_code' => $text('Short code of the copy'),
                'version' => $text('Version of the copy')
            ), array(
                'standard_id',
                'standard_name',
                'short_code'
            )),
            'delete_standard' => $tool('staff', true, 'Delete a standard.', array(
                'standard_id' => $id('Standard id')
            ), array(
                'standard_id'
            )),
            'save_controls' => $tool('staff', true, 'Add controls to a standard or change existing ones, one or many in one step. Give control_id only when changing.', array(
                'standard_id' => $id('Standard id'),
                'controls' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'control_id' => $id('Control id when changing'),
                            'control_identifier' => $text('Identifier, e.g. A.5.1'),
                            'control_title' => $text('Title'),
                            'description' => $text('Description'),
                            'family' => $text('Family or domain')
                        ),
                        'required' => array()
                    )
                )
            ), array(
                'standard_id',
                'controls'
            )),
            'delete_control' => $tool('staff', true, 'Delete a control from a standard.', array(
                'control_id' => $id('Control id')
            ), array(
                'control_id'
            )),
            'update_my_profile' => $tool('staff', true, 'Change the signed-in person\'s own name, username or email.', array(
                'first_name' => $text('First name'),
                'last_name' => $text('Last name'),
                'u_name' => $text('Username'),
                'user_email' => $text('Email')
            ), array()),

            'save_user' => $tool('admin', true, 'Add a user (omit user_id; they are emailed an invite) or change one (give user_id and only the fields to change). Portal users belong to a client.', array(
                'user_id' => $id('User id when changing'),
                'first_name' => $text('First name'),
                'last_name' => $text('Last name'),
                'u_name' => $text('Username'),
                'user_email' => $text('Email'),
                'user_status' => $choice(array('Active', 'Disabled'), 'Status'),
                'user_type' => $choice(array('staff', 'portal'), 'staff, or portal for a client login'),
                'role_id' => $id('Role id for staff (see list_roles)'),
                'client_id' => $id('Client id for a portal user')
            ), array()),
            'delete_user' => $tool('admin', true, 'Remove a user account.', array(
                'user_id' => $id('User id')
            ), array(
                'user_id'
            )),
            'reset_user_password' => $tool('admin', true, 'Email a user a password reset link.', array(
                'user_id' => $id('User id')
            ), array(
                'user_id'
            )),
            'update_company' => $tool('admin', true, 'Change the organisation\'s details or branding. Give only the fields to change.', array(
                'company_name' => $text('Organisation name'),
                'website' => $text('Website, starting with https://'),
                'email_domain' => $text('Email domain, e.g. example.com'),
                'address_1' => $text('Street address'),
                'address_2' => $text('Street address, second line'),
                'city' => $text('City'),
                'state' => $text('State or region'),
                'postal_code' => $text('Postal code'),
                'country' => $text('Country'),
                'timezone' => $text('PHP timezone, e.g. Europe/Zurich'),
                'time_format' => $choice(array('H:i', 'H:i:s', 'g:i A', 'g:i a', 'g:i:s A'), 'Time format'),
                'brand_color' => $text('Main colour, #RRGGBB'),
                'brand_color_secondary' => $text('Secondary colour, #RRGGBB'),
                'brand_color_accent' => $text('Accent colour, #RRGGBB')
            ), array()),
            'update_security_policy' => $tool('admin', true, 'Change the sign-in security policy. Give only the settings to change. Note: multi-factor sign-in is stored but not yet enforced by the application.', array(
                'session_timeout_enabled' => $flag('Sign idle users out'),
                'session_timeout_minutes' => $id('Idle minutes before sign-out, 5 to 1440'),
                'password_expiry_enabled' => $flag('Require periodic password changes'),
                'password_expiry_days' => $id('Days between changes, 1 to 3650'),
                'account_lockout_enabled' => $flag('Lock accounts after failed sign-ins'),
                'lockout_attempts' => $id('Failed attempts before lockout, 1 to 100'),
                'lockout_minutes' => $id('Lockout length in minutes, 1 to 1440'),
                'mfa_enabled' => $flag('Multi-factor sign-in'),
                'mfa_methods' => $text('Comma-separated: authenticator, email')
            ), array()),
            'remove_logo' => $tool('admin', true, 'Remove the organisation\'s logo.', array(), array()),
            'save_invoice' => $tool('admin', true, 'Create a draft invoice (omit invoice_id) or change a draft (give invoice_id; lines replace all existing lines when given).', array(
                'invoice_id' => $id('Draft invoice id when changing'),
                'client_id' => $id('Client billed'),
                'due_date' => $text('Due date, YYYY-MM-DD'),
                'invoice_footer' => $text('Footer text'),
                'lines' => $invoice_lines
            ), array()),
            'send_invoice' => $tool('admin', true, 'Finalise a draft invoice and email it to the client for payment.', array(
                'invoice_id' => $id('Invoice id'),
                'recipient_name' => $text('Recipient name; omit for the client billing contact'),
                'recipient_email' => $text('Recipient email; omit for the client billing contact')
            ), array(
                'invoice_id'
            )),
            'resend_invoice' => $tool('admin', true, 'Email an open invoice to the client again.', array(
                'invoice_id' => $id('Invoice id'),
                'recipient_name' => $text('Recipient name; omit for the client billing contact'),
                'recipient_email' => $text('Recipient email; omit for the client billing contact')
            ), array(
                'invoice_id'
            )),
            'void_invoice' => $tool('admin', true, 'Void an open invoice so it can no longer be paid.', array(
                'invoice_id' => $id('Invoice id')
            ), array(
                'invoice_id'
            )),
            'delete_invoice' => $tool('admin', true, 'Delete a draft invoice.', array(
                'invoice_id' => $id('Invoice id')
            ), array(
                'invoice_id'
            )),
            'save_subscription' => $tool('admin', true, 'Create a draft recurring subscription (omit subscription_id) or change a draft.', array(
                'subscription_id' => $id('Draft subscription id when changing'),
                'client_id' => $id('Client billed'),
                'subscription_name' => $text('Name shown on invoices'),
                'unit_amount' => $text('Price per period, e.g. "1500.00"'),
                'quantity' => $id('Quantity'),
                'billing_interval' => $choice(array('Month', 'Quarter', 'Year'), 'Billing period'),
                'due_days' => $id('Days to pay each invoice'),
                'start_date' => $text('Start date, YYYY-MM-DD; omit to start on activation')
            ), array()),
            'activate_subscription' => $tool('admin', true, 'Start billing a draft subscription.', array(
                'subscription_id' => $id('Subscription id')
            ), array(
                'subscription_id'
            )),
            'cancel_subscription' => $tool('admin', true, 'Cancel an active subscription.', array(
                'subscription_id' => $id('Subscription id')
            ), array(
                'subscription_id'
            )),
            'delete_subscription' => $tool('admin', true, 'Delete a draft subscription.', array(
                'subscription_id' => $id('Subscription id')
            ), array(
                'subscription_id'
            ))
        );
    }

    /* ----------------------------------------------------------------- reads */

    /** The data for a read tool, or null when it is not found or not allowed. */
    public function read(string $name, array $input)
    {
        if ($this->is_portal()) {
            return $this->portal_read($name, $input);
        }

        return $this->staff_read($name, $input);
    }

    private function staff_read(string $name, array $input)
    {
        $company_id = Session::get('company_id');

        if ($name === 'get_overview') {
            return array(
                'totals' => self::rows($this->dashboard_model->totals($company_id)),
                'assessment_status' => self::rows($this->dashboard_model->assessment_status($company_id)),
                'results' => self::rows($this->dashboard_model->result_totals($company_id)),
                'by_client' => self::rows($this->dashboard_model->by_client($company_id)),
                'assessments' => self::rows($this->dashboard_model->assessments($company_id)),
                'needs_attention' => self::rows($this->dashboard_model->attention($company_id))
            );
        }

        if ($name === 'list_clients') {
            return self::rows($this->clients_model->load_clients($company_id));
        }

        if ($name === 'get_client') {
            $client_id = (int) ($input['client_id'] ?? 0);
            $client = $this->clients_model->get_client($client_id, $company_id);
            if (count($client) !== 1) {
                return null;
            }
            return array(
                'client' => self::rows($client)[0],
                'assessments' => self::rows($this->assessments_model->load_client_assessments($client_id, $company_id)),
                'evidence_folders' => self::rows($this->evidence_model->load_folders($client_id, $company_id))
            );
        }

        if ($name === 'list_all_assessments') {
            $client_id = (int) ($input['client_id'] ?? 0);
            if ($client_id === 0) {
                return self::rows($this->assessments_model->load_all_assessments($company_id));
            }
            if (count($this->clients_model->get_client($client_id, $company_id)) !== 1) {
                return null;
            }
            return self::rows($this->assessments_model->load_client_assessments($client_id, $company_id));
        }

        if ($name === 'list_assessment_controls') {
            $assessment_id = (int) ($input['assessment_id'] ?? 0);
            if (count($this->assessments_model->get_assessment($assessment_id, $company_id)) !== 1) {
                return null;
            }
            return self::rows($this->assessments_model->load_controls($assessment_id, $company_id));
        }

        if ($name === 'get_assessment_results') {
            $assessment_id = (int) ($input['assessment_id'] ?? 0);
            $assessment = $this->assessments_model->get_assessment($assessment_id, $company_id);
            if (count($assessment) !== 1) {
                return null;
            }
            return array(
                'assessment' => self::rows($assessment)[0],
                'totals' => self::rows($this->assessments_model->result_totals($assessment_id, $company_id)),
                'by_family' => self::rows($this->assessments_model->results_by_family($assessment_id, $company_id))
            );
        }

        if ($name === 'get_control_evidence') {
            $item_id = (int) ($input['item_id'] ?? 0);
            if (count($this->assessments_model->get_item($item_id, $company_id)) !== 1) {
                return null;
            }
            return self::rows($this->evidence_model->load_item_evidence($item_id, $company_id));
        }

        if ($name === 'search_evidence') {
            $client_id = (int) ($input['client_id'] ?? 0);
            if (count($this->clients_model->get_client($client_id, $company_id)) !== 1) {
                return null;
            }
            return self::rows($this->evidence_model->search_evidence($client_id, $company_id, trim((string) ($input['term'] ?? '')), 200, 0));
        }

        if ($name === 'get_evidence_links') {
            $evidence_id = (int) ($input['evidence_id'] ?? 0);
            if (count($this->evidence_model->get_evidence($evidence_id, $company_id)) !== 1) {
                return null;
            }
            return self::rows($this->evidence_model->load_evidence_links($evidence_id, $company_id));
        }

        if ($name === 'list_standards') {
            return self::rows($this->standards_model->load_standards($company_id));
        }

        if ($name === 'list_standard_controls') {
            $standard_id = (int) ($input['standard_id'] ?? 0);
            if (count($this->standards_model->get_standard($standard_id, $company_id)) !== 1) {
                return null;
            }
            return self::rows($this->standards_model->load_controls($standard_id, $company_id));
        }

        if (!$this->is_admin()) {
            return null;
        }

        if ($name === 'list_users') {
            return self::rows($this->users_model->get_users_by_company($company_id));
        }

        if ($name === 'list_roles') {
            return self::rows($this->users_model->get_roles());
        }

        if ($name === 'get_company_settings') {
            return self::rows($this->companies_model->get_company($company_id));
        }

        if ($name === 'list_invoices') {
            return self::rows($this->invoices_model->load_invoices($company_id));
        }

        if ($name === 'get_invoice') {
            $invoice_id = (int) ($input['invoice_id'] ?? 0);
            $invoice = $this->invoices_model->get_invoice($invoice_id, $company_id);
            if (count($invoice) !== 1) {
                return null;
            }
            return array(
                'invoice' => self::rows($invoice)[0],
                'lines' => self::rows($this->invoices_model->get_invoice_items($invoice_id))
            );
        }

        if ($name === 'list_subscriptions') {
            return self::rows($this->subscriptions_model->load_subscriptions($company_id));
        }

        return null;
    }

    /**
     * client_id always comes from the session. Notes on controls are the
     * assessor's working notes, which the portal never shows, so neither does
     * this.
     */
    private function portal_read(string $name, array $input)
    {
        $company_id = Session::get('company_id');
        $client_id = Session::get('client_id');

        if ($name === 'list_assessments') {
            return self::rows($this->assessments_model->load_client_assessments($client_id, $company_id));
        }

        if ($name === 'list_my_invoices') {
            return self::rows($this->invoices_model->portal_invoices($client_id, $company_id));
        }

        if ($name === 'list_evidence') {
            return array(
                'folders' => self::rows($this->evidence_model->portal_folders($client_id, $company_id)),
                'files' => self::rows($this->evidence_model->portal_evidence($client_id, $company_id))
            );
        }

        if ($name === 'list_assessment_controls') {
            $assessment_id = (int) ($input['assessment_id'] ?? 0);
            if (count($this->assessments_model->client_assessment($assessment_id, $client_id, $company_id)) !== 1) {
                return null;
            }
            return self::rows($this->assessments_model->portal_items($assessment_id, $client_id, $company_id));
        }

        if ($name === 'get_control_evidence') {
            $item_id = (int) ($input['item_id'] ?? 0);
            if ($this->portal_item($item_id) === null) {
                return null;
            }
            return self::rows($this->evidence_model->portal_item_evidence($item_id, $client_id, $company_id));
        }

        return null;
    }

    private function portal_item($item_id)
    {
        $item = $this->assessments_model->get_item((int) $item_id, Session::get('company_id'));

        if (count($item) !== 1 || (int) $item[0]['client_id'] !== (int) Session::get('client_id')) {
            return null;
        }

        return $item[0];
    }

    /** Evidence a portal user may see: in their own client's vault and not private. */
    private function portal_evidence($evidence_id)
    {
        $evidence = $this->evidence_model->get_evidence((int) $evidence_id, Session::get('company_id'));

        if (count($evidence) !== 1 || (int) $evidence[0]['evidence_private'] === 1 || (int) $evidence[0]['client_id'] !== (int) Session::get('client_id')) {
            return null;
        }

        return $evidence[0];
    }

    /**
     * Rows as the model should see them: stored text decoded, internal and
     * payment-provider columns removed, long lists capped with a note.
     */
    private static function rows($rows, array $also_drop = array()): array
    {
        if (!is_array($rows)) {
            return array();
        }

        $drop = array_merge(self::DROPPED_COLUMNS, $also_drop);
        $out = array();

        foreach (array_slice($rows, 0, self::MAX_ROWS) as $row) {
            $clean = array();
            foreach ($row as $key => $value) {
                if (in_array($key, $drop, true) || substr((string) $key, -4) === '_sql') {
                    continue;
                }
                $clean[$key] = is_string($value) ? html_entity_decode($value, ENT_QUOTES, 'UTF-8') : $value;
            }
            $out[] = $clean;
        }

        if (count($rows) > self::MAX_ROWS) {
            $out[] = array(
                'note' => 'Showing the first '.self::MAX_ROWS.' of '.count($rows).' rows.'
            );
        }

        return $out;
    }

    /* ------------------------------------------------------------- proposals */

    /**
     * A change as the person will confirm it: a one-line summary, the fields
     * shown on the card, and the API calls the browser makes. Returns
     * array('error' => ...) instead when the change cannot be proposed.
     */
    public function propose(string $name, array $input): array
    {
        $builder = 'propose_'.$name;

        if (!$this->is_change($name) || !method_exists($this, $builder)) {
            return self::fail('That change is not available to this person.');
        }

        return $this->$builder($input);
    }

    private function propose_save_client(array $input): array
    {
        $labels = array(
            'company_name' => 'Company',
            'contact_name' => 'Contact',
            'contact_title' => 'Contact title',
            'contact_email' => 'Contact email',
            'contact_phone' => 'Contact phone',
            'address_1' => 'Address',
            'address_2' => 'Address line 2',
            'city' => 'City',
            'state' => 'State',
            'postal_code' => 'Postal code',
            'country' => 'Country',
            'billing_name' => 'Billing contact',
            'billing_title' => 'Billing contact title',
            'billing_email' => 'Billing email',
            'billing_phone' => 'Billing phone'
        );

        $client_id = (int) ($input['client_id'] ?? 0);
        $current = array();

        if ($client_id > 0) {
            $current = $this->first($this->clients_model->get_client($client_id, Session::get('company_id')));
            if ($current === null) {
                return self::fail('Client '.$client_id.' was not found.');
            }
        }

        $params = $this->merge($labels, $input, $current);
        $params['client_id'] = $client_id;

        $summary = $client_id > 0
            ? 'Update client "'.self::plain($current['company_name']).'"'
            : 'Add client "'.$params['company_name'].'"';

        return self::proposal($summary, 'save_client', $params, $this->changed_fields($labels, $input, $params, $client_id === 0));
    }

    private function propose_delete_client(array $input): array
    {
        $client = $this->first($this->clients_model->get_client((int) ($input['client_id'] ?? 0), Session::get('company_id')));

        if ($client === null) {
            return self::fail('That client was not found.');
        }

        return self::proposal('Delete client "'.self::plain($client['company_name']).'"', 'delete_client', array(
            'client_id' => (int) $client['id']
        ), array(
            'Client' => self::plain($client['company_name'])
        ));
    }

    private function propose_create_assessment(array $input): array
    {
        $company_id = Session::get('company_id');
        $client = $this->first($this->clients_model->get_client((int) ($input['client_id'] ?? 0), $company_id));
        $standard = $this->first($this->standards_model->get_standard((int) ($input['standard_id'] ?? 0), $company_id));

        if ($client === null || $standard === null) {
            return self::fail('That client or standard was not found.');
        }

        $schedule = (string) ($input['schedule'] ?? '');

        if (!in_array($schedule, self::SCHEDULES, true)) {
            return self::fail('schedule must be one of: '.implode(', ', self::SCHEDULES));
        }

        $fixed = $schedule === 'Fixed dates';

        $params = array(
            'client_id' => (int) $client['id'],
            'standard_id' => (int) $standard['id'],
            'assessment_name' => trim((string) ($input['assessment_name'] ?? '')),
            'schedule' => $schedule,
            'start_date' => $fixed ? trim((string) ($input['start_date'] ?? '')) : '',
            'end_date' => $fixed ? trim((string) ($input['end_date'] ?? '')) : ''
        );

        $fields = array(
            'Client' => self::plain($client['company_name']),
            'Standard' => self::plain($standard['standard_name']),
            'Name' => $params['assessment_name'],
            'Schedule' => $schedule
        );

        if ($fixed) {
            $fields['Start'] = $params['start_date'];
            $fields['End'] = $params['end_date'];
        }

        return self::proposal('Start assessment "'.$params['assessment_name'].'"', 'create_assessment', $params, $fields);
    }

    private function propose_update_assessment(array $input): array
    {
        $assessment = $this->assessment($input);

        if ($assessment === null) {
            return self::fail('That assessment was not found.');
        }

        $current_name = self::plain($assessment['assessment_name']);
        $name = array_key_exists('assessment_name', $input) ? trim((string) $input['assessment_name']) : $current_name;

        $params = array(
            'assessment_id' => (int) $assessment['id'],
            'assessment_name' => $name
        );

        $fields = array();

        if ($name !== $current_name) {
            $fields['Name'] = $name;
        }

        if (array_key_exists('schedule', $input)) {

            $schedule = (string) $input['schedule'];

            if (!in_array($schedule, self::SCHEDULES, true)) {
                return self::fail('schedule must be one of: '.implode(', ', self::SCHEDULES));
            }

            $fixed = $schedule === 'Fixed dates';
            $params['schedule'] = $schedule;
            $params['start_date'] = $fixed ? trim((string) ($input['start_date'] ?? substr((string) ($assessment['start_date'] ?? ''), 0, 10))) : '';
            $params['end_date'] = $fixed ? trim((string) ($input['end_date'] ?? substr((string) ($assessment['end_date'] ?? ''), 0, 10))) : '';
            $fields['Schedule'] = $schedule;

            if ($fixed) {
                $fields['Start'] = $params['start_date'];
                $fields['End'] = $params['end_date'];
            }
        }

        if (count($fields) === 0) {
            return self::fail('Give a new name, or a schedule with its dates.');
        }

        return self::proposal('Update assessment "'.$current_name.'"', 'save_assessment', $params, $fields);
    }

    private function propose_delete_assessment(array $input): array
    {
        $assessment = $this->assessment($input);

        if ($assessment === null) {
            return self::fail('That assessment was not found.');
        }

        return self::proposal('Delete assessment "'.self::plain($assessment['assessment_name']).'"', 'delete_assessment', array(
            'assessment_id' => (int) $assessment['id']
        ), array(
            'Assessment' => self::plain($assessment['assessment_name'])
        ));
    }

    private function propose_complete_assessment(array $input): array
    {
        $assessment = $this->assessment($input);

        if ($assessment === null) {
            return self::fail('That assessment was not found.');
        }

        return self::proposal('Mark "'.self::plain($assessment['assessment_name']).'" complete', 'complete_assessment', array(
            'assessment_id' => (int) $assessment['id']
        ), array(
            'Assessment' => self::plain($assessment['assessment_name'])
        ));
    }

    private function propose_set_control_results(array $input): array
    {
        $results = is_array($input['results'] ?? null) ? $input['results'] : array();

        if (count($results) === 0 || count($results) > 500) {
            return self::fail('Give between 1 and 500 results.');
        }

        $calls = array();
        $fields = array();

        foreach ($results as $result) {

            $item = $this->first($this->assessments_model->get_item((int) ($result['item_id'] ?? 0), Session::get('company_id')));

            if ($item === null) {
                return self::fail('Assessment control '.(int) ($result['item_id'] ?? 0).' was not found.');
            }

            if (!in_array($result['item_result'] ?? '', self::ITEM_RESULTS, true)) {
                return self::fail('item_result must be one of: '.implode(', ', self::ITEM_RESULTS));
            }

            $calls[] = array(
                'endpoint' => 'save_item',
                'params' => array(
                    'item_id' => (int) $item['id'],
                    'item_result' => $result['item_result']
                )
            );

            $fields[self::plain($item['control_identifier'])] = $result['item_result'];
        }

        $summary = count($calls) === 1
            ? 'Set '.array_key_first($fields).' to '.$calls[0]['params']['item_result']
            : 'Record results for '.count($calls).' controls';

        return self::batch($summary, $calls, $fields);
    }

    private function propose_link_evidence(array $input): array
    {
        return $this->links($input, true);
    }

    private function propose_unlink_evidence(array $input): array
    {
        return $this->links($input, false);
    }

    private function links(array $input, bool $attach): array
    {
        $pairs = is_array($input['links'] ?? null) ? $input['links'] : array();

        if (count($pairs) === 0 || count($pairs) > 500) {
            return self::fail('Give between 1 and 500 pairs.');
        }

        $endpoint = ($this->is_portal() ? 'portal_' : '').($attach ? 'link_evidence' : 'unlink_evidence');
        $calls = array();
        $fields = array();

        foreach ($pairs as $pair) {

            if ($this->is_portal()) {
                $evidence = $this->portal_evidence($pair['evidence_id'] ?? 0);
                $item = $this->portal_item($pair['item_id'] ?? 0);
            } else {
                $evidence = $this->first($this->evidence_model->get_evidence((int) ($pair['evidence_id'] ?? 0), Session::get('company_id')));
                $item = $this->first($this->assessments_model->get_item((int) ($pair['item_id'] ?? 0), Session::get('company_id')));
            }

            if ($evidence === null || $item === null) {
                return self::fail('Evidence '.(int) ($pair['evidence_id'] ?? 0).' or control '.(int) ($pair['item_id'] ?? 0).' was not found.');
            }

            $calls[] = array(
                'endpoint' => $endpoint,
                'params' => array(
                    'evidence_id' => (int) $evidence['id'],
                    'item_id' => (int) $item['id']
                )
            );

            $fields[] = array(
                'label' => self::plain($item['control_identifier']),
                'value' => self::plain($evidence['evidence_title'])
            );
        }

        $summary = ($attach ? 'Attach ' : 'Detach ').(count($calls) === 1 ? 'evidence' : count($calls).' evidence links');

        return array(
            'summary' => $summary,
            'calls' => $calls,
            'fields' => $fields
        );
    }

    private function propose_save_file_to_evidence(array $input): array
    {
        $file = $this->first($this->ai_model->find_conversation_file($this->conversation_id, trim((string) ($input['file_name'] ?? ''))));

        if ($file === null || empty($file['file_key'])) {
            return self::fail('No file named "'.(string) ($input['file_name'] ?? '').'" is in this conversation.');
        }

        $client_id = $this->is_portal() ? (int) Session::get('client_id') : (int) ($input['client_id'] ?? 0);
        $client = $this->first($this->clients_model->get_client($client_id, Session::get('company_id')));

        if ($client === null) {
            return self::fail('Give the client_id of an existing client.');
        }

        $params = array(
            'attachment_id' => (int) $file['id'],
            'client_id' => $client_id,
            'folder_id' => (int) ($input['folder_id'] ?? 0),
            'evidence_title' => trim((string) ($input['evidence_title'] ?? '')),
            'description' => trim((string) ($input['description'] ?? ''))
        );

        $fields = array(
            'File' => $file['file_name'],
            'Client' => self::plain($client['company_name']),
            'Title' => $params['evidence_title'],
            'Description' => $params['description']
        );

        if ($params['folder_id'] > 0) {
            $folder = $this->first($this->evidence_model->get_folder($params['folder_id'], Session::get('company_id')));
            if ($folder === null || (int) $folder['client_id'] !== $client_id) {
                return self::fail('That folder is not in that client\'s evidence vault.');
            }
            $fields['Folder'] = self::plain($folder['folder_name']);
        }

        return self::proposal('Save "'.$file['file_name'].'" to the evidence vault', 'ai_file_evidence', $params, $fields);
    }

    private function propose_create_folder(array $input): array
    {
        $client = $this->first($this->clients_model->get_client((int) ($input['client_id'] ?? 0), Session::get('company_id')));

        if ($client === null) {
            return self::fail('That client was not found.');
        }

        $params = array(
            'folder_id' => 0,
            'client_id' => (int) $client['id'],
            'parent_id' => (int) ($input['parent_id'] ?? 0),
            'folder_name' => trim((string) ($input['folder_name'] ?? ''))
        );

        return self::proposal('Create folder "'.$params['folder_name'].'"', 'save_folder', $params, array(
            'Client' => self::plain($client['company_name']),
            'Folder' => $params['folder_name']
        ));
    }

    private function propose_rename_folder(array $input): array
    {
        $folder = $this->first($this->evidence_model->get_folder((int) ($input['folder_id'] ?? 0), Session::get('company_id')));

        if ($folder === null) {
            return self::fail('That folder was not found.');
        }

        $name = trim((string) ($input['folder_name'] ?? ''));

        return self::proposal('Rename folder "'.self::plain($folder['folder_name']).'"', 'save_folder', array(
            'folder_id' => (int) $folder['id'],
            'client_id' => (int) $folder['client_id'],
            'parent_id' => (int) $folder['parent_id'],
            'folder_name' => $name
        ), array(
            'From' => self::plain($folder['folder_name']),
            'To' => $name
        ));
    }

    private function propose_delete_folder(array $input): array
    {
        $folder = $this->first($this->evidence_model->get_folder((int) ($input['folder_id'] ?? 0), Session::get('company_id')));

        if ($folder === null) {
            return self::fail('That folder was not found.');
        }

        return self::proposal('Delete folder "'.self::plain($folder['folder_name']).'"', 'delete_folder', array(
            'folder_id' => (int) $folder['id']
        ), array(
            'Folder' => self::plain($folder['folder_name'])
        ));
    }

    private function propose_move_evidence(array $input): array
    {
        $evidence = $this->first($this->evidence_model->get_evidence((int) ($input['evidence_id'] ?? 0), Session::get('company_id')));

        if ($evidence === null) {
            return self::fail('That evidence file was not found.');
        }

        $folder_id = (int) ($input['folder_id'] ?? 0);
        $destination = 'Top level';

        if ($folder_id > 0) {
            $folder = $this->first($this->evidence_model->get_folder($folder_id, Session::get('company_id')));
            if ($folder === null || (int) $folder['client_id'] !== (int) $evidence['client_id']) {
                return self::fail('That folder is not in the same client\'s evidence vault.');
            }
            $destination = self::plain($folder['folder_name']);
        }

        return self::proposal('Move "'.self::plain($evidence['evidence_title']).'"', 'move_evidence', array(
            'evidence_id' => (int) $evidence['id'],
            'folder_id' => $folder_id
        ), array(
            'File' => self::plain($evidence['evidence_title']),
            'To' => $destination
        ));
    }

    private function propose_update_evidence(array $input): array
    {
        $evidence = $this->first($this->evidence_model->get_evidence((int) ($input['evidence_id'] ?? 0), Session::get('company_id')));

        if ($evidence === null) {
            return self::fail('That evidence file was not found.');
        }

        $labels = array(
            'evidence_title' => 'Title',
            'description' => 'Description'
        );

        $params = $this->merge($labels, $input, $evidence);
        $params['evidence_id'] = (int) $evidence['id'];

        return self::proposal('Update evidence "'.self::plain($evidence['evidence_title']).'"', 'save_evidence', $params, $this->changed_fields($labels, $input, $params, false));
    }

    private function propose_set_evidence_private(array $input): array
    {
        $evidence = $this->first($this->evidence_model->get_evidence((int) ($input['evidence_id'] ?? 0), Session::get('company_id')));

        if ($evidence === null) {
            return self::fail('That evidence file was not found.');
        }

        $private = !empty($input['private']);

        return self::proposal(($private ? 'Hide' : 'Share').' "'.self::plain($evidence['evidence_title']).'" '.($private ? 'from' : 'with').' the client', 'save_evidence_private', array(
            'evidence_id' => (int) $evidence['id'],
            'evidence_private' => $private ? 1 : 0
        ), array(
            'File' => self::plain($evidence['evidence_title']),
            'Client can see it' => $private ? 'No' : 'Yes'
        ));
    }

    private function propose_delete_evidence(array $input): array
    {
        if ($this->is_portal()) {
            $evidence = $this->portal_evidence($input['evidence_id'] ?? 0);
        } else {
            $evidence = $this->first($this->evidence_model->get_evidence((int) ($input['evidence_id'] ?? 0), Session::get('company_id')));
        }

        if ($evidence === null) {
            return self::fail('That evidence file was not found.');
        }

        return self::proposal('Delete evidence "'.self::plain($evidence['evidence_title']).'"', $this->is_portal() ? 'portal_delete_evidence' : 'delete_evidence', array(
            'evidence_id' => (int) $evidence['id']
        ), array(
            'File' => self::plain($evidence['evidence_title'])
        ));
    }

    private function propose_save_standard(array $input): array
    {
        $labels = array(
            'standard_name' => 'Name',
            'short_code' => 'Short code',
            'version' => 'Version',
            'description' => 'Description',
            'standard_status' => 'Status'
        );

        $standard_id = (int) ($input['standard_id'] ?? 0);
        $current = array(
            'standard_status' => 'Active'
        );

        if ($standard_id > 0) {
            $current = $this->first($this->standards_model->get_standard($standard_id, Session::get('company_id')));
            if ($current === null) {
                return self::fail('Standard '.$standard_id.' was not found.');
            }
        }

        $params = $this->merge($labels, $input, $current);
        $params['standard_id'] = $standard_id;

        $summary = $standard_id > 0
            ? 'Update standard "'.self::plain($current['standard_name']).'"'
            : 'Add standard "'.$params['standard_name'].'"';

        return self::proposal($summary, 'save_standard', $params, $this->changed_fields($labels, $input, $params, $standard_id === 0));
    }

    private function propose_duplicate_standard(array $input): array
    {
        $standard = $this->first($this->standards_model->get_standard((int) ($input['standard_id'] ?? 0), Session::get('company_id')));

        if ($standard === null) {
            return self::fail('That standard was not found.');
        }

        $params = array(
            'standard_id' => (int) $standard['id'],
            'standard_name' => trim((string) ($input['standard_name'] ?? '')),
            'short_code' => trim((string) ($input['short_code'] ?? '')),
            'version' => trim((string) ($input['version'] ?? ''))
        );

        return self::proposal('Copy "'.self::plain($standard['standard_name']).'" as "'.$params['standard_name'].'"', 'duplicate_standard', $params, array(
            'From' => self::plain($standard['standard_name']),
            'New name' => $params['standard_name'],
            'Short code' => $params['short_code'],
            'Version' => $params['version']
        ));
    }

    private function propose_delete_standard(array $input): array
    {
        $standard = $this->first($this->standards_model->get_standard((int) ($input['standard_id'] ?? 0), Session::get('company_id')));

        if ($standard === null) {
            return self::fail('That standard was not found.');
        }

        return self::proposal('Delete standard "'.self::plain($standard['standard_name']).'"', 'delete_standard', array(
            'standard_id' => (int) $standard['id']
        ), array(
            'Standard' => self::plain($standard['standard_name'])
        ));
    }

    private function propose_save_controls(array $input): array
    {
        $company_id = Session::get('company_id');
        $standard = $this->first($this->standards_model->get_standard((int) ($input['standard_id'] ?? 0), $company_id));
        $controls = is_array($input['controls'] ?? null) ? $input['controls'] : array();

        if ($standard === null) {
            return self::fail('That standard was not found.');
        }

        if (count($controls) === 0 || count($controls) > 500) {
            return self::fail('Give between 1 and 500 controls.');
        }

        $labels = array(
            'control_identifier' => 'Identifier',
            'control_title' => 'Title',
            'description' => 'Description',
            'family' => 'Family'
        );

        $calls = array();
        $fields = array();

        foreach ($controls as $control) {

            $control_id = (int) ($control['control_id'] ?? 0);
            $current = array();

            if ($control_id > 0) {
                $current = $this->first($this->standards_model->get_control($control_id, $company_id));
                if ($current === null || (int) $current['standard_id'] !== (int) $standard['id']) {
                    return self::fail('Control '.$control_id.' is not in that standard.');
                }
            }

            $params = $this->merge($labels, $control, $current);
            $params['control_id'] = $control_id;
            $params['standard_id'] = (int) $standard['id'];

            $calls[] = array(
                'endpoint' => 'save_control',
                'params' => $params
            );

            $fields[] = array(
                'label' => ($control_id > 0 ? 'Change ' : 'Add ').$params['control_identifier'],
                'value' => $params['control_title']
            );
        }

        return array(
            'summary' => (count($calls) === 1 ? 'Save a control' : 'Save '.count($calls).' controls').' in "'.self::plain($standard['standard_name']).'"',
            'calls' => $calls,
            'fields' => $fields
        );
    }

    private function propose_delete_control(array $input): array
    {
        $control = $this->first($this->standards_model->get_control((int) ($input['control_id'] ?? 0), Session::get('company_id')));

        if ($control === null) {
            return self::fail('That control was not found.');
        }

        return self::proposal('Delete control '.self::plain($control['control_identifier']), 'delete_control', array(
            'control_id' => (int) $control['id']
        ), array(
            'Control' => self::plain($control['control_identifier'].' '.$control['control_title'])
        ));
    }

    private function propose_update_my_profile(array $input): array
    {
        $current = $this->first($this->users_model->get_user_by_id(Session::get('user_id')));

        if ($current === null) {
            return self::fail('Your account was not found.');
        }

        $labels = array(
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'u_name' => 'Username',
            'user_email' => 'Email'
        );

        $params = $this->merge($labels, $input, $current);

        return self::proposal('Update your profile', 'save_profile', $params, $this->changed_fields($labels, $input, $params, false));
    }

    private function propose_save_user(array $input): array
    {
        $labels = array(
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'u_name' => 'Username',
            'user_email' => 'Email',
            'user_status' => 'Status',
            'user_type' => 'Type'
        );

        $user_id = (int) ($input['user_id'] ?? 0);
        $current = array(
            'user_status' => 'Active',
            'user_type' => 'staff',
            'role_id' => 0,
            'client_id' => 0
        );

        if ($user_id > 0) {
            $current = $this->first($this->users_model->get_company_user($user_id, Session::get('company_id')));
            if ($current === null || (int) $current['global_admin'] === 1) {
                return self::fail('User '.$user_id.' was not found.');
            }
        }

        $params = $this->merge($labels, $input, $current);
        $params['toDo'] = $user_id > 0 ? 'update' : 'add';
        $params['user_id'] = $user_id;
        $params['role_id'] = (int) ($input['role_id'] ?? $current['role_id']);
        $params['client_id'] = (int) ($input['client_id'] ?? $current['client_id']);

        $fields = $this->changed_fields($labels, $input, $params, $user_id === 0);

        if (array_key_exists('role_id', $input)) {
            foreach ($this->users_model->get_roles() as $role) {
                if ((int) $role['id'] === $params['role_id']) {
                    $fields['Role'] = self::plain($role['role_name']);
                }
            }
        }

        if ($params['user_type'] === 'portal' && array_key_exists('client_id', $input)) {
            $client = $this->first($this->clients_model->get_client($params['client_id'], Session::get('company_id')));
            if ($client === null) {
                return self::fail('A portal user needs the client_id of an existing client.');
            }
            $fields['Client'] = self::plain($client['company_name']);
        }

        $summary = $user_id > 0
            ? 'Update user '.self::plain($current['first_name'].' '.$current['last_name'])
            : 'Add user '.$params['first_name'].' '.$params['last_name'].($params['user_status'] === 'Active' ? ' and email an invite' : '');

        return self::proposal($summary, 'save_user', $params, $fields);
    }

    private function propose_delete_user(array $input): array
    {
        $user = $this->editable_user($input);

        if ($user === null) {
            return self::fail('That user was not found.');
        }

        $name = self::plain($user['first_name'].' '.$user['last_name']);

        return self::proposal('Remove user '.$name, 'delete_user', array(
            'user_id' => (int) $user['user_id']
        ), array(
            'User' => $name,
            'Email' => self::plain($user['user_email'])
        ));
    }

    private function propose_reset_user_password(array $input): array
    {
        $user = $this->editable_user($input);

        if ($user === null) {
            return self::fail('That user was not found.');
        }

        $name = self::plain($user['first_name'].' '.$user['last_name']);

        return self::proposal('Email '.$name.' a password reset link', 'reset_user_password', array(
            'user_id' => (int) $user['user_id']
        ), array(
            'User' => $name,
            'Email' => self::plain($user['user_email'])
        ));
    }

    private function propose_update_company(array $input): array
    {
        $current = $this->first($this->companies_model->get_company(Session::get('company_id')));

        $labels = array(
            'company_name' => 'Name',
            'website' => 'Website',
            'email_domain' => 'Email domain',
            'address_1' => 'Address',
            'address_2' => 'Address line 2',
            'city' => 'City',
            'state' => 'State',
            'postal_code' => 'Postal code',
            'country' => 'Country',
            'timezone' => 'Timezone',
            'time_format' => 'Time format',
            'brand_color' => 'Main colour',
            'brand_color_secondary' => 'Secondary colour',
            'brand_color_accent' => 'Accent colour'
        );

        $params = $this->merge($labels, $input, $current);
        $params['date_format_id'] = (int) $current['date_format_id'];

        return self::proposal('Update organisation details', 'save_company', $params, $this->changed_fields($labels, $input, $params, false));
    }

    private function propose_update_security_policy(array $input): array
    {
        $current = $this->first($this->companies_model->get_company(Session::get('company_id')));

        $switches = array(
            'session_timeout_enabled' => 'Sign out idle users',
            'password_expiry_enabled' => 'Password expiry',
            'account_lockout_enabled' => 'Account lockout',
            'mfa_enabled' => 'Multi-factor sign-in'
        );

        $numbers = array(
            'session_timeout_minutes' => 'Idle minutes',
            'password_expiry_days' => 'Days between changes',
            'lockout_attempts' => 'Failed attempts',
            'lockout_minutes' => 'Lockout minutes'
        );

        $params = array();
        $fields = array();

        foreach ($switches as $key => $label) {
            $on = array_key_exists($key, $input) ? !empty($input[$key]) : (int) $current[$key] === 1;
            $params[$key] = $on ? 1 : 0;
            if (array_key_exists($key, $input)) {
                $fields[$label] = $on ? 'On' : 'Off';
            }
        }

        foreach ($numbers as $key => $label) {
            $params[$key] = array_key_exists($key, $input) ? (int) $input[$key] : (int) $current[$key];
            if (array_key_exists($key, $input)) {
                $fields[$label] = (string) $params[$key];
            }
        }

        $params['mfa_methods'] = array_key_exists('mfa_methods', $input) ? (string) $input['mfa_methods'] : (string) $current['mfa_methods'];

        if (array_key_exists('mfa_methods', $input)) {
            $fields['MFA methods'] = $params['mfa_methods'];
        }

        return self::proposal('Update the security policy', 'save_security', $params, $fields);
    }

    private function propose_remove_logo(array $input): array
    {
        return self::proposal('Remove the organisation logo', 'remove_logo', array(), array(
            'Logo' => 'Removed'
        ));
    }

    private function propose_save_invoice(array $input): array
    {
        $company_id = Session::get('company_id');
        $invoice_id = (int) ($input['invoice_id'] ?? 0);
        $current = array();
        $current_lines = array();

        if ($invoice_id > 0) {
            $current = $this->first($this->invoices_model->get_invoice($invoice_id, $company_id));
            if ($current === null) {
                return self::fail('Invoice '.$invoice_id.' was not found.');
            }
            foreach ($this->invoices_model->get_invoice_items($invoice_id) as $item) {
                $current_lines[] = array(
                    'item_description' => self::plain($item['item_description']),
                    'quantity' => Money::format_quantity($item['quantity_milli']),
                    'unit_amount' => number_format($item['unit_amount_cents'] / 100, 2, '.', ''),
                    'discount_type' => $item['discount_type'],
                    'discount_value' => $item['discount_type'] === 'Percent'
                        ? Money::format_percent($item['discount_percent_bp'])
                        : ($item['discount_type'] === 'Amount' ? number_format($item['discount_cents'] / 100, 2, '.', '') : '0')
                );
            }
        }

        $lines = array_key_exists('lines', $input) && is_array($input['lines']) ? $input['lines'] : $current_lines;

        foreach ($lines as $index => $line) {
            $lines[$index] = array(
                'item_description' => (string) ($line['item_description'] ?? ''),
                'quantity' => (string) ($line['quantity'] ?? '1'),
                'unit_amount' => (string) ($line['unit_amount'] ?? ''),
                'discount_type' => (string) ($line['discount_type'] ?? 'None'),
                'discount_value' => (string) ($line['discount_value'] ?? '0')
            );
        }

        $client_id = (int) ($input['client_id'] ?? ($current['client_id'] ?? 0));
        $client = $this->first($this->clients_model->get_client($client_id, $company_id));

        if ($client === null) {
            return self::fail('Give the client_id of an existing client.');
        }

        $params = array(
            'invoice_id' => $invoice_id,
            'client_id' => $client_id,
            'due_date' => (string) ($input['due_date'] ?? substr((string) ($current['due_date'] ?? ''), 0, 10)),
            'due_days' => (int) ($current['due_days'] ?? 30),
            'invoice_footer' => (string) ($input['invoice_footer'] ?? self::plain($current['invoice_footer'] ?? '')),
            'lines_json' => json_encode($lines)
        );

        $fields = array(
            'Client' => self::plain($client['company_name']),
            'Due' => $params['due_date']
        );

        foreach ($lines as $line) {
            $fields[] = array(
                'label' => $line['quantity'].' x '.$line['unit_amount'],
                'value' => $line['item_description']
            );
        }

        return self::proposal($invoice_id > 0 ? 'Update draft invoice' : 'Create a draft invoice for '.self::plain($client['company_name']), 'save_invoice', $params, $fields);
    }

    private function propose_send_invoice(array $input): array
    {
        return $this->invoice_action($input, 'send_invoice', 'Send', true);
    }

    private function propose_resend_invoice(array $input): array
    {
        return $this->invoice_action($input, 'resend_invoice', 'Resend', true);
    }

    private function propose_void_invoice(array $input): array
    {
        return $this->invoice_action($input, 'void_invoice', 'Void', false);
    }

    private function propose_delete_invoice(array $input): array
    {
        return $this->invoice_action($input, 'delete_invoice', 'Delete', false);
    }

    private function invoice_action(array $input, string $endpoint, string $verb, bool $recipient): array
    {
        $invoice = $this->first($this->invoices_model->get_invoice((int) ($input['invoice_id'] ?? 0), Session::get('company_id')));

        if ($invoice === null) {
            return self::fail('That invoice was not found.');
        }

        $label = trim(($invoice['invoice_number'] ?? '') !== '' ? (string) $invoice['invoice_number'] : 'draft invoice for '.self::plain($invoice['client_name'] ?? ''));

        $params = array(
            'invoice_id' => (int) $invoice['id']
        );

        $fields = array(
            'Invoice' => $label,
            'Amount' => (string) ($invoice['total_display'] ?? $invoice['amount_display'] ?? '')
        );

        if ($recipient) {
            $params['recipient_name'] = (string) ($input['recipient_name'] ?? '');
            $params['recipient_email'] = (string) ($input['recipient_email'] ?? '');
            $fields['To'] = $params['recipient_email'] !== '' ? $params['recipient_email'] : 'Client billing contact';
        }

        return self::proposal($verb.' '.$label, $endpoint, $params, $fields);
    }

    private function propose_save_subscription(array $input): array
    {
        $company_id = Session::get('company_id');
        $subscription_id = (int) ($input['subscription_id'] ?? 0);
        $current = array(
            'quantity' => 1,
            'billing_interval' => 'Month',
            'due_days' => 30
        );

        if ($subscription_id > 0) {
            $current = $this->first($this->subscriptions_model->get_subscription($subscription_id, $company_id));
            if ($current === null) {
                return self::fail('Subscription '.$subscription_id.' was not found.');
            }
            $current['unit_amount'] = number_format($current['unit_amount_cents'] / 100, 2, '.', '');
            $current['start_date'] = substr((string) ($current['start_date'] ?? ''), 0, 10);
        }

        $labels = array(
            'subscription_name' => 'Name',
            'unit_amount' => 'Price',
            'billing_interval' => 'Every',
            'start_date' => 'Starts'
        );

        $params = $this->merge($labels, $input, $current);
        $params['subscription_id'] = $subscription_id;
        $params['client_id'] = (int) ($input['client_id'] ?? ($current['client_id'] ?? 0));
        $params['quantity'] = (int) ($input['quantity'] ?? $current['quantity']);
        $params['due_days'] = (int) ($input['due_days'] ?? $current['due_days']);

        $client = $this->first($this->clients_model->get_client($params['client_id'], $company_id));

        if ($client === null) {
            return self::fail('Give the client_id of an existing client.');
        }

        $fields = array_merge(array(
            'Client' => self::plain($client['company_name']),
            'Quantity' => (string) $params['quantity']
        ), $this->changed_fields($labels, $input, $params, $subscription_id === 0));

        return self::proposal($subscription_id > 0 ? 'Update draft subscription' : 'Create a draft subscription for '.self::plain($client['company_name']), 'save_subscription', $params, $fields);
    }

    private function propose_activate_subscription(array $input): array
    {
        return $this->subscription_action($input, 'activate_subscription', 'Start billing');
    }

    private function propose_cancel_subscription(array $input): array
    {
        return $this->subscription_action($input, 'cancel_subscription', 'Cancel');
    }

    private function propose_delete_subscription(array $input): array
    {
        return $this->subscription_action($input, 'delete_subscription', 'Delete');
    }

    private function subscription_action(array $input, string $endpoint, string $verb): array
    {
        $subscription = $this->first($this->subscriptions_model->get_subscription((int) ($input['subscription_id'] ?? 0), Session::get('company_id')));

        if ($subscription === null) {
            return self::fail('That subscription was not found.');
        }

        return self::proposal($verb.' subscription "'.self::plain($subscription['subscription_name']).'"', $endpoint, array(
            'subscription_id' => (int) $subscription['id']
        ), array(
            'Subscription' => self::plain($subscription['subscription_name']),
            'Client' => self::plain($subscription['client_name']),
            'Amount' => (string) $subscription['amount_display']
        ));
    }

    /* --------------------------------------------------------------- helpers */

    private function assessment(array $input)
    {
        return $this->first($this->assessments_model->get_assessment((int) ($input['assessment_id'] ?? 0), Session::get('company_id')));
    }

    /** Global admins are never editable by a company admin, through any route. */
    private function editable_user(array $input)
    {
        $user = $this->first($this->users_model->get_company_user((int) ($input['user_id'] ?? 0), Session::get('company_id')));

        if ($user === null || (int) $user['global_admin'] === 1) {
            return null;
        }

        return $user;
    }

    private function first($rows)
    {
        return is_array($rows) && count($rows) === 1 ? $rows[0] : null;
    }

    /** Every labelled field: the model's value where given, else the stored one. */
    private function merge(array $labels, array $input, array $current): array
    {
        $params = array();

        foreach (array_keys($labels) as $key) {
            $params[$key] = array_key_exists($key, $input) ? trim((string) $input[$key]) : self::plain($current[$key] ?? '');
        }

        return $params;
    }

    /** Fields to show on the card: all of a new record, only what changes on an edit. */
    private function changed_fields(array $labels, array $input, array $params, bool $is_new): array
    {
        $fields = array();

        foreach ($labels as $key => $label) {
            if (($is_new || array_key_exists($key, $input)) && (string) $params[$key] !== '') {
                $fields[$label] = (string) $params[$key];
            }
        }

        return $fields;
    }

    private static function proposal(string $summary, string $endpoint, array $params, array $fields): array
    {
        $calls = array(
            array(
                'endpoint' => $endpoint,
                'params' => $params
            )
        );

        return self::batch($summary, $calls, $fields);
    }

    private static function batch(string $summary, array $calls, array $fields): array
    {
        $shown = array();

        foreach ($fields as $label => $value) {
            if (is_array($value)) {
                $shown[] = $value;
                continue;
            }
            $shown[] = array(
                'label' => (string) $label,
                'value' => (string) $value
            );
        }

        return array(
            'summary' => $summary,
            'calls' => $calls,
            'fields' => $shown
        );
    }

    private static function fail(string $message): array
    {
        return array(
            'error' => $message
        );
    }

    private static function plain($value): string
    {
        return trim(html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8'));
    }

}
