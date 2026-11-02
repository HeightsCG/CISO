<?php
/**
 * Everything about a client deliverable report except drawing it: the
 * defaults a new report starts with, the rules for each section's fields, and
 * the assembled report (with scores, ratings and counts worked out) that the
 * report page, the portal, the assistant and the renderers all read.
 *
 * Ratings follow the method in the report itself: likelihood times impact,
 * each 1 to 5, gives a score of 1 to 25. High is 15 to 25, Medium 8 to 14,
 * Low 1 to 7.
 */
class ReportBuilder {

    const RATINGS = array(
        'High' => 15,
        'Medium' => 8,
        'Low' => 1
    );

    const DOMAIN_RATINGS = array(
        'Satisfactory',
        'Satisfactory with exceptions',
        'Needs improvement',
        'Unsatisfactory'
    );

    const RESPONSES = array(
        'Pending',
        'Agreed',
        'Partially agreed',
        'Not agreed'
    );

    const CSF_FUNCTIONS = array(
        'Govern',
        'Identify',
        'Protect',
        'Detect',
        'Respond',
        'Recover'
    );

    const TIERS = array(
        1 => 'Partial',
        2 => 'Risk Informed',
        3 => 'Repeatable',
        4 => 'Adaptive'
    );

    /**
     * Each section's fields and how they are checked. Types: text (one line),
     * long (paragraphs), score (1 to 5), date, count, enum, domain (a domain of
     * this report, or none), finding (a finding of this report, or none).
     */
    const SECTIONS = array(
        'version' => array(
            'version_label' => 'text|20|required',
            'version_date' => 'date|0|required',
            'version_note' => 'text|500|optional',
            'version_author' => 'text|255|optional'
        ),
        'person' => array(
            'person_kind' => 'enum|distribution,team|required',
            'person_name' => 'text|255|optional',
            'person_title' => 'text|255|optional',
            'organisation' => 'text|255|optional',
            'responsibility' => 'text|500|optional'
        ),
        'domain' => array(
            'domain_name' => 'text|255|required',
            'areas_reviewed' => 'text|1000|optional',
            'csf_refs' => 'text|255|optional',
            'domain_rating' => 'enum|Satisfactory,Satisfactory with exceptions,Needs improvement,Unsatisfactory|optional',
            'domain_summary' => 'long|20000|optional'
        ),
        'finding' => array(
            'domain_id' => 'domain|0|optional',
            'finding_ref' => 'text|20|optional',
            'finding_title' => 'text|500|required',
            'observation' => 'long|20000|optional',
            'risk_text' => 'long|20000|optional',
            'recommendation' => 'long|20000|optional',
            'likelihood' => 'score|0|required',
            'impact' => 'score|0|required',
            'csf_refs' => 'text|500|optional',
            'response_status' => 'enum|Pending,Agreed,Partially agreed,Not agreed|required',
            'management_response' => 'long|20000|optional',
            'response_owner' => 'text|255|optional',
            'target_date' => 'date|0|optional'
        ),
        'risk' => array(
            'domain_id' => 'domain|0|optional',
            'finding_id' => 'finding|0|optional',
            'risk_ref' => 'text|20|optional',
            'risk_text' => 'text|1000|required',
            'threat_refs' => 'text|255|optional',
            'likelihood' => 'score|0|required',
            'impact' => 'score|0|required',
            'treatment' => 'text|500|optional'
        ),
        'threat' => array(
            'threat_ref' => 'text|20|optional',
            'threat_name' => 'text|255|required',
            'threat_description' => 'text|1000|optional',
            'parties_affected' => 'text|255|optional'
        ),
        'asset' => array(
            'asset_name' => 'text|255|required',
            'asset_kind' => 'text|100|optional',
            'asset_owner' => 'text|255|optional',
            'data_held' => 'text|1000|optional',
            'sensitivity' => 'enum|High,Medium,Low|required'
        ),
        'scan_row' => array(
            'scan_rating' => 'enum|Critical,High,Medium,Low|required',
            'issue_count' => 'count|0|required',
            'scan_summary' => 'text|1000|optional',
            'disposition' => 'text|500|optional'
        ),
        'xref' => array(
            'xref_section' => 'text|100|required',
            'requirement' => 'text|1000|optional',
            'where_addressed' => 'text|500|optional'
        )
    );

