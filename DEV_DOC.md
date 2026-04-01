# Developer Documentation

## Setting Up the Environment from Scratch

### Prerequisites

- VirtualBox 7.x
- A Debian 12 (Bookworm) virtual machine with:
  - Docker Engine (`docker-ce`)
  - Docker Compose plugin (`docker-compose-plugin`)
  - `make`, `openssl`, `git`

### Install Docker inside the VM

```bash
sudo apt-get update
sudo apt-get install -y ca-certificates curl gnupg lsb-release git make openssl

sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/debian/gpg | \
  sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg

echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/debian $(lsb_release -cs) stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io \
  docker-buildx-plugin docker-compose-plugin

sudo usermod -aG docker $USER
# Log out and back in for group change to take effect
```

### Configure Domain Resolution

```bash
echo "127.0.0.1 vszpiech.42.fr" | sudo tee -a /etc/hosts
```

### Generate Secrets

```bash
mkdir -p secrets
openssl rand -base64 16 | tr -d '\n' > secrets/db_password.txt
openssl rand -base64 16 | tr -d '\n' > secrets/db_root_password.txt
printf 'vszpiech_boss\n' > secrets/credentials.txt
openssl rand -base64 16 | tr -d '\n' >> secrets/credentials.txt
chmod 600 secrets/*
```

### Generate SSL Certificate

```bash
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout srcs/requirements/nginx/tools/vszpiech.42.fr.key \
  -out srcs/requirements/nginx/tools/vszpiech.42.fr.crt \
  -subj "/C=DE/ST=Niedersachsen/L=Wolfsburg/O=42/CN=vszpiech.42.fr"
```

## Building and Launching

```bash
# Build all images and start containers
make

# Or equivalently:
docker compose -f srcs/docker-compose.yml up -d --build
```

## Managing Containers and Volumes

```bash
# Stop containers (preserves data)
make down

# Full cleanup (removes images, volumes, data)
make fclean

# Rebuild from scratch
make re

# View container status
docker compose -f srcs/docker-compose.yml ps

# View logs (follow mode)
docker compose -f srcs/docker-compose.yml logs -f

# Execute a command inside a container
docker exec -it nginx bash
docker exec -it wordpress bash
docker exec -it mariadb bash

# List volumes
docker volume ls

# Inspect a volume
docker volume inspect srcs_wordpress_files
docker volume inspect srcs_mariadb_data
```

## Data Storage and Persistence

### Volume Locations

| Volume | Container Path | Host Path |
|--------|---------------|-----------|
| `wordpress_files` | `/var/www/html` | `/home/vszpiech/data/wordpress` |
| `mariadb_data` | `/var/lib/mysql` | `/home/vszpiech/data/mariadb` |

### How Persistence Works

- Docker named volumes with `driver_opts` map to host directories under `/home/vszpiech/data/`
- Data survives container restarts and `make down` / `make up` cycles
- `make clean` removes volume contents but preserves the directories
- `make fclean` removes everything including the directories

### Architecture

```
Port 443 (HTTPS)
    │
    ▼
┌────────┐     ┌───────────┐     ┌─────────┐
│ NGINX  │────▶│ WordPress │────▶│ MariaDB │
│ :443   │     │ php-fpm   │     │ :3306   │
│        │     │ :9000     │     │         │
└────────┘     └───────────┘     └─────────┘
    │               │                 │
    ▼               ▼                 ▼
[wordpress_files] [wordpress_files] [mariadb_data]
(read-only)       (read-write)      (read-write)
```

All containers are connected via the `inception_network` Docker bridge network. Only NGINX exposes a port to the host (443).
