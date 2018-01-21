<?php 

include_once 'krumo/class.krumo.php';

require 'Wappalyzer/src/drivers/php/Wappalyzer.php';
require 'Wappalyzer/src/drivers/php/WappalyzerException.php';
require __DIR__ . '/vendor/autoload.php';

// Open MySQL
$username="root";
$password="root";
$database="curlphp";

// $query = "INSERT INTO tablename VALUES ('','$field1-name','$field2-name','$field3-name','$field4-name','$field5-name')";


try {
    $dbh = new PDO('mysql:host=localhost;dbname=curlphp', $username, $password);
    // $results = $dbh->query('SELECT * FROM twelve');
    $results = $dbh->query('SELECT * FROM twelve WHERE `Website` IS NULL');

    if (!empty($results)) {
        foreach ($results as $comp) {
            process_mysql($comp, $dbh);
        }
    }
    
    $dbh = null;
} catch (PDOException $e) {
    print "Error!: " . $e->getMessage() . "<br/>";
    die();
}

function process_mysql($comp = NULL, $dbh = NULL) {
    $wapp = trim($comp['Wappalyzer']);
    if (empty($wapp) && strpos($comp['Wappalyzer'], 'skip') !== TRUE) {

        $base_domain = '';
        if ($comp['Website'] == NULL || empty($comp['Website'])) {
            // Duck Duck
            $duckduck = 'http://duckduckgo.com/html/?q='.rawurlencode($comp['Company']);
            $base_domain = $url = search_dom($duckduck);
            if (strpos($url, 'http://') == FALSE) {
                $url = 'http://' . trim($url);
            }
        } else {
            $url = $comp['Website'];
        }

        // Set bad headers to skip.
        $bad_http = array(400, 401);

        // Test URL
        $curl = curl_request($url);
        if (!empty($curl['error'])) {
            // Parse URL, try WWW if not already.
            $parsed = parse_url($curl['info']['url']);
            if (strpos($parsed['host'], 'www') == FALSE) {
                $url = $parsed['scheme'] . 'www.' . $parsed['host'];
                $curl = curl_request($url);
            }
        }
        krumo($url);
        krumo($curl);
        if ($curl && !in_array($curl['info']['http_code'], $bad_http) && empty($curl['error']) && checkHeader($curl['headers'])) {
                // Get headers.
            $headers = processHeaders($curl['headers']);
            $url = (!empty($curl['headers']['Location']) && strpos($curl['headers']['Location'], 'http://') !== FALSE) ? trim($curl['headers']['Location']) : $url;

            $wappalyzer = new Wappalyzer($url);
            $detectedApps = $wappalyzer->analyze();
            $services = array();
            if (!empty($detectedApps)) {
                $services = processWappalyzer(object_to_array($detectedApps));
            }

            $stuff = array(
                'Website' => $url,
                'Wappalyzer' => $services,
                'Headers' => $headers,
                );

            $yay = $dbh->prepare('UPDATE twelve SET Website = :website, Wappalyzer = :wappalyzer, Headers = :headers WHERE Rank = :rank');
            // $yay->bindParam(':table', $table);
            $yay->bindParam(':website', $stuff['Website']);
            $yay->bindParam(':wappalyzer', $stuff['Wappalyzer']);
            $yay->bindParam(':headers', $stuff['Headers']);
            $yay->bindParam(':rank', $comp['Rank']);
            $yay->execute();

                // $create = json_encode($sheetsu_data, JSON_FORCE_OBJECT);
                // $data = curlWrap($sheetsu, $create, "PATCH", $comp['Rank']);
        }
    }
}

function curl_request($url, $response = 'header') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);

    $response = curl_exec($ch);

    // Then, after your curl_exec call:
    $headers = array();
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);

    if (!empty($headers)) {
        $hdr = explode("\n", $headers);
        $tmp = array();
        foreach ($hdr as $str) {
            $name = explode(':', $str, 2);
            if (!empty($name)) {
                if (!empty($name[1])) {
                    $key = $name[0];
                    unset($name[0]);
                    $tmp[$key] = implode(':', $name);
                } else {
                    $tmp[] = $name;
                }
            }
        }
        $headers = $tmp;
    }

    $arr = array(
        'body' => $body,
        'headers' => $headers,
        'error' => curl_error($ch),
        'info' => curl_getinfo($ch),
        );
    
    return $arr;

}

function curl_body($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $body = curl_exec($ch);

    return $body;
}

function object_to_array($obj) {
    $arrObj = is_object($obj) ? get_object_vars($obj) : $obj;
    $arr = array();
    foreach ($arrObj as $key => $val) {
        $val = (is_array($val) || is_object($val)) ? object_to_array($val) : $val;
        $arr[$key] = $val;
    }
    return $arr;
}

function curlWrap($url, $json, $action, $rank = 1) {

    $url .= '/Rank/' . $rank;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10 );
    curl_setopt($ch, CURLOPT_URL, $url);

    switch ($action) {
        case "POST":
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        break;
        case "GET":
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        break;
        case "PUT":
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        case "PATCH":
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        default:
        break;
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
    curl_setopt($ch, CURLOPT_USERAGENT, "MozillaXYZ/1.0");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $output = curl_exec($ch);
    curl_close($ch);
    $decoded = json_decode($output);
    return $decoded;
}

function processWappalyzer($apps) {
    $string = '';
    $count = 0;
    foreach ($apps as $key => $value) {
        if ($count > 0) {
            $string .= ', ';
        }
        $string .= str_replace('␣', ' ', $key);
        $count++;
    }
    return $string;
}

function processHeaders($headers) {
    $string = '';
    if (!empty($headers)) {
        $count = 0;
        foreach ($headers as $key => $value) {
            if ($count > 0) {
                $string .= ' | ';
            }
            if (!is_array($value)) {
                $string .= $key .': '.$value;
            }
        }
    }
    return $string;
}

function dpm($var) {
    krumo($var);
}

function search_dom($url) {
    libxml_use_internal_errors(TRUE); //disable libxml errors

    if (!empty($url)) {

        $doc = new DOMDocument();
        $doc->loadHTMLFile($url);
        // print  ($doc->saveHTML());

        libxml_clear_errors(); //remove errors for yucky html

        $xpath = new DOMXpath($doc);

        //get all the a's with class "result__a"
        $inc_row = $xpath->query("//div[contains(@class, 'results')]//div[contains(@class, 'web-result')]//a[contains(@class, 'result__url')]");

        // dpm($inc_row);
        if($inc_row->length > 0){
            // dpm($inc_row[0]->attributes);
            return ($inc_row[0]->nodeValue);
        }
    }
}

function checkHeader($curl) {
    if (isset($curl['Connection']) && $curl['Connection'] == 'close') {
        return FALSE;
    }

    return TRUE;
}