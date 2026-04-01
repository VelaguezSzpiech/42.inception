# User Documentation

## Services Provided

This stack provides a WordPress website accessible via HTTPS:

- **NGINX**: Reverse proxy handling TLS termination on port 443
- **WordPress**: Content management system with php-fpm
- **MariaDB**: Database backend for WordPress

## Starting and Stopping

### Start the project
```bash
make
```

### Stop the project (containers stop, data preserved)
```bash
make down
```

### Restart the project
```bash
make start
```

### Full reset (removes all data and images)
```bash
make fclean
```

### Rebuild from scratch
```bash
make re
```

## Accessing the Website

1. Ensure `vszpiech.42.fr` resolves to `127.0.0.1` in `/etc/hosts`
2. Open a browser and navigate to: `https://vszpiech.42.fr`
3. Accept the self-signed certificate warning

### WordPress Administration Panel

- URL: `https://vszpiech.42.fr/wp-admin`
- Admin username: found in `secrets/credentials.txt` (first line)
- Admin password: found in `secrets/credentials.txt` (second line)

## Managing Credentials

All sensitive credentials are stored in the `secrets/` directory:

| File | Contents |
|------|----------|
| `secrets/db_password.txt` | MariaDB user password |
| `secrets/db_root_password.txt` | MariaDB root password |
| `secrets/credentials.txt` | WordPress admin username (line 1) and password (line 2) |

These files have `600` permissions and should never be committed to git.

## Checking Service Status

```bash
# View running containers
make containers

# Or directly:
docker compose -f srcs/docker-compose.yml ps

# View logs
docker compose -f srcs/docker-compose.yml logs -f

# View logs for a specific service
docker compose -f srcs/docker-compose.yml logs -f nginx
docker compose -f srcs/docker-compose.yml logs -f wordpress
docker compose -f srcs/docker-compose.yml logs -f mariadb
```

## Verifying Services

```bash
# Test HTTPS access
curl -kL https://vszpiech.42.fr

# Test TLS version
openssl s_client -connect vszpiech.42.fr:443 -tls1_2 </dev/null

# Test database connection
docker exec mariadb mysqladmin -u wp_user -p"$(cat secrets/db_password.txt)" ping
```