    /** Reference prefixes filled in automatically when a row is saved without one. */
    const AUTO_REFS = array(
        'finding' => 'finding_ref|F-|2',
        'risk' => 'risk_ref|R-|2',
        'threat' => 'threat_ref|T|1'
    );

    const DETAIL_FIELDS = array(
        'report_title' => 'text|255|required',
        'report_date' => 'date|0|optional',
        'period_start' => 'date|0|optional',
        'period_end' => 'date|0|optional',
        'regulatory_reference' => 'text|255|optional',
        'purpose_text' => 'long|20000|optional',
        'conclusion_text' => 'long|20000|optional',
        'background_text' => 'long|20000|optional',
        'scope_text' => 'long|20000|optional',
        'out_of_scope_text' => 'long|20000|optional',
        'approach_text' => 'long|20000|optional',
        'methodology_text' => 'long|20000|optional',
        'limitations_text' => 'long|20000|optional',
        'review_text' => 'long|20000|optional',
        'scan_text' => 'long|20000|optional',
        'signoff_name' => 'text|255|optional',
        'signoff_title' => 'text|255|optional'
    );

    private $reports_model;
    private $projects_model;
    private $clients_model;
    private $companies_model;

    public function __construct()
    {
        $this->reports_model = new ReportsModel();
        $this->projects_model = new ProjectsModel();
        $this->clients_model = new ClientsModel();
        $this->companies_model = new CompaniesModel();
    }

    public static function rating(int $score): string
    {
        foreach (self::RATINGS as $rating => $minimum) {
            if ($score >= $minimum) {
                return $rating;
            }
        }

        return 'Low';
    }

    /* --------------------------------------------------------------- create */

    /**
     * A new report for a project, started from the structure of the sample:
     * the seven control domains, the common threats, a CSF row per function,
     * and standard methodology, limitations and review wording naming the
     * consultancy and the client.
     */
    public function create(int $project_id): int
    {
        $company_id = Session::get('company_id');
        $project = $this->projects_model->get_project($project_id, $company_id)[0];
        $client = $this->clients_model->get_client($project['client_id'], $company_id)[0];
        $company = $this->companies_model->get_company($company_id)[0];

        $firm = self::plain($company['company_name']);
        $client_name = self::plain($client['company_name']);

        $report_id = (int) $this->reports_model->add_report(
            $company_id,
            $project_id,
            'Cybersecurity Risk Assessment',
            self::default_methodology(),
            self::default_limitations($firm),
            'This risk assessment will be reviewed and updated at least annually, and sooner if any change in the business or its technology materially changes '.$client_name.'\'s cyber risk, including a new system or service provider that handles sensitive data, a merger or new line of business, a significant cybersecurity event, a material change in locations or remote working, or a new regulatory requirement.',
            Session::get('user_id')
        );

        $order = 1;

        foreach (self::default_domains() as $domain) {
            $this->reports_model->add_domain($report_id, $domain[0], $domain[1], $domain[2], null, null, $order++);
        }

        $order = 1;

        foreach (self::default_threats() as $threat) {
            $this->reports_model->add_threat($report_id, 'T'.$order, $threat[0], $threat[1], $threat[2], $order++);
        }

        foreach (self::CSF_FUNCTIONS as $function) {
            $this->reports_model->save_csf($report_id, $function, null, null, '');
        }

        $this->reports_model->add_person($report_id, 'team', '', 'Engagement lead', $firm, 'Scope, findings and final sign-off', 1);
        $this->reports_model->add_person($report_id, 'team', '', 'Lead assessor', $firm, 'Fieldwork, interviews, technical review', 2);
        $this->reports_model->add_person($report_id, 'team', '', 'Quality reviewer', $firm, 'Independent review of findings and ratings', 3);

        if (self::plain($client['contact_name'] ?? '') !== '') {
            $this->reports_model->add_person($report_id, 'distribution', self::plain($client['contact_name']), self::plain($client['contact_title'] ?? ''), $client_name, '', 1);
        }

        return $report_id;
    }

