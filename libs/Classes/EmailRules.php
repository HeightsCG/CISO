<?php

/**
 * Format checks for contact details. Business addresses only: a mailbox at a
 * free consumer provider says nothing about the organisation it belongs to.
 */
class EmailRules
{
    const FREE_DOMAINS = array(
        'gmail.com',
        'googlemail.com',
        'yahoo.com',
        'yahoo.co.uk',
        'yahoo.fr',
        'yahoo.de',
        'yahoo.es',
        'yahoo.it',
        'yahoo.ca',
        'yahoo.com.au',
        'ymail.com',
        'rocketmail.com',
        'hotmail.com',
        'hotmail.co.uk',
        'hotmail.fr',
        'hotmail.de',
        'hotmail.it',
        'hotmail.es',
        'outlook.com',
        'outlook.fr',
        'outlook.de',
        'live.com',
        'live.co.uk',
        'live.fr',
        'msn.com',
        'aol.com',
        'icloud.com',
        'me.com',
        'mac.com',
        'proton.me',
        'protonmail.com',
        'pm.me',
        'gmx.com',
        'gmx.net',
        'gmx.de',
        'gmx.ch',
        'gmx.at',
        'web.de',
        't-online.de',
        'mail.com',
        'zoho.com',
        'zohomail.com',
        'yandex.com',
        'yandex.ru',
        'mail.ru',
        'qq.com',
        '163.com',
        '126.com',
        'tutanota.com',
        'tuta.io',
        'fastmail.com',
        'hey.com',
        'bluewin.ch',
        'orange.fr',
        'free.fr',
        'laposte.net',
        'libero.it',
        'comcast.net',
        'verizon.net',
        'att.net',
        'sbcglobal.net'
    );

    public static function is_valid_format($email): bool
    {
        return filter_var((string) $email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function is_free($email): bool
    {
        $at = strrpos((string) $email, '@');

        if ($at === false) {
            return false;
        }

        return in_array(strtolower(substr((string) $email, $at + 1)), self::FREE_DOMAINS, true);
    }

    /** International format: a leading + and country code, 8 to 15 digits in all. */
    public static function is_phone($phone): bool
    {
        $phone = trim((string) $phone);

        if (!preg_match('/^\+[0-9][0-9 ().\-]*$/', $phone)) {
            return false;
        }

        $digits = strlen(preg_replace('/\D/', '', $phone));

        return $digits >= 8 && $digits <= 15;
    }
}
