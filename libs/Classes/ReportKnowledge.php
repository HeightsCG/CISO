<?php
/**
 * Framework-agnostic security knowledge used by reports: what each control
 * area is for, what could go wrong when it is weak, how to fix it, what to
 * deliver and how an assessor would confirm the fix. Controls from any
 * framework are mapped to a topic by weighted keyword scoring.
 */
class ReportKnowledge {

    const FALLBACK = 'governance';

    const PHASES = array(
        'Immediate priorities',
        'Foundational improvements',
        'Operational control enhancements',
        'Program maturity'
    );

    const SHORT_TITLE_WORDS = 12;

    const LONG_TEXT_WORDS = 40;

    const FIELD_WEIGHTS = array(
        'title' => 6.0,
        'description' => 2.0,
        'family' => 2.5,
        'guidance' => 1.0,
        'objective_title' => 1.0,
        'objective_description' => 0.5
    );

    private static $topics = null;
    private static $patterns = null;

    /**
     * All topics keyed by topic key, in program order.
     */
    public static function topics(): array
    {
        if (self::$topics === null) {
            self::$topics = array();

            foreach (self::definitions() as $key => $topic) {
                self::$topics[$key] = array_merge(
                    array(
                        'key' => $key
                    ),
                    $topic,
                    array(
                        'evidence' => array_column(self::evidence_catalog()[$key] ?? array(), 'text')
                    )
                );
            }
        }

        return self::$topics;
    }

    /**
     * One topic; unknown keys fall back to governance.
     */
    public static function topic(string $key): array
    {
        $topics = self::topics();

        return $topics[$key] ?? $topics[self::FALLBACK];
    }

    /**
     * Best topic key for a control (control_title, description, guidance,
     * family) and its objectives (control_title, description).
     */
    public static function topic_for(array $control, array $objectives = array()): string
    {
        $title = trim((string) ($control['control_title'] ?? ''));
        $description = trim((string) ($control['description'] ?? ''));
        $fields = array(
            'title' => array(),
            'description' => array(),
            'family' => array(
                $control['family'] ?? ''
            ),
            'guidance' => array(
                $control['guidance'] ?? ''
            ),
            'objective_title' => array(),
            'objective_description' => array()
        );

        if (str_word_count($title) > self::SHORT_TITLE_WORDS) {
            $fields['description'][] = $title;
        } else {
            $fields['title'][] = $title;
        }

        if ($description !== $title) {
            $fields['description'][] = $description;
        }

        $objective_titles = array();
        $objective_descriptions = array();

        foreach ($objectives as $objective) {
            $objective_title = trim((string) ($objective['control_title'] ?? ''));
            $objective_description = trim((string) ($objective['description'] ?? ''));
            $objective_titles[] = $objective_title;

            if ($objective_description !== $objective_title) {
                $objective_descriptions[] = $objective_description;
            }
        }

        $fields['objective_title'][] = implode("\n", $objective_titles);
        $fields['objective_description'][] = implode("\n", $objective_descriptions);

        $scores = self::scores($fields);
        $best = self::FALLBACK;
        $best_score = 0.0;

        foreach ($scores as $key => $score) {
            if ($score > $best_score) {
                $best = $key;
                $best_score = $score;
            }
        }

        return $best;
    }

    /**
     * Suggested evidence for a requirement: examples from its topics (the
     * first is the primary one), ranked by how many of each example's
     * keywords appear in the requirement text, then by topic and catalog
     * order. These are suggestions for future collection, never records
     * that exist.
     */
    public static function suggested_evidence(array $topic_keys, string $text, int $limit = 4): array
    {
        $catalog = self::evidence_catalog();
        $topic_keys = array_values(array_unique($topic_keys));
        $limit = max(1, $limit);

        if (count($topic_keys) === 0) {
            $topic_keys[] = self::FALLBACK;
        }

        $matched = array();
        $primary_hit = false;

        foreach ($topic_keys as $topic_index => $key) {
            foreach ($catalog[$key] ?? array() as $item_index => $item) {
                $hits = 0;
                foreach ($item['keywords'] as $keyword) {
                    if (preg_match('/\\b'.preg_quote($keyword, '/').'/i', $text) === 1) {
                        $hits++;
                    }
                }
                if ($hits === 0) {
                    continue;
                }
                if ($topic_index === 0) {
                    $primary_hit = true;
                }
                $score = 10 * min($hits, 3) - 8 * $topic_index - $item_index;
                if (!isset($matched[$item['text']]) || $matched[$item['text']] < $score) {
                    $matched[$item['text']] = $score;
                }
            }
        }

        arsort($matched);
        $out = array_keys($matched);
        $defaults = array_column($catalog[$topic_keys[0]] ?? $catalog[self::FALLBACK], 'text');

        if (!$primary_hit && count($defaults) > 0) {
            array_unshift($out, $defaults[0]);
        }

        foreach ($defaults as $default) {
            if (count($out) >= min(2, $limit)) {
                break;
            }
            if (!in_array($default, $out, true)) {
                $out[] = $default;
            }
        }

        return array_slice(array_values(array_unique($out)), 0, $limit);
    }

