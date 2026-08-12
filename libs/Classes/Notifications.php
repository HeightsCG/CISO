<?php
class Notifications {

    public function clean_template($template_name, $variables){
        $template = $this->get_email_template($template_name);
        if (!is_string($template)) {
            return '';
        }
        if (is_array($variables)) {
            foreach ($variables as $key => $value) {
                $template = str_replace('{' . $key . '}', (string) $value, $template);
            }
        }
        return $template;
    }

    public function get_email_template($template_name){
        $file = Main::app_path() . '/app/email_templates/' . $template_name . '.html';
        if (file_exists($file)) {
            return file_get_contents($file);
        }
        return 0;
    }

    public function send_email($to_array, $cc_array, $subject, $message, $attachments = array()){
        $from = (string) Main::config('global', 'mail_from', '');
        if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            error_log('[mailer] mail_from is not configured in app.ini; email not sent');
            return false;
        }

        $token = (string) Main::config('global', 'postmark_api_token', '');
        if ($token === '') {
            error_log('[mailer] postmark_api_token is not configured in app.ini; email not sent');
            return false;
        }

        $from_name = (string) Main::config('global', 'mail_from_name', '');
        if ($from_name === '') {
            $from_name = Main::site_name();
        }

        // Defend against header injection.
        $subject    = $this->strip_header($subject);
        $from_name  = $this->strip_header($from_name);
        $from_field = ($from_name !== '') ? '"' . $from_name . '" <' . $from . '>' : $from;

        $cc = array();
        if (is_array($cc_array)) {
            foreach ($cc_array as $c) {
                if (!empty($c['email']) && filter_var($c['email'], FILTER_VALIDATE_EMAIL)) {
                    $cc[] = $c['email'];
                }
            }
        }

        $sent = false;
        if (is_array($to_array)) {
            foreach ($to_array as $to) {
                if (empty($to['email']) || !filter_var($to['email'], FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                if ($this->postmark_send($token, $from_field, $to['email'], $cc, $subject, $message, $attachments)) {
                    $sent = true;
                }
            }
        }
        return $sent;
    }

    /**
     * Deliver one HTML message through the Postmark HTTP API. Returns true on a 200.
     *
     * Attachments arrive already base64 encoded, as Postmark wants them inline in
     * the JSON body rather than as a multipart upload.
     */
    private function postmark_send($token, $from_field, $to_email, $cc, $subject, $message, $attachments = array()){
        $payload = array(
            'From'          => $from_field,
            'To'            => $to_email,
            'Subject'       => $subject,
            'HtmlBody'      => $message,
            'MessageStream' => 'outbound',
        );
        if (!empty($cc)) {
            $payload['Cc'] = implode(',', $cc);
        }
        if (!empty($attachments) && is_array($attachments)) {
            $payload['Attachments'] = $attachments;
        }

        $ch = curl_init('https://api.postmarkapp.com/email');
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => array(
                'Accept: application/json',
                'Content-Type: application/json',
                'X-Postmark-Server-Token: ' . $token,
            ),
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 30,
        ));
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($code === 200) {
            return true;
        }
        error_log('[postmark] send failed (http ' . $code . '): ' . ($resp !== false ? $resp : $err));
        return false;
    }

    public function remote_attachment($url, $filename, $content_type){

        if (!preg_match('#^https://[a-z0-9.\-]+\.stripe\.com/#i', (string) $url)) {
            error_log('[mailer] refused to attach a file from an unexpected origin');
            return array();
        }

        $context = stream_context_create(array('http' => array('timeout' => 15)));
        $body    = @file_get_contents($url, false, $context);

        if ($body === false || $body === '') {
            error_log('[mailer] attachment could not be downloaded');
            return array();
        }

        /* Postmark caps a message at 10MB including the encoded attachments, and
           base64 adds about a third. Well under that here, but a runaway file
           should drop the attachment rather than fail the send. */
        if (strlen($body) > 6 * 1024 * 1024) {
            error_log('[mailer] attachment too large to send');
            return array();
        }

        return array(
            'Name'        => $this->strip_header($filename),
            'Content'     => base64_encode($body),
            'ContentType' => $this->strip_header($content_type)
        );
    }

    private function strip_header($value){
        return trim(str_replace(array("\r", "\n"), '', (string) $value));
    }

}
