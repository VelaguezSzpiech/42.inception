#!/bin/bash

# Grant www-data access to Docker socket for live dashboard API
if [ -S /var/run/docker.sock ]; then
    DOCKER_GID=$(stat -c '%g' /var/run/docker.sock)
    groupadd -g "$DOCKER_GID" dockersock 2>/dev/null || true
    usermod -aG dockersock www-data 2>/dev/null || true
fi

DB_PASSWORD=$(cat /run/secrets/db_password)
WP_ADMIN_PASSWORD=$(tail -1 /run/secrets/credentials)
WP_ADMIN_USER_FROM_SECRET=$(head -1 /run/secrets/credentials)

until mysqladmin -h mariadb -u "${MYSQL_USER}" -p"${DB_PASSWORD}" ping --silent 2>/dev/null; do
    echo "Waiting for MariaDB..."
    sleep 2
done

cd /var/www/html

if [ ! -f wp-config.php ]; then
    wp core download --version=6.4.3 --allow-root

    wp config create \
        --dbname="${MYSQL_DATABASE}" \
        --dbuser="${MYSQL_USER}" \
        --dbpass="${DB_PASSWORD}" \
        --dbhost=mariadb:3306 \
        --allow-root

    wp core install \
        --url="https://${DOMAIN_NAME}" \
        --title="${WP_TITLE}" \
        --admin_user="${WP_ADMIN_USER_FROM_SECRET}" \
        --admin_password="${WP_ADMIN_PASSWORD}" \
        --admin_email="${WP_ADMIN_EMAIL}" \
        --allow-root

    wp user create "${WP_USER}" "${WP_USER_EMAIL}" \
        --role=editor \
        --user_pass="$(openssl rand -base64 12)" \
        --allow-root

    # Install custom theme
    cp -r /opt/inception-theme /var/www/html/wp-content/themes/inception-theme
    wp theme activate inception-theme --allow-root
    wp option update show_on_front page --allow-root
    wp option update page_on_front 2 --allow-root

    chown -R www-data:www-data /var/www/html
fi

# Always refresh theme from image (so rebuilds pick up changes)
cp -r /opt/inception-theme/* /var/www/html/wp-content/themes/inception-theme/ 2>/dev/null || \
    cp -r /opt/inception-theme /var/www/html/wp-content/themes/inception-theme
chown -R www-data:www-data /var/www/html/wp-content/themes/inception-theme

exec php-fpm8.2 --nodaemonize
