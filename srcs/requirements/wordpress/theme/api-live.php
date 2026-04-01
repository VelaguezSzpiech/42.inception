<?php
// Live system data API for the architecture dashboard
// Returns JSON with real-time container info, configs, paths, volumes, network
header('Content-Type: application/json');
header('Cache-Control: no-cache');

// =====================================================================
// HELPER: Convert a relative path to a stable node ID
// =====================================================================
function pathToId(string $relPath): string {
    $p = rtrim(strtolower($relPath), '/');
    // Strip leading dots from path segments (.env -> env)
    $p = preg_replace('#(?:^|/)\.#', '$0', $p); // keep dots for now
    $p = preg_replace('#(^|/)\.#', '$1', $p);
    // Remove known file extensions
    $p = preg_replace('/\.(yml|yaml|php|css|sh|conf|cnf|txt|env)$/i', '', $p);
    $p = str_replace('/', '-', $p);
    if ($p === '') return 'root';
    return $p;
}

// =====================================================================
// HELPER: Get a short description for a file
// =====================================================================
function getFileDescription(string $relPath, string $name): string {
    $pathDescs = [
        'Makefile' => 'build automation',
        'srcs/docker-compose.yml' => 'orchestration',
        'srcs/.env' => 'environment variables',
    ];
    if (isset($pathDescs[$relPath])) return $pathDescs[$relPath];

    if ($name === 'Dockerfile') return 'container image';
    $nameDescs = [
        'nginx.conf'       => 'TLS + reverse proxy',
        'www.conf'          => 'PHP-FPM pool :9000',
        'wp_setup.sh'       => 'WP init script',
        'init_db.sh'        => 'DB init script',
        '50-server.cnf'     => 'MariaDB config',
        'admin-style.css'   => 'WP admin styles',
        'api-live.php'      => 'live data API',
        'comments.php'      => 'comments template',
        'footer.php'        => 'footer partial',
        'front-page.php'    => 'main template',
        'functions.php'     => 'theme setup',
        'header.php'        => 'header partial',
        'index.php'         => 'fallback',
        'page.php'          => 'page template',
        'single.php'        => 'single post template',
        'style.css'         => 'theme styles',
        'db_password.txt'       => 'database password',
        'db_root_password.txt'  => 'root password',
        'credentials.txt'       => 'WP admin credentials',
    ];
    if (preg_match('/\.crt$/', $name)) return 'SSL certificate';
    if (preg_match('/\.key$/', $name)) return 'SSL private key';
    return $nameDescs[$name] ?? '';
}

// =====================================================================
// TREE SCANNER: Recursively build the project tree from the filesystem
// =====================================================================
function scanTree(string $dirPath, string $relPath = ''): ?array {
    $name = basename($dirPath);
    $skipNames = ['.git', '.claude', '.playwright-mcp', '.DS_Store', 'node_modules'];
    $skipFiles = ['.gitignore', '.gitkeep', '.dockerignore'];

    if (in_array($name, $skipNames, true)) return null;
    if (is_file($dirPath) && in_array($name, $skipFiles, true)) return null;

    $id = ($relPath === '') ? 'root' : pathToId($relPath);

    if (is_dir($dirPath)) {
        $entries = scandir($dirPath);
        $dirs = [];
        $files = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $full = $dirPath . '/' . $entry;
            $childRel = ($relPath === '') ? $entry : $relPath . '/' . $entry;
            if (is_dir($full)) $dirs[] = [$full, $childRel, $entry];
            else $files[] = [$full, $childRel, $entry];
        }
        usort($dirs, fn($a, $b) => strcasecmp($a[2], $b[2]));
        usort($files, fn($a, $b) => strcasecmp($a[2], $b[2]));

        $children = [];
        foreach (array_merge($dirs, $files) as [$childFull, $childRel, $_]) {
            $child = scanTree($childFull, $childRel);
            if ($child !== null) $children[] = $child;
        }
        if (empty($children) && $relPath !== '') return null;

        $defaultExpanded = ['', 'srcs', 'srcs/requirements'];
        return [
            'name' => $name . '/',
            'type' => 'dir',
            'id'   => $id,
            'expanded' => in_array($relPath, $defaultExpanded, true),
            'children' => $children,
        ];
    }

    // File node
    $isSecret = (strpos($relPath, 'secrets/') === 0);
    $isCert = (bool)preg_match('/\.(crt|key|pem)$/', $name);
    return [
        'name'     => $name,
        'type'     => 'file',
        'id'       => $id,
        'filePath' => ($isSecret || $isCert) ? null : $relPath,
        'desc'     => getFileDescription($relPath, $name),
    ];
}

