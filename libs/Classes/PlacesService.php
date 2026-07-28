<?php
/**
 * Amazon Location Service Places V2 (geo-places) for address autocomplete.
 *
 * Configuration comes only from places_* keys in app.ini for the current
 * environment. There is deliberately no credential fallback: no ambient ~/.aws
 * profile, no instance-role chain, no borrowing the s3_* identity. A fallback
 * would resolve to whatever AWS identity happened to be available and the real
 * misconfiguration would never surface.
 *
 * Failures are not swallowed either — they are returned to the caller so the UI
 * can report them.
 *
 * IAM needs exactly geo-places:Autocomplete and geo-places:GetPlace.
 */
class PlacesService {

    private static function cfg($key): string
    {
        $config = Main::get_config();
        $env    = Main::get_environment();
        return (string) ($config[$env][$key] ?? '');
    }

    public static function region(): string
    {
        return self::cfg('places_region');
    }

    /** Empty string when usable, otherwise the reason it is not. */
    public static function configuration_error(): string
    {
        if (self::region() === '') {
            return 'places_region is not set in app.ini';
        }

        if (self::cfg('places_key') === '' || self::cfg('places_secret') === '') {
            return 'places_key and places_secret are not set in app.ini';
        }

        return '';
    }

    private static function client(): \Aws\GeoPlaces\GeoPlacesClient
    {
        return new \Aws\GeoPlaces\GeoPlacesClient(array(
            'version'     => 'latest',
            'region'      => self::region(),
            'credentials' => array(
                'key'    => self::cfg('places_key'),
                'secret' => self::cfg('places_secret'),
            ),
        ));
    }

    /**
     * Typeahead suggestions. Returns ['error' => string, 'items' => array].
     * Autocomplete only supports IntendedUse SingleUse; Storage is applied on
     * GetPlace, which is where the persisted address comes from.
     */
    public static function autocomplete($query, $country_code = '', $max_results = 6): array
    {
        $error = self::configuration_error();

        if ($error !== '') {
            return array('error' => $error, 'items' => array());
        }

        try {

            $items = self::query($query, $country_code, $max_results);

            /**
             * IncludeCountries is a hard filter, not a bias, so searching it alone
             * would make a client in another country unfindable. Results are biased
             * to the country already on the form, then retried without it so an
             * international address is still reachable.
             */
            if (empty($items) && $country_code !== '') {
                $items = self::query($query, '', $max_results);
            }

        } catch (\Throwable $e) {
            error_log('[places] autocomplete failed: ' . $e->getMessage());
            return array('error' => self::readable($e), 'items' => array());
        }

        return array('error' => '', 'items' => $items);
    }

    private static function query($query, $country_code, $max_results): array
    {
        $args = array(
            'QueryText'  => (string) $query,
            'MaxResults' => (int) $max_results,
            'Filter'     => array(
                'IncludePlaceTypes' => array('PointAddress', 'InterpolatedAddress', 'Street')
            ),
        );

        if ($country_code !== '') {
            $args['Filter']['IncludeCountries'] = array($country_code);
        }

        $result = self::client()->autocomplete($args);
        $items = array();

        foreach (($result['ResultItems'] ?? array()) as $item) {

            $label = (string) ($item['Address']['Label'] ?? $item['Title'] ?? '');

            if ($label === '' || empty($item['PlaceId'])) {
                continue;
            }

            $items[] = array(
                'place_id' => (string) $item['PlaceId'],
                'label'    => $label
            );
        }

        return $items;
    }

    /**
     * Full structured address for a PlaceId, mapped onto the client form fields.
     * IntendedUse is Storage because the selected address is persisted.
     * Returns ['error' => string, 'address' => array].
     */
    public static function get_place($place_id): array
    {
        $error = self::configuration_error();

        if ($error !== '') {
            return array('error' => $error, 'address' => array());
        }

        try {
            $result = self::client()->getPlace(array(
                'PlaceId'     => (string) $place_id,
                'IntendedUse' => 'Storage',
            ));
        } catch (\Throwable $e) {
            error_log('[places] get_place failed: ' . $e->getMessage());
            return array('error' => self::readable($e), 'address' => array());
        }

        $address = $result['Address'] ?? array();

        $street = trim(
            (string) ($address['AddressNumber'] ?? '') . ' ' . (string) ($address['Street'] ?? '')
        );

        return array(
            'error' => '',
            'address' => array(
                'street'      => ($street === '' ? (string) ($address['Street'] ?? '') : $street),
                'city'        => (string) ($address['Locality'] ?? ''),
                'state'       => (string) ($address['Region']['Name'] ?? $address['Region']['Code'] ?? ''),
                'postal_code' => (string) ($address['PostalCode'] ?? ''),
                'country'     => (string) ($address['Country']['Name'] ?? $address['Country']['Code2'] ?? ''),
            )
        );
    }

    /** First line of an AWS exception, which carries the actionable part. */
    private static function readable(\Throwable $e): string
    {
        $message = trim(strtok($e->getMessage(), "\n"));

        if (strpos($e->getMessage(), 'is not authorized to perform') !== false) {
            return 'Address lookup denied: the configured AWS user lacks geo-places permissions';
        }

        return ($message === '' ? 'Address lookup failed' : $message);
    }

}
