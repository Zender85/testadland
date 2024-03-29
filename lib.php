<?php
// Importing libraries
require_once dirname(__FILE__) . '/vendor/autoload.php';
// Importing config
require_once dirname(__FILE__) . '/config.php';

use GeoIp2\Database\Reader;

// Get list of IP addresses that are recorded in user's HTTP headers
function Get_Ip_List($headers)
{
    $ip_list = array();
    // This is header we are looking into
    $ip_headers = array(
        'HTTP_X_FORWARDED_FOR', 'HTTP_CF_CONNECTING_IP', 'HTTP_FORWARDED_FOR',
        'HTTP_X_COMING_FROM', 'HTTP_COMING_FROM', 'HTTP_FORWARDED_FOR_IP',
        'HTTP_CLIENT_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'
    );
    // Order of headers is important, do not change!
    foreach ($ip_headers as $header) {
        if (array_key_exists($header, $headers)) {
            foreach (explode(',', $headers[$header]) as $ip) {
                if (
                    filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) &&
                    filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE)
                ) {
                    array_push($ip_list, $ip);
                }
            }
        }
    }
    return $ip_list;
}

/**
 * @param $ip_list array generated by Get_Ip_List
 * @return array with ip, country name and country iso code
 */
function Get_GeoIP($ip_list)
{
    try {
        $reader = new Reader(dirname(__FILE__) . '/GeoLite2-Country.mmdb');
        if (isset($reader)) {
            foreach ($ip_list as $ip) {
                try {
                    $record = $reader->country($ip);
                    $geoip = array(
                        'ip' => $ip,
                        'name' => $record->country->name,
                        'isoCode' => $record->country->isoCode
                    );
                    return $geoip;
                } catch (GeoIp2\Exception\AddressNotFoundException $e) {
                    continue;
                }
            }
        }
    } catch (MaxMind\Db\Reader\InvalidDatabaseException $e) {}
    // We will set to this value if DB not found
    return array(
        'ip' => $_SERVER['REMOTE_ADDR'],
        'name' => "Other Country",
        'isoCode' => "O1"
    );
}

// Log order result to orderlog.php
function Log_Order($params, $request_url, $response)
{
    $log_filename = 'orderlog.php';
    $date_now = date('Y-m-d H:i:s');
    if (file_exists($log_filename)) {
        $fp = fopen($log_filename, 'a');
    } else {
        $fp = fopen($log_filename, 'a');
        fwrite($fp, "<?php exit(\"Access Denied\"); ?>\n");
    }
    fwrite($fp, "Offer id: {$params['offer_id']}\nIP: {$params['ip']}\nReferer: {$params['referrer']}\nDate: {$date_now}\nRequest URL: {$request_url}\nResponse: {$response}\n\n\n=====================\n\n\n");
    fclose($fp);
}


// Sending order to API
function Send_Order($request)
{
    unset($request['PHPSESSID']);

    $context = stream_context_create(array(
        "http" => array(
            "ignore_errors" => true,
            'timeout' => 10,
            "method" => "GET",
            "header" => $_SERVER
        ),
        "ssl" => array(
            "verify_peer" => false,
            "verify_peer_name" => false,
        ),
    ));

    $request['api_key'] = ACLandingConfig::API_KEY;
    $request['ip'] = isset($request['ip']) ? $request['ip'] : $_SERVER['REMOTE_ADDR'];
    $request['base_url'] = $_SERVER['HTTP_REFERER'];
    // Failover. If JavaScript footer failed, add custom params to request, filtering out macros.
    if (defined('ACLandingConfig::CUSTOM_PARAMS')) {
        $custom_params = array();
        foreach (ACLandingConfig::CUSTOM_PARAMS as $param => $value) {
            if (preg_match('/{\w+}/', $value) === 0) {
                $custom_params[$param] = $value;
            }
        }
        $request = $request + $custom_params;
    }
    $landing = parse_url($_SERVER['HTTP_REFERER']);
    parse_str($landing['query'], $land_params);
    $request_url = ACLandingConfig::API_URL . '?' . http_build_query($request + $land_params, '', '&');
    // Finally, make request to API. file_get_contents needs custom error handler otherwise set ignore_errors
    set_error_handler(
        function ($severity, $message, $file, $line) {
            throw new ErrorException($message, $severity, $severity, $file, $line);
        }
    );
    try {
        $resp = file_get_contents($request_url, false, $context);
    } catch (Exception $e) {
        $resp = json_encode(array(
            "code" => "exception",
            "error" => $e->getMessage(),
        ));
    }
    restore_error_handler();

    // Log order
    if (ACLandingConfig::LOG_ENABLED) {
        Log_Order($request, $request_url, $resp);
    }
    $data = json_decode($resp, true);
    return $data;
}

// Template "rendering". Currently only corrects paths and adds JS code to the footer
function Render_Template($content_path)
{
    $rendered_template = file_get_contents(dirname(__FILE__) . '/' . $content_path . 'index.html');
    $rendered_template = preg_replace(
        '/(href|src)=(\")?((\w+)\/[\w+\/\.\-]+)([\"\s>])/i',
        '${1}=${2}content/${3}${5}',
        $rendered_template
    );

    // If GEOIP_ENABLED = true in config file and we have GeoIP Database, then detect visitors geo
    if (ACLandingConfig::GEOIP_ENABLED && file_exists(dirname(__FILE__) . '/GeoLite2-Country.mmdb')) {
        $geo_ip = Get_GeoIP(Get_Ip_List($_SERVER));
        $ip = $geo_ip['ip'];
        $ip_country = $geo_ip['isoCode'];
        $ip_country_name = $geo_ip['name'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
        $ip_country = '';
        $ip_country_name = '';
    }
    $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "";

    // Add custom parameters
    $custom_params = '';
    if (defined('ACLandingConfig::CUSTOM_PARAMS')) {
        foreach (ACLandingConfig::CUSTOM_PARAMS as $param => $value) {
            $custom_params = $custom_params . 'form.appendChild(inputElem("' . $param . '", "' . $value . '"));';
        }
    }
    // This JavaScript will add all needed data to order form
    /** @noinspection CommaExpressionJS ExpressionStatementJS JSUnnecessarySemicolon */
    $jsfooter = <<<EOV
<script type='text/javascript'>
function inputElem(name,value) {
    let element = document.createElement("input");
    element.setAttribute("type", "hidden");
    element.setAttribute("name", name);
    element.setAttribute("value", value);
    return element;
}
document.querySelectorAll('form').forEach(
    form => {
        form.appendChild(inputElem("referrer", "$referrer"));
        form.appendChild(inputElem("ip", "$ip"));
        form.appendChild(inputElem("ip_country", "$ip_country"));
        form.appendChild(inputElem("ip_country_name", "$ip_country_name"));
        // Here be custom parameters
        $custom_params
    }
);
</script>
EOV;

    $rendered_template = preg_replace(
        '/\<\/body\>/i',
        $jsfooter,
        $rendered_template
    );
    eval('?>' . $rendered_template);
}
