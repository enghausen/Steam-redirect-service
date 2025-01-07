<?php
// Get the full request URI
$request_uri = $_SERVER['REQUEST_URI'];

// Remove the leading slash
$steam_path = ltrim($request_uri, '/');

// Extract hostname, mandatory port, and optional password
if (preg_match('/^([^:\/]+):(\d+)(?:\/(.*))?$/', $steam_path, $matches)) {
    $host = $matches[1];         // Domain or IP
    $port = $matches[2];         // Mandatory port
    $password = $matches[3] ?? ''; // Optional password

    // Resolve hostname to IP if it's a domain
    if (filter_var($host, FILTER_VALIDATE_IP) === false) {
        $resolved_ip = gethostbyname($host);
        if ($resolved_ip === $host || empty($resolved_ip)) {
            // Host could not be resolved
            http_response_code(400);
            echo "Error: Invalid hostname or domain - '$host'";
            exit();
        }
    } else {
        $resolved_ip = $host; // Already an IP
    }

    // Construct the Steam URL
    $steam_url = "steam://connect/" . $resolved_ip . ":" . $port;

    // Append password if provided
    if (!empty($password)) {
        $steam_url .= "/" . $password;
    }

    // Redirect to the Steam URL
    header("Location: " . $steam_url);
    exit();
} else {
    // Invalid URL format
    http_response_code(400);
    echo "Error: Invalid request format. Use the following format:<br>";
    echo "<strong>https://yourdomain.com/hostname:port/password</strong><br>";
    echo "Examples:<br>";
    echo "https://steam.example.com/116.202.245.30:27015/password<br>";
    echo "https://steam.example.com/teamserver.example.com:27015/password<br>";
    echo "https://steam.example.com/116.202.245.30:27015<br>";
    echo "https://steam.example.com/teamserver.example.com:27015";
    exit();
}
?>
