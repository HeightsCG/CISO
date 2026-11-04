<?php

/**
 * Countries and their first-level subdivisions (states, provinces, cantons),
 * from ISO 3166-1 and ISO 3166-2. Clients store the country and state by name,
 * so names are what the form submits and what this class checks.
 */
class Regions
{
    private static $countries = null;

    public static function countries(): array
    {
        if (self::$countries === null) {
            $json = (string) @file_get_contents(Main::lib_path().'/data/regions.json');
            $data = json_decode($json, true);
            self::$countries = is_array($data) ? $data : array();
        }

        return self::$countries;
    }

    /** The country with this name or ISO code, or null when there is none. */
    public static function find($country): ?array
    {
        $country = trim((string) $country);

        if ($country === '') {
            return null;
        }

        foreach (self::countries() as $row) {
            if (strcasecmp($row['name'], $country) === 0 || strcasecmp($row['code'], $country) === 0 || strcasecmp($row['iso3'], $country) === 0) {
                return $row;
            }
        }

        return null;
    }

    /**
     * The number in international form. A number already starting with + or 00
     * keeps its own country code; anything else gets the client country's code,
     * minus the national trunk prefix (the leading 0 in 022 555 0110). Returns
     * '' when there is no country code to use.
     */
    public static function phone($country, $phone): string
    {
        $phone = trim(preg_replace('/\s+/', ' ', preg_replace('/[^0-9+ ]/', '', preg_replace('/[().\-\/]/', ' ', (string) $phone))));

        if ($phone === '') {
            return '';
        }

        if (strpos($phone, '00') === 0) {
            $phone = '+'.ltrim(substr($phone, 2));
        }

        if ($phone[0] === '+') {
            return '+'.trim(str_replace('+', '', $phone));
        }

        $row = self::find($country);

        if ($row === null || $row['dial'] === '') {
            return '';
        }

        if ($row['trunk'] !== '' && strpos($phone, $row['trunk']) === 0) {
            $phone = ltrim(substr($phone, strlen($row['trunk'])));
        }

        return '+'.$row['dial'].' '.$phone;
    }

    public static function is_state($country, $state): bool
    {
        $row = self::find($country);

        if ($row === null) {
            return false;
        }

        return in_array((string) $state, $row['states'], true);
    }
}
