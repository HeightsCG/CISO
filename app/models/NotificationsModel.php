<?php
class NotificationsModel extends Model {

    /** Where all mail is diverted outside production. */
    const NON_PRODUCTION_INBOX = 'danglauber@gmail.com';

    public function __construct(){
        parent::__construct();
    }

    public function app_url()
    {
        $env = Main::get_environment();
        $config = Main::get_config();
        return rtrim($config[$env]['app_url'], '/');
    }

    public function build_email($template_name, $subject, $preheader, $footnote, $variables)
    {
        $notifications = new Notifications();

        $content = $notifications->clean_template($template_name, $variables);

        if ($content === '') {
            error_log('[notifications] template not found: ' . $template_name);
            return '';
        }

        $shell = array(
            'content' => $content,
            'subject' => $subject,
            'preheader' => $preheader,
            'footnote' => $footnote,
            'site_name' => Main::site_name(),
            'logo_url' => $this->app_url() . '/images/logo-light.png',
            'year' => date('Y')
        );

        return $notifications->clean_template('master', $shell);
    }

    public function send_password_reset($user_email, $first_name, $raw_token)
    {
        $subject = 'Reset your ' . Main::site_name() . ' password';

        $variables = array(
            'first_name' => htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8'),
            'reset_url' => $this->app_url() . '/account/reset/token/' . $raw_token
        );

        $message = $this->build_email(
            'password_reset',
            $subject,
            'Use the link inside to choose a new password. It expires in 60 minutes.',
            'Did not request this? No action is needed and your password stays the same. If you were not expecting it, tell your administrator.',
            $variables
        );

        if ($message === '') {
            return false;
        }

        $to_array = array(
            array(
                'email' => $user_email,
                'name' => $first_name
            )
        );

        return $this->send($to_array, 0, $subject, $message);
    }

    /**
     * The invoice, sent by us rather than by the payment provider. The provider
     * hosts the payment page and the PDF; the email that carries the link is ours,
     * so it reads in this product's voice and goes out through the same mail path
     * as everything else the application sends.
     *
     * $resent only changes the words. The link is the same either way - a resend
     * is the same invoice, chased again, not a new one.
     */
    public function send_invoice_email($to_email, $to_name, $company_name, $invoice_number, $amount_due, $due_date, $pay_url, $pdf_url = '', $resent = false)
    {
        $subject = ($resent ? 'Reminder: invoice ' : 'Invoice ').$invoice_number.' from '.$company_name;

        $variables = array(
            'heading'        => htmlspecialchars(($resent ? 'Invoice reminder' : 'New invoice'), ENT_QUOTES, 'UTF-8'),
            'recipient_name' => htmlspecialchars($to_name, ENT_QUOTES, 'UTF-8'),
            'intro'          => htmlspecialchars(
                ($resent
                    ? 'This is a reminder about an invoice from '.$company_name.' that is still outstanding. You can view it and pay online using the button below.'
                    : $company_name.' has sent you an invoice. You can view it and pay online using the button below.'),
                ENT_QUOTES,
                'UTF-8'
            ),
            'invoice_number' => htmlspecialchars($invoice_number, ENT_QUOTES, 'UTF-8'),
            'amount_due'     => htmlspecialchars($amount_due, ENT_QUOTES, 'UTF-8'),
            'due_date'       => htmlspecialchars(($due_date === '' ? 'On receipt' : $due_date), ENT_QUOTES, 'UTF-8'),
            'pay_url'        => htmlspecialchars($pay_url, ENT_QUOTES, 'UTF-8')
        );

        $message = $this->build_email(
            'invoice_sent',
            $subject,
            ($resent ? 'A reminder about an outstanding invoice.' : 'An invoice is ready to view and pay.'),
            'This invoice was sent through '.Main::site_name().' on behalf of '.$company_name.'. Reply to this email to reach them directly.',
            $variables
        );

        if ($message === '') {
            return false;
        }

        $to_array = array(
            array(
                'email' => $to_email,
                'name'  => $to_name
            )
        );

        /* The PDF rides along so the client has the document itself, not only a
           link to it - some finance teams file the attachment and never open the
           page. A download that fails drops the attachment and still sends. */
        $attachments = array();

        if ($pdf_url !== '') {

            $filename = preg_replace('/[^A-Za-z0-9\-]/', '', (string) $invoice_number);
            $attached = (new Notifications())->remote_attachment(
                $pdf_url,
                ($filename === '' ? 'invoice' : $filename).'.pdf',
                'application/pdf'
            );

            if (count($attached) > 0) {
                $attachments[] = $attached;
            }
        }

        return $this->send($to_array, 0, $subject, $message, $attachments);
    }

    /**
     * The single way out. Outside production the recipients are replaced, so a test
     * invite can never reach a real client's inbox, and the subject carries who it
     * was meant for because every diverted message otherwise looks identical.
     */
    private function send($to_array, $cc_array, $subject, $message, $attachments = array())
    {
        if (Main::get_environment() !== 'production') {

            $intended = array();

            foreach ($to_array as $recipient) {
                $intended[] = $recipient['email'];
            }

            $subject = '['.Main::get_environment().' to '.implode(', ', $intended).'] '.$subject;
            $cc_array = 0;
            $to_array = array(
                array(
                    'email' => self::NON_PRODUCTION_INBOX,
                    'name' => Main::site_name()
                )
            );
        }

        return (new Notifications())->send_email($to_array, $cc_array, $subject, $message, $attachments);
    }

}