    /* ------------------------------------------------------------- validate */

    /**
     * Check posted values against a field list. Returns array('values' => ...)
     * in field order, or array('error' => message).
     */
    public function clean(array $fields, array $posted, int $report_id): array
    {
        $values = array();

        foreach ($fields as $field => $spec) {

            $rule = explode('|', $spec);
            $rule[2] = $rule[2] === 'required';
            $type = $rule[0];
            $raw = trim(html_entity_decode((string) ($posted[$field] ?? ''), ENT_QUOTES, 'UTF-8'));
            $label = ucfirst(str_replace('_', ' ', preg_replace('/_text$/', '', $field)));

            if ($rule[2] && $raw === '' && !in_array($type, array('score', 'count'), true)) {
                return self::invalid($field, $label.' is required');
            }

            if ($type === 'text' || $type === 'long') {
                if (mb_strlen($raw) > (int) $rule[1]) {
                    return self::invalid($field, $label.' is too long');
                }
                $values[$field] = $raw;
                continue;
            }

            if ($type === 'score') {
                $number = (int) $raw;
                if ($number < 1 || $number > 5) {
                    return self::invalid($field, $label.' must be between 1 and 5');
                }
                $values[$field] = $number;
                continue;
            }

            if ($type === 'count') {
                $values[$field] = max(0, (int) $raw);
                continue;
            }

            if ($type === 'date') {
                if ($raw !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                    return self::invalid($field, 'Enter '.strtolower($label).' as a valid date');
                }
                $values[$field] = $raw === '' ? null : $raw;
                continue;
            }

            if ($type === 'enum') {
                if ($raw !== '' && !in_array($raw, explode(',', $rule[1]), true)) {
                    return self::invalid($field, 'Choose a valid '.strtolower($label));
                }
                $values[$field] = $raw === '' ? null : $raw;
                continue;
            }

            if ($type === 'domain' || $type === 'finding') {
                $id = (int) $raw;
                if ($id > 0) {
                    $getter = 'get_'.$type;
                    if (count($this->reports_model->$getter($id, $report_id)) !== 1) {
                        return self::invalid($field, 'That '.$type.' is not part of this report');
                    }
                }
                $values[$field] = $id > 0 ? $id : null;
            }
        }

        return array(
            'values' => $values
        );
    }

    /** Save one row of a section, numbering findings, risks and threats when no reference is given. */
    public function save_row(string $section, int $report_id, int $row_id, array $posted): array
    {
        $checked = $this->clean(self::SECTIONS[$section], $posted, $report_id);

        if (isset($checked['error'])) {
            return $checked;
        }

        $values = $checked['values'];

        if (isset(self::AUTO_REFS[$section])) {
            $ref_field = explode('|', self::AUTO_REFS[$section])[0];
            if ($values[$ref_field] === '') {
                $values[$ref_field] = $this->next_ref($section, $report_id);
            }
        }

        if ($row_id > 0) {
            if (count($this->reports_model->{'get_'.$section}($row_id, $report_id)) !== 1) {
                return array(
                    'error' => 'That entry could not be found'
                );
            }
            $this->reports_model->{'update_'.$section}($row_id, $report_id, ...array_values($values));
            return array(
                'id' => $row_id
            );
        }

        $id = $this->reports_model->{'add_'.$section}($report_id, ...array_merge(array_values($values), array(
            $this->reports_model->next_sort_order($section, $report_id)
        )));

        return array(
            'id' => (int) $id
        );
    }