    /**
     * Topic keys for a control and its objectives, the control's own topic
     * first, then the other topics its objectives map to, most frequent first.
     */
    public static function topics_for(array $control, array $objectives = array()): array
    {
        $primary = self::topic_for($control, $objectives);
        $counts = array();

        foreach ($objectives as $objective) {
            $scores = self::scores(array(
                'title' => array(
                    $objective['control_title'] ?? ''
                ),
                'description' => array(
                    $objective['description'] ?? ''
                )
            ));
            arsort($scores);
            $key = (string) array_key_first($scores);
            if ($scores[$key] >= 3.0 && $key !== $primary) {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        arsort($counts);

        return array_merge(array(
            $primary
        ), array_keys($counts));
    }

    /**
     * Score of every topic for a set of texts grouped by field.
     */
    private static function scores(array $fields): array
    {
        $scores = array();

        foreach (array_keys(self::topics()) as $key) {
            $scores[$key] = 0.0;
        }

        foreach ($fields as $field => $texts) {
            $weight = self::FIELD_WEIGHTS[$field];

            foreach ($texts as $text) {
                $text = strtolower(trim((string) $text));

                if ($text === '') {
                    continue;
                }

                $damping = min(1.0, sqrt(self::LONG_TEXT_WORDS / max(1, str_word_count($text))));

                foreach (self::patterns() as $key => $patterns) {
                    foreach ($patterns as $pattern => $keyword_weight) {
                        if (preg_match($pattern, $text)) {
                            $scores[$key] += $weight * $damping * $keyword_weight;
                        }
                    }
                }
            }
        }

        return $scores;
    }

    /**
     * Compiled keyword patterns per topic. A trailing * matches any word
     * ending; otherwise the keyword matches as a whole word or its plural.
     */
    private static function patterns(): array
    {
        if (self::$patterns === null) {
            self::$patterns = array();

            foreach (self::keywords() as $key => $keywords) {
                self::$patterns[$key] = array();

                foreach ($keywords as $keyword => $weight) {
                    if (substr($keyword, -1) === '*') {
                        $pattern = '/\b'.preg_quote(substr($keyword, 0, -1), '/').'[\w-]*/';
                    } else {
                        $pattern = '/\b'.preg_quote($keyword, '/').'(s|es)?\b/';
                    }

                    self::$patterns[$key][$pattern] = $weight;
                }
            }
        }

        return self::$patterns;
    }

    private static function keywords(): array
    {
        return array(
            'governance' => array(
                'governance' => 3,
                'oversight' => 3,
                'board' => 3,
                'board of directors' => 2,
                'senior management' => 2,
                'top management' => 3,
                'leadership' => 2,
                'management review' => 3,
                'management commitment' => 3,
                'organizational context' => 2,
                'context of the organization' => 3,
                'interested parties' => 2,
                'stakeholder*' => 1,
                'mission' => 2,
                'program management' => 2,
                'strategy' => 1.5,
                'strategic' => 1,
                'management system' => 2,
                'information security management system' => 2,
                'security program' => 1.5,
                'cybersecurity program' => 2,
                'resources' => 1,
                'legal' => 1,
                'regulatory' => 1,
                'contact with authorities' => 3,
                'special interest groups' => 3,
                'intellectual property' => 2
            ),
            'ethics' => array(
                'ethic*' => 3,
                'code of conduct' => 3,
                'standards of conduct' => 3,
                'conduct' => 1.5,
                'whistleblow*' => 3,
                'disciplinary' => 3,
                'sanction*' => 2.5,
                'rules of behavior' => 3,
                'acceptable use' => 2,
                'integrity and ethical values' => 3,
                'misconduct' => 3
            ),
            'risk' => array(
                'risk*' => 0.5,
                'risk assessment' => 3,
                'risk management' => 1,
                'risk register' => 3,
                'risk treatment' => 3,
                'risk response' => 3,
                'risk tolerance' => 3,
                'risk appetite' => 3,
                'risk analysis' => 3,
                'assess the risk' => 3,
                'assess risk' => 3,
                'risk is assessed' => 3,
                'risks are assessed' => 3,
                'threat*' => 1,
                'likelihood' => 2,
                'fraud' => 2.5,
                'categorization' => 2,
                'security categorization' => 3,
                'criticality analysis' => 2,
                'threat intelligence' => 1,
                'significant change' => 1
            ),
            'policy' => array(
                'polic*' => 2.5,
                'policies and procedures' => -1.5,
                'procedure*' => 0.5,
                'documentation' => 2,
                'documented information' => 3,
                'control of documented information' => 3,
                'security plan' => 2,
                'system security plan' => 3,
                'planning' => 1,
                'concept of operations' => 2,
                'review and update' => 0.5,
                'operating procedure' => 2,
                'documented operating procedure' => 2
            ),
            'roles' => array(
                'role*' => 2,
                'responsibilit*' => 2,
                'accountab*' => 2,
                'authority' => 1,
                'authorities' => 1,
                'separation of duties' => 3,
                'segregation of duties' => 3,
                'duties' => 2,
                'separate the duties' => 3,
                'organizational structure' => 2,
                'reporting line*' => 3,
                'appoint*' => 2,
                'designat*' => 1,
                'chief information security officer' => 3,
                'ciso' => 3,
                'qualified individual' => 3,
                'security officer' => 3,
                'security official' => 3
            ),
            'personnel' => array(
                'personnel' => 2,
                'screening' => 3,
                'screen individuals' => 3,
                'background check*' => 3,
                'background verification' => 3,
                'hiring' => 2,
                'termination' => 3,
                'terminat*' => 1,
                'transfer*' => 0.5,
                'employment' => 2,
                'confidentiality agreement*' => 3,
                'non-disclosure' => 2,
                'nondisclosure' => 2,
                'access agreement*' => 3,
                'position risk' => 3,
                'position description*' => 2,
                'external personnel' => 2,
                'recruit*' => 2,
                'workforce' => 1,
                'workforce clearance' => 3,
                'remote working' => 1
            ),
            'awareness' => array(
                'awareness' => 3,
                'aware' => 3,
                'training' => 3,
                'train*' => 1,
                'educat*' => 2,
                'reminder*' => 2,
                'phishing' => 1.5,
                'social engineering' => 2,
                'competen*' => 2,
                'skill*' => 2,
                'literacy' => 3,
                'insider threat' => 1
            ),
            'assets' => array(
                'inventor*' => 3,
                'asset*' => 2,
                'asset management' => 3,
                'component inventory' => 3,
                'information system component' => 1,
                'hardware' => 1,
                'unauthorized software' => 2,
                'boundar*' => 1,
                'data flow*' => 1.5,
                'ownership' => 1,
                'return of assets' => 3,
                'labeling' => 1.5,
                'labelling' => 1.5,
                'classification of information' => 2,
                'device*' => 0.5
            ),
            'identity' => array(
                'authenticat*' => 3,
                'identification' => 2,
                'identity' => 2.5,
                'identities' => 2.5,
                'password*' => 3,
                'passphrase*' => 3,
                'credential*' => 2,
                'multi-factor' => 3,
                'multifactor' => 3,
                'mfa' => 3,
                'authenticator*' => 3,
                'single sign-on' => 2,
                'session lock' => 3,
                'user session' => 3,
                'session termination' => 3,
                'session*' => 1,
                'logon' => 2,
                'logoff' => 3,
                'log-off' => 3,
                'login' => 2,
                'log-on' => 2,
                'unsuccessful logon' => 3,
                'identifier management' => 3,
                'biometric*' => 2,
                'unique user' => 3,
                'user id' => 2,
                'replay resistant' => 2,
                'device identification' => 2
            ),
            'privileged' => array(
                'privileged' => 4,
                'privilege*' => 1.5,
                'administrator*' => 2,
                'admin' => 2,
                'administrative access' => 3,
                'superuser' => 3,
                'root' => 2,
                'elevated' => 2,
                'security functions' => 2,
                'non-privileged' => 2,
                'privileged utility' => 3,
                'privileged access' => 2
            ),
            'access' => array(
                'access' => 1,
                'access control' => 2,
                'logical access' => 3,
                'least privilege' => 3,
                'need to know' => 3,
                'need-to-know' => 3,
                'provision*' => 2,
                'deprovision*' => 2,
                'access review*' => 3,
                'review of user access' => 3,
                'account management' => 3,
                'account*' => 1,
                'entitlement*' => 2,
                'authoriz*' => 1,
                'permission*' => 2,
                'revok*' => 2,
                'role-based' => 2,
                'access right*' => 3,
                'access enforcement' => 3,
                'access restriction*' => 2,
                'user access' => 2,
                'inactive' => 2,
                'dormant' => 2,
                'restrict access' => 2,
                'publicly accessible' => 2
            ),
            'network' => array(
                'network*' => 2,
                'firewall*' => 3,
                'segment*' => 3,
                'boundary protection' => 3,
                'remote access' => 2.5,
                'wireless' => 3,
                'vpn' => 2,
                'router*' => 2,
                'perimeter' => 2,
                'ingress' => 2,
                'egress' => 2,
                'traffic' => 2,
                'dns' => 2,
                'domain name' => 1,
                'port*' => 0.5,
                'internet' => 1,
                'communications protection' => 2,
                'communication' => 1,
                'external boundar*' => 3,
                'internal boundar*' => 2,
                'denial of service' => 2,
                'denial-of-service' => 2,
                'external connection*' => 2,
                'interconnection*' => 2,
                'information flow' => 2,
                'zone*' => 1.5,
                'dmz' => 3,
                'isolation' => 2,
                'security group*' => 2,
                'network security control*' => 3,
                'web filtering' => 1,
                'split tunneling' => 3,
                'collaborative computing' => 2,
                'trusted path' => 1,
                'public networks' => 1
            ),
            'data_protection' => array(
                'encrypt*' => 3,
                'cryptograph*' => 3,
                'cipher*' => 2,
                'key management' => 3,
                'cryptographic key*' => 3,
                'tls' => 2,
                'confidential*' => 1.5,
                'at rest' => 2,
                'in transit' => 2,
                'transmission' => 1.5,
                'media' => 2,
                'sanitiz*' => 3,
                'disposal' => 2,
                'destruction' => 2,
                'destroy*' => 1,
                'data loss' => 3,
                'leakage' => 2,
                'dlp' => 3,
                'masking' => 3,
                'mask*' => 1,
                'truncat*' => 2,
                'pan' => 3,
                'card verification code*' => 3,
                'account data' => 3,
                'cardholder data' => 1,
                'sensitive authentication data' => 3,
                'removable' => 2,
                'portable storage' => 2,
                'storage' => 0.5,
                'information transfer' => 2,
                'data security' => 2,
                'data integrity' => 2,
                'processing integrity' => 2,
                'input*' => 1,
                'output*' => 1,
                'hash*' => 1,
                'certificate*' => 1,
                'protected health information' => 0.5,
                'integrity' => 0.5,
                'processing' => 0.5,
                'accuracy' => 1
            ),
            'privacy' => array(
                'privacy' => 3,
                'security and privacy' => -2.5,
                'personal information' => 3,
                'personal data' => 3,
                'personally identifiable' => 3,
                'pii' => 3,
                'consent' => 3,
                'notice' => 1,
                'privacy notice' => 3,
                'retention' => 2,
                'retain*' => 1,
                'data subject*' => 3,
                'individual*' => 0.5,
                'disclos*' => 1,
                'choice' => 2,
                'opt-out' => 3,
                'minimiz*' => 2,
                'purpose specification' => 3,
                'collect*' => 1,
                'correction' => 2,
                'redress' => 2,
                'complaint*' => 1,
                'protected health information' => 1,
                'de-identif*' => 3,
                'record*' => 0.5
            ),
            'configuration' => array(
                'configuration*' => 3,
                'hardening' => 3,
                'harden*' => 2,
                'baseline*' => 3,
                'default*' => 2,
                'least functionality' => 3,
                'unnecessary' => 2,
                'maintenance' => 3,
                'maintenance tools' => 2,
                'vendor-supplied' => 2,
                'setting*' => 2,
                'secure configuration' => 3,
                'image*' => 1,
                'drift' => 2,
                'nonlocal maintenance' => 2,
                'software usage' => 2,
                'user-installed software' => 3,
                'installation of software' => 2,
                'timely maintenance' => 2
            ),
            'vulnerability' => array(
                'vulnerabilit*' => 3,
                'patch*' => 3,
                'scan*' => 2,
                'penetration' => 3,
                'flaw remediation' => 3,
                'flaw*' => 2,
                'security update*' => 2,
                'security patch*' => 2,
                'unsupported' => 2,
                'end of life' => 2,
                'end-of-life' => 2,
                'exploit*' => 1,
                'technical vulnerabilit*' => 2,
                'red team' => 2
            ),
            'endpoint' => array(
                'malware' => 3,
                'malicious code' => 3,
                'malicious software' => 3,
                'anti-malware' => 3,
                'antivirus' => 3,
                'virus*' => 2,
                'endpoint*' => 3,
                'mobile device*' => 3,
                'mobile code' => 2,
                'anti-phishing' => 3,
                'spam' => 2,
                'email' => 1,
                'e-mail' => 1,
                'application control' => 2,
                'workstation*' => 1,
                'laptop*' => 1,
                'ransomware' => 2,
                'portable' => 1,
                'user endpoint device*' => 3,
                'web browser*' => 1,
                'executable' => 1
            ),
            'change' => array(
                'change*' => 2,
                'change management' => 3,
                'change control' => 3,
                'configuration change' => 2,
                'emergency change' => 3,
                'approv*' => 0.5,
                'deploy*' => 1,
                'release*' => 1,
                'production' => 1,
                'rollback' => 2,
                'back-out' => 2,
                'impact analys*' => 1.5,
                'security impact' => 2,
                'test environment*' => 2,
                'separation of development' => 3,
                'pre-production' => 2,
                'version control' => 1
            ),
            'sdlc' => array(
                'development' => 2,
                'secure development' => 3,
                'software development' => 3,
                'development life cycle' => 2,
                'system development life cycle' => 2,
                'sdlc' => 3,
                'coding' => 3,
                'code' => 2,
                'source code' => 3,
                'application security' => 3,
                'bespoke' => 2,
                'custom software' => 3,
                'acquisition*' => 2,
                'developer*' => 3,
                'threat model*' => 3,
                'security engineering' => 3,
                'engineering principles' => 3,
                'outsourced development' => 3,
                'web application*' => 2,
                'payment page*' => 2,
                'script*' => 1,
                'architecture' => 1,
                'security requirements' => 1,
                'software libraries' => 2,
                'test data' => 2,
                'security testing' => 1
            ),
            'logging' => array(
                'log' => 3,
                'logging' => 3,
                'audit log*' => 3,
                'audit record*' => 3,
                'audit trail*' => 3,
                'audit event*' => 3,
                'audit and accountability' => 3,
                'audit control*' => 3,
                'logged' => 3,
                'traced' => 2,
                'audit review' => 2,
                'monitor*' => 2,
                'siem' => 3,
                'alert*' => 2,
                'detect*' => 1,
                'detection' => 1,
                'anomal*' => 2,
                'intrusion*' => 2,
                'clock synchronization' => 3,
                'time stamp*' => 2,
                'timestamp*' => 2,
                'time synchronization' => 3,
                'adverse event*' => 2,
                'security event*' => 2,
                'continuous monitoring' => 1,
                'system monitoring' => 3,
                'unauthorized use' => 1
            ),
            'incident' => array(
                'incident*' => 3,
                'incident response' => 3,
                'breach*' => 2,
                'notification' => 1,
                'notif*' => 1,
                'forensic*' => 2,
                'containment' => 2,
                'contain' => 1,
                'eradicat*' => 2,
                'response plan*' => 2,
                'cybersecurity event*' => 2,
                'escalat*' => 1,
                'lessons learned' => 2,
                'tabletop' => 2,
                'triage' => 2,
                'collection of evidence' => 3,
                'reportable' => 2,
                'security event reporting' => 2
            ),
            'continuity' => array(
                'backup*' => 3,
                'back up' => 3,
                'back-up' => 3,
                'contingency' => 3,
                'continuity' => 3,
                'disaster' => 3,
                'recovery' => 2,
                'recover*' => 1,
                'restor*' => 2,
                'resilien*' => 2,
                'availability' => 2,
                'redundan*' => 2,
                'alternate' => 2,
                'capacity' => 2,
                'business impact' => 3,
                'outage*' => 1,
                'failover' => 2,
                'emergency mode' => 3,
                'emergency access' => 3,
                'emergency operation*' => 2,
                'disruption' => 2
            ),
            'third_party' => array(
                'supplier*' => 3,
                'supplier service*' => 3,
                'vendor*' => 2,
                'third part*' => 3,
                'third-part*' => 3,
                'service provider*' => 3,
                'supply chain' => 4,
                'outsourc*' => 2,
                'contract*' => 1,
                'agreement*' => 0.5,
                'business associate*' => 3,
                'external service*' => 3,
                'external system service*' => 3,
                'cloud service*' => 1.5,
                'tpsp*' => 3,
                'partner*' => 1,
                'subservice' => 3,
                'procurement' => 2,
                'counterfeit' => 2,
                'provenance' => 2,
                'external provider*' => 3
            ),
            'physical' => array(
                'physical*' => 3,
                'physical access' => 2,
                'facilit*' => 2,
                'premises' => 3,
                'visitor*' => 3,
                'badge*' => 2,
                'environmental' => 2,
                'fire' => 2,
                'power' => 1,
                'water damage' => 2,
                'temperature' => 2,
                'humidity' => 2,
                'cabling' => 2,
                'equipment' => 1,
                'secure area*' => 3,
                'clear desk' => 3,
                'clear screen' => 2,
                'delivery and removal' => 2,
                'escort*' => 2,
                'poi' => 2,
                'point of interaction' => 2,
                'tamper*' => 1,
                'workplace' => 1,
                'work site*' => 2,
                'off-premises' => 2,
                'emergency lighting' => 3,
                'emergency shutoff' => 3,
                'utilities' => 2,
                'offices' => 2
            ),
            'control_monitoring' => array(
                'internal audit*' => 3,
                'audit program*' => 2,
                'independent review' => 3,
                'independent assessment*' => 3,
                'assessment*' => 1,
                'control assessment*' => 3,
                'security assessment*' => 2,
                'monitoring activities' => 3,
                'evaluat*' => 1,
                'deficienc*' => 3,
                'nonconformit*' => 3,
                'corrective action*' => 3,
                'plan of action' => 3,
                'compliance' => 1,
                'continual improvement' => 3,
                'tests and exercises' => 3,
                'improvement*' => 3,
                'measurement' => 1.5,
                'performance evaluation' => 3,
                'effectiveness' => 1.5,
                'metrics' => 1,
                'measures of performance' => 3,
                'authorization to operate' => 2,
                'audit' => 1
            ),
            'communication' => array(
                'communication' => 1,
                'communicates' => 2,
                'communicated' => 1,
                'internal communication' => 3,
                'lines of communication' => 3,
                'external communication' => 3,
                'information and communication' => 3,
                'quality information' => 3,
                'information quality' => 3,
                'relevant information' => 2,
                'relevant, quality information' => 3,
                'external parties' => 1,
                'commitments' => 2,
                'system description' => 3,
                'information sharing' => 2,
                'reporting channel*' => 2,
                'objectives' => 0.5,
                'transparency' => 1
            )
        );
    }

    /**
     * Suggested evidence by topic: records that would typically demonstrate
     * a control in the topic. Keywords (matched at the start of a word)
     * rank the examples against the wording of a specific requirement.
     */
    private static function evidence_catalog(): array
    {
        return array(
            'governance' => array(
                array(
                    'text' => 'Minutes of the last security oversight or board meetings, with decisions and actions recorded',
                    'keywords' => array(
                        'board',
                        'oversight',
                        'governance',
                        'meeting',
                        'independen'
                    )
                ),
                array(
                    'text' => 'Approved security program charter or terms of reference for the oversight body',
                    'keywords' => array(
                        'charter',
                        'oversight',
                        'structure',
                        'terms of reference'
                    )
                ),
                array(
                    'text' => 'Periodic security status reports or metrics packs presented to leadership',
                    'keywords' => array(
                        'report',
                        'metric',
                        'performance',
                        'status'
                    )
                ),
                array(
                    'text' => 'Signed appointment of the executive accountable for the security program',
                    'keywords' => array(
                        'appoint',
                        'accountab',
                        'responsib',
                        'sponsor',
                        'officer'
                    )
                ),
                array(
                    'text' => 'Records of external advisors or specialists engaged to support oversight',
                    'keywords' => array(
                        'expertise',
                        'advisor',
                        'specialist',
                        'supplement'
                    )
                )
            ),
            'ethics' => array(
                array(
                    'text' => 'Approved code of conduct with its version history',
                    'keywords' => array(
                        'conduct',
                        'ethic',
                        'integrity',
                        'standard'
                    )
                ),
                array(
                    'text' => 'Signed code of conduct acknowledgements for a sample of employees and contractors',
                    'keywords' => array(
                        'acknowledg',
                        'contractor',
                        'vendor employee',
                        'adherence'
                    )
                ),
                array(
                    'text' => 'Whistleblower or ethics reporting channel log showing reports received and closed',
                    'keywords' => array(
                        'whistle',
                        'report',
                        'hotline',
                        'concern',
                        'deviation'
                    )
                ),
                array(
                    'text' => 'Records of disciplinary cases showing the sanctions process was followed',
                    'keywords' => array(
                        'disciplin',
                        'sanction',
                        'deviation',
                        'timely',
                        'enforce'
                    )
                )
            ),
            'risk' => array(
                array(
                    'text' => 'Current risk register with owners, ratings and treatment decisions',
                    'keywords' => array(
                        'risk',
                        'register',
                        'treatment',
                        'identif'
                    )
                ),
                array(
                    'text' => 'Approved risk assessment methodology with likelihood and impact criteria',
                    'keywords' => array(
                        'methodolog',
                        'criteria',
                        'toleran',
                        'objective'
                    )
                ),
                array(
                    'text' => 'Latest risk assessment report and the management approval of it',
                    'keywords' => array(
                        'assess',
                        'analy',
                        'significance',
                        'approv'
                    )
                ),
                array(
                    'text' => 'Fraud risk assessment covering incentives, pressures and opportunities',
                    'keywords' => array(
                        'fraud',
                        'incentive',
                        'pressure'
                    )
                ),
                array(
                    'text' => 'Records of risk reviews triggered by significant changes in the business or technology',
                    'keywords' => array(
                        'change',
                        'significant',
                        'environment',
                        'business model'
                    )
                ),
                array(
                    'text' => 'Risk acceptance records signed by the accountable executive',
                    'keywords' => array(
                        'accept',
                        'toleran',
                        'residual'
                    )
                )
            ),
            'policy' => array(
                array(
                    'text' => 'Approved policy set with owner, version and approval date for each document',
                    'keywords' => array(
                        'policy',
                        'policies',
                        'approv',
                        'version'
                    )
                ),
                array(
                    'text' => 'Policy review log showing each policy reviewed within its review cycle',
                    'keywords' => array(
                        'review',
                        'periodic',
                        'update',
                        'reassess'
                    )
                ),
                array(
                    'text' => 'Records showing policies were published and communicated to staff',
                    'keywords' => array(
                        'communicat',
                        'publish',
                        'deploy',
                        'distribut'
                    )
                ),
                array(
                    'text' => 'Approved policy exception records with expiry dates',
                    'keywords' => array(
                        'exception',
                        'deviation',
                        'waiver'
                    )
                )
            ),
            'roles' => array(
                array(
                    'text' => 'Organization chart showing security and control reporting lines',
                    'keywords' => array(
                        'structure',
                        'reporting line',
                        'organization chart',
                        'chart'
                    )
                ),
                array(
                    'text' => 'Security responsibility (RACI) matrix for key controls',
                    'keywords' => array(
                        'responsib',
                        'raci',
                        'authorit',
                        'accountab'
                    )
                ),
                array(
                    'text' => 'Job descriptions that include security responsibilities',
                    'keywords' => array(
                        'job description',
                        'role',
                        'competen'
                    )
                ),
                array(
                    'text' => 'Segregation of duties matrix for sensitive functions',
                    'keywords' => array(
                        'segregat',
                        'separation',
                        'duties',
                        'incompatible'
                    )
                )
            ),
            'personnel' => array(
                array(
                    'text' => 'Background screening records for a sample of new hires and contractors',
                    'keywords' => array(
                        'background',
                        'screen',
                        'vetting',
                        'hire',
                        'check'
                    )
                ),
                array(
                    'text' => 'Signed confidentiality or non-disclosure agreements',
                    'keywords' => array(
                        'confidential',
                        'non-disclosure',
                        'agreement',
                        'terms'
                    )
                ),
                array(
                    'text' => 'Onboarding and offboarding checklists completed for a sample of joiners and leavers',
                    'keywords' => array(
                        'onboard',
                        'offboard',
                        'terminat',
                        'transfer',
                        'leaver',
                        'joiner'
                    )
                ),
                array(
                    'text' => 'Competence assessments or skills reviews for staff in security roles',
                    'keywords' => array(
                        'competen',
                        'skill',
                        'technical',
                        'qualified',
                        'evaluat'
                    )
                ),
                array(
                    'text' => 'Performance reviews or objectives that include control responsibilities',
                    'keywords' => array(
                        'performance',
                        'incentive',
                        'reward',
                        'accountab'
                    )
                )
            ),
            'awareness' => array(
                array(
                    'text' => 'Security awareness training completion records for all staff in the period',
                    'keywords' => array(
                        'awareness',
                        'training',
                        'complet'
                    )
                ),
                array(
                    'text' => 'Training curriculum and materials, including role-based content',
                    'keywords' => array(
                        'curriculum',
                        'role-based',
                        'content',
                        'technical training',
                        'competenc'
                    )
                ),
                array(
                    'text' => 'Phishing simulation results and follow-up training records',
                    'keywords' => array(
                        'phish',
                        'simulat',
                        'social engineering'
                    )
                ),
                array(
                    'text' => 'Insider-threat or privacy training records for staff handling sensitive data',
                    'keywords' => array(
                        'insider',
                        'privacy',
                        'personal'
                    )
                )
            ),
            'assets' => array(
                array(
                    'text' => 'Current hardware and software asset inventory with owners',
                    'keywords' => array(
                        'inventor',
                        'asset',
                        'hardware',
                        'software',
                        'component'
                    )
                ),
                array(
                    'text' => 'Data flow diagrams for systems processing sensitive information',
                    'keywords' => array(
                        'flow',
                        'diagram',
                        'boundar'
                    )
                ),
                array(
                    'text' => 'Asset classification records showing criticality or sensitivity',
                    'keywords' => array(
                        'classif',
                        'criticality',
                        'sensitiv'
                    )
                ),
                array(
                    'text' => 'Records of asset disposal or return when equipment is retired',
                    'keywords' => array(
                        'dispos',
                        'retire',
                        'return',
                        'sanitiz'
                    )
                )
            ),
            'identity' => array(
                array(
                    'text' => 'User access request and approval records for a sample of new accounts',
                    'keywords' => array(
                        'provision',
                        'request',
                        'registration',
                        'new user',
                        'authoriz',
                        'grant'
                    )
                ),
                array(
                    'text' => 'Leaver records showing access removed within the defined timeframe',
                    'keywords' => array(
                        'terminat',
                        'revok',
                        'remov',
                        'leaver',
                        'deprovision',
                        'disabl'
                    )
                ),
                array(
                    'text' => 'User access review records with reviewer sign-off and resulting changes',
                    'keywords' => array(
                        'review',
                        'recertif',
                        'periodic',
                        'appropriate'
                    )
                ),
                array(
                    'text' => 'Authentication configuration showing password and multi-factor settings',
                    'keywords' => array(
                        'authenticat',
                        'password',
                        'credential',
                        'multi-factor',
                        'mfa',
                        'factor',
                        'logon',
                        'identif'
                    )
                ),
                array(
                    'text' => 'Account inventory reconciled to the HR roster',
                    'keywords' => array(
                        'account',
                        'inventor',
                        'unique',
                        'identif',
                        'shared'
                    )
                )
            ),
            'privileged' => array(
                array(
                    'text' => 'Privileged account inventory with named owners',
                    'keywords' => array(
                        'privileg',
                        'administrat',
                        'admin',
                        'elevated'
                    )
                ),
                array(
                    'text' => 'Privileged access approval and periodic review records',
                    'keywords' => array(
                        'approv',
                        'review',
                        'least privilege'
                    )
                ),
                array(
                    'text' => 'Session logs or privileged access management reports for administrative activity',
                    'keywords' => array(
                        'session',
                        'logs',
                        'monitor',
                        'audit'
                    )
                ),
                array(
                    'text' => 'Break-glass or emergency account usage records',
                    'keywords' => array(
                        'emergency',
                        'break-glass',
                        'shared'
                    )
                )
            ),
            'access' => array(
                array(
                    'text' => 'Access control policy and role-based access matrix',
                    'keywords' => array(
                        'role',
                        'matrix',
                        'least privilege',
                        'need to know',
                        'polic'
                    )
                ),
                array(
                    'text' => 'Access modification records for staff changing roles',
                    'keywords' => array(
                        'modif',
                        'transfer',
                        'change',
                        'role'
                    )
                ),
                array(
                    'text' => 'Remote access configuration and approval records',
                    'keywords' => array(
                        'remote',
                        'vpn',
                        'external',
                        'session'
                    )
                ),
                array(
                    'text' => 'Session lock and timeout configuration screenshots',
                    'keywords' => array(
                        'session',
                        'lock',
                        'timeout',
                        'inactiv'
                    )
                ),
                array(
                    'text' => 'Wireless and mobile device access configuration records',
                    'keywords' => array(
                        'wireless',
                        'mobile',
                        'device',
                        'portable'
                    )
                )
            ),
            'network' => array(
                array(
                    'text' => 'Current network diagram showing zones, boundaries and external connections',
                    'keywords' => array(
                        'diagram',
                        'boundar',
                        'zone',
                        'segment',
                        'architecture'
                    )
                ),
                array(
                    'text' => 'Firewall and boundary rule sets with the record of their latest review',
                    'keywords' => array(
                        'firewall',
                        'rule',
                        'boundary',
                        'perimeter',
                        'external access',
                        'access point'
                    )
                ),
                array(
                    'text' => 'External attack surface or penetration test report with remediation status',
                    'keywords' => array(
                        'penetration',
                        'external',
                        'threat',
                        'attack',
                        'exposure'
                    )
                ),
                array(
                    'text' => 'Encryption-in-transit configuration for external connections',
                    'keywords' => array(
                        'transmi',
                        'transit',
                        'encrypt',
                        'channel',
                        'tls'
                    )
                ),
                array(
                    'text' => 'Intrusion detection or prevention alerts and the triage record',
                    'keywords' => array(
                        'intrusion',
                        'detect',
                        'prevent',
                        'malicious'
                    )
                )
            ),
            'data_protection' => array(
                array(
                    'text' => 'Data classification standard and a sample of labelled information assets',
                    'keywords' => array(
                        'classif',
                        'label',
                        'confidential',
                        'sensitiv',
                        'identif'
                    )
                ),
                array(
                    'text' => 'Encryption configuration records for data at rest and in transit',
                    'keywords' => array(
                        'encrypt',
                        'crypto',
                        'key management',
                        'transit',
                        'at rest'
                    )
                ),
                array(
                    'text' => 'Retention schedule and records of secure destruction or disposal',
                    'keywords' => array(
                        'retention',
                        'retain',
                        'destr',
                        'dispos',
                        'archiv',
                        'purge'
                    )
                ),
                array(
                    'text' => 'Processing specifications and input validation checks for key systems',
                    'keywords' => array(
                        'processing',
                        'specification',
                        'input',
                        'definition'
                    )
                ),
                array(
                    'text' => 'Processing reconciliation or error-correction reports',
                    'keywords' => array(
                        'complete',
                        'accura',
                        'error',
                        'reconcil',
                        'correction'
                    )
                ),
                array(
                    'text' => 'Output distribution records showing delivery to intended recipients',
                    'keywords' => array(
                        'output',
                        'distribut',
                        'recipient',
                        'deliver'
                    )
                ),
                array(
                    'text' => 'Removable media and data transfer handling records',
                    'keywords' => array(
                        'removable',
                        'media',
                        'transfer',
                        'portable'
                    )
                )
            ),
            'privacy' => array(
                array(
                    'text' => 'Published privacy notice with its version history and approval',
                    'keywords' => array(
                        'notice',
                        'privacy notice',
                        'inform'
                    )
                ),
                array(
                    'text' => 'Consent records and the consent capture mechanism',
                    'keywords' => array(
                        'consent',
                        'choice',
                        'opt-in',
                        'opt-out'
                    )
                ),
                array(
                    'text' => 'Records of processing activities or personal data inventory',
                    'keywords' => array(
                        'collect',
                        'inventor',
                        'processing',
                        'personal information',
                        'purpose'
                    )
                ),
                array(
                    'text' => 'Data subject request log showing requests received, fulfilled or denied',
                    'keywords' => array(
                        'access',
                        'correction',
                        'request',
                        'data subject',
                        'inquir',
                        'dispute',
                        'complaint'
                    )
                ),
                array(
                    'text' => 'Disclosure and third-party sharing records for personal information',
                    'keywords' => array(
                        'disclos',
                        'third part',
                        'sharing',
                        'authoriz'
                    )
                ),
                array(
                    'text' => 'Breach notification procedure and notification records',
                    'keywords' => array(
                        'breach',
                        'notif'
                    )
                ),
                array(
                    'text' => 'Retention and disposal records for personal information',
                    'keywords' => array(
                        'retention',
                        'dispos',
                        'destr'
                    )
                )
            ),
            'configuration' => array(
                array(
                    'text' => 'Approved configuration baselines for each system type',
                    'keywords' => array(
                        'baseline',
                        'configur',
                        'standard',
                        'harden'
                    )
                ),
                array(
                    'text' => 'Configuration compliance scan results against the baselines',
                    'keywords' => array(
                        'scan',
                        'complian',
                        'drift',
                        'deviation',
                        'compar'
                    )
                ),
                array(
                    'text' => 'Inventory of permitted software and records of unauthorized software removal',
                    'keywords' => array(
                        'software',
                        'unauthorized',
                        'install',
                        'permit',
                        'allow'
                    )
                ),
                array(
                    'text' => 'Records of disabled or restricted unnecessary ports, protocols and services',
                    'keywords' => array(
                        'port',
                        'protocol',
                        'service',
                        'functionality',
                        'least functionality'
                    )
                )
            ),
            'vulnerability' => array(
                array(
                    'text' => 'Vulnerability scan results for the period, internal and external',
                    'keywords' => array(
                        'scan',
                        'vulnerab',
                        'identif',
                        'detect'
                    )
                ),
                array(
                    'text' => 'Remediation tracking records showing vulnerabilities closed within target timeframes',
                    'keywords' => array(
                        'remediat',
                        'track',
                        'timeframe',
                        'patch',
                        'flaw',
                        'correct'
                    )
                ),
                array(
                    'text' => 'Patch deployment reports for servers and endpoints',
                    'keywords' => array(
                        'patch',
                        'update',
                        'deploy'
                    )
                ),
                array(
                    'text' => 'Penetration test report and the remediation status of its findings',
                    'keywords' => array(
                        'penetration',
                        'test',
                        'exploit'
                    )
                ),
                array(
                    'text' => 'Threat intelligence or security advisory monitoring records',
                    'keywords' => array(
                        'threat',
                        'advisor',
                        'alert',
                        'intelligence'
                    )
                )
            ),
            'endpoint' => array(
                array(
                    'text' => 'Endpoint protection deployment and coverage report',
                    'keywords' => array(
                        'malware',
                        'anti-malware',
                        'antivirus',
                        'endpoint',
                        'protection',
                        'malicious'
                    )
                ),
                array(
                    'text' => 'Malware detection alerts and the response taken',
                    'keywords' => array(
                        'detect',
                        'alert',
                        'malicious',
                        'respon'
                    )
                ),
                array(
                    'text' => 'Email and web filtering configuration',
                    'keywords' => array(
                        'email',
                        'web',
                        'filter',
                        'spam'
                    )
                ),
                array(
                    'text' => 'Device encryption and mobile device management reports',
                    'keywords' => array(
                        'device',
                        'mobile',
                        'encrypt',
                        'laptop'
                    )
                ),
                array(
                    'text' => 'Records of scanning of media and files introduced from outside',
                    'keywords' => array(
                        'scan',
                        'introduced',
                        'external',
                        'media',
                        'file'
                    )
                )
            ),
            'change' => array(
                array(
                    'text' => 'Change requests with approval, testing and implementation records for a sample of changes',
                    'keywords' => array(
                        'change',
                        'approv',
                        'authoriz',
                        'test'
                    )
                ),
                array(
                    'text' => 'Change advisory or approval board minutes',
                    'keywords' => array(
                        'advisory',
                        'board',
                        'approv'
                    )
                ),
                array(
                    'text' => 'Emergency change records with retrospective approval',
                    'keywords' => array(
                        'emergency',
                        'urgent'
                    )
                ),
                array(
                    'text' => 'Comparison of deployed changes with approved change tickets',
                    'keywords' => array(
                        'unauthorized',
                        'detect',
                        'compar',
                        'reconcil',
                        'track'
                    )
                ),
                array(
                    'text' => 'Rollback or back-out plans recorded for significant changes',
                    'keywords' => array(
                        'rollback',
                        'back-out',
                        'recover'
                    )
                )
            ),
            'sdlc' => array(
                array(
                    'text' => 'Secure development lifecycle procedure and coding standards',
                    'keywords' => array(
                        'development',
                        'lifecycle',
                        'coding',
                        'design',
                        'acquisit'
                    )
                ),
                array(
                    'text' => 'Code review and security testing records for a sample of releases',
                    'keywords' => array(
                        'code review',
                        'testing',
                        'test',
                        'review',
                        'release'
                    )
                ),
                array(
                    'text' => 'Records of separation between development, test and production',
                    'keywords' => array(
                        'environment',
                        'separat',
                        'production'
                    )
                ),
                array(
                    'text' => 'Acquisition or vendor software security requirements and acceptance testing',
                    'keywords' => array(
                        'acquisit',
                        'procure',
                        'purchas',
                        'acceptance'
                    )
                )
            ),
            'logging' => array(
                array(
                    'text' => 'Logging standard and the inventory of log sources',
                    'keywords' => array(
                        'logging',
                        'logs',
                        'log source',
                        'audit log',
                        'event'
                    )
                ),
                array(
                    'text' => 'Samples of security event logs retained for the defined period',
                    'keywords' => array(
                        'retention',
                        'retain',
                        'review',
                        'protect'
                    )
                ),
                array(
                    'text' => 'Alert triage records from monitoring or SIEM tooling',
                    'keywords' => array(
                        'monitor',
                        'alert',
                        'anomal',
                        'siem',
                        'detect',
                        'analy'
                    )
                ),
                array(
                    'text' => 'Records of log review and follow-up of anomalies',
                    'keywords' => array(
                        'review',
                        'anomal',
                        'investigat',
                        'correlat'
                    )
                ),
                array(
                    'text' => 'Time synchronization configuration for logged systems',
                    'keywords' => array(
                        'time synchron',
                        'clock',
                        'synchron'
                    )
                )
            ),
            'incident' => array(
                array(
                    'text' => 'Approved incident response plan and playbooks',
                    'keywords' => array(
                        'plan',
                        'response',
                        'procedur',
                        'playbook',
                        'respond'
                    )
                ),
                array(
                    'text' => 'Incident register with classification, timeline and closure for each incident',
                    'keywords' => array(
                        'register',
                        'record',
                        'track',
                        'classif',
                        'report'
                    )
                ),
                array(
                    'text' => 'Incident response exercise or tabletop test records with lessons learned',
                    'keywords' => array(
                        'exercise',
                        'test',
                        'tabletop',
                        'lesson',
                        'evaluat'
                    )
                ),
                array(
                    'text' => 'Post-incident review reports and the resulting corrective actions',
                    'keywords' => array(
                        'review',
                        'lesson',
                        'root cause',
                        'remediat',
                        'correct'
                    )
                ),
                array(
                    'text' => 'Notification records to customers, regulators or data subjects where required',
                    'keywords' => array(
                        'notif',
                        'communicat',
                        'breach',
                        'external',
                        'regulator'
                    )
                )
            ),
            'continuity' => array(
                array(
                    'text' => 'Backup job logs and backup schedule for in-scope systems',
                    'keywords' => array(
                        'backup',
                        'back up',
                        'copies',
                        'schedule'
                    )
                ),
                array(
                    'text' => 'Restore test records showing data recovered successfully',
                    'keywords' => array(
                        'restor',
                        'recover',
                        'integrity',
                        'test'
                    )
                ),
                array(
                    'text' => 'Business continuity and disaster recovery plans with recovery objectives',
                    'keywords' => array(
                        'continuity',
                        'disaster',
                        'recovery plan',
                        'objective',
                        'rto',
                        'rpo'
                    )
                ),
                array(
                    'text' => 'Disaster recovery test or exercise reports',
                    'keywords' => array(
                        'test',
                        'exercise',
                        'recovery'
                    )
                ),
                array(
                    'text' => 'Capacity monitoring reports and forecast reviews',
                    'keywords' => array(
                        'capacity',
                        'demand',
                        'forecast',
                        'usage',
                        'performance'
                    )
                ),
                array(
                    'text' => 'Environmental protection maintenance records for facilities hosting systems',
                    'keywords' => array(
                        'environment',
                        'power',
                        'fire',
                        'temperature',
                        'infrastructure'
                    )
                )
            ),
            'third_party' => array(
                array(
                    'text' => 'Supplier inventory with risk tiers and named relationship owners',
                    'keywords' => array(
                        'supplier',
                        'vendor',
                        'inventor',
                        'tier',
                        'owner',
                        'third part'
                    )
                ),
                array(
                    'text' => 'Supplier due diligence or security assessments before onboarding',
                    'keywords' => array(
                        'due diligence',
                        'assess',
                        'onboard',
                        'select'
                    )
                ),
                array(
                    'text' => 'Contracts showing security, confidentiality and breach notification clauses',
                    'keywords' => array(
                        'contract',
                        'agreement',
                        'commitment',
                        'clause',
                        'terms'
                    )
                ),
                array(
                    'text' => 'Annual review records of independent assurance reports for critical suppliers',
                    'keywords' => array(
                        'review',
                        'monitor',
                        'assurance',
                        'report'
                    )
                ),
                array(
                    'text' => 'Supplier offboarding records showing access removed and data returned',
                    'keywords' => array(
                        'terminat',
                        'offboard',
                        'return',
                        'exit'
                    )
                )
            ),
            'physical' => array(
                array(
                    'text' => 'Badge access lists for secure areas and the record of their periodic review',
                    'keywords' => array(
                        'badge',
                        'access',
                        'secure area',
                        'facilit',
                        'physical'
                    )
                ),
                array(
                    'text' => 'Visitor logs and escort records',
                    'keywords' => array(
                        'visitor',
                        'escort',
                        'guest'
                    )
                ),
                array(
                    'text' => 'Records of physical access removal for leavers',
                    'keywords' => array(
                        'remov',
                        'terminat',
                        'revok'
                    )
                ),
                array(
                    'text' => 'CCTV or entry monitoring records for sensitive areas',
                    'keywords' => array(
                        'monitor',
                        'cctv',
                        'camera',
                        'surveil'
                    )
                ),
                array(
                    'text' => 'Environmental control maintenance records such as fire suppression and power',
                    'keywords' => array(
                        'environment',
                        'fire',
                        'power',
                        'temperature'
                    )
                )
            ),
            'control_monitoring' => array(
                array(
                    'text' => 'Control testing or internal audit plan for the period',
                    'keywords' => array(
                        'plan',
                        'audit',
                        'testing',
                        'evaluat',
                        'ongoing'
                    )
                ),
                array(
                    'text' => 'Control assessment or internal audit reports with findings',
                    'keywords' => array(
                        'assess',
                        'report',
                        'separate evaluation',
                        'finding'
                    )
                ),
                array(
                    'text' => 'Deficiency tracker showing owners, target dates and closure',
                    'keywords' => array(
                        'deficienc',
                        'track',
                        'remediat',
                        'corrective',
                        'communicat'
                    )
                ),
                array(
                    'text' => 'Management review records of control performance',
                    'keywords' => array(
                        'review',
                        'management',
                        'performance'
                    )
                )
            ),
            'communication' => array(
                array(
                    'text' => 'Internal security communications issued in the period, such as policy change notices',
                    'keywords' => array(
                        'internal',
                        'communicat',
                        'staff',
                        'personnel'
                    )
                ),
                array(
                    'text' => 'System description and customer-facing security commitments',
                    'keywords' => array(
                        'system description',
                        'boundar',
                        'external',
                        'customer',
                        'commitment'
                    )
                ),
                array(
                    'text' => 'External reporting channel records showing issues received and routed',
                    'keywords' => array(
                        'external',
                        'report',
                        'channel',
                        'complaint',
                        'inquir'
                    )
                ),
                array(
                    'text' => 'Records showing the information used to operate controls is checked for quality',
                    'keywords' => array(
                        'information quality',
                        'quality',
                        'relevant',
                        'accura',
                        'source'
                    )
                )
            )
        );
    }

    private static function definitions(): array
    {
        return array(
            'governance' => array(
                'name' => 'Governance and oversight',
                'purpose' => 'Governance controls are intended to ensure that leadership sets direction for the security program, allocates resources to it and holds management accountable for its performance through regular, documented oversight.',
                'consequences' => array(
                    'Security priorities could be set without leadership visibility, leaving significant risks accepted by default rather than by decision.',
                    'Investment in security may not track the organization\'s actual risk exposure, so material gaps could persist across budget cycles.',
                    'Without documented oversight, the organization may be unable to demonstrate to customers, regulators or insurers that leadership exercises due care.'
                ),
                'activities' => array(
                    'Define a security governance charter that names the oversight body, its membership, meeting cadence and decision rights.',
                    'Schedule recurring leadership reviews of program status, open risks, incidents and remediation progress, and minute each meeting.',
                    'Agree a small set of security metrics that leadership receives each period, with thresholds that trigger escalation.',
                    'Record leadership decisions on risk acceptance, funding and priorities, with the rationale and the approver.',
                    'Assign an executive sponsor accountable for the security program and confirm the appointment in writing.'
                ),
                'deliverables' => array(
                    'Approved security governance charter',
                    'Leadership meeting minutes and decision log',
                    'Periodic security status report and metrics pack',
                    'Documented appointment of the executive sponsor'
                ),
                'validation' => array(
                    'Inspect minutes from the last two oversight meetings for evidence that program status and risks were discussed and decisions recorded.',
                    'Compare the metrics reported to leadership against source data for one reporting period.',
                    'Confirm the charter has been approved and that its membership matches the current organization chart.'
                ),
                'outcome' => 'Leadership receives regular, evidence-based reporting on security and records the decisions it takes on risk, priorities and resources.',
                'criticality' => 2,
                'initiative' => array(
                    'title' => 'Formalize security governance and leadership oversight',
                    'phase' => 'Foundational improvements'
                ),
                'depends_on' => array()
            ),
            'ethics' => array(
                'name' => 'Ethics, integrity and conduct',
                'purpose' => 'Conduct controls are intended to establish the expected standards of integrity, make them known to every member of the workforce and give people a safe route to report concerns.',
                'consequences' => array(
                    'Misconduct such as misuse of data or circumvention of controls could go unreported if staff have no trusted channel to raise concerns.',
                    'Inconsistent handling of conduct breaches may signal that security obligations are optional, weakening every control that relies on individual behavior.',
                    'The organization may struggle to take defensible disciplinary action when the expected standards were never formally communicated or acknowledged.'
                ),
                'activities' => array(
                    'Publish a code of conduct that addresses acceptable use, confidentiality, conflicts of interest and the reporting of violations.',
                    'Collect signed acknowledgement of the code from every employee and contractor at onboarding and annually thereafter.',
                    'Establish a confidential or anonymous reporting channel and document how reports are triaged, investigated and closed.',
                    'Define a sanctions process that links conduct breaches to proportionate disciplinary outcomes, and apply it consistently.'
                ),
                'deliverables' => array(
                    'Approved code of conduct',
                    'Code of conduct acknowledgement records',
                    'Ethics reporting procedure and case log',
                    'Documented sanctions process'
                ),
                'validation' => array(
                    'Sample new hires and existing staff and confirm a current signed acknowledgement exists for each.',
                    'Inspect the reporting channel case log for evidence that reports were received, investigated and closed.',
                    'Review a recent disciplinary case to confirm the documented sanctions process was followed.'
                ),
                'outcome' => 'Every member of the workforce has acknowledged the expected standards of conduct, and concerns are reported, investigated and resolved through a defined process.',
                'criticality' => 1,
                'initiative' => array(
                    'title' => 'Establish conduct standards and reporting channels',
                    'phase' => 'Program maturity'
                ),
                'depends_on' => array(
                    'governance'
                )
            ),
            'risk' => array(
                'name' => 'Risk assessment and management',
                'purpose' => 'Risk management controls are intended to identify threats to the organization\'s systems and data, evaluate their likelihood and impact, and drive treatment decisions that are recorded and revisited as conditions change.',
                'consequences' => array(
                    'Security effort and spending could be directed at visible issues rather than at the risks most likely to cause material harm.',
                    'New threats, technologies or business changes may be introduced without anyone assessing how they alter the risk profile.',
                    'Risks could be accepted informally by individuals who lack the authority to do so, leaving the organization exposed without a record of the decision.'
                ),
                'activities' => array(
                    'Adopt a documented risk methodology that defines likelihood and impact scales, risk appetite and acceptance authority.',
                    'Conduct a risk assessment covering in-scope systems, data, threats, vulnerabilities and existing controls.',
                    'Record each risk in a register with an owner, rating, treatment decision and target date.',
                    'Require formal sign-off for accepted risks by a manager with the delegated authority to accept them.',
                    'Repeat the assessment at least annually and whenever significant changes to systems, suppliers or operations occur.'
                ),
                'deliverables' => array(
                    'Risk management methodology',
                    'Current risk assessment report',
                    'Risk register with owners and treatment plans',
                    'Signed risk acceptance records'
                ),
                'validation' => array(
                    'Inspect the risk register for completeness, ownership and evidence of review within the defined period.',
                    'Trace a sample of high-rated risks to treatment actions and confirm progress against target dates.',
                    'Confirm that accepted risks carry approval from someone with the documented authority to accept them.'
                ),
                'outcome' => 'Risks are identified using a consistent method, owned by named individuals, and treated or formally accepted within agreed timeframes.',
                'criticality' => 2,
                'initiative' => array(
                    'title' => 'Establish a repeatable risk assessment and treatment process',
                    'phase' => 'Foundational improvements'
                ),
                'depends_on' => array(
                    'governance'
                )
            ),
            'policy' => array(
                'name' => 'Policy and documentation',
                'purpose' => 'Policy controls are intended to set out, in approved and current documents, what the organization requires of its people and systems, so that controls are applied consistently and can be assessed against a defined standard.',
                'consequences' => array(
                    'Staff may apply controls inconsistently because expectations exist only as informal knowledge.',
                    'Outdated documents could describe practices that no longer operate, undermining reliance on them during an audit or dispute.',
                    'Without approved procedures, critical tasks may depend on a few individuals and fail when those individuals are unavailable.'
                ),
                'activities' => array(
                    'Build a policy framework that lists each required policy and standard, its owner and its review frequency.',
                    'Draft or update policies so that each states its scope, requirements, roles and the consequence of non-compliance.',
                    'Obtain formal management approval for each policy and record the version and approval date.',
                    'Publish policies where every relevant employee can access them, and track acknowledgement.',
                    'Review each document at least annually or after significant change, and retire documents that have been superseded.'
                ),
                'deliverables' => array(
                    'Policy framework and document register',
                    'Approved, version-controlled policy set',
                    'Operating procedures for key control areas',
                    'Policy acknowledgement records'
                ),
                'validation' => array(
                    'Inspect the document register for owners, versions, approval dates and review dates within the defined cycle.',
                    'Select a sample of policies and confirm that the documented practices match how the controls actually operate.',
                    'Confirm that staff can locate current policies and have acknowledged them.'
                ),
                'outcome' => 'A complete, approved and current set of policies and procedures defines how controls operate and is known to the people who apply them.',
                'criticality' => 1,
                'initiative' => array(
                    'title' => 'Develop and approve the security policy framework',
                    'phase' => 'Foundational improvements'
                ),
                'depends_on' => array(
                    'governance'
                )
            ),
            'roles' => array(
                'name' => 'Roles, responsibilities and accountability',
                'purpose' => 'Accountability controls are intended to assign every security responsibility to a named role, give that role sufficient authority, and separate duties so that no single person can both perform and approve a sensitive action.',
                'consequences' => array(
                    'Controls could lapse unnoticed when no individual is clearly responsible for operating or reviewing them.',
                    'A single person holding conflicting duties may be able to make and conceal an unauthorized change.',
                    'Escalation during an incident or audit may be delayed while the organization establishes who has the authority to decide.'
                ),
                'activities' => array(
                    'Appoint a named individual responsible for the security program and document their authority and reporting line.',
                    'Produce a responsibility matrix that maps each control area to the roles that operate, review and approve it.',
                    'Identify incompatible duties and separate them, or document a compensating review where separation is not practical.',
                    'Include security responsibilities in job descriptions and performance objectives for relevant roles.'
                ),
                'deliverables' => array(
                    'Security responsibility (RACI) matrix',
                    'Appointment letter for the security lead',
                    'Segregation of duties analysis',
                    'Updated job descriptions for security-relevant roles'
                ),
                'validation' => array(
                    'Inspect the responsibility matrix and confirm that each control area has a current, named owner.',
                    'Interview a sample of control owners to confirm they understand and perform their assigned responsibilities.',
                    'Test a sample of sensitive transactions for evidence that the preparer and the approver were different people.'
                ),
                'outcome' => 'Every control has an accountable owner with the authority to operate it, and conflicting duties are separated or independently reviewed.',
                'criticality' => 1,
                'initiative' => array(
                    'title' => 'Define security roles and separation of duties',
                    'phase' => 'Foundational improvements'
                ),
                'depends_on' => array(
                    'governance'
                )
            ),
            'personnel' => array(
                'name' => 'Personnel security',
                'purpose' => 'Personnel security controls are intended to ensure that people are screened before they are trusted with access, are bound by confidentiality obligations, and lose that access promptly when their role changes or ends.',
                'consequences' => array(
                    'Individuals with undisclosed risk factors could be given access to sensitive systems without appropriate screening.',
                    'Former employees or contractors may retain working credentials after departure and use them to access or remove data.',
                    'Staff moving between roles could accumulate access far beyond what their current position requires.'
                ),
                'activities' => array(
                    'Define screening requirements proportionate to role sensitivity and complete them before access is granted.',
                    'Require signed confidentiality and acceptable use agreements as a condition of employment or engagement.',
                    'Link HR joiner, mover and leaver events to the access management process so that each change triggers same-day action.',
                    'Complete an exit checklist for every leaver covering account disablement, asset return and a reminder of continuing obligations.'
                ),
                'deliverables' => array(
                    'Personnel screening standard',
                    'Signed confidentiality agreements',
                    'Joiner, mover and leaver procedure',
                    'Completed termination checklists'
                ),
                'validation' => array(
                    'Sample recent hires and confirm screening was completed before their access start date.',
                    'Sample recent leavers and compare termination dates with account disablement timestamps.',
                    'Sample role changes and confirm that access from the previous role was removed.'
                ),
                'outcome' => 'People are screened and bound by confidentiality before receiving access, and their access changes promptly when they move or leave.',
                'criticality' => 2,
                'initiative' => array(
                    'title' => 'Strengthen personnel screening and termination controls',
                    'phase' => 'Operational control enhancements'
                ),
                'depends_on' => array(
                    'policy',
                    'roles'
                )
            ),
            'awareness' => array(
                'name' => 'Security awareness and training',
                'purpose' => 'Awareness controls are intended to give every member of the workforce the knowledge to recognize and report threats such as phishing and social engineering, and to equip specialist roles with the skills their security duties require.',
                'consequences' => array(
                    'Staff could fall victim to phishing or pretexting attacks that bypass technical controls by targeting human judgment.',
                    'Employees may mishandle sensitive information simply because they do not know the applicable rules.',
                    'Personnel with security duties may lack the skills to operate the tools and processes they are accountable for.'
                ),
                'activities' => array(
                    'Deliver security awareness training to all staff at onboarding and at least annually, covering current threats and reporting routes.',
                    'Run periodic phishing simulations and provide targeted follow-up training to users who fail them.',
                    'Provide role-based training for administrators, developers and incident responders aligned to their responsibilities.',
                    'Track training completion and escalate overdue training to line managers.'
                ),
                'deliverables' => array(
                    'Training plan and curriculum',
                    'Training completion records',
                    'Phishing simulation results and trend report',
                    'Role-based training materials'
                ),
                'validation' => array(
                    'Compare the training completion report with the current staff list to identify anyone not trained in the period.',
                    'Review phishing simulation results across successive campaigns for evidence of improvement.',
                    'Confirm that a sample of privileged users completed the role-based training required for their duties.'
                ),
                'outcome' => 'The workforce is trained at a defined frequency and can recognize and report common attacks, and specialist roles hold the skills their duties require.',
                'criticality' => 1,
                'initiative' => array(
                    'title' => 'Deliver a measured security awareness and training program',
                    'phase' => 'Operational control enhancements'
                ),
                'depends_on' => array(
                    'policy'
                )
            ),
            'assets' => array(
                'name' => 'Asset inventory and management',
                'purpose' => 'Asset management controls are intended to maintain an accurate inventory of hardware, software, cloud services and data, each with an owner and classification, so that every other control can be applied to a known population.',
                'consequences' => array(
                    'Unknown or unmanaged devices could operate without patching, monitoring or endpoint protection and provide an entry point for attackers.',
                    'Unauthorized software and unsanctioned cloud services may process sensitive data outside approved controls.',
                    'Without a reliable inventory, the organization may be unable to confirm that any control covers its full scope.'
                ),
                'activities' => array(
                    'Build an inventory of hardware, software, cloud services and data stores, using automated discovery where possible.',
                    'Assign an owner and a classification to every inventoried asset.',
                    'Reconcile the inventory against network discovery, endpoint management and procurement records on a defined schedule.',
                    'Define an approved software list and a process to detect and remove unauthorized software.',
                    'Document the system boundary and data flows for each in-scope environment.'
                ),
                'deliverables' => array(
                    'Asset inventory with owners and classifications',
                    'Data classification scheme',
                    'System boundary and data flow diagrams',
                    'Inventory reconciliation records'
                ),
                'validation' => array(
                    'Compare a network or endpoint discovery scan against the inventory and investigate unmatched assets.',
                    'Select assets from the floor or from cloud consoles and trace them to inventory records, and trace inventory records back to live assets.',
                    'Confirm that a sample of inventory entries carries a current owner and classification.'
                ),
                'outcome' => 'The organization maintains a reconciled inventory of its assets and data, each with an owner and classification, which gives every other control a reliable scope.',
                'criticality' => 2,
                'initiative' => array(
                    'title' => 'Build and maintain an authoritative asset inventory',
                    'phase' => 'Foundational improvements'
                ),
                'depends_on' => array(
                    'policy'
                )
            ),
            'identity' => array(
                'name' => 'Identity and authentication',
                'purpose' => 'Identity controls are intended to ensure that every user, device and service is uniquely identified and proves its identity with credentials strong enough for the access requested before it reaches systems or data.',
                'consequences' => array(
                    'Stolen or guessed passwords could give an attacker the same access as a legitimate user, particularly on internet-facing services.',
                    'Shared or generic accounts may make it impossible to attribute actions to an individual during an investigation.',
                    'Weak credential handling, such as unprotected storage or unchanged default passwords, could expose credentials to theft at scale.'
                ),
                'activities' => array(
                    'Enforce multi-factor authentication for remote access, cloud administration consoles, email and any system holding sensitive data.',
                    'Eliminate shared and generic user accounts, or restrict them with documented justification and individual accountability.',
                    'Apply a password standard covering length, reuse, compromised-password screening and account lockout.',
                    'Centralize authentication through a single identity provider with single sign-on where practical.',
                    'Inventory service and machine accounts, assign each an owner and rotate their credentials on a defined schedule.'
                ),
                'deliverables' => array(
                    'Authentication standard',
                    'Multi-factor authentication coverage report',
                    'Service account inventory with owners',
                    'Identity provider configuration evidence'
                ),
                'validation' => array(
                    'Attempt to authenticate to a sample of in-scope systems and confirm that a second factor is required.',
                    'Inspect identity provider settings for password, lockout and session parameters against the standard.',
                    'Review account listings for shared, generic or default accounts and confirm each is justified or removed.'
                ),
                'outcome' => 'Every account is attributable to a single owner and protected by authentication appropriate to its risk, with multi-factor authentication on all high-risk access paths.',
                'criticality' => 3,
                'initiative' => array(
                    'title' => 'Enforce strong authentication and unique identities',
                    'phase' => 'Immediate priorities'
                ),
                'depends_on' => array(
                    'assets'
                )
            ),
            'privileged' => array(
                'name' => 'Privileged access',
                'purpose' => 'Privileged access controls are intended to restrict administrative and superuser rights to the minimum number of named individuals, protect those accounts with stronger safeguards and record how they are used.',
                'consequences' => array(
                    'A compromised administrator account could give an attacker full control of systems, data and security tooling, including the ability to disable logging.',
                    'Excess administrative rights on workstations may allow malware to install itself and spread across the environment.',
                    'Unmonitored privileged activity could allow unauthorized changes or data extraction to go undetected.'
                ),
                'activities' => array(
                    'Inventory all privileged accounts across infrastructure, cloud, databases and applications, and map each to a named owner.',
                    'Remove standing administrative rights that are not required, and separate administrative accounts from everyday user accounts.',
                    'Require multi-factor authentication for all privileged access and route it through a managed access path such as a bastion host or privileged access tool.',
                    'Log privileged sessions and commands, and send the records to a location administrators cannot alter.',
                    'Review privileged entitlements at least quarterly, with sign-off by the system owner.'
                ),
                'deliverables' => array(
                    'Privileged account inventory with owners',
                    'Privileged access standard',
                    'Quarterly privileged access review records',
                    'Privileged session logging configuration'
                ),
                'validation' => array(
                    'Reconcile privileged group membership on each in-scope system against the approved inventory.',
                    'Confirm that administrative actions cannot be performed from a standard user account.',
                    'Inspect a sample of privileged session logs and the most recent quarterly review sign-off.'
                ),
                'outcome' => 'Privileged rights are held by a small, approved set of named accounts that use strong authentication and whose activity is logged and periodically reviewed.',
                'criticality' => 3,
                'initiative' => array(
                    'title' => 'Establish privileged access governance',
                    'phase' => 'Immediate priorities'
                ),
                'depends_on' => array(
                    'identity'
                )
            ),
            'access' => array(
                'name' => 'Access provisioning and review',
                'purpose' => 'Access management controls are intended to grant access only on approved business need, keep permissions aligned to least privilege as roles change, and remove access promptly when it is no longer required.',
                'consequences' => array(
                    'Users could accumulate permissions over time that allow them to view or change data unrelated to their duties.',
                    'Access granted without approval may go unnoticed, providing a quiet path to sensitive information.',
                    'Dormant accounts that are never removed could be taken over and used without detection.'
                ),
                'activities' => array(
                    'Require documented approval from the data or system owner before any access is granted or elevated.',
                    'Define role-based access profiles for common job functions and grant access against those profiles.',
                    'Perform user access reviews at a defined frequency, with owners certifying or revoking each entitlement.',
                    'Disable accounts that have been inactive beyond a defined threshold, and remove access promptly for leavers.',
                    'Retain evidence of each access review, including the changes made as a result.'
                ),
                'deliverables' => array(
                    'Access control policy and provisioning procedure',
                    'Role-based access matrix',
                    'Completed user access review records',
                    'Dormant account report'
                ),
                'validation' => array(
                    'Sample access grants and confirm documented approval existed before the grant date.',
                    'Re-perform a user access review for one system and compare the results with the owner\'s certification.',
                    'Run a dormant account report and confirm that accounts beyond the threshold are disabled.'
                ),
                'outcome' => 'Access is granted on documented approval, aligned to role, reviewed periodically by owners and removed promptly when no longer required.',
                'criticality' => 2,
                'initiative' => array(
                    'title' => 'Implement access approval and periodic access reviews',
                    'phase' => 'Operational control enhancements'
                ),
                'depends_on' => array(
                    'identity',
                    'roles'
                )
            ),
            'network' => array(
                'name' => 'Network security and segmentation',
                'purpose' => 'Network controls are intended to restrict traffic to what is explicitly required, separate sensitive systems from general networks and the internet, and protect remote and wireless connections.',
                'consequences' => array(
                    'A flat network could allow an attacker who compromises one device to reach critical systems without further obstacles.',
                    'Unnecessary services exposed to the internet may be discovered and exploited by automated scanning.',
                    'Unmanaged firewall rules could accumulate over time and open paths that no one has approved or can explain.'
                ),
                'activities' => array(
                    'Document the network architecture, trust zones and all connections to external networks.',
                    'Segment sensitive systems into separate zones with deny-by-default rules between them.',
                    'Review firewall and security group rule sets at least every six months and remove rules without a documented business justification.',
                    'Restrict remote access to managed, encrypted connections that require multi-factor authentication.',
                    'Secure wireless networks with strong encryption and isolate guest access from internal networks.'
                ),
                'deliverables' => array(
                    'Current network diagram showing zones and external connections',
                    'Firewall rule set review records',
                    'Network segmentation standard',
                    'Remote access configuration evidence'
                ),
                'validation' => array(
                    'Compare the network diagram against firewall configurations and cloud security groups.',
                    'Perform segmentation testing to confirm that traffic between zones is limited to approved flows.',
                    'Sample firewall rules and confirm each has an owner, a business justification and a recent review.'
                ),
                'outcome' => 'Network traffic is limited to documented and approved flows, sensitive systems are isolated, and remote and wireless access is controlled.',
                'criticality' => 3,
                'initiative' => array(
                    'title' => 'Segment and restrict network traffic',
                    'phase' => 'Immediate priorities'
                ),
                'depends_on' => array(
                    'assets'
                )
            ),
            'data_protection' => array(
                'name' => 'Data protection and encryption',
                'purpose' => 'Data protection controls are intended to keep sensitive information confidential and intact throughout its lifecycle, using classification, encryption in storage and transit, sound key management and secure disposal of media.',
                'consequences' => array(
                    'Sensitive data stored or transmitted in clear text could be read by anyone who intercepts traffic or obtains a copy of the storage.',
                    'Lost or stolen laptops and removable media may result in a reportable data breach if the data on them is not encrypted.',
                    'Poor key management could leave encrypted data exposed because keys are stored alongside it or never rotated.',
                    'Media disposed of without sanitization may expose recoverable data to third parties.'
                ),
                'activities' => array(
                    'Identify where sensitive data is stored, processed and transmitted, and record the locations in a data inventory.',
                    'Encrypt sensitive data at rest and in transit using current algorithms and protocols, and disable weak ciphers.',
                    'Document key management procedures covering key generation, storage, rotation, access and destruction.',
                    'Control the use of removable media, and sanitize or destroy media before disposal or reuse, keeping certificates of destruction.',
                    'Deploy data loss prevention controls on the main channels through which sensitive data could leave the organization.'
                ),
                'deliverables' => array(
                    'Data handling and encryption standard',
                    'Sensitive data inventory',
                    'Key management procedure and key inventory',
                    'Media sanitization and destruction records'
                ),
                'validation' => array(
                    'Inspect storage and database configurations for a sample of systems to confirm encryption at rest is enabled.',
                    'Scan external and internal service endpoints for weak protocols and ciphers.',
                    'Review key management records for rotation and access restriction, and sample media disposal certificates.'
                ),
                'outcome' => 'Sensitive data is identified, encrypted in storage and transit with managed keys, and securely destroyed when no longer required.',
                'criticality' => 3,
                'initiative' => array(
                    'title' => 'Protect sensitive data with encryption and handling controls',
                    'phase' => 'Immediate priorities'
                ),
                'depends_on' => array(
                    'assets'
                )
            ),
            'privacy' => array(
                'name' => 'Privacy and personal data handling',
                'purpose' => 'Privacy controls are intended to ensure that personal information is collected, used, retained, disclosed and disposed of in line with the notices given to individuals, their choices and applicable legal obligations.',
                'consequences' => array(
                    'Personal information could be used or shared for purposes individuals were never told about, creating regulatory and contractual exposure.',
                    'Requests from individuals to access, correct or delete their data may be missed or handled outside required timeframes.',
                    'Data kept longer than necessary could enlarge the impact of any breach and conflict with retention obligations.'
                ),
                'activities' => array(
                    'Map the personal information the organization collects, together with its purpose, legal basis, location and recipients.',
                    'Publish and maintain privacy notices that accurately describe collection, use, sharing and retention.',
                    'Record and honor consent and opt-out choices wherever they are required.',
                    'Define a retention schedule for personal information and delete it when the retention period ends.',
                    'Establish a procedure for handling individual rights requests within the required timeframes.'
                ),
                'deliverables' => array(
                    'Personal data inventory and processing records',
                    'Privacy notice',
                    'Retention and disposal schedule',
                    'Individual rights request log'
                ),
                'validation' => array(
                    'Compare the privacy notice with the personal data inventory to confirm that disclosures are accurate and complete.',
                    'Sample individual rights requests and confirm each was verified and completed within the required timeframe.',
                    'Inspect a data store for personal records held beyond the retention schedule.'
                ),
                'outcome' => 'Personal information is handled in line with published notices and individual choices, retained only as long as justified and disposed of on schedule.',
                'criticality' => 2,
                'initiative' => array(
                    'title' => 'Operationalize privacy notice, consent and retention commitments',
                    'phase' => 'Operational control enhancements'
                ),
                'depends_on' => array(
                    'data_protection',
                    'policy'
                )
            ),
            'configuration' => array(
                'name' => 'Secure configuration and hardening',
                'purpose' => 'Configuration controls are intended to build systems from approved hardened baselines, remove unnecessary functions, keep settings under control and ensure that maintenance is performed through authorized and supervised means.',
                'consequences' => array(
                    'Default settings, accounts and unnecessary services could leave systems open to well-known attacks.',
                    'Configuration drift may introduce weaknesses gradually, without anyone noticing until they are exploited.',
                    'Unsupervised maintenance or remote support sessions could introduce unauthorized changes or malicious tools.'
                ),
                'activities' => array(
                    'Define secure configuration baselines for each operating system, database, network device and cloud service in use, based on a recognized hardening benchmark.',
                    'Remove or disable unnecessary services, ports, protocols, default accounts and sample content.',
                    'Scan systems against their baselines on a defined schedule and remediate deviations.',
                    'Authorize and log maintenance activity, including remote vendor support sessions and the tools used.'
                ),
                'deliverables' => array(
                    'Approved configuration baselines',
                    'Configuration compliance scan results',
                    'Documented baseline exceptions with approvals',
                    'Maintenance log'
                ),
                'validation' => array(
                    'Run a configuration compliance scan on a sample of systems and compare the results with the approved baseline.',
                    'Inspect a newly built system to confirm it was deployed from the baseline image or template.',
                    'Review the maintenance log for authorization and supervision of a sample of sessions.'
                ),
                'outcome' => 'Systems are deployed from hardened baselines, monitored for drift and maintained only through authorized and recorded activity.',
                'criticality' => 2,
                'initiative' => array(
                    'title' => 'Define and enforce hardened configuration baselines',
                    'phase' => 'Operational control enhancements'
                ),
                'depends_on' => array(
                    'assets',
                    'policy'
                )
            ),
            'vulnerability' => array(
                'name' => 'Vulnerability and patch management',
                'purpose' => 'Vulnerability management controls are intended to discover technical weaknesses through scanning and testing, prioritize them by risk and remediate them within defined timeframes before they can be exploited.',
                'consequences' => array(
                    'Publicly known vulnerabilities could be exploited on internet-facing systems within days of disclosure if patches are not applied.',
                    'Without regular scanning, the organization may be unaware of exploitable weaknesses that an attacker could find easily.',
                    'Unsupported software that no longer receives security updates may remain in production indefinitely.'
                ),
                'activities' => array(
                    'Run authenticated vulnerability scans of internal and external systems at least monthly and after significant change.',
                    'Define remediation timeframes by severity and track every finding to closure or an approved exception.',
                    'Apply security patches to operating systems, applications and firmware within the defined timeframes.',
                    'Commission penetration testing of the external perimeter and critical applications at least annually.',
                    'Identify unsupported software and plan its upgrade, replacement or isolation.'
                ),
                'deliverables' => array(
                    'Vulnerability management standard with remediation timeframes',
                    'Scan reports and remediation tracker',
                    'Patch compliance report',
                    'Penetration test report and retest evidence'
                ),
                'validation' => array(
                    'Review consecutive scan reports to confirm high-severity findings were remediated within the defined timeframe.',
                    'Inspect patch levels on a sample of systems against current vendor releases.',
                    'Confirm that penetration test findings were retested and closed.'
                ),
                'outcome' => 'Vulnerabilities are identified regularly, prioritized by risk and remediated within defined timeframes, with any exceptions formally approved.',
                'criticality' => 3,
                'initiative' => array(
                    'title' => 'Close exploitable vulnerabilities and institutionalize patching',
                    'phase' => 'Immediate priorities'
                ),
                'depends_on' => array(
                    'assets'
                )
            ),
            'endpoint' => array(
                'name' => 'Malware and endpoint protection',
                'purpose' => 'Endpoint protection controls are intended to prevent, detect and contain malicious software on workstations, servers and mobile devices through managed protection tools and restrictions on what can execute.',
                'consequences' => array(
                    'Ransomware could encrypt business systems and data if endpoints lack current protection and execution controls.',
                    'Malicious email attachments or downloads may run undetected and establish persistent access for an attacker.',
                    'Unmanaged mobile devices could hold business data with no ability to locate, lock or wipe them.'
                ),
                'activities' => array(
                    'Deploy endpoint detection and response or anti-malware software to all workstations and servers, and confirm that every agent reports in.',
                    'Configure automatic signature and engine updates, and alert when an agent stops reporting.',
                    'Filter email and web traffic for malicious attachments, links and sites.',
                    'Restrict execution of unapproved software and macros through application control.',
                    'Enroll mobile devices that access business data in device management with encryption and remote wipe.'
                ),
                'deliverables' => array(
                    'Endpoint protection deployment report',
                    'Email and web filtering configuration',
                    'Application control policy',
                    'Mobile device management enrollment report'
                ),
                'validation' => array(
                    'Reconcile the endpoint protection console against the asset inventory to identify unprotected devices.',
                    'Test detection using a harmless test file on a sample endpoint.',
                    'Confirm that a sample of mobile devices is enrolled with encryption and remote wipe enabled.'
                ),
                'outcome' => 'Every endpoint runs current, centrally managed protection, and malicious code is blocked or detected before it can spread.',
                'criticality' => 2,
                'initiative' => array(
                    'title' => 'Deploy managed endpoint and malware protection',
                    'phase' => 'Operational control enhancements'
                ),
                'depends_on' => array(
                    'assets',
                    'configuration'
                )
            ),
            'change' => array(
                'name' => 'Change management',
                'purpose' => 'Change management controls are intended to ensure that changes to systems, infrastructure and software are requested, assessed for impact, tested, approved and recorded before they reach production.',
                'consequences' => array(
                    'Untested changes could cause outages or data corruption in production systems.',
                    'Unauthorized changes may introduce security weaknesses or malicious code without detection.',
                    'Without change records, the organization may be unable to determine what changed when diagnosing an incident.'
                ),
                'activities' => array(
                    'Define a change process covering request, risk and impact assessment, testing, approval, implementation and post-implementation review.',
                    'Separate development, test and production environments and restrict who can deploy to production.',
                    'Require approval from someone other than the change author before deployment.',
                    'Define an emergency change route with retrospective review and approval.',
                    'Reconcile production changes against approved change tickets on a periodic basis.'
                ),
                'deliverables' => array(
                    'Change management procedure',
                    'Change records with approvals and test evidence',
                    'Emergency change log',
                    'Change reconciliation report'
                ),
                'validation' => array(
                    'Sample production changes from system logs or deployment history and trace each to an approved change record.',
                    'Confirm that the change author and the approver were different people for the sampled changes.',
                    'Review emergency changes for retrospective approval within the defined period.'
                ),
                'outcome' => 'Every production change is traceable to an approved, tested record, and unauthorized changes are detected through reconciliation.',
                'criticality' => 2,
                'initiative' => array(
                    'title' => 'Formalize change control',
                    'phase' => 'Operational control enhancements'
                ),
                'depends_on' => array(
                    'policy',
                    'configuration'
                )
            ),
            'sdlc' => array(
                'name' => 'Secure development and acquisition',
                'purpose' => 'Secure development controls are intended to build security into software and acquired systems from design through release, using secure coding practices, code review, security testing and defined security requirements.',
                'consequences' => array(
                    'Common coding flaws such as injection or broken authentication could be released to production and exploited.',
                    'Vulnerable open-source components may be included in applications without the organization knowing they are present.',
                    'Secrets embedded in source code could be exposed through code repositories.'
                ),
                'activities' => array(
                    'Document a secure development lifecycle that defines security requirements, threat modeling, review and testing gates.',
                    'Require peer code review against security criteria before code is merged.',
                    'Integrate static analysis, dependency scanning and secret scanning into the build pipeline.',
                    'Perform dynamic or manual security testing of applications before major releases.',
                    'Train developers in secure coding practices relevant to the languages and frameworks they use.'
                ),
                'deliverables' => array(
                    'Secure development lifecycle procedure',
                    'Code review records',
                    'Pipeline security scan results',
                    'Application security test reports'
                ),
                'validation' => array(
                    'Sample merged pull requests and confirm that security-focused review occurred before merge.',
                    'Inspect the pipeline configuration to confirm security scans run and block releases at defined severities.',
                    'Review the latest application security test and confirm findings were addressed before release.'
                ),
                'outcome' => 'Security requirements, review and testing are built into the development lifecycle, and vulnerable code or components are caught before release.',
                'criticality' => 2,
                'initiative' => array(
                    'title' => 'Embed security in the development lifecycle',
                    'phase' => 'Operational control enhancements'
                ),
                'depends_on' => array(
                    'change'
                )
            ),
            'logging' => array(
                'name' => 'Logging and monitoring',
                'purpose' => 'Logging and monitoring controls are intended to record security-relevant events from systems and applications, protect those records from tampering and review them so that suspicious activity is detected and investigated promptly.',
                'consequences' => array(
                    'An intrusion could continue for weeks or months because no one is reviewing the events that would reveal it.',
                    'Missing or overwritten logs may prevent the organization from determining what data was accessed during an incident.',
                    'An attacker with administrative access could delete or alter logs to conceal activity if the records are not protected.'
                ),
                'activities' => array(
                    'Define which events must be logged for each system type, including authentication, privileged actions and changes to security settings.',
                    'Forward logs to a central platform with restricted access and retention aligned to investigation and legal needs.',
                    'Synchronize system clocks to an authoritative time source so that events can be correlated.',
                    'Configure alerting for high-risk events and assign responsibility for triage within defined timeframes.',
                    'Review log source coverage periodically to identify systems that have stopped sending logs.'
                ),
                'deliverables' => array(
                    'Logging and monitoring standard',
                    'Log source inventory',
                    'Alert use cases and triage procedure',
                    'Evidence of alert review and escalation'
                ),
                'validation' => array(
                    'Compare the log source inventory with the asset inventory to identify unmonitored systems.',
                    'Generate a test security event and confirm it is logged, alerted and triaged as designed.',
                    'Confirm that log storage is protected against modification and retains records for the defined period.'
                ),
                'outcome' => 'Security events from all in-scope systems are collected centrally, protected from tampering and reviewed, so that suspicious activity is detected and escalated promptly.',
                'criticality' => 3,
                'initiative' => array(
                    'title' => 'Centralize security logging and alerting',
                    'phase' => 'Operational control enhancements'
                ),
                'depends_on' => array(
                    'assets',
                    'policy'
                )
            ),
            'incident' => array(
                'name' => 'Incident response',
                'purpose' => 'Incident response controls are intended to prepare the organization to detect, contain, investigate and recover from security incidents, and to meet its notification obligations to customers, regulators and other parties.',
                'consequences' => array(
                    'An incident could escalate while staff decide who is in charge and what steps to take.',
                    'Evidence needed to understand the scope of an incident may be lost through uncoordinated response actions.',
                    'Regulatory or contractual notification deadlines could be missed, adding penalties and reputational damage to the incident itself.'
                ),
                'activities' => array(
                    'Write an incident response plan that defines severity levels, roles, escalation paths and communication responsibilities.',
                    'Prepare playbooks for likely scenarios such as ransomware, business email compromise and data exposure.',
                    'Document notification obligations, deadlines and contacts for regulators, customers, insurers and law enforcement.',
                    'Run a tabletop exercise at least annually and track the resulting improvement actions to completion.',
                    'Record every incident in a log with its timeline, impact, root cause and lessons learned.'
                ),
                'deliverables' => array(
                    'Incident response plan and playbooks',
                    'Notification obligations register',
                    'Incident log',
                    'Tabletop exercise report and action plan'
                ),
                'validation' => array(
                    'Inspect the incident log for a sample of events and confirm they were handled according to the plan.',
                    'Review the most recent exercise report and confirm that improvement actions were completed.',
                    'Interview incident team members to confirm they know their roles and escalation routes.'
                ),
                'outcome' => 'The organization has a tested plan to detect, contain and recover from incidents, with clear roles and notification obligations met on time.',
                'criticality' => 3,
                'initiative' => array(
                    'title' => 'Establish and exercise the incident response capability',
                    'phase' => 'Immediate priorities'
                ),
                'depends_on' => array(
                    'roles',
                    'policy'
                )
            ),
            'continuity' => array(
                'name' => 'Business continuity, backup and recovery',
                'purpose' => 'Continuity controls are intended to keep critical services available through disruption by defining recovery objectives, maintaining protected backups and proving through testing that systems and data can be restored in time.',
                'consequences' => array(
                    'A ransomware attack or infrastructure failure could cause a prolonged outage if backups are incomplete, corrupted or reachable by the attacker.',
                    'Recovery may take far longer than the business can tolerate because recovery time objectives were never defined or tested.',
                    'Availability commitments to customers could be breached during a disruption, triggering contractual penalties.'
                ),
                'activities' => array(
                    'Perform a business impact analysis to identify critical services and set recovery time and recovery point objectives.',
                    'Back up critical systems and data at frequencies that meet recovery point objectives, keeping at least one copy offline or immutable.',
                    'Test restoration from backup on a defined schedule and record the time taken against the objectives.',
                    'Document business continuity and disaster recovery plans for critical services, including alternate processing arrangements.',
                    'Exercise the continuity plan at least annually and update it based on the results.'
                ),
                'deliverables' => array(
                    'Business impact analysis with recovery objectives',
                    'Backup schedule and immutable backup configuration',
                    'Restore test records',
                    'Business continuity and disaster recovery plans'
                ),
                'validation' => array(
                    'Inspect backup job histories for a sample of critical systems and confirm success and frequency.',
                    'Observe or review a restore test and compare the actual recovery time with the objective.',
                    'Confirm that at least one backup copy cannot be modified or deleted with production administrator credentials.'
                ),
                'outcome' => 'Critical services have defined recovery objectives, protected backups and tested recovery plans that demonstrate the organization can restore operations in time.',
                'criticality' => 3,
                'initiative' => array(
                    'title' => 'Establish tested backup and recovery capability',
                    'phase' => 'Foundational improvements'
                ),
                'depends_on' => array(
                    'assets',
                    'risk'
                )
            ),
            'third_party' => array(
                'name' => 'Third-party and supply-chain risk',
                'purpose' => 'Third-party controls are intended to assess suppliers and service providers before they receive access to systems or data, bind them to security obligations and monitor their performance throughout the relationship.',
                'consequences' => array(
                    'A compromised or poorly secured supplier could provide an attacker with a trusted path into the organization\'s systems.',
                    'Sensitive data shared with service providers may be exposed under weaker controls than the organization applies itself.',
                    'Contracts without security, notification and audit terms could leave the organization without remedy when a supplier fails.'
                ),
                'activities' => array(
                    'Maintain an inventory of suppliers and service providers, tiered by the access and data they hold.',
                    'Perform security due diligence before onboarding, proportionate to the supplier\'s tier.',
                    'Include security requirements, breach notification, audit rights and data return or deletion terms in contracts.',
                    'Review independent assurance reports or questionnaires for critical suppliers annually and follow up on exceptions.',
                    'Remove supplier access and confirm data return or destruction when a contract ends.'
                ),
                'deliverables' => array(
                    'Supplier inventory with risk tiers',
                    'Supplier due diligence assessments',
                    'Standard contract security clauses',
                    'Annual review records for critical suppliers'
                ),
                'validation' => array(
                    'Sample suppliers from accounts payable and confirm each appears in the supplier inventory with a risk tier.',
                    'Inspect contracts for a sample of critical suppliers for security and notification clauses.',
                    'Confirm that assurance reports for critical suppliers were obtained and reviewed within the past year.'
                ),
                'outcome' => 'Suppliers are assessed before engagement, contractually bound to security obligations and monitored in proportion to the risk they present.',
                'criticality' => 2,
                'initiative' => array(
                    'title' => 'Implement third-party risk management',
                    'phase' => 'Operational control enhancements'
                ),
                'depends_on' => array(
                    'risk',
                    'policy'
                )
            ),
            'physical' => array(
                'name' => 'Physical and environmental security',
                'purpose' => 'Physical controls are intended to restrict entry to facilities and equipment to authorized people, record that access and protect equipment against environmental hazards and theft.',
                'consequences' => array(
                    'Unauthorized individuals could gain physical access to servers, network equipment or paper records and bypass logical controls entirely.',
                    'Equipment could be stolen or tampered with when facility access is not controlled or recorded.',
                    'Power, fire or water events may damage equipment that lacks adequate environmental protection.'
                ),
                'activities' => array(
                    'Restrict access to facilities and sensitive areas using badges or keys issued on approval and recovered at departure.',
                    'Escort and log visitors in areas containing sensitive equipment or information.',
                    'Review physical access lists periodically and remove people who no longer need access.',
                    'Protect equipment rooms with environmental controls such as fire suppression, temperature monitoring and uninterruptible power.'
                ),
                'deliverables' => array(
                    'Physical security policy',
                    'Badge access lists and review records',
                    'Visitor logs',
                    'Environmental control maintenance records'
                ),
                'validation' => array(
                    'Compare the physical access list for sensitive areas with the current staff list.',
                    'Inspect visitor logs for a sample of dates.',
                    'Observe entry points and environmental controls during a site walkthrough.'
                ),
                'outcome' => 'Access to facilities and equipment is limited to authorized people, recorded and reviewed, and equipment is protected from environmental hazards.',
                'criticality' => 2,
                'initiative' => array(
                    'title' => 'Tighten physical and environmental safeguards',
                    'phase' => 'Operational control enhancements'
                ),
                'depends_on' => array(
                    'assets'
                )
            ),
            'control_monitoring' => array(
                'name' => 'Internal audit and control monitoring',
                'purpose' => 'Control monitoring is intended to give management independent assurance that controls are designed and operating as intended, and to ensure that deficiencies are reported, tracked and corrected.',
                'consequences' => array(
                    'Controls could stop operating without anyone noticing until an external audit or incident reveals the gap.',
                    'Known deficiencies may remain open indefinitely when there is no tracked remediation process.',
                    'Management may give assurances to customers or regulators that are not supported by evidence.'
                ),
                'activities' => array(
                    'Establish an annual control testing plan that covers key controls on a risk basis.',
                    'Perform independent assessments or internal audits and report the results to leadership.',
                    'Log all deficiencies in a tracker with an owner, severity and target date, and report progress monthly.',
                    'Collect evidence of control operation continuously rather than only at audit time.'
                ),
                'deliverables' => array(
                    'Control testing or internal audit plan',
                    'Control assessment reports',
                    'Deficiency and remediation tracker',
                    'Control evidence repository'
                ),
                'validation' => array(
                    'Compare completed testing against the plan for the period.',
                    'Sample closed deficiencies and confirm that evidence of remediation was verified.',
                    'Inspect leadership reporting for deficiency status and overdue items.'
                ),
                'outcome' => 'Controls are tested on a planned basis, deficiencies are tracked to closure and leadership receives evidence-based assurance on the control environment.',
                'criticality' => 1,
                'initiative' => array(
                    'title' => 'Implement ongoing control testing and deficiency tracking',
                    'phase' => 'Program maturity'
                ),
                'depends_on' => array(
                    'governance',
                    'risk'
                )
            ),
            'communication' => array(
                'name' => 'Information and communication',
                'purpose' => 'Communication controls are intended to ensure that the information needed to operate controls is accurate and available, and that security responsibilities, commitments and changes are communicated to staff, customers and other external parties.',
                'consequences' => array(
                    'Staff may not learn of changes to security requirements and could continue following outdated practices.',
                    'Customers could be unaware of their own security responsibilities or how to report concerns, increasing the chance of shared-responsibility failures.',
                    'Decisions may be based on incomplete or inaccurate information when the quality of data used to operate controls is not managed.'
                ),
                'activities' => array(
                    'Identify the information required to operate key controls and define its source, owner and quality checks.',
                    'Communicate security objectives, responsibilities and policy changes to staff through defined channels.',
                    'Publish a description of the system, its boundaries and customer responsibilities for external parties.',
                    'Provide external parties with a channel to report security issues and incidents, and route reports to the right team.'
                ),
                'deliverables' => array(
                    'Internal security communication plan',
                    'System description and customer responsibility documentation',
                    'External reporting channel and procedure',
                    'Records of security communications issued'
                ),
                'validation' => array(
                    'Inspect communications issued in the period for policy changes and security responsibilities.',
                    'Review external-facing documentation for an accurate description of commitments and responsibilities.',
                    'Submit a test report through the external channel and confirm it is routed and acknowledged.'
                ),
                'outcome' => 'Staff and external parties receive accurate, timely information about security commitments, responsibilities and changes through defined channels.',
                'criticality' => 1,
                'initiative' => array(
                    'title' => 'Formalize internal and external security communication',
                    'phase' => 'Program maturity'
                ),
                'depends_on' => array(
                    'governance',
                    'policy'
                )
            )
        );
    }

}
