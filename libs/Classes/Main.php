<?php
class Main {

    /**
     * The forwarded header is read for the same reason Bootstrap::is_https()
     * reads it: TLS terminates at the proxy, so neither HTTPS nor SERVER_PORT
     * says https on the request this app is handed. An absolute URL built
     * without it goes out as http, and a scraper handed an http image on an
     * https page either downgrades the card or drops the image.
     */
    public static function site_protocol(): string
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return 'https://';
        }

        if (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            return 'https://';
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return 'https://';
        }

        return 'http://';
    }

    public static function controller_name(): string
    {
        $url = self::get_url();
        if (isset($url[0]) && $url[0] !== '') {
            $segment = preg_replace('/[^A-Za-z0-9_]/', '', $url[0]);
            if ($segment !== '') {
                return ucfirst($segment).'Controller';
            }
        }
        return 'IndexController';
    }

    public static function method_name(): string
    {
        $url = self::get_url();
        if (isset($url[1]) && $url[1] !== '') {
            $segment = preg_replace('/[^A-Za-z0-9_]/', '', $url[1]);
            if ($segment !== '') {
                return strtolower($segment).'Action';
            }
        }
        return 'indexAction';
    }
    
    public static function get_config(): array
    {
        $config_file = self::config_path().'/app.ini';
        if (is_file($config_file)) {
            $parsed = parse_ini_file($config_file, true);
            return is_array($parsed) ? $parsed : array();
        }
        return array();
    }

    public static function config(string $section, string $key)
    {
        $config = self::get_config();
        return $config[$section][$key];
    }

    public static function site_name(): string
    {
        return self::config('global', 'site_name');
    }

    /**
     * Icons and the link preview, identical on every page. Nothing here varies
     * by page on purpose: the app is behind auth, so a scraper following any
     * shared link is answered by the sign-in screen, and a per-page title would
     * put client and project names into a preview that everyone in the channel
     * can read. Absolute URLs, because a scraper is given no page to resolve a
     * relative one against.
     *
     * The card is versioned by mtime so replacing the artwork replaces what the
     * unfurl caches already hold, which key on the image URL and not on its
     * contents.
     */
    public static function head_meta(): string
    {
        $base        = self::get_base_domain();
        $name        = self::site_name();
        $description = 'Aviation cybersecurity governance, centralized. Manage risk, compliance, evidence, and executive oversight from one secure platform.';
        $card        = $base.'/images/og-card.png?v='.(int) @filemtime(self::app_path().'/public/images/og-card.png');

        $tags = array(
            '<link rel="icon" href="/favicon.ico" sizes="any">',
            '<link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32.png">',
            '<link rel="icon" type="image/png" sizes="512x512" href="/images/icon-512.png">',
            '<link rel="apple-touch-icon" href="/images/apple-touch-icon.png">',
            '<meta name="theme-color" content="#101B2B">',
            '<meta name="description" content="'.self::attr($description).'">',
            '<meta property="og:type" content="website">',
            '<meta property="og:site_name" content="'.self::attr($name).'">',
            '<meta property="og:title" content="'.self::attr($name).'">',
            '<meta property="og:description" content="'.self::attr($description).'">',
            '<meta property="og:url" content="'.self::attr($base.'/').'">',
            '<meta property="og:image" content="'.self::attr($card).'">',
            '<meta property="og:image:type" content="image/png">',
            '<meta property="og:image:width" content="1200">',
            '<meta property="og:image:height" content="630">',
            '<meta property="og:image:alt" content="'.self::attr($name).'">',
            '<meta name="twitter:card" content="summary_large_image">',
            '<meta name="twitter:title" content="'.self::attr($name).'">',
            '<meta name="twitter:description" content="'.self::attr($description).'">',
            '<meta name="twitter:image" content="'.self::attr($card).'">'
        );

        return implode("\n", $tags)."\n";
    }

    private static function attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /** Public-facing brand domain for creator profile URLs (distinct from the infra host). */
    public static function public_domain(): string
    {
        return self::config('global', 'public_domain');
    }

    /** Platform's percentage cut of paid creator subscriptions (Stripe application fee). */
    public static function platform_fee_percent(): float
    {
        $config = self::get_config();
        return (float) ($config['global']['platform_fee_percent'] ?? 10);
    }

    /** Processing (merchant service) fee % added on top of a credit purchase. */
    public static function credit_fee_percent(): float
    {
        $config = self::get_config();
        return (float) ($config['global']['credit_fee_percent'] ?? 5);
    }

    public static function get_url(): array
    {
        $url = array();
        if (isset($_GET['url'])) {  
            $raw = trim((string) $_GET['url']);
            $raw = trim($raw, '/');
            if ($raw !== '') {
                $url = explode('/', $raw);
            }
        }
        return $url;
    }
    
    public static function app_path(): string
    {
        $current_path = dirname(__DIR__);
        return dirname($current_path);
    }

    public static function vendor_path(): string
    {
        return self::app_path() . '/vendor';
    }

    public static function config_path(): string
    {
        return self::app_path().'/app/config';
    }
    
    public static function lib_path(): string
    {
        return dirname(__DIR__);
    }
    
    public static function get_param($id){
        $value = 0;
        $url = self::get_url();
        if (in_array($id, $url)) {
            $key = array_search($id, $url) + 1;
            if (key_exists($key,$url)) {
                $value = $url[$key];
            }
        }
        return $value;
    }
    
    public static function get_environment(): string
    {
        return getenv('APPLICATION_ENV');
    }
    
    public static function do_logout(): void
    {
        Session::destroy();
    }

    public static function get_base_domain(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? self::config('development', 'domain');
        return self::site_protocol() . $host;
    }

}