    private function next_ref(string $section, int $report_id): string
    {
        $rule = explode('|', self::AUTO_REFS[$section]);
        $loader = 'load_'.$section.'s';
        $highest = 0;

        foreach ($this->reports_model->$loader($report_id) as $row) {
            if (preg_match('/(\d+)$/', (string) $row[$rule[0]], $match)) {
                $highest = max($highest, (int) $match[1]);
            }
        }

        return $rule[1].str_pad((string) ($highest + 1), (int) $rule[2], '0', STR_PAD_LEFT);
    }

    /* ------------------------------------------------------------- assemble */

    /**
     * The whole report with everything worked out: finding and risk scores and
     * ratings, findings grouped by domain, results by domain, and the totals.
     */
    public function assemble(array $report): array
    {
        $company_id = (int) $report['company_id'];
        $report_id = (int) $report['id'];

        $project = $this->projects_model->get_project($report['project_id'], $company_id)[0] ?? array();
        $client = $this->clients_model->get_client($project['client_id'] ?? 0, $company_id)[0] ?? array();
        $company = $this->companies_model->get_company($company_id)[0] ?? array();

        $domains = $this->reports_model->load_domains($report_id);
        $findings = $this->reports_model->load_findings($report_id);
        $risks = $this->reports_model->load_risks($report_id);

        foreach ($findings as $index => $finding) {
            $findings[$index]['score'] = (int) $finding['likelihood'] * (int) $finding['impact'];
            $findings[$index]['rating'] = self::rating($findings[$index]['score']);
        }

        foreach ($risks as $index => $risk) {
            $risks[$index]['score'] = (int) $risk['likelihood'] * (int) $risk['impact'];
            $risks[$index]['rating'] = self::rating($risks[$index]['score']);
        }

        $by_domain = array();
        $totals = array(
            'High' => 0,
            'Medium' => 0,
            'Low' => 0
        );

        foreach ($domains as $index => $domain) {
            $domains[$index]['counts'] = array(
                'High' => 0,
                'Medium' => 0,
                'Low' => 0
            );
            $domains[$index]['findings'] = array();
            $by_domain[(int) $domain['id']] = $index;
        }

        $unassigned = array();

        foreach ($findings as $finding) {
            $totals[$finding['rating']]++;
            $key = (int) $finding['domain_id'];
            if (isset($by_domain[$key])) {
                $domains[$by_domain[$key]]['counts'][$finding['rating']]++;
                $domains[$by_domain[$key]]['findings'][] = $finding;
            } else {
                $unassigned[] = $finding;
            }
        }

        $ranked = $findings;
        usort($ranked, $this->by_score(...));

        $ranked_risks = $risks;
        usort($ranked_risks, $this->by_score(...));

        $people = $this->reports_model->load_persons($report_id);

        return array(
            'report' => $report,
            'project' => $project,
            'client' => $client,
            'company' => $company,
            'versions' => $this->reports_model->load_versions($report_id),
            'distribution' => array_values(array_filter($people, function ($p) {
                return $p['person_kind'] === 'distribution';
            })),
            'team' => array_values(array_filter($people, function ($p) {
                return $p['person_kind'] === 'team';
            })),
            'domains' => $domains,
            'findings' => $findings,
            'findings_ranked' => $ranked,
            'unassigned_findings' => $unassigned,
            'totals' => $totals,
            'risks' => $risks,
            'risks_ranked' => $ranked_risks,
            'threats' => $this->reports_model->load_threats($report_id),
            'assets' => $this->reports_model->load_assets($report_id),
            'csf' => $this->reports_model->load_csf($report_id),
            'scan_rows' => $this->reports_model->load_scan_rows($report_id),
            'xrefs' => $this->reports_model->load_xrefs($report_id)
        );
    }

    /** Highest score first; ties keep the order they were entered in. */
    public function by_score(array $a, array $b): int
    {
        if ($a['score'] !== $b['score']) {
            return $b['score'] <=> $a['score'];
        }

        return (int) $a['sort_order'] <=> (int) $b['sort_order'];
    }

    /* ------------------------------------------------------------- defaults */

