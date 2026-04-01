#!/bin/bash

# Exit on any error, unset variable, or pipe failure
set -euo pipefail

# --- Path configuration ---
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${REPO_ROOT}/srcs/.env"
SECRETS_DIR="${REPO_ROOT}/secrets"
CERT_DIR="${REPO_ROOT}/srcs/requirements/nginx/tools"
DATA_DIR="/home/vszpiech/data"
BOOTSTRAP_USER="${SUDO_USER:-$USER}"

# --- Determine how to run commands as root ---
if [ "$(id -u)" -eq 0 ]; then
    SUDO_CMD=""
elif command -v sudo >/dev/null 2>&1 && sudo -n true 2>/dev/null; then
    SUDO_CMD="sudo"
else
    # No sudo available — install it via su and configure passwordless access
    echo "sudo not available or not configured — bootstrapping now..."
    echo "Enter root password:"
    su -c "apt-get install -y sudo && echo '${BOOTSTRAP_USER} ALL=(ALL) NOPASSWD: ALL' > /etc/sudoers.d/${BOOTSTRAP_USER} && chmod 440 /etc/sudoers.d/${BOOTSTRAP_USER}" root
    SUDO_CMD="sudo"
fi

# Helper: run a command as root (uses sudo if needed)
run_root() {
    if [ -n "${SUDO_CMD}" ]; then
        "${SUDO_CMD}" "$@"
    else
        "$@"
    fi
}

# --- Generate .env file if it doesn't exist ---
ensure_env_file() {
    if [ -f "${ENV_FILE}" ]; then
        echo ".env already exists, skipping generation."
        return
    fi

    echo "Generating ${ENV_FILE} ..."
    local login
    login="$(whoami)"

    cat > "${ENV_FILE}" <<ENVEOF
DOMAIN_NAME=${login}.42.fr
# MYSQL SETUP
MYSQL_DATABASE=wordpress_db
MYSQL_USER=wp_user
# WORDPRESS SETUP
WP_TITLE=Inception
WP_ADMIN_USER=${login}_boss
WP_ADMIN_EMAIL=${login}@student.42wolfsburg.de
WP_USER=${login}_editor
WP_USER_EMAIL=editor@${login}.42.fr
ENVEOF

    echo "Created ${ENV_FILE}"
}

ensure_env_file

# Load .env variables into the current shell
set -a
. "${ENV_FILE}"
set +a

# Verify required variables are set
: "${DOMAIN_NAME:?DOMAIN_NAME must be set in srcs/.env}"
: "${WP_ADMIN_USER:?WP_ADMIN_USER must be set in srcs/.env}"

# Helper: check if a command exists
need_cmd() {
    command -v "$1" >/dev/null 2>&1
}

# --- Install essential system packages ---
ensure_package_basics() {
    run_root apt-get update -qq
    run_root apt-get install -y -qq ca-certificates curl openssl gnupg
}

# --- Add Docker's official apt repository ---
ensure_docker_repo() {
    run_root install -m 0755 -d /etc/apt/keyrings

    # Download Docker's GPG key
    if [ ! -f /etc/apt/keyrings/docker.asc ]; then
        run_root curl -fsSL https://download.docker.com/linux/debian/gpg \
            -o /etc/apt/keyrings/docker.asc
        run_root chmod a+r /etc/apt/keyrings/docker.asc
    fi

    # Add the Docker apt source
    if [ ! -f /etc/apt/sources.list.d/docker.sources ]; then
        codename="$(. /etc/os-release && echo "${VERSION_CODENAME}")"
        run_root tee /etc/apt/sources.list.d/docker.sources >/dev/null <<EOF
Types: deb
URIs: https://download.docker.com/linux/debian
Suites: ${codename}
Components: stable
Signed-By: /etc/apt/keyrings/docker.asc
EOF
    fi
}

# --- Install Docker Engine and Compose plugin ---
ensure_docker() {
    # Skip if already installed
    if need_cmd docker && docker compose version >/dev/null 2>&1; then
        return
    fi

    ensure_package_basics
    ensure_docker_repo

    run_root apt-get update -qq
    run_root apt-get install -y -qq \
        docker-ce \
        docker-ce-cli \
        containerd.io \
        docker-buildx-plugin \
        docker-compose-plugin

    run_root systemctl enable --now docker
}

# --- Add user to the docker group so they can run docker without sudo ---
ensure_docker_group() {
    run_root groupadd docker 2>/dev/null || true

    if ! id -nG "${BOOTSTRAP_USER}" | tr ' ' '\n' | grep -qx docker; then
        run_root usermod -aG docker "${BOOTSTRAP_USER}"
        echo "Added ${BOOTSTRAP_USER} to docker group."
        echo "A new login shell is normally required, but make will use sudo docker until then."
    fi
}

