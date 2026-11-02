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

    const PROJECT_ROLES = array(
        'Project Lead',
        'Lead Assessor',
        'Assessor',
        'Reviewer',
        'Contributor',
        'Observer'
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
    private $projects_model;
    private $assessments_model;
    private $evidence_model;
    private $standards_model;
    private $invoices_model;
    private $subscriptions_model;
    private $dashboard_model;
    private $companies_model;
    private $users_model;
    private $reports_model;

    public function __construct($conversation_id)
    {
        $this->conversation_id = (int) $conversation_id;
        $this->ai_model = new AiModel();
        $this->clients_model = new ClientsModel();
        $this->projects_model = new ProjectsModel();
        $this->assessments_model = new AssessmentsModel();
        $this->evidence_model = new EvidenceModel();
        $this->standards_model = new StandardsModel();
        $this->invoices_model = new InvoicesModel();
        $this->subscriptions_model = new SubscriptionsModel();
        $this->dashboard_model = new DashboardModel();
        $this->companies_model = new CompaniesModel();
        $this->users_model = new UsersModel();
        $this->reports_model = new ReportsModel();
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
            'project_id' => $id('Project whose evidence vault receives the file'),
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

            'list_projects' => $tool('portal', false, 'The person\'s own projects.', array(), array()),
            'list_assessments' => $tool('portal', false, 'Assessments in one of the person\'s projects, with progress.', array(
                'project_id' => $id('Project id')
            ), array(
                'project_id'
            )),
            'list_assessment_controls' => $tool('any', false, 'Every control in an assessment with its result (and, for staff, notes and evidence count).', array(
                'assessment_id' => $id('Assessment id')
            ), array(
                'assessment_id'
            )),
            'list_evidence' => $tool('portal', false, 'Evidence files and folders shared with the person for a project.', array(
                'project_id' => $id('Project id')
            ), array(
                'project_id'
            )),
            'get_control_evidence' => $tool('any', false, 'Evidence files attached to one assessment control.', array(
                'item_id' => $id('Assessment control (item) id')
            ), array(
                'item_id'
            )),
            'list_my_invoices' => $tool('portal', false, 'The person\'s invoices and their payment state.', array(), array()),

            'get_overview' => $tool('staff', false, 'Dashboard figures: totals, assessment status, results, project progress and items needing attention.', array(), array()),
            'list_clients' => $tool('staff', false, 'All clients with contact details.', array(), array()),
            'get_client' => $tool('staff', false, 'One client with its projects and assessments.', array(
                'client_id' => $id('Client id')
            ), array(
                'client_id'
            )),
            'list_all_projects' => $tool('staff', false, 'All projects with client, dates and status.', array(), array()),
            'get_project' => $tool('staff', false, 'One project with its assessments, evidence folders and team.', array(
                'project_id' => $id('Project id')
            ), array(
                'project_id'
            )),
            'get_assessment_results' => $tool('staff', false, 'Result totals for an assessment, overall and by control family. Use for reports.', array(
                'assessment_id' => $id('Assessment id')
            ), array(
                'assessment_id'
            )),
            'search_evidence' => $tool('staff', false, 'Evidence files in a project, optionally filtered by words in the title, description or file name.', array(
                'project_id' => $id('Project id'),
                'term' => $text('Words to match; omit for all files')
            ), array(
                'project_id'
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
            'list_project_candidates' => $tool('staff', false, 'Staff who can be added to a project team.', array(
                'project_id' => $id('Project id')
            ), array(
                'project_id'
            )),
            'get_report' => $tool('staff', false, 'The client deliverable report for a project, with every section: details and narrative, domains, findings with scores and ratings, risks, threats, systems inventory, CSF profile, scan summary, cross-references, people and versions. Returns report null when none has been started.', array(
                'project_id' => $id('Project id')
            ), array(
                'project_id'
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
                'website' => $text('Website, starting with https://'),
                'address_1' => $text('Street address'),
                'address_2' => $text('Street address, second line'),
                'city' => $text('City'),
                'state' => $text('State or region'),
                'postal_code' => $text('Postal code'),
                'country' => $text('Country'),
                'billing_name' => $text('Billing contact name'),
                'billing_email' => $text('Billing contact email')
            ), array()),
            'delete_client' => $tool('staff', true, 'Delete a client.', array(
                'client_id' => $id('Client id')
            ), array(
                'client_id'
            )),
            'save_project' => $tool('staff', true, 'Add a project (omit project_id) or change one (give project_id and only the fields to change).', array(
                'project_id' => $id('Project id when changing an existing project'),
                'client_id' => $id('Client the project belongs to'),
                'project_name' => $text('Project name'),
                'description' => $text('Project description'),
                'start_date' => $text('Start date, YYYY-MM-DD'),
                'end_date' => $text('End date, YYYY-MM-DD')
            ), array()),
            'delete_project' => $tool('staff', true, 'Delete a project with its assessments.', array(
                'project_id' => $id('Project id')
            ), array(
                'project_id'
            )),
            'save_project_member' => $tool('staff', true, 'Add a staff member to a project team (give project_id and user_id) or change a member\'s role (give member_id).', array(
                'member_id' => $id('Team membership id when changing a role'),
                'project_id' => $id('Project id when adding'),
                'user_id' => $id('Staff user id when adding'),
                'project_role' => $choice(self::PROJECT_ROLES, 'Role on the project')
            ), array(
                'project_role'
            )),
            'remove_project_member' => $tool('staff', true, 'Remove someone from a project team.', array(
                'member_id' => $id('Team membership id')
            ), array(
                'member_id'
            )),
            'create_assessment' => $tool('staff', true, 'Start an assessment of a project against a standard.', array(
                'project_id' => $id('Project id'),
                'standard_id' => $id('Standard id'),
                'assessment_name' => $text('Assessment name')
            ), array(
                'project_id',
                'standard_id',
                'assessment_name'
            )),
            'rename_assessment' => $tool('staff', true, 'Rename an assessment.', array(
                'assessment_id' => $id('Assessment id'),
                'assessment_name' => $text('New name')
            ), array(
                'assessment_id',
                'assessment_name'
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
            'set_control_results' => $tool('staff', true, 'Record results, and optionally notes, for one or many assessment controls in one step.', array(
                'results' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'item_id' => $id('Assessment control (item) id'),
                            'item_result' => $choice(self::ITEM_RESULTS, 'Result'),
                            'notes' => $text('Assessor notes; omit to keep the existing notes')
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
            'save_file_to_evidence' => $tool('any', true, 'File a document attached to this conversation, or a report you created, into a project\'s evidence vault.', $file_to_vault, array(
                'file_name',
                'project_id',
                'evidence_title'
            )),
            'create_folder' => $tool('staff', true, 'Create a folder in a project\'s evidence vault.', array(
                'project_id' => $id('Project id'),
                'folder_name' => $text('Folder name'),
                'parent_id' => $id('Parent folder id; omit for the top level')
            ), array(
                'project_id',
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
            'move_evidence' => $tool('staff', true, 'Move an evidence file to another folder in the same project.', array(
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

            'start_report' => $tool('staff', true, 'Start the client deliverable report for a project. It begins with the standard domains, common threats, assessment team and methodology wording.', array(
                'project_id' => $id('Project id')
            ), array(
                'project_id'
            )),
            'save_report_details' => $tool('staff', true, 'Change a report\'s details and narrative. Give only the fields to change. Narrative fields are plain text; blank lines separate paragraphs and lines starting with "- " become bullet lists.', array(
                'project_id' => $id('Project id'),
                'report_title' => $text('Title, e.g. Cybersecurity Risk Assessment'),
                'report_date' => $text('Report date, YYYY-MM-DD'),
                'period_start' => $text('Fieldwork start, YYYY-MM-DD'),
                'period_end' => $text('Fieldwork end, YYYY-MM-DD'),
                'regulatory_reference' => $text('Regulation the assessment addresses, e.g. NYDFS 23 NYCRR Part 500, Sections 500.1(p) and 500.9'),
                'purpose_text' => $text('Executive summary: purpose'),
                'conclusion_text' => $text('Executive summary: overall conclusion'),
                'background_text' => $text('Background on the client'),
                'scope_text' => $text('Scope'),
                'out_of_scope_text' => $text('What was out of scope'),
                'approach_text' => $text('Approach and fieldwork performed'),
                'methodology_text' => $text('Rating methodology'),
                'limitations_text' => $text('Limitations and responsibilities'),
                'review_text' => $text('Ongoing review'),
                'scan_text' => $text('Introduction to the vulnerability scan appendix'),
                'signoff_name' => $text('Name of the client signer for the acknowledgment'),
                'signoff_title' => $text('Title of the client signer')
            ), array(
                'project_id'
            )),
            'save_report_rows' => $tool('staff', true, 'Add or change rows in one section of a project\'s report, many at once. Give id only to change an existing row; omitted fields keep their values. Fields by section - domain: domain_name, areas_reviewed, csf_refs, domain_rating (Satisfactory, Satisfactory with exceptions, Needs improvement, Unsatisfactory), domain_summary. finding: domain_id, finding_ref (leave blank to number automatically), finding_title, observation, risk_text, recommendation, likelihood (1-5), impact (1-5), csf_refs, response_status (Pending, Agreed, Partially agreed, Not agreed), management_response, response_owner, target_date (YYYY-MM-DD). risk: domain_id, finding_id or link_finding_ref (e.g. F-03), risk_ref, risk_text, threat_refs (e.g. T1, T2), likelihood, impact, treatment. threat: threat_ref, threat_name, threat_description, parties_affected. asset: asset_name, asset_kind, asset_owner, data_held, sensitivity (High, Medium, Low). scan_row: scan_rating (Critical, High, Medium, Low), issue_count, scan_summary, disposition. xref: xref_section, requirement, where_addressed. version: version_label, version_date, version_note, version_author. person: person_kind (distribution or team), person_name, person_title, organisation, responsibility.', array(
                'project_id' => $id('Project id'),
                'section' => $choice(array_keys(ReportBuilder::SECTIONS), 'Report section'),
                'rows' => array(
                    'type' => 'array',
                    'description' => 'Rows to add or change, as objects of the section\'s fields',
                    'items' => array(
                        'type' => 'object'
                    )
                )
            ), array(
                'project_id',
                'section',
                'rows'
            )),
            'delete_report_rows' => $tool('staff', true, 'Remove rows from one section of a project\'s report.', array(
                'project_id' => $id('Project id'),
                'section' => $choice(array_keys(ReportBuilder::SECTIONS), 'Report section'),
                'ids' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'integer'
                    )
                )
            ), array(
                'project_id',
                'section',
                'ids'
            )),
            'save_csf_profile' => $tool('staff', true, 'Set the NIST CSF 2.0 current and target tiers (1 Partial, 2 Risk Informed, 3 Repeatable, 4 Adaptive) and main gaps for one or more functions in a project\'s report.', array(
                'project_id' => $id('Project id'),
                'functions' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'csf_function' => $choice(ReportBuilder::CSF_FUNCTIONS, 'Function'),
                            'current_tier' => $id('Current tier 1-4'),
                            'target_tier' => $id('Target tier 1-4'),
                            'main_gaps' => $text('Main gaps, citing finding references')
                        ),
                        'required' => array(
                            'csf_function'
                        )
                    )
                )
            ), array(
                'project_id',
                'functions'
            )),
            'issue_report' => $tool('staff', true, 'Issue a draft report to the client so their portal users can respond to the findings.', array(
                'project_id' => $id('Project id')
            ), array(
                'project_id'
            )),
            'finalise_report' => $tool('staff', true, 'Make the report final. The client can then download the PDF from the portal.', array(
                'project_id' => $id('Project id')
            ), array(
                'project_id'
            )),

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
                'project_id' => $id('Project the invoice is for; optional'),
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
                'project_id' => $id('Project; optional'),
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
                'project_progress' => self::rows($this->assessments_model->project_progress($company_id)),
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
                'projects' => self::rows($this->projects_model->client_projects($client_id, $company_id)),
                'assessments' => self::rows($this->assessments_model->client_assessments($client_id, $company_id))
            );
        }

        if ($name === 'list_all_projects') {
            return self::rows($this->projects_model->load_projects($company_id));
        }

        if ($name === 'get_project') {
            $project_id = (int) ($input['project_id'] ?? 0);
            $project = $this->projects_model->get_project($project_id, $company_id);
            if (count($project) !== 1) {
                return null;
            }
            return array(
                'project' => self::rows($project)[0],
                'assessments' => self::rows($this->assessments_model->load_assessments($project_id, $company_id)),
                'evidence_folders' => self::rows($this->evidence_model->load_folders($project_id, $company_id)),
                'team' => self::rows($this->projects_model->load_project_users($project_id, $company_id))
            );
        }

        if ($name === 'list_assessment_controls') {
            $assessment_id = (int) ($input['assessment_id'] ?? 0);
            if (count($this->assessments_model->get_assessment($assessment_id, $company_id)) !== 1) {
                return null;
            }
            return self::rows($this->assessments_model->load_items($assessment_id, $company_id));
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
            $project_id = (int) ($input['project_id'] ?? 0);
            if (count($this->projects_model->get_project($project_id, $company_id)) !== 1) {
                return null;
            }
            return self::rows($this->evidence_model->search_evidence($project_id, $company_id, trim((string) ($input['term'] ?? '')), 200, 0));
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

        if ($name === 'list_project_candidates') {
            $project_id = (int) ($input['project_id'] ?? 0);
            if (count($this->projects_model->get_project($project_id, $company_id)) !== 1) {
                return null;
            }
            return self::rows($this->projects_model->available_users($project_id, $company_id));
        }

        if ($name === 'get_report') {
            $project_id = (int) ($input['project_id'] ?? 0);
            if (count($this->projects_model->get_project($project_id, $company_id)) !== 1) {
                return null;
            }
            $report = $this->first($this->reports_model->get_report_by_project($project_id, $company_id));
            if ($report === null) {
                return array(
                    'report' => null,
                    'note' => 'No report has been started for this project. Use start_report.'
                );
            }
            $assembled = (new ReportBuilder())->assemble($report);
            unset($assembled['company'], $assembled['project'], $assembled['client'], $assembled['findings_ranked'], $assembled['risks_ranked'], $assembled['unassigned_findings']);
            foreach ($assembled['domains'] as $index => $domain) {
                $assembled['domains'][$index]['finding_refs'] = array_column($domain['findings'], 'finding_ref');
                unset($assembled['domains'][$index]['findings']);
            }
            return $assembled;
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

        if ($name === 'list_projects') {
            return self::rows($this->projects_model->client_projects($client_id, $company_id));
        }

        if ($name === 'list_my_invoices') {
            return self::rows($this->invoices_model->portal_invoices($client_id, $company_id));
        }

        if ($name === 'list_assessments' || $name === 'list_evidence') {
            $project_id = (int) ($input['project_id'] ?? 0);
            if (!$this->client_owns_project($project_id)) {
                return null;
            }
            if ($name === 'list_assessments') {
                return self::rows($this->assessments_model->load_assessments($project_id, $company_id));
            }
            return array(
                'folders' => self::rows($this->evidence_model->portal_folders($project_id, $client_id, $company_id)),
                'files' => self::rows($this->evidence_model->portal_evidence($project_id, $client_id, $company_id))
            );
        }

        if ($name === 'list_assessment_controls') {
            $assessment_id = (int) ($input['assessment_id'] ?? 0);
            if (count($this->assessments_model->client_assessment($assessment_id, $client_id, $company_id)) !== 1) {
                return null;
            }
            return self::rows($this->assessments_model->portal_items($assessment_id, $client_id, $company_id), array(
                'notes'
            ));
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

    private function client_owns_project($project_id): bool
    {
        return count($this->projects_model->client_project((int) $project_id, Session::get('client_id'), Session::get('company_id'))) === 1;
    }

    private function portal_item($item_id)
    {
        $item = $this->assessments_model->get_item((int) $item_id, Session::get('company_id'));

        if (count($item) !== 1 || (int) $item[0]['client_id'] !== (int) Session::get('client_id')) {
            return null;
        }

        return $item[0];
    }

    /** Evidence a portal user may see: in their own project and not private. */
    private function portal_evidence($evidence_id)
    {
        $evidence = $this->evidence_model->get_evidence((int) $evidence_id, Session::get('company_id'));

        if (count($evidence) !== 1 || (int) $evidence[0]['evidence_private'] === 1 || !$this->client_owns_project($evidence[0]['project_id'])) {
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
            'website' => 'Website',
            'address_1' => 'Address',
            'address_2' => 'Address line 2',
            'city' => 'City',
            'state' => 'State',
            'postal_code' => 'Postal code',
            'country' => 'Country',
            'billing_name' => 'Billing contact',
            'billing_email' => 'Billing email'
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

    private function propose_save_project(array $input): array
    {
        $company_id = Session::get('company_id');
        $project_id = (int) ($input['project_id'] ?? 0);
        $current = array();

        if ($project_id > 0) {
            $current = $this->first($this->projects_model->get_project($project_id, $company_id));
            if ($current === null) {
                return self::fail('Project '.$project_id.' was not found.');
            }
            $current['start_date'] = substr((string) $current['start_date'], 0, 10);
            $current['end_date'] = substr((string) $current['end_date'], 0, 10);
        }

        $labels = array(
            'project_name' => 'Name',
            'description' => 'Description',
            'start_date' => 'Start',
            'end_date' => 'End'
        );

        $params = $this->merge($labels, $input, $current);
        $params['project_id'] = $project_id;
        $params['client_id'] = (int) ($input['client_id'] ?? ($current['client_id'] ?? 0));

        $client = $this->first($this->clients_model->get_client($params['client_id'], $company_id));

        if ($client === null) {
            return self::fail('Give the client_id of an existing client.');
        }

        $fields = array(
            'Client' => self::plain($client['company_name'])
        );

        $summary = $project_id > 0
            ? 'Update project "'.self::plain($current['project_name']).'"'
            : 'Add project "'.$params['project_name'].'"';

        return self::proposal($summary, 'save_project', $params, array_merge($fields, $this->changed_fields($labels, $input, $params, $project_id === 0)));
    }

    private function propose_delete_project(array $input): array
    {
        $project = $this->first($this->projects_model->get_project((int) ($input['project_id'] ?? 0), Session::get('company_id')));

        if ($project === null) {
            return self::fail('That project was not found.');
        }

        return self::proposal('Delete project "'.self::plain($project['project_name']).'"', 'delete_project', array(
            'project_id' => (int) $project['id']
        ), array(
            'Project' => self::plain($project['project_name']),
            'Note' => 'Its assessments are deleted with it.'
        ));
    }

    private function propose_save_project_member(array $input): array
    {
        $company_id = Session::get('company_id');
        $member_id = (int) ($input['member_id'] ?? 0);
        $role = (string) ($input['project_role'] ?? '');

        if (!in_array($role, self::PROJECT_ROLES, true)) {
            return self::fail('project_role must be one of: '.implode(', ', self::PROJECT_ROLES));
        }

        if ($member_id > 0) {
            $member = $this->first($this->projects_model->get_project_user($member_id, $company_id));
            if ($member === null) {
                return self::fail('That team member was not found.');
            }
            $params = array(
                'id' => $member_id,
                'project_id' => (int) $member['project_id'],
                'user_id' => (int) $member['user_id'],
                'project_role' => $role
            );
            return self::proposal('Change project role to '.$role, 'save_project_user', $params, array(
                'Member' => $this->person_name($member['user_id']),
                'Role' => $role
            ));
        }

        $project = $this->first($this->projects_model->get_project((int) ($input['project_id'] ?? 0), $company_id));
        $user = $this->first($this->users_model->get_company_user((int) ($input['user_id'] ?? 0), $company_id));

        if ($project === null || $user === null) {
            return self::fail('Give the project_id of an existing project and the user_id of a staff member.');
        }

        $params = array(
            'id' => 0,
            'project_id' => (int) $project['id'],
            'user_id' => (int) $user['user_id'],
            'project_role' => $role
        );

        return self::proposal('Add '.self::plain($user['first_name'].' '.$user['last_name']).' to "'.self::plain($project['project_name']).'"', 'save_project_user', $params, array(
            'Project' => self::plain($project['project_name']),
            'Person' => self::plain($user['first_name'].' '.$user['last_name']),
            'Role' => $role
        ));
    }

    private function propose_remove_project_member(array $input): array
    {
        $member = $this->first($this->projects_model->get_project_user((int) ($input['member_id'] ?? 0), Session::get('company_id')));

        if ($member === null) {
            return self::fail('That team member was not found.');
        }

        $person = $this->person_name($member['user_id']);

        return self::proposal('Remove '.$person.' from the project team', 'delete_project_user', array(
            'id' => (int) $member['id']
        ), array(
            'Member' => $person
        ));
    }

    private function propose_create_assessment(array $input): array
    {
        $company_id = Session::get('company_id');
        $project = $this->first($this->projects_model->get_project((int) ($input['project_id'] ?? 0), $company_id));
        $standard = $this->first($this->standards_model->get_standard((int) ($input['standard_id'] ?? 0), $company_id));

        if ($project === null || $standard === null) {
            return self::fail('That project or standard was not found.');
        }

        $params = array(
            'project_id' => (int) $project['id'],
            'standard_id' => (int) $standard['id'],
            'assessment_name' => trim((string) ($input['assessment_name'] ?? ''))
        );

        return self::proposal('Start assessment "'.$params['assessment_name'].'"', 'create_assessment', $params, array(
            'Project' => self::plain($project['project_name']),
            'Standard' => self::plain($standard['standard_name']),
            'Name' => $params['assessment_name']
        ));
    }

    private function propose_rename_assessment(array $input): array
    {
        $assessment = $this->assessment($input);

        if ($assessment === null) {
            return self::fail('That assessment was not found.');
        }

        $name = trim((string) ($input['assessment_name'] ?? ''));

        return self::proposal('Rename "'.self::plain($assessment['assessment_name']).'"', 'save_assessment', array(
            'assessment_id' => (int) $assessment['id'],
            'assessment_name' => $name
        ), array(
            'From' => self::plain($assessment['assessment_name']),
            'To' => $name
        ));
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

            $notes = array_key_exists('notes', $result) ? (string) $result['notes'] : self::plain($item['notes'] ?? '');

            $calls[] = array(
                'endpoint' => 'save_item',
                'params' => array(
                    'item_id' => (int) $item['id'],
                    'item_result' => $result['item_result'],
                    'notes' => $notes
                )
            );

            $fields[self::plain($item['control_identifier'])] = $result['item_result'].(array_key_exists('notes', $result) && $notes !== '' ? ' - '.$notes : '');
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

        $project_id = (int) ($input['project_id'] ?? 0);

        if ($this->is_portal()) {
            $project = $this->client_owns_project($project_id)
                ? $this->first($this->projects_model->get_project($project_id, Session::get('company_id')))
                : null;
        } else {
            $project = $this->first($this->projects_model->get_project($project_id, Session::get('company_id')));
        }

        if ($project === null) {
            return self::fail('That project was not found.');
        }

        $params = array(
            'attachment_id' => (int) $file['id'],
            'project_id' => $project_id,
            'folder_id' => (int) ($input['folder_id'] ?? 0),
            'evidence_title' => trim((string) ($input['evidence_title'] ?? '')),
            'description' => trim((string) ($input['description'] ?? ''))
        );

        $fields = array(
            'File' => $file['file_name'],
            'Project' => self::plain($project['project_name']),
            'Title' => $params['evidence_title'],
            'Description' => $params['description']
        );

        if ($params['folder_id'] > 0) {
            $folder = $this->first($this->evidence_model->get_folder($params['folder_id'], Session::get('company_id')));
            if ($folder === null || (int) $folder['project_id'] !== $project_id) {
                return self::fail('That folder is not in that project.');
            }
            $fields['Folder'] = self::plain($folder['folder_name']);
        }

        return self::proposal('Save "'.$file['file_name'].'" to the evidence vault', 'ai_file_evidence', $params, $fields);
    }

    private function propose_create_folder(array $input): array
    {
        $project = $this->first($this->projects_model->get_project((int) ($input['project_id'] ?? 0), Session::get('company_id')));

        if ($project === null) {
            return self::fail('That project was not found.');
        }

        $params = array(
            'folder_id' => 0,
            'project_id' => (int) $project['id'],
            'parent_id' => (int) ($input['parent_id'] ?? 0),
            'folder_name' => trim((string) ($input['folder_name'] ?? ''))
        );

        return self::proposal('Create folder "'.$params['folder_name'].'"', 'save_folder', $params, array(
            'Project' => self::plain($project['project_name']),
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
            'project_id' => (int) $folder['project_id'],
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
            if ($folder === null || (int) $folder['project_id'] !== (int) $evidence['project_id']) {
                return self::fail('That folder is not in the same project.');
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

    /** The report for a project in this company, or null. */
    private function project_report(array $input)
    {
        $project = $this->first($this->projects_model->get_project((int) ($input['project_id'] ?? 0), Session::get('company_id')));

        if ($project === null) {
            return null;
        }

        $report = $this->first($this->reports_model->get_report_by_project((int) $project['id'], Session::get('company_id')));

        if ($report !== null) {
            $report['project_name'] = self::plain($project['project_name']);
        }

        return $report;
    }

    private function propose_start_report(array $input): array
    {
        $project = $this->first($this->projects_model->get_project((int) ($input['project_id'] ?? 0), Session::get('company_id')));

        if ($project === null) {
            return self::fail('That project was not found.');
        }

        if ($this->project_report($input) !== null) {
            return self::fail('This project already has a report. Use get_report.');
        }

        return self::proposal('Start the report for "'.self::plain($project['project_name']).'"', 'report_create', array(
            'project_id' => (int) $project['id']
        ), array(
            'Project' => self::plain($project['project_name'])
        ));
    }

    private function propose_save_report_details(array $input): array
    {
        $report = $this->project_report($input);

        if ($report === null) {
            return self::fail('That project has no report yet. Use start_report first.');
        }

        $params = array(
            'report_id' => (int) $report['id']
        );
        $fields = array();

        foreach (array_keys(ReportBuilder::DETAIL_FIELDS) as $key) {
            if (array_key_exists($key, $input)) {
                $params[$key] = trim((string) $input[$key]);
                $fields[ucfirst(str_replace('_', ' ', preg_replace('/_text$/', '', $key)))] = mb_strimwidth($params[$key], 0, 400, '...');
            }
        }

        if (count($fields) === 0) {
            return self::fail('Give at least one report field to change.');
        }

        return self::batch('Update the report for "'.$report['project_name'].'"', array(
            array(
                'endpoint' => 'report_save_details',
                'params' => $params
            )
        ), $fields);
    }

    private function propose_save_report_rows(array $input): array
    {
        $report = $this->project_report($input);
        $section = (string) ($input['section'] ?? '');
        $rows = is_array($input['rows'] ?? null) ? $input['rows'] : array();

        if ($report === null) {
            return self::fail('That project has no report yet. Use start_report first.');
        }

        if (!isset(ReportBuilder::SECTIONS[$section])) {
            return self::fail('section must be one of: '.implode(', ', array_keys(ReportBuilder::SECTIONS)));
        }

        if (count($rows) === 0 || count($rows) > 200) {
            return self::fail('Give between 1 and 200 rows.');
        }

        $allowed = array_keys(ReportBuilder::SECTIONS[$section]);

        if ($section === 'risk') {
            $allowed[] = 'link_finding_ref';
        }

        $calls = array();
        $fields = array();

        foreach ($rows as $row) {

            if (!is_array($row)) {
                return self::fail('Each row must be an object of fields.');
            }

            $row_id = (int) ($row['id'] ?? 0);

            if ($row_id > 0 && count($this->reports_model->{'get_'.$section}($row_id, (int) $report['id'])) !== 1) {
                return self::fail('Row '.$row_id.' is not in this report\'s '.$section.' section.');
            }

            $params = array(
                'report_id' => (int) $report['id'],
                'section' => $section,
                'id' => $row_id
            );

            foreach ($row as $key => $value) {
                if (in_array($key, $allowed, true)) {
                    $params[$key] = is_bool($value) ? ($value ? '1' : '0') : trim((string) $value);
                }
            }

            $calls[] = array(
                'endpoint' => 'report_save_row',
                'params' => $params
            );

            $fields[] = array(
                'label' => ($row_id > 0 ? 'Change ' : 'Add ').self::row_label($section, $params),
                'value' => self::row_summary($section, $params)
            );
        }

        return array(
            'summary' => 'Save '.count($calls).' '.str_replace('_', ' ', $section).(count($calls) === 1 ? '' : 's').' in the report for "'.$report['project_name'].'"',
            'calls' => $calls,
            'fields' => $fields
        );
    }

    private static function row_label(string $section, array $params): string
    {
        $keys = array(
            'finding' => 'finding_ref',
            'risk' => 'risk_ref',
            'threat' => 'threat_ref',
            'domain' => 'domain_name',
            'asset' => 'asset_name',
            'scan_row' => 'scan_rating',
            'xref' => 'xref_section',
            'version' => 'version_label',
            'person' => 'person_name'
        );

        $label = (string) ($params[$keys[$section]] ?? '');

        return $label !== '' ? $label : str_replace('_', ' ', $section);
    }

    private static function row_summary(string $section, array $params): string
    {
        $keys = array(
            'finding' => 'finding_title',
            'risk' => 'risk_text',
            'threat' => 'threat_name',
            'domain' => 'domain_rating',
            'asset' => 'data_held',
            'scan_row' => 'scan_summary',
            'xref' => 'requirement',
            'version' => 'version_note',
            'person' => 'person_title'
        );

        $text = (string) ($params[$keys[$section]] ?? '');

        if (isset($params['likelihood'], $params['impact'])) {
            $score = (int) $params['likelihood'] * (int) $params['impact'];
            $text .= ($text === '' ? '' : ' - ').ReportBuilder::rating($score).' ('.$score.')';
        }

        return mb_strimwidth($text, 0, 300, '...');
    }

    private function propose_delete_report_rows(array $input): array
    {
        $report = $this->project_report($input);
        $section = (string) ($input['section'] ?? '');
        $ids = is_array($input['ids'] ?? null) ? $input['ids'] : array();

        if ($report === null || !isset(ReportBuilder::SECTIONS[$section]) || count($ids) === 0) {
            return self::fail('Give a project with a report, a section and the ids to remove.');
        }

        $calls = array();
        $fields = array();

        foreach ($ids as $id) {
            $row = $this->first($this->reports_model->{'get_'.$section}((int) $id, (int) $report['id']));
            if ($row === null) {
                return self::fail('Row '.(int) $id.' is not in this report\'s '.$section.' section.');
            }
            $calls[] = array(
                'endpoint' => 'report_delete_row',
                'params' => array(
                    'report_id' => (int) $report['id'],
                    'section' => $section,
                    'id' => (int) $id
                )
            );
            $fields[] = array(
                'label' => 'Remove '.self::row_label($section, $row),
                'value' => self::row_summary($section, $row)
            );
        }

        return array(
            'summary' => 'Remove '.count($calls).' '.str_replace('_', ' ', $section).(count($calls) === 1 ? '' : 's').' from the report',
            'calls' => $calls,
            'fields' => $fields
        );
    }

    private function propose_save_csf_profile(array $input): array
    {
        $report = $this->project_report($input);
        $rows = is_array($input['functions'] ?? null) ? $input['functions'] : array();

        if ($report === null) {
            return self::fail('That project has no report yet. Use start_report first.');
        }

        $current = array();

        foreach ($this->reports_model->load_csf((int) $report['id']) as $row) {
            $current[$row['csf_function']] = $row;
        }

        $calls = array();
        $fields = array();

        foreach ($rows as $row) {

            $function = (string) ($row['csf_function'] ?? '');

            if (!in_array($function, ReportBuilder::CSF_FUNCTIONS, true)) {
                return self::fail('csf_function must be one of: '.implode(', ', ReportBuilder::CSF_FUNCTIONS));
            }

            $params = array(
                'report_id' => (int) $report['id'],
                'csf_function' => $function,
                'current_tier' => (int) ($row['current_tier'] ?? ($current[$function]['current_tier'] ?? 0)),
                'target_tier' => (int) ($row['target_tier'] ?? ($current[$function]['target_tier'] ?? 0)),
                'main_gaps' => (string) ($row['main_gaps'] ?? ($current[$function]['main_gaps'] ?? ''))
            );

            $calls[] = array(
                'endpoint' => 'report_save_csf',
                'params' => $params
            );

            $fields[] = array(
                'label' => $function,
                'value' => 'Tier '.$params['current_tier'].' now, '.$params['target_tier'].' target'.($params['main_gaps'] !== '' ? ' - '.$params['main_gaps'] : '')
            );
        }

        if (count($calls) === 0) {
            return self::fail('Give at least one CSF function.');
        }

        return array(
            'summary' => 'Set the NIST CSF 2.0 profile in the report for "'.$report['project_name'].'"',
            'calls' => $calls,
            'fields' => $fields
        );
    }

    private function propose_issue_report(array $input): array
    {
        $report = $this->project_report($input);

        if ($report === null) {
            return self::fail('That project has no report yet.');
        }

        return self::proposal('Issue the report for "'.$report['project_name'].'" to the client for response', 'report_issue', array(
            'report_id' => (int) $report['id']
        ), array(
            'Version' => $report['report_version'],
            'Effect' => 'The client\'s portal users can see and respond to the findings'
        ));
    }

    private function propose_finalise_report(array $input): array
    {
        $report = $this->project_report($input);

        if ($report === null) {
            return self::fail('That project has no report yet.');
        }

        return self::proposal('Finalise the report for "'.$report['project_name'].'"', 'report_finalise', array(
            'report_id' => (int) $report['id']
        ), array(
            'Effect' => 'Becomes the final version; the client can download the PDF'
        ));
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
            'project_id' => (int) ($input['project_id'] ?? ($current['project_id'] ?? 0)),
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
        $params['project_id'] = (int) ($input['project_id'] ?? ($current['project_id'] ?? 0));
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

    private function person_name($user_id): string
    {
        $user = $this->first($this->users_model->get_company_user((int) $user_id, Session::get('company_id')));

        return $user === null ? 'User '.(int) $user_id : self::plain($user['first_name'].' '.$user['last_name']);
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