function buildTree(string $basePath): array {
    $root = [
        'name' => 'inception',
        'type' => 'dir',
        'id'   => 'root',
        'expanded' => true,
        'children' => [],
    ];
    $rootChildren = ['Makefile', 'srcs', 'secrets'];
    foreach ($rootChildren as $child) {
        $full = $basePath . '/' . $child;
        if (!file_exists($full)) continue;
        $node = scanTree($full, $child);
        if ($node !== null) {
            // Makefile is a file, not a dir, so scanTree returns a file node
            $root['children'][] = $node;
        }
    }
    return $root;
}

// =====================================================================
// HELPER: Extract all node IDs from a tree
// =====================================================================
function extractAllIds(array $node): array {
    $ids = [$node['id']];
    if (isset($node['children'])) {
        foreach ($node['children'] as $child) {
            $ids = array_merge($ids, extractAllIds($child));
        }
    }
    return $ids;
}

// =====================================================================
// CONNECTION PARSERS
// =====================================================================

// Find all Dockerfiles under a base path
function findDockerfiles(string $basePath): array {
    $results = [];
    $reqDir = $basePath . '/srcs/requirements';
    if (!is_dir($reqDir)) return $results;
    foreach (scandir($reqDir) as $service) {
        if ($service === '.' || $service === '..') continue;
        $df = $reqDir . '/' . $service . '/Dockerfile';
        if (is_file($df)) {
            $results[] = 'srcs/requirements/' . $service . '/Dockerfile';
        }
    }
    return $results;
}

function parseDockerfileCopies(string $basePath): array {
    $connections = [];
    $dockerfiles = findDockerfiles($basePath);
    foreach ($dockerfiles as $dfRelPath) {
        $dfId = pathToId($dfRelPath);
        $dfDir = dirname($dfRelPath);
        $content = file_get_contents($basePath . '/' . $dfRelPath);
        if (!$content) continue;

        preg_match_all('/^COPY\s+(?:--\S+\s+)?(\S+)\s+/m', $content, $matches);
        foreach ($matches[1] as $src) {
            $srcRel = $dfDir . '/' . $src;
            $srcRel = rtrim($srcRel, '/');
            $isDir = (substr($src, -1) === '/');
            $srcId = pathToId($srcRel);
            $label = $isDir ? 'COPY ' . basename($src) . '/' : 'COPY';
            $connections[] = [
                'from'  => $srcId,
                'to'    => $dfId,
                'label' => $label,
                'color' => '#a89880',
            ];
        }
    }
    return $connections;
}

function parseComposeFile(string $basePath): array {
    $connections = [];
    $composePath = 'srcs/docker-compose.yml';
    $content = file_get_contents($basePath . '/' . $composePath);
    if (!$content) return $connections;
    $composeId = pathToId($composePath);

    // build: directives -> compose references Dockerfiles
    preg_match_all('/^\s+build:\s*(\S+)/m', $content, $buildMatches);
    foreach ($buildMatches[1] as $buildPath) {
        $resolvedDir = 'srcs/' . ltrim($buildPath, './');
        $dfPath = $resolvedDir . '/Dockerfile';
        $connections[] = [
            'from'  => $composeId,
            'to'    => pathToId($dfPath),
            'label' => 'build:',
            'color' => '#a89880',
        ];
    }

    // env_file: -> .env feeds into compose (deduplicated)
    if (strpos($content, '.env') !== false) {
        $connections[] = [
            'from'  => pathToId('srcs/.env'),
            'to'    => $composeId,
            'label' => 'env_file:',
            'color' => '#8fa07e',
        ];
    }

    // secrets file: directives -> secret files feed into compose
    preg_match_all('/^\s+file:\s*(\S+)/m', $content, $secretMatches);
    foreach ($secretMatches[1] as $secretPath) {
        // Resolve relative to srcs/ (e.g., ../secrets/db_password.txt -> secrets/db_password.txt)
        $parts = explode('/', 'srcs/' . $secretPath);
        $normalized = [];
        foreach ($parts as $part) {
            if ($part === '..') array_pop($normalized);
            elseif ($part !== '.' && $part !== '') $normalized[] = $part;
        }
        $resolvedPath = implode('/', $normalized);
        $connections[] = [
            'from'  => pathToId($resolvedPath),
            'to'    => $composeId,
            'label' => 'secrets:',
            'color' => '#a88a63',
        ];
    }

    return $connections;
}

