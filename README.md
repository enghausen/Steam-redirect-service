# Steam Redirect Service (PHP + NGINX)

This service allows the creation of **clickable Steam connect links** in platforms like **Discord**, where the `steam://` URL scheme is no longer clickable. It is particularly useful for **game servers** where users need quick-connect links, such as **Counter-Strike 2 (CS2)** or other games managed by **DiscordGSM/GameServerManager**.

---

## Features

- **Steam Connect Links**: Generates clickable links for connecting to Steam servers via IP or hostname.
- **Mandatory Port Support**: Ensures compatibility with different games by requiring explicit port numbers.
- **Optional Password Handling**: Supports servers with or without passwords.
- **DNS Resolution**: Automatically resolves hostnames to IP addresses (useful because `steam://` does not support hostnames).
- **Error Handling**: Provides user-friendly error messages for invalid input.
- **HTTPS with Certbot**: Ensures secure connections using Let's Encrypt certificates.

---

## Requirements

- **Alma Linux 9.5** (or compatible).
- **PHP 8.4 or higher** with PHP-FPM enabled.
- **NGINX** web server.
- **Certbot** for SSL.

---

## Installation Steps

### 1. Install Required Packages

```bash
sudo dnf install -y epel-release
sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm
sudo dnf module reset php -y
sudo dnf module enable php:remi-8.4 -y
sudo dnf install -y php php-fpm php-json php-mbstring php-opcache nginx certbot python3-certbot-nginx
sudo dnf -y update
```

### 2. Configure NGINX

#### Option 1: Non-SSL Configuration (before Certbot)
```nginx
server {
    listen 80;
    server_name steam.example.com;

    root /usr/share/nginx/steam;
    index index.php;

    # Route everything to index.php
    location / {
        try_files $uri /index.php?$args;
    }

    # Handle PHP files
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Suppress favicon.ico errors
    location = /favicon.ico {
        log_not_found off;
        access_log off;
    }
}
```

You can use this configuration to test the service before enabling HTTPS.

#### Option 2: SSL Configuration (after Certbot)
```nginx
server {
    server_name steam.example.com;

    root /usr/share/nginx/steam;
    index index.php;

    # Route everything to index.php
    location / {
        try_files $uri /index.php?$args; # Always fallback to index.php
    }

    # Handle PHP files
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Suppress favicon.ico errors
    location = /favicon.ico {
        log_not_found off;
        access_log off;
    }

    listen 443 ssl; # managed by Certbot
    ssl_certificate /etc/letsencrypt/live/steam.example.com/fullchain.pem; # managed by Certbot
    ssl_certificate_key /etc/letsencrypt/live/steam.example.com/privkey.pem; # managed by Certbot
    include /etc/letsencrypt/options-ssl-nginx.conf; # managed by Certbot
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem; # managed by Certbot
}

server {
    if ($host = steam.example.com) {
        return 301 https://$host$request_uri;
    } # managed by Certbot

    listen 80;
    server_name steam.example.com;
    return 404; # managed by Certbot
}
```

### 3. Obtain SSL Certificate

```bash
sudo certbot --nginx -d steam.example.com
```

### 4. Create PHP Script

```bash
sudo mkdir -p /usr/share/nginx/steam
sudo nano /usr/share/nginx/steam/index.php
```
Paste the following code:

```php
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
```

### 5. Restart Services

```bash
sudo systemctl restart nginx php-fpm
```

---

## Usage

### Example Links:
- `https://steam.example.com/116.202.245.30:27015/password`
- `https://steam.example.com/teamserver.example.com:27015/password`

### Supported Formats:
- **Mandatory Port:** Required to support multiple games.
- **Optional Passwords:** Included in the URL if provided.

---

## License
This project is licensed under the MIT License - see the LICENSE file for details.

---