    private static function default_domains(): array
    {
        return array(
            array(
                'Identity and access management',
                'Multi-factor authentication, sign-in methods, administrator accounts, joiner and leaver process, access reviews',
                'PR.AA'
            ),
            array(
                'Network and infrastructure',
                'Firewall and remote access, patching of network devices, wireless networks, network separation, internet-facing systems',
                'PR.IR, PR.PS, ID.RA'
            ),
            array(
                'Data protection and governance',
                'Encryption at rest and in transit, backup and recovery, device security, data handling, third-party service providers',
                'PR.DS, GV.SC, ID.AM'
            ),
            array(
                'Application and software security',
                'Cloud application settings, connected third-party applications, software inventory, patching of computers',
                'PR.PS, ID.AM'
            ),
            array(
                'Policy',
                'Written cybersecurity policies, approval and review cycle',
                'GV.PO, GV.RR, GV.OC'
            ),
            array(
                'Personnel',
                'Security awareness training, phishing exercises, background checks, responsibilities of staff and contractors',
                'PR.AT, GV.RR'
            ),
            array(
                'Incident response',
                'Incident response and business continuity plans, testing, logging and alerting, regulatory notification',
                'RS, RC, DE'
            )
        );
    }

    private static function default_threats(): array
    {
        return array(
            array(
                'Credential theft and account takeover',
                'Phishing or reused passwords give an attacker control of a user account',
                'Clients, firm, regulators'
            ),
            array(
                'Ransomware',
                'Malicious software encrypts data and systems, often after deleting backups',
                'Firm, clients, counterparties'
            ),
            array(
                'Exploitation of unpatched or exposed systems',
                'Attackers use known flaws in internet-facing or internal systems to gain access',
                'Firm, clients'
            ),
            array(
                'Third-party compromise',
                'A service provider holding sensitive data is breached',
                'Clients, firm'
            ),
            array(
                'Lost or stolen device',
                'A device containing sensitive data leaves the firm\'s control',
                'Clients'
            ),
            array(
                'Insider misuse',
                'Current or former staff access data without a business need',
                'Clients, firm'
            ),
            array(
                'Malicious or over-permissioned applications',
                'A connected application reads or exports data beyond what it needs',
                'Clients, firm'
            ),
            array(
                'Service disruption',
                'Failure of a key system, provider or internet connection',
                'Firm, clients'
            )
        );
    }

    private static function default_methodology(): string
    {
        return 'Controls were evaluated against the NIST Cybersecurity Framework (CSF) 2.0, and each finding is referenced to the CSF subcategory it relates to. '
            .'Risks were assessed using the process in NIST Special Publication 800-30 Revision 1, Guide for Conducting Risk Assessments, adapted to the size of the organization. '
            .'Each risk was rated for likelihood and impact on a five-point scale after taking existing controls into account. The two ratings are multiplied to give a score between 1 and 25, which sets the overall rating: High (15 to 25), Medium (8 to 14) or Low (1 to 7).'
            ."\n\n"
            .'For each CSF function we also assigned a current implementation tier, using the four CSF tiers (Partial, Risk Informed, Repeatable, Adaptive), and agreed a target tier with management.';
    }

    private static function default_limitations(string $firm): string
    {
        return 'This assessment reflects conditions observed during fieldwork and is based on information and access provided by management and its service providers. '
            .'It is not an audit, and '.$firm.' does not express an opinion on the effectiveness of internal controls or on compliance with any regulation. '
            .'Conditions may change after the date of this report, and controls that are effective today may become less effective over time.'
            ."\n\n"
            .'Management is responsible for the design and operation of the cybersecurity program, for deciding how each risk is treated, and for the accuracy of the information it provided. '
            .$firm.'\'s procedures were limited to those described in this report and were not designed to detect fraud or every weakness that may exist.';
    }

    private static function invalid(string $field, string $message): array
    {
        return array(
            'error' => $message,
            'field' => $field
        );
    }

    public static function plain($value): string
    {
        return trim(html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8'));
    }

}
