FROM debian:bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    php8.2-fpm php8.2-mysql php8.2-curl php8.2-gd php8.2-intl \
    php8.2-mbstring php8.2-xml php8.2-zip \
    ca-certificates curl mariadb-client openssl && rm -rf /var/lib/apt/lists/*

RUN curl -o /usr/local/bin/wp \
    https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x /usr/local/bin/wp

# Docker CLI for live dashboard API
RUN install -m 0755 -d /etc/apt/keyrings \
    && curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc \
    && chmod a+r /etc/apt/keyrings/docker.asc \
    && echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/debian bookworm stable" > /etc/apt/sources.list.d/docker.list \
    && apt-get update && apt-get install -y --no-install-recommends docker-ce-cli \
    && rm -rf /var/lib/apt/lists/*

COPY conf/www.conf /etc/php/8.2/fpm/pool.d/www.conf
COPY tools/wp_setup.sh /usr/local/bin/wp_setup.sh
RUN chmod +x /usr/local/bin/wp_setup.sh

COPY theme/ /opt/inception-theme/

RUN mkdir -p /var/www/html /run/php

WORKDIR /var/www/html

EXPOSE 9000

ENTRYPOINT ["wp_setup.sh"]
