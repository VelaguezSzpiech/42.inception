#!/bin/bash

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${REPO_ROOT}/srcs/.env"
SECRETS_DIR="${REPO_ROOT}/secrets"
CERT_DIR="${REPO_ROOT}/srcs/requirements/nginx/tools"
DATA_DIR="/home/vszpiech/data"
BOOTSTRAP_USER="${SUDO_USER:-$USER}"

if [ "$(id -u)" -eq 0 ]; then
    SUDO_CMD=""
elif command -v sudo >/dev/null 2>&1 && sudo -n true 2>/dev/null; then
    SUDO_CMD="sudo"
else
    echo "sudo not available or not configured — bootstrapping now..."
    echo "Enter root password:"
    su -c "apt-get install -y sudo && echo '${BOOTSTRAP_USER} ALL=(ALL) NOPASSWD: ALL' > /etc/sudoers.d/${BOOTSTRAP_USER} && chmod 440 /etc/sudoers.d/${BOOTSTRAP_USER}" root
    SUDO_CMD="sudo"
fi

run_root() {
    if [ -n "${SUDO_CMD}" ]; then
        "${SUDO_CMD}" "$@"
    else
        "$@"
    fi
}

if [ ! -f "${ENV_FILE}" ]; then
    echo "Missing ${ENV_FILE}" >&2
    exit 1
fi

set -a
. "${ENV_FILE}"
set +a

: "${DOMAIN_NAME:?DOMAIN_NAME must be set in srcs/.env}"
: "${WP_ADMIN_USER:?WP_ADMIN_USER must be set in srcs/.env}"

need_cmd() {
    command -v "$1" >/dev/null 2>&1
}

ensure_package_basics() {
    run_root apt-get update -qq
    run_root apt-get install -y -qq ca-certificates curl openssl gnupg
}

ensure_docker_repo() {
    run_root install -m 0755 -d /etc/apt/keyrings

    if [ ! -f /etc/apt/keyrings/docker.asc ]; then
        run_root curl -fsSL https://download.docker.com/linux/debian/gpg \
            -o /etc/apt/keyrings/docker.asc
        run_root chmod a+r /etc/apt/keyrings/docker.asc
    fi

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

ensure_docker() {
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

ensure_docker_group() {
    run_root groupadd docker 2>/dev/null || true

    if ! id -nG "${BOOTSTRAP_USER}" | tr ' ' '\n' | grep -qx docker; then
        run_root usermod -aG docker "${BOOTSTRAP_USER}"
        echo "Added ${BOOTSTRAP_USER} to docker group."
        echo "A new login shell is normally required, but make will use sudo docker until then."
    fi
}

ensure_hosts_entry() {
    if ! grep -Eq "[[:space:]]${DOMAIN_NAME}([[:space:]]|\$)" /etc/hosts; then
        echo "127.0.0.1 ${DOMAIN_NAME}" | run_root tee -a /etc/hosts >/dev/null
    fi
}

ensure_data_dirs() {
    run_root mkdir -p "${DATA_DIR}/wordpress" "${DATA_DIR}/mariadb"
    run_root chown -R "${BOOTSTRAP_USER}:${BOOTSTRAP_USER}" "${DATA_DIR}"
}

random_secret() {
    openssl rand -base64 16 | tr -d '\n'
}

ensure_secrets() {
    mkdir -p "${SECRETS_DIR}"

    if [ ! -f "${SECRETS_DIR}/db_password.txt" ]; then
        random_secret > "${SECRETS_DIR}/db_password.txt"
    fi

    if [ ! -f "${SECRETS_DIR}/db_root_password.txt" ]; then
        random_secret > "${SECRETS_DIR}/db_root_password.txt"
    fi

    if [ ! -f "${SECRETS_DIR}/credentials.txt" ]; then
        {
            printf '%s\n' "${WP_ADMIN_USER}"
            random_secret
        } > "${SECRETS_DIR}/credentials.txt"
    fi

    chmod 600 "${SECRETS_DIR}/db_password.txt" \
        "${SECRETS_DIR}/db_root_password.txt" \
        "${SECRETS_DIR}/credentials.txt"
}

ensure_certificate() {
    local crt key subject

    mkdir -p "${CERT_DIR}"
    crt="${CERT_DIR}/${DOMAIN_NAME}.crt"
    key="${CERT_DIR}/${DOMAIN_NAME}.key"
    subject="/C=DE/ST=Niedersachsen/L=Wolfsburg/O=42/CN=${DOMAIN_NAME}"

    if [ ! -f "${crt}" ] || [ ! -f "${key}" ]; then
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
            -keyout "${key}" \
            -out "${crt}" \
            -subj "${subject}"
        chmod 600 "${key}"
    fi
}

ensure_guest_additions() {
    if lsmod | grep -q vboxguest; then
        return
    fi

    run_root apt-get install -y -qq linux-headers-"$(uname -r)" build-essential dkms

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

main() {
    ensure_package_basics
    ensure_guest_additions
    ensure_docker
    ensure_docker_group
    ensure_hosts_entry
    ensure_data_dirs
    ensure_secrets
    ensure_certificate

    echo "Bootstrap complete."
}

main "$@"