function parseNginxConf(string $basePath): array {
    $connections = [];
    $confPath = 'srcs/requirements/nginx/conf/nginx.conf';
    $fullPath = $basePath . '/' . $confPath;
    if (!is_file($fullPath)) return $connections;
    $content = file_get_contents($fullPath);
    if (!$content) return $connections;
    $confId = pathToId($confPath);

    // fastcgi_pass -> nginx.conf to www.conf
    if (preg_match('/fastcgi_pass\s+(\w+):(\d+)/', $content, $m)) {
        $wwwConfId = pathToId('srcs/requirements/wordpress/conf/www.conf');
        $connections[] = [
            'from'  => $confId,
            'to'    => $wwwConfId,
            'label' => 'fastcgi_pass :' . $m[2],
            'color' => '#c97764',
        ];
    }

    // ssl_certificate -> cert file feeds into nginx.conf
    if (preg_match('/ssl_certificate\s+(\S+);/', $content, $m)) {
        $certFile = basename($m[1]);
        $connections[] = [
            'from'  => pathToId('srcs/requirements/nginx/tools/' . $certFile),
            'to'    => $confId,
            'label' => 'ssl_certificate',
            'color' => '#a88a63',
        ];
    }
    if (preg_match('/ssl_certificate_key\s+(\S+);/', $content, $m)) {
        $keyFile = basename($m[1]);
        $connections[] = [
            'from'  => pathToId('srcs/requirements/nginx/tools/' . $keyFile),
            'to'    => $confId,
            'label' => 'ssl_certificate_key',
            'color' => '#a88a63',
        ];
    }

    return $connections;
}

function parseShellScripts(string $basePath): array {
    $connections = [];
    $scripts = [
        'srcs/requirements/wordpress/tools/wp_setup.sh',
        'srcs/requirements/mariadb/tools/init_db.sh',
    ];
    $secretMap = [
        'db_password'      => 'secrets/db_password.txt',
        'db_root_password' => 'secrets/db_root_password.txt',
        'credentials'      => 'secrets/credentials.txt',
    ];

    foreach ($scripts as $scriptPath) {
        $fullPath = $basePath . '/' . $scriptPath;
        if (!is_file($fullPath)) continue;
        $content = file_get_contents($fullPath);
        if (!$content) continue;
        $scriptId = pathToId($scriptPath);

        // /run/secrets/<name> references
        preg_match_all('#/run/secrets/(\w+)#', $content, $secretMatches);
        $seen = [];
        foreach ($secretMatches[1] as $secretName) {
            if (isset($seen[$secretName])) continue;
            $seen[$secretName] = true;
            if (isset($secretMap[$secretName])) {
                $connections[] = [
                    'from'  => pathToId($secretMap[$secretName]),
                    'to'    => $scriptId,
                    'label' => '/run/secrets/',
                    'color' => '#a88a63',
                ];
            }
        }

        // wp_setup.sh -> mariadb runtime connection
        if (preg_match('/(?:-h\s+mariadb|dbhost=mariadb)/', $content)) {
            $dbInitId = pathToId('srcs/requirements/mariadb/tools/init_db.sh');
            $connections[] = [
                'from'  => $scriptId,
                'to'    => $dbInitId,
                'label' => 'mysql :3306',
                'color' => '#c97764',
            ];
        }
    }

    return $connections;
}

function parseMakefile(string $basePath): array {
    $connections = [];
    $content = file_get_contents($basePath . '/Makefile');
    if (!$content) return $connections;

    if (preg_match('/docker.*compose.*-f\s+(\S+)/', $content, $m)) {
        $connections[] = [
            'from'  => pathToId('Makefile'),
            'to'    => pathToId($m[1]),
            'label' => 'make up',
            'color' => '#c97764',
        ];
    }
    return $connections;
}

function parseConnections(string $basePath): array {
    return array_merge(
        parseDockerfileCopies($basePath),
        parseComposeFile($basePath),
        parseNginxConf($basePath),
        parseShellScripts($basePath),
        parseMakefile($basePath)
    );
}

// =====================================================================
// ACTION: tree — Dynamic file tree + connections
// =====================================================================
if (isset($_GET['action']) && $_GET['action'] === 'tree') {
    $basePath = '/home/vszpiech/inception';
    $tree = buildTree($basePath);
    $connections = parseConnections($basePath);

    // Validate: drop connections referencing non-existent nodes
    $allIds = extractAllIds($tree);
    $connections = array_values(array_filter($connections, fn($c) =>
        in_array($c['from'], $allIds, true) && in_array($c['to'], $allIds, true)
    ));

    echo json_encode(['tree' => $tree, 'connections' => $connections], JSON_UNESCAPED_SLASHES);
    exit;
}

// --- FILE CONTENT API (for the file tree explorer) ---
if (isset($_GET['action']) && $_GET['action'] === 'file') {
    $basePath = '/home/vszpiech/inception';
    $requestedPath = $_GET['path'] ?? '';

    // Deny sensitive paths
    $deniedPatterns = ['secrets/', '.crt', '.key', '.pem', '.git/'];
    foreach ($deniedPatterns as $pattern) {
        if (strpos($requestedPath, $pattern) !== false) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied to sensitive file']);
            exit;
        }
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
