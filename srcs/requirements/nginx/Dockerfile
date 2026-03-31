FROM debian:bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx openssl && rm -rf /var/lib/apt/lists/*

COPY conf/nginx.conf /etc/nginx/nginx.conf
COPY tools/vszpiech.42.fr.crt /etc/ssl/certs/
COPY tools/vszpiech.42.fr.key /etc/ssl/private/

EXPOSE 443

CMD ["nginx", "-g", "daemon off;"]
