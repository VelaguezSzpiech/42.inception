<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php bloginfo('name'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
    <?php include get_stylesheet_directory() . '/style.css'; ?>
    </style>
    <?php wp_head(); ?>
</head>
<body>

<!-- ===================== HERO ===================== -->
<section class="hero">
    <div class="hero-content">
        <h1>Inception</h1>
        <p class="subtitle">A containerized WordPress infrastructure, built from scratch</p>
        <a href="<?php echo esc_url(admin_url()); ?>" class="btn">Open Dashboard</a>
        <p class="quote">"One container is not enough &mdash; we need to go deeper."</p>
    </div>
</section>

<!-- ================= INTERACTIVE ARCHITECTURE — FILE TREE ================= -->
<div class="section-dark">
    <div class="section">
        <h2>System <span class="hl">Architecture</span></h2>
        <p class="section-sub">Explore the project file tree. Click directories to expand, files to view source. Animated lines show how config files reference each other.</p>

        <div class="filetree-toolbar">
            <button class="filetree-expand-btn" id="expandAllBtn" onclick="toggleExpandAll()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                <span id="expandAllLabel">Expand All</span>
            </button>
        </div>

        <div class="filetree-wrapper" id="filetreeWrapper">
            <svg class="filetree-connections" id="filetreeConnections"></svg>
            <div class="filetree" id="filetree"></div>
        </div>

        <div class="filetree-legend">
            <span class="filetree-legend__item"><span class="filetree-legend__dot" style="background:#c97764"></span>Build / Network</span>
            <span class="filetree-legend__item"><span class="filetree-legend__dot" style="background:#a89880"></span>COPY / Build Context</span>
            <span class="filetree-legend__item"><span class="filetree-legend__dot" style="background:#8fa07e"></span>Environment Vars</span>
            <span class="filetree-legend__item"><span class="filetree-legend__dot" style="background:#a88a63"></span>Secrets / Credentials</span>
        </div>

        <div class="graph-detail" id="file-detail-panel">
            <div class="graph-detail__header">
                <span id="fdp-icon">&#x1F4C4;</span> <span id="fdp-title">Select a file...</span>
                <button class="graph-detail__close" onclick="closeFileDetail()">&times;</button>
            </div>
            <div class="graph-detail__body" id="fdp-body">
                <div class="graph-detail__loading">Click any file in the tree to view its source code.</div>
            </div>
        </div>

    </div>
</div>


<!-- =================== COMMENTS =================== -->
<div class="section-dark">
    <div class="section">
        <h2>Leave a <span class="hl">Comment</span></h2>
        <p class="section-sub">No login required</p>
        <div class="card">
            <?php
            global $post;
            if ($post && comments_open($post->ID)) {
                comments_template();
            } else {
                // Fallback: show comments for the front page post
                $front_id = get_option('page_on_front');
                if ($front_id) {
                    $GLOBALS['post'] = get_post($front_id);
                    setup_postdata($GLOBALS['post']);
                    comments_template();
                    wp_reset_postdata();
                }
            }
            ?>
        </div>
    </div>
</div>

<!-- =================== FOOTER =================== -->
<div class="footer">
    <p>Built with <span class="heart">&hearts;</span> by <strong>vszpiech</strong> &mdash; 42 Wolfsburg</p>
    <p style="margin-top: 0.5rem; opacity: 0.5;">Inception &middot; System Administration &middot; Docker &middot; 2026</p>
</div>

<script>
(function() {
    var apiUrl = window.location.protocol + '//' + window.location.host + '/wp-content/themes/inception-theme/api-live.php';
    var fileCache = {};
    var panel = document.getElementById('file-detail-panel');
    var svg = document.getElementById('filetreeConnections');
    var wrapper = document.getElementById('filetreeWrapper');
    var treeEl = document.getElementById('filetree');

    /* ========== PROJECT FILE TREE DATA ========== */
    var TREE_DATA = {
        name: 'inception', type: 'dir', expanded: true, id: 'root', children: [
            { name: 'Makefile', type: 'file', id: 'makefile', filePath: 'Makefile', desc: 'build automation' },
            { name: 'srcs/', type: 'dir', expanded: true, id: 'srcs', children: [
                { name: 'docker-compose.yml', type: 'file', id: 'docker-compose', filePath: 'srcs/docker-compose.yml', desc: 'orchestration' },
                { name: '.env', type: 'file', id: 'env-file', filePath: 'srcs/.env', desc: 'environment variables' },
                { name: 'requirements/', type: 'dir', expanded: true, id: 'requirements', children: [
                    { name: 'nginx/', type: 'dir', expanded: false, id: 'nginx-dir', children: [
                        { name: 'Dockerfile', type: 'file', id: 'nginx-dockerfile', filePath: 'srcs/requirements/nginx/Dockerfile', desc: 'container image' },
                        { name: 'conf/', type: 'dir', expanded: false, id: 'nginx-conf-dir', children: [
                            { name: 'nginx.conf', type: 'file', id: 'nginx-conf', filePath: 'srcs/requirements/nginx/conf/nginx.conf', desc: 'TLS + reverse proxy' }
                        ]},
                        { name: 'tools/', type: 'dir', expanded: false, id: 'nginx-tools', children: [
                            { name: 'vszpiech.42.fr.crt', type: 'file', id: 'nginx-crt', filePath: null, desc: 'SSL certificate' },
                            { name: 'vszpiech.42.fr.key', type: 'file', id: 'nginx-key', filePath: null, desc: 'SSL private key' }
                        ]}
                    ]},
                    { name: 'wordpress/', type: 'dir', expanded: false, id: 'wp-dir', children: [
                        { name: 'Dockerfile', type: 'file', id: 'wp-dockerfile', filePath: 'srcs/requirements/wordpress/Dockerfile', desc: 'container image' },
                        { name: 'conf/', type: 'dir', expanded: false, id: 'wp-conf-dir', children: [
                            { name: 'www.conf', type: 'file', id: 'wp-wwwconf', filePath: 'srcs/requirements/wordpress/conf/www.conf', desc: 'PHP-FPM pool :9000' }
                        ]},
                        { name: 'tools/', type: 'dir', expanded: false, id: 'wp-tools', children: [
                            { name: 'wp_setup.sh', type: 'file', id: 'wp-setup', filePath: 'srcs/requirements/wordpress/tools/wp_setup.sh', desc: 'WP init script' }
                        ]},
                        { name: 'theme/', type: 'dir', expanded: false, id: 'wp-theme-dir', children: [
                            { name: 'front-page.php', type: 'file', id: 'wp-frontpage', filePath: 'srcs/requirements/wordpress/theme/front-page.php', desc: 'main template' },
                            { name: 'style.css', type: 'file', id: 'wp-style', filePath: 'srcs/requirements/wordpress/theme/style.css', desc: 'theme styles' },
                            { name: 'api-live.php', type: 'file', id: 'wp-api', filePath: 'srcs/requirements/wordpress/theme/api-live.php', desc: 'live data API' },
                            { name: 'functions.php', type: 'file', id: 'wp-functions', filePath: 'srcs/requirements/wordpress/theme/functions.php', desc: 'theme setup' },
                            { name: 'index.php', type: 'file', id: 'wp-index', filePath: 'srcs/requirements/wordpress/theme/index.php', desc: 'fallback' }
                        ]}
                    ]},
                    { name: 'mariadb/', type: 'dir', expanded: false, id: 'db-dir', children: [
                        { name: 'Dockerfile', type: 'file', id: 'db-dockerfile', filePath: 'srcs/requirements/mariadb/Dockerfile', desc: 'container image' },
                        { name: 'conf/', type: 'dir', expanded: false, id: 'db-conf-dir', children: [
                            { name: '50-server.cnf', type: 'file', id: 'db-cnf', filePath: 'srcs/requirements/mariadb/conf/50-server.cnf', desc: 'MariaDB config' }
                        ]},
                        { name: 'tools/', type: 'dir', expanded: false, id: 'db-tools', children: [
                            { name: 'init_db.sh', type: 'file', id: 'db-init', filePath: 'srcs/requirements/mariadb/tools/init_db.sh', desc: 'DB init script' }
                        ]}
                    ]}
                ]}
            ]},
            { name: 'secrets/', type: 'dir', expanded: false, id: 'secrets-dir', children: [
                { name: 'db_password.txt', type: 'file', id: 'secret-dbpw', filePath: null, desc: 'database password' },
                { name: 'db_root_password.txt', type: 'file', id: 'secret-dbrootpw', filePath: null, desc: 'root password' },
                { name: 'credentials.txt', type: 'file', id: 'secret-creds', filePath: null, desc: 'WP admin credentials' }
            ]}
        ]
    };

    /* ========== DATA FLOW CONNECTIONS ==========
     * Arrows show realistic data flow direction:
     *   BUILD: Makefile → compose → Dockerfiles → COPY source files
     *   CONFIG: .env → compose, secrets → compose → containers
     *   RUNTIME: nginx → wordpress:9000, wordpress → mariadb:3306
     *   SECRETS: secret files → mounted into → entrypoint scripts
     */
    var CONNECTIONS = [
        // ── Build pipeline (Makefile triggers compose, compose triggers Dockerfile builds) ──
        { from: 'makefile',         to: 'docker-compose',   label: 'make up',              color: '#c97764' },
        { from: 'docker-compose',   to: 'nginx-dockerfile', label: 'build:',               color: '#a89880' },
        { from: 'docker-compose',   to: 'wp-dockerfile',    label: 'build:',               color: '#a89880' },
        { from: 'docker-compose',   to: 'db-dockerfile',    label: 'build:',               color: '#a89880' },

        // ── Config flows INTO compose (compose reads these) ──
        { from: 'env-file',         to: 'docker-compose',   label: 'env_file:',            color: '#8fa07e' },
        { from: 'secret-dbpw',      to: 'docker-compose',   label: 'secrets:',             color: '#a88a63' },
        { from: 'secret-dbrootpw',  to: 'docker-compose',   label: 'secrets:',             color: '#a88a63' },
        { from: 'secret-creds',     to: 'docker-compose',   label: 'secrets:',             color: '#a88a63' },

        // ── Dockerfile COPY (source files flow INTO the image at build time) ──
        { from: 'nginx-conf',       to: 'nginx-dockerfile', label: 'COPY',                 color: '#a89880' },
        { from: 'nginx-crt',        to: 'nginx-dockerfile', label: 'COPY',                 color: '#a89880' },
        { from: 'nginx-key',        to: 'nginx-dockerfile', label: 'COPY',                 color: '#a89880' },
        { from: 'wp-wwwconf',       to: 'wp-dockerfile',    label: 'COPY',                 color: '#a89880' },
        { from: 'wp-setup',         to: 'wp-dockerfile',    label: 'COPY',                 color: '#a89880' },
        { from: 'wp-theme-dir',     to: 'wp-dockerfile',    label: 'COPY theme/',          color: '#a89880' },
        { from: 'db-cnf',           to: 'db-dockerfile',    label: 'COPY',                 color: '#a89880' },
        { from: 'db-init',          to: 'db-dockerfile',    label: 'COPY',                 color: '#a89880' },

        // ── Runtime: secrets mounted into containers, read by entrypoints ──
        { from: 'secret-dbpw',      to: 'wp-setup',         label: '/run/secrets/',        color: '#a88a63' },
        { from: 'secret-creds',     to: 'wp-setup',         label: '/run/secrets/',        color: '#a88a63' },
        { from: 'secret-dbrootpw',  to: 'db-init',          label: '/run/secrets/',        color: '#a88a63' },
        { from: 'secret-dbpw',      to: 'db-init',          label: '/run/secrets/',        color: '#a88a63' },

        // ── Runtime: network traffic between services ──
        { from: 'nginx-conf',       to: 'wp-wwwconf',       label: 'fastcgi_pass :9000',   color: '#c97764' },
        { from: 'wp-setup',         to: 'db-init',          label: 'mysql :3306',          color: '#c97764' },

        // ── Runtime: nginx loads SSL certs ──
        { from: 'nginx-crt',        to: 'nginx-conf',       label: 'ssl_certificate',      color: '#a88a63' },
        { from: 'nginx-key',        to: 'nginx-conf',       label: 'ssl_certificate_key',  color: '#a88a63' }
    ];

    /* ========== HELPERS ========== */
    function esc(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

    /* ========== RENDER TREE ========== */
    function renderTree(node, container) {
        if (node.type === 'dir') {
            var row = document.createElement('div');
            row.className = 'filetree-node filetree-dir' + (node.expanded ? ' expanded' : '');
            row.setAttribute('data-node-id', node.id);
            row.innerHTML = '<span class="filetree-chevron">&#x25B6;</span>'
                + '<span class="filetree-icon">&#x1F4C1;</span>'
                + '<span class="filetree-name">' + esc(node.name) + '</span>';

            var children = document.createElement('div');
            children.className = 'filetree-children' + (node.expanded ? ' open' : '');

            row.addEventListener('click', function(e) {
                e.stopPropagation();
                var isExpanded = row.classList.contains('expanded');
                if (isExpanded) {
                    row.classList.remove('expanded');
                    children.classList.remove('open');
                    children.style.maxHeight = children.scrollHeight + 'px';
                    requestAnimationFrame(function() { children.style.maxHeight = '0'; });
                } else {
                    row.classList.add('expanded');
                    children.classList.add('open');
                    children.style.maxHeight = children.scrollHeight + 'px';
                    setTimeout(function() { children.style.maxHeight = '3000px'; }, 400);
                }
                setTimeout(drawConnections, 420);
            });

            row.addEventListener('mouseenter', function() { highlightConnections(node.id); });
            row.addEventListener('mouseleave', clearConnectionHighlights);

            container.appendChild(row);
            if (node.children) {
                node.children.forEach(function(child) { renderTree(child, children); });
            }
            container.appendChild(children);
        } else {
            var fileRow = document.createElement('div');
            fileRow.className = 'filetree-node filetree-file';
            fileRow.setAttribute('data-node-id', node.id);

            var icon = '&#x1F4C4;';
            var name = node.name;
            if (/Dockerfile/.test(name)) icon = '&#x1F40B;';
            else if (/\.sh$/.test(name)) icon = '&#x2699;';
            else if (/\.yml$/.test(name)) icon = '&#x1F4E6;';
            else if (/\.php$/.test(name)) icon = '&#x1F418;';
            else if (/\.css$/.test(name)) icon = '&#x1F3A8;';
            else if (/\.conf$|\.cnf$/.test(name)) icon = '&#x2699;';
            else if (/\.crt$|\.key$/.test(name)) icon = '&#x1F512;';
            else if (/\.txt$/.test(name)) icon = '&#x1F510;';
            else if (/\.env/.test(name)) icon = '&#x1F30D;';
            else if (/Makefile/.test(name)) icon = '&#x1F527;';

            fileRow.innerHTML = '<span class="filetree-chevron" style="visibility:hidden">&#x25B6;</span>'
                + '<span class="filetree-icon">' + icon + '</span>'
                + '<span class="filetree-name">' + esc(name) + '</span>'
                + (node.desc ? '<span class="filetree-desc">' + esc(node.desc) + '</span>' : '');

            fileRow.addEventListener('click', function(e) {
                e.stopPropagation();
                showFileContent(node.id, node.filePath, node.name);
            });

            fileRow.addEventListener('mouseenter', function() { highlightConnections(node.id); });
            fileRow.addEventListener('mouseleave', clearConnectionHighlights);

            container.appendChild(fileRow);
        }
    }

    /* ========== FILE CONTENT VIEWER ========== */
    function showFileContent(fileId, filePath, fileName) {
        document.querySelectorAll('.filetree-node').forEach(function(n) { n.classList.remove('active'); });
        var el = document.querySelector('[data-node-id="' + fileId + '"]');
        if (el) el.classList.add('active');

        document.getElementById('fdp-title').textContent = fileName;
        panel.classList.add('open');

        if (!filePath) {
            document.getElementById('fdp-body').innerHTML =
                '<div class="graph-detail__loading" style="color:#a88a63">&#x1F512; This file contains sensitive data and is not displayed.</div>';
            setTimeout(function() { panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }, 80);
            return;
        }

        if (fileCache[filePath]) {
            renderFileContent(fileCache[filePath], fileName);
            return;
        }

        document.getElementById('fdp-body').innerHTML =
            '<div class="graph-detail__loading"><div class="live-spinner"></div>Loading file...</div>';

        fetch(apiUrl + '?action=file&path=' + encodeURIComponent(filePath))
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.error) {
                    document.getElementById('fdp-body').innerHTML =
                        '<div class="graph-detail__loading" style="color:#c97764">' + esc(d.error) + '</div>';
                    return;
                }
                fileCache[filePath] = d;
                renderFileContent(d, fileName);
            })
            .catch(function(e) {
                document.getElementById('fdp-body').innerHTML =
                    '<div class="graph-detail__loading" style="color:#c97764">Failed: ' + esc(e.message) + '</div>';
            });
    }

    function renderFileContent(data, fileName) {
        var highlighted = highlightSyntax(data.content, fileName);
        var lines = highlighted.split('\n');
        var numbered = lines.map(function(line, i) {
            var num = '<span class="hl-comment" style="opacity:0.4;user-select:none">' + String(i + 1).padStart(3, ' ') + ' </span>';
            return num + line;
        }).join('\n');
        document.getElementById('fdp-body').innerHTML =
            '<div style="margin-bottom:0.5rem">'
            + '<span style="color:var(--text-muted);font-size:0.78rem">' + esc(data.path) + '</span>'
            + '<span style="color:var(--text-muted);font-size:0.72rem;margin-left:1rem;opacity:0.5">'
            + data.size + ' bytes &middot; ' + esc(data.modified) + '</span></div>'
            + '<pre class="live-pre" style="max-height:500px">' + numbered + '</pre>';
        setTimeout(function() { panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }, 80);
    }

    /* ========== SYNTAX HIGHLIGHTING ========== */
    function highlightSyntax(code, fileName) {
        var text = esc(code);
        var ext = fileName.toLowerCase();

        if (/dockerfile/i.test(ext)) {
            text = text.replace(/(#.*)/gm, '<span class="hl-comment">$1</span>');
            text = text.replace(/^(FROM|RUN|COPY|EXPOSE|CMD|ENTRYPOINT|WORKDIR|ENV|ARG|ADD|LABEL|USER|VOLUME|SHELL)\b/gm, '<span class="hl-directive">$1</span>');
            text = text.replace(/(&quot;[^&]*?&quot;)/g, '<span class="hl-string">$1</span>');
        } else if (/\.sh$/.test(ext)) {
            text = text.replace(/(#!\/bin\/bash)/g, '<span class="hl-directive">$1</span>');
            text = text.replace(/(#(?!!\/bin).*)/gm, '<span class="hl-comment">$1</span>');
            text = text.replace(/\b(if|then|else|fi|while|do|done|for|in|case|esac|function|local|export|exec|exit|return|echo|cd|mkdir|chown|chmod|cat|cp|rm|ls|sleep|until)\b/g, '<span class="hl-keyword">$1</span>');
            text = text.replace(/(\$\{[^}]+\}|\$[A-Za-z_][A-Za-z0-9_]*)/g, '<span class="hl-variable">$1</span>');
            text = text.replace(/(&quot;[^&]*?&quot;)/g, '<span class="hl-string">$1</span>');
        } else if (/\.yml$|\.yaml$/.test(ext)) {
            text = text.replace(/(#.*)/gm, '<span class="hl-comment">$1</span>');
            text = text.replace(/^(\s*[\w.-]+)(:)/gm, '<span class="hl-section">$1</span>$2');
            text = text.replace(/(&quot;[^&]*?&quot;)/g, '<span class="hl-string">$1</span>');
        } else if (/\.php$/.test(ext)) {
            text = text.replace(/(\/\/.*)/gm, '<span class="hl-comment">$1</span>');
            text = text.replace(/(&lt;\?php|&lt;\?|\?&gt;)/g, '<span class="hl-directive">$1</span>');
            text = text.replace(/\b(function|return|if|else|elseif|foreach|while|for|echo|include|require|require_once|include_once|class|new|public|private|protected|static|var|isset|exit|null|true|false)\b/g, '<span class="hl-keyword">$1</span>');
            text = text.replace(/(\$[A-Za-z_][A-Za-z0-9_]*)/g, '<span class="hl-variable">$1</span>');
            text = text.replace(/(&#39;[^&]*?&#39;)/g, '<span class="hl-string">$1</span>');
        } else if (/\.conf$|\.cnf$/.test(ext)) {
            text = text.replace(/(#.*)/gm, '<span class="hl-comment">$1</span>');
            text = text.replace(/^(\[.+?\])/gm, '<span class="hl-section">$1</span>');
            text = text.replace(/^(\s*[a-z_.-]+)(\s*=)/gm, '<span class="hl-directive">$1</span>$2');
        } else if (/makefile/i.test(ext)) {
            text = text.replace(/(#.*)/gm, '<span class="hl-comment">$1</span>');
            text = text.replace(/^([a-zA-Z_][a-zA-Z0-9_.-]*)\s*(:)/gm, '<span class="hl-section">$1</span>$2');
            text = text.replace(/(\$\([^)]+\)|\$\$\([^)]+\))/g, '<span class="hl-variable">$1</span>');
            text = text.replace(/\b(\.PHONY)\b/g, '<span class="hl-directive">$1</span>');
        } else if (/\.env/.test(ext)) {
            text = text.replace(/(#.*)/gm, '<span class="hl-comment">$1</span>');
            text = text.replace(/^([A-Z_]+)(=)/gm, '<span class="hl-variable">$1</span>$2');
        }

        return text;
    }

    /* ========== SVG CONNECTION DRAWING ========== */
    function drawConnections() {
        svg.innerHTML = '';
        var wRect = wrapper.getBoundingClientRect();
        var svgNS = 'http://www.w3.org/2000/svg';

        CONNECTIONS.forEach(function(conn, idx) {
            var fromEl = document.querySelector('[data-node-id="' + conn.from + '"]');
            var toEl = document.querySelector('[data-node-id="' + conn.to + '"]');
            if (!fromEl || !toEl) return;
            if (!isVisible(fromEl) || !isVisible(toEl)) return;

            var fRect = fromEl.getBoundingClientRect();
            var tRect = toEl.getBoundingClientRect();

            var x1 = fRect.left - wRect.left;
            var y1 = fRect.top + fRect.height / 2 - wRect.top;
            var x2 = tRect.left - wRect.left;
            var y2 = tRect.top + tRect.height / 2 - wRect.top;

            var minX = Math.min(x1, x2);
            var dx = Math.min(Math.abs(y2 - y1) * 0.15 + 30, minX - 12);
            dx = Math.max(dx, 15);
            var d = 'M ' + x1 + ',' + y1 + ' C ' + (x1 - dx) + ',' + y1 + ' ' + (x2 - dx) + ',' + y2 + ' ' + x2 + ',' + y2;

            var pathId = 'conn-' + idx;
            var path = document.createElementNS(svgNS, 'path');
            path.setAttribute('d', d);
            path.setAttribute('stroke', conn.color);
            path.setAttribute('id', pathId);
            path.setAttribute('data-from', conn.from);
            path.setAttribute('data-to', conn.to);
            svg.appendChild(path);

            var circle = document.createElementNS(svgNS, 'circle');
            circle.setAttribute('r', '3');
            circle.setAttribute('fill', conn.color);
            circle.setAttribute('opacity', '0.7');
            var anim = document.createElementNS(svgNS, 'animateMotion');
            anim.setAttribute('dur', (2.5 + (idx % 5) * 0.4) + 's');
            anim.setAttribute('repeatCount', 'indefinite');
            anim.setAttribute('begin', (idx * 0.3) + 's');
            var mpath = document.createElementNS(svgNS, 'mpath');
            mpath.setAttributeNS('http://www.w3.org/1999/xlink', 'href', '#' + pathId);
            anim.appendChild(mpath);
            circle.appendChild(anim);
            svg.appendChild(circle);
        });

        svg.setAttribute('viewBox', '0 0 ' + wrapper.offsetWidth + ' ' + wrapper.offsetHeight);
        svg.style.width = wrapper.offsetWidth + 'px';
        svg.style.height = wrapper.offsetHeight + 'px';
    }

    function isVisible(el) {
        var parent = el.parentElement;
        while (parent && parent !== wrapper) {
            if (parent.classList.contains('filetree-children') && !parent.classList.contains('open')) return false;
            parent = parent.parentElement;
        }
        return true;
    }

    /* ========== CONNECTION HOVER HIGHLIGHTS ========== */
    function highlightConnections(nodeId) {
        svg.querySelectorAll('path').forEach(function(p) {
            if (p.getAttribute('data-from') === nodeId || p.getAttribute('data-to') === nodeId) {
                p.classList.add('conn-active');
                p.classList.remove('conn-dimmed');
            } else {
                p.classList.add('conn-dimmed');
                p.classList.remove('conn-active');
            }
        });
    }

    function clearConnectionHighlights() {
        svg.querySelectorAll('path').forEach(function(p) {
            p.classList.remove('conn-active', 'conn-dimmed');
        });
    }

    /* ========== CLOSE DETAIL PANEL ========== */
    window.closeFileDetail = function() {
        panel.classList.remove('open');
        document.querySelectorAll('.filetree-node').forEach(function(n) { n.classList.remove('active'); });
    };

    /* ========== INIT ========== */
    renderTree(TREE_DATA, treeEl);
    setTimeout(drawConnections, 100);

    var resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(drawConnections, 150);
    });

    if (typeof ResizeObserver !== 'undefined') {
        new ResizeObserver(function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(drawConnections, 150);
        }).observe(wrapper);
    }
    // ---- Expand All / Collapse All ----
    var allExpanded = false;
    window.toggleExpandAll = function() {
        allExpanded = !allExpanded;
        var dirs = wrapper.querySelectorAll('.filetree-node.filetree-dir');
        var childDivs = wrapper.querySelectorAll('.filetree-children');

        dirs.forEach(function(dir) {
            if (allExpanded) {
                dir.classList.add('expanded');
            } else {
                // Keep root and srcs expanded
                var nodeId = dir.getAttribute('data-node-id');
                if (nodeId === 'root' || nodeId === 'srcs' || nodeId === 'requirements') {
                    dir.classList.add('expanded');
                } else {
                    dir.classList.remove('expanded');
                }
            }
        });

        childDivs.forEach(function(ch) {
            if (allExpanded) {
                ch.classList.add('open');
                ch.style.maxHeight = '3000px';
            } else {
                var prevSib = ch.previousElementSibling;
                if (prevSib && prevSib.classList.contains('expanded')) {
                    ch.classList.add('open');
                    ch.style.maxHeight = '3000px';
                } else {
                    ch.classList.remove('open');
                    ch.style.maxHeight = '0';
                }
            }
        });

        var btn = document.getElementById('expandAllBtn');
        var label = document.getElementById('expandAllLabel');
        var svg = btn.querySelector('svg');
        label.textContent = allExpanded ? 'Collapse All' : 'Expand All';
        svg.style.transform = allExpanded ? 'rotate(180deg)' : '';

        setTimeout(drawConnections, 450);
    };
})();
</script>
<?php wp_footer(); ?>
</body>
</html>