# --- Add domain name to /etc/hosts so it resolves to localhost ---
ensure_hosts_entry() {
    if ! grep -Eq "[[:space:]]${DOMAIN_NAME}([[:space:]]|\$)" /etc/hosts; then
        echo "127.0.0.1 ${DOMAIN_NAME}" | run_root tee -a /etc/hosts >/dev/null
    fi
}

# --- Create host directories for Docker volume data ---
ensure_data_dirs() {
    run_root mkdir -p "${DATA_DIR}/wordpress" "${DATA_DIR}/mariadb"
    run_root chown -R "${BOOTSTRAP_USER}:${BOOTSTRAP_USER}" "${DATA_DIR}"
}

# Helper: generate a random 16-byte base64 password
random_secret() {
    openssl rand -base64 16 | tr -d '\n'
}

# --- Generate secret files for Docker secrets (skip if they already exist) ---
ensure_secrets() {
    mkdir -p "${SECRETS_DIR}"

    if [ ! -f "${SECRETS_DIR}/db_password.txt" ]; then
        echo "Generating db_password.txt ..."
        random_secret > "${SECRETS_DIR}/db_password.txt"
    else
        echo "db_password.txt already exists, skipping."
    fi

    if [ ! -f "${SECRETS_DIR}/db_root_password.txt" ]; then
        echo "Generating db_root_password.txt ..."
        random_secret > "${SECRETS_DIR}/db_root_password.txt"
    else
        echo "db_root_password.txt already exists, skipping."
    fi

    if [ ! -f "${SECRETS_DIR}/credentials.txt" ]; then
        echo "Generating credentials.txt ..."
        {
            printf '%s\n' "${WP_ADMIN_USER}"
            random_secret
        } > "${SECRETS_DIR}/credentials.txt"
    else
        echo "credentials.txt already exists, skipping."
    fi

    # Lock down permissions — only owner can read
    chmod 600 "${SECRETS_DIR}/db_password.txt" \
        "${SECRETS_DIR}/db_root_password.txt" \
        "${SECRETS_DIR}/credentials.txt"
}

# --- Generate a self-signed SSL certificate for NGINX ---
ensure_certificate() {
    local crt key subject

    mkdir -p "${CERT_DIR}"
    crt="${CERT_DIR}/${DOMAIN_NAME}.crt"
    key="${CERT_DIR}/${DOMAIN_NAME}.key"
    subject="/C=DE/ST=Niedersachsen/L=Wolfsburg/O=42/CN=${DOMAIN_NAME}"

    if [ ! -f "${crt}" ] || [ ! -f "${key}" ]; then
        echo "Generating SSL certificate and key for ${DOMAIN_NAME} ..."
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
            -keyout "${key}" \
            -out "${crt}" \
            -subj "${subject}"
        chmod 600 "${key}"
    else
        echo "SSL certificate and key already exist, skipping."
    fi
}

# --- Install VirtualBox Guest Additions (for shared folders, etc.) ---
ensure_guest_additions() {
    # Skip if already loaded
    if lsmod | grep -q vboxguest; then
        return
    fi

    run_root apt-get install -y -qq linux-headers-"$(uname -r)" build-essential dkms

    # Try mounting from CD, fall back to apt packages
    if [ -b /dev/cdrom ]; then
        local mnt="/mnt/vbox-ga"
        run_root mkdir -p "${mnt}"
        run_root mount /dev/cdrom "${mnt}" 2>/dev/null || true
        if [ -f "${mnt}/VBoxLinuxAdditions.run" ]; then
            run_root "${mnt}/VBoxLinuxAdditions.run" --nox11 || true
            run_root umount "${mnt}" 2>/dev/null || true
        fi
    else
        run_root apt-get install -y -qq virtualbox-guest-utils virtualbox-guest-x11 2>/dev/null || \
            echo "Guest Additions ISO not mounted. Attach it in VirtualBox settings and re-run."
    fi
}

# --- Run all bootstrap steps in order ---
main() {
    echo "==> Installing base packages ..."
    ensure_package_basics
    echo "==> Checking Guest Additions ..."
    ensure_guest_additions
    echo "==> Ensuring Docker is installed ..."
    ensure_docker
    echo "==> Configuring docker group ..."
    ensure_docker_group
    echo "==> Ensuring /etc/hosts entry ..."
    ensure_hosts_entry
    echo "==> Creating data directories ..."
    ensure_data_dirs
    echo "==> Setting up secrets ..."
    ensure_secrets
    echo "==> Setting up SSL certificate ..."
    ensure_certificate

    echo "Bootstrap complete."
}

main "$@"
