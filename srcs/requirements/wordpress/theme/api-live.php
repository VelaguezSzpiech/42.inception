<?php
// Live system data API for the architecture dashboard
// Returns JSON with real-time container info, configs, paths, volumes, network
header('Content-Type: application/json');
header('Cache-Control: no-cache');

// --- FILE CONTENT API (for the file tree explorer) ---
if (isset($_GET['action']) && $_GET['action'] === 'file') {
    $basePath = '/home/vszpiech/inception';
    $requestedPath = $_GET['path'] ?? '';

    $allowedFiles = [
        'Makefile',
        'srcs/docker-compose.yml',
        'srcs/.env',
        'srcs/requirements/nginx/Dockerfile',
        'srcs/requirements/nginx/conf/nginx.conf',
        'srcs/requirements/wordpress/Dockerfile',
        'srcs/requirements/wordpress/conf/www.conf',
        'srcs/requirements/wordpress/tools/wp_setup.sh',
        'srcs/requirements/wordpress/theme/front-page.php',
        'srcs/requirements/wordpress/theme/style.css',
        'srcs/requirements/wordpress/theme/api-live.php',
        'srcs/requirements/wordpress/theme/functions.php',
        'srcs/requirements/wordpress/theme/index.php',
        'srcs/requirements/wordpress/theme/header.php',
        'srcs/requirements/wordpress/theme/footer.php',
        'srcs/requirements/wordpress/theme/single.php',
        'srcs/requirements/wordpress/theme/page.php',
        'srcs/requirements/wordpress/theme/comments.php',
        'srcs/requirements/wordpress/theme/admin-style.css',
        'srcs/requirements/mariadb/Dockerfile',
        'srcs/requirements/mariadb/conf/50-server.cnf',
        'srcs/requirements/mariadb/tools/init_db.sh',
    ];

    if (!in_array($requestedPath, $allowedFiles, true)) {
        http_response_code(403);
        echo json_encode(['error' => 'File not in allowed list']);
        exit;
    }

    $fullPath = $basePath . '/' . $requestedPath;
    $realPath = realpath($fullPath);

    if ($realPath === false || strpos($realPath, $basePath) !== 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Path traversal denied']);
        exit;
    }

    if (!is_file($realPath) || !is_readable($realPath)) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found or unreadable']);
        exit;
    }

    $content = file_get_contents($realPath);
    echo json_encode([
        'path' => $requestedPath,
        'content' => $content,
        'size' => filesize($realPath),
        'modified' => date('Y-m-d H:i:s', filemtime($realPath)),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function cmd($c) { return trim(shell_exec($c . ' 2>/dev/null') ?? ''); }

$data = [];

// --- NGINX ---
$nx = [];
$nx['status'] = cmd("docker inspect nginx --format '{{.State.Status}}'");
$nx['pid'] = cmd("docker inspect nginx --format '{{.State.Pid}}'");
$nx['started'] = cmd("docker inspect nginx --format '{{.State.StartedAt}}'");
$nx['restart'] = cmd("docker inspect nginx --format '{{.HostConfig.RestartPolicy.Name}}'");
$nx['image'] = cmd("docker inspect nginx --format '{{.Config.Image}}'");
$nx['pid1'] = cmd("docker exec nginx cat /proc/1/cmdline | tr '\\0' ' '");
$nx['version'] = cmd("docker exec nginx nginx -V 2>&1 | grep 'nginx version'");
$nx['config'] = cmd("docker exec nginx cat /etc/nginx/nginx.conf");
$nx['mounts'] = cmd("docker inspect nginx --format '{{range .Mounts}}{{.Source}} -> {{.Destination}} ({{.Mode}})\\n{{end}}'");
$nx['cert_subject'] = cmd("docker exec nginx openssl x509 -in /etc/ssl/certs/vszpiech.42.fr.crt -noout -subject -dates 2>/dev/null");
$nx['dockerfile'] = cmd("cat /home/vszpiech/inception/srcs/requirements/nginx/Dockerfile");
$data['nginx'] = $nx;

// --- WORDPRESS ---
$wp = [];
$wp['status'] = cmd("docker inspect wordpress --format '{{.State.Status}}'");
$wp['pid'] = cmd("docker inspect wordpress --format '{{.State.Pid}}'");
$wp['started'] = cmd("docker inspect wordpress --format '{{.State.StartedAt}}'");
$wp['restart'] = cmd("docker inspect wordpress --format '{{.HostConfig.RestartPolicy.Name}}'");
$wp['image'] = cmd("docker inspect wordpress --format '{{.Config.Image}}'");
$wp['pid1'] = cmd("docker exec wordpress cat /proc/1/cmdline | tr '\\0' ' '");
$wp['php_version'] = cmd("docker exec wordpress php -v | head -1");
$wp['wp_version'] = cmd("docker exec wordpress wp core version --allow-root");
$wp['users'] = cmd("docker exec wordpress wp user list --allow-root --fields=user_login,roles,user_email --format=json");
$wp['fpm_config'] = cmd("docker exec wordpress cat /etc/php/8.2/fpm/pool.d/www.conf");
$wp['mounts'] = cmd("docker inspect wordpress --format '{{range .Mounts}}{{.Source}} -> {{.Destination}} ({{.Mode}})\\n{{end}}'");
$wp['secrets'] = cmd("docker exec wordpress ls -la /run/secrets/ | tail -n +2");
$wp['dockerfile'] = cmd("cat /home/vszpiech/inception/srcs/requirements/wordpress/Dockerfile");
$data['wordpress'] = $wp;

// --- MARIADB ---
$db = [];
$db['status'] = cmd("docker inspect mariadb --format '{{.State.Status}}'");
$db['pid'] = cmd("docker inspect mariadb --format '{{.State.Pid}}'");
$db['started'] = cmd("docker inspect mariadb --format '{{.State.StartedAt}}'");
$db['restart'] = cmd("docker inspect mariadb --format '{{.HostConfig.RestartPolicy.Name}}'");
$db['image'] = cmd("docker inspect mariadb --format '{{.Config.Image}}'");
$db['pid1'] = cmd("docker exec mariadb cat /proc/1/cmdline | tr '\\0' ' '");
$db['version'] = cmd("docker exec mariadb mysql --version");
$db['config'] = cmd("docker exec mariadb cat /etc/mysql/mariadb.conf.d/50-server.cnf");
$db['mounts'] = cmd("docker inspect mariadb --format '{{range .Mounts}}{{.Source}} -> {{.Destination}} ({{.Mode}})\\n{{end}}'");
$db['secrets'] = cmd("docker exec mariadb ls -la /run/secrets/ | tail -n +2");
$db['dockerfile'] = cmd("cat /home/vszpiech/inception/srcs/requirements/mariadb/Dockerfile");
$data['mariadb'] = $db;

// --- VOLUMES ---
$vol = [];
$vol['wp_files'] = cmd("ls -lah /home/vszpiech/data/wordpress/ | head -12");
$vol['wp_size'] = cmd("du -sh /home/vszpiech/data/wordpress/");
$vol['db_files'] = cmd("ls -lah /home/vszpiech/data/mariadb/ | head -12");
$vol['db_size'] = cmd("du -sh /home/vszpiech/data/mariadb/");
$vol['docker_volumes'] = cmd("docker volume ls --format '{{.Name}}: {{.Driver}}'");
$data['volumes'] = $vol;

// --- NETWORK ---
$net = [];
$net['name'] = cmd("docker network ls --filter name=inception --format '{{.Name}}'");
$net['driver'] = cmd("docker network ls --filter name=inception --format '{{.Driver}}'");
$net['subnet'] = cmd("docker network inspect srcs_inception_network --format '{{range .IPAM.Config}}{{.Subnet}}{{end}}'");
$net['gateway'] = cmd("docker network inspect srcs_inception_network --format '{{range .IPAM.Config}}{{.Gateway}}{{end}}'");
$net['containers'] = cmd("docker network inspect srcs_inception_network --format '{{range .Containers}}{{.Name}}: {{.IPv4Address}}  {{end}}'");
$data['network'] = $net;

// --- TLS ---
$tls = [];
$tls['protocol'] = cmd("echo | openssl s_client -connect vszpiech.42.fr:443 -tls1_2 2>/dev/null | grep 'Protocol  :'");
$tls['cipher'] = cmd("echo | openssl s_client -connect vszpiech.42.fr:443 -tls1_2 2>/dev/null | grep 'Cipher    :'");
$tls['cert'] = cmd("echo | openssl s_client -connect vszpiech.42.fr:443 2>/dev/null | openssl x509 -noout -subject -issuer -dates 2>/dev/null");
$data['tls'] = $tls;

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
