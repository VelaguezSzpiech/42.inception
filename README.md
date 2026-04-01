*This project has been created as part of the 42 curriculum by vszpiech.*

# 42.inception

## Description

Inception is a system administration project that sets up a small web infrastructure using Docker containers inside a Virtual Machine. The stack consists of three services — NGINX (reverse proxy with TLS), WordPress with php-fpm, and MariaDB — each running in its own dedicated container, connected via a Docker bridge network.

The project demonstrates containerization principles, secure credential management, and infrastructure-as-code practices.

### Project Description

This project uses **Docker** and **Docker Compose** to orchestrate a multi-container WordPress infrastructure. Each service is built from a custom Dockerfile based on Debian Bookworm (penultimate stable). No pre-built images from DockerHub are used.

**Sources included:**
- Custom NGINX configuration with TLS 1.2/1.3 enforcement
- MariaDB initialization scripts for automated database setup
- WordPress + php-fpm configuration with WP-CLI for automated installation
- Docker Compose orchestration with named volumes, secrets, and bridge networking

### Design Choices

**Virtual Machines vs Docker:**
- Virtual Machines provide full OS isolation with their own kernel, offering stronger security boundaries but consuming more resources (RAM, CPU, disk). Each VM runs a complete operating system.
- Docker containers share the host kernel and isolate processes using namespaces and cgroups. They are lightweight, start in seconds, and use minimal resources. However, they provide weaker isolation than VMs since they share the kernel.
- In this project, the VM provides the base environment, while Docker containers run the individual services efficiently within it.

**Secrets vs Environment Variables:**
- Environment variables (`.env` files) store non-sensitive configuration like domain names, database names, and usernames.
- Docker Secrets store sensitive data (passwords, API keys) and are mounted as files in `/run/secrets/` inside containers. They are never exposed in environment variables, logs, or Docker inspect output.
- This separation follows the principle of least privilege and prevents accidental credential exposure.

**Docker Network vs Host Network:**
- Host networking (`network: host`) shares the host's network stack directly. While simpler, it removes network isolation between containers and the host, creates port conflicts, and is explicitly forbidden by the project subject.
- Docker bridge networking creates an isolated virtual network where containers communicate by service name (DNS resolution). Only explicitly published ports (443 for NGINX) are accessible from outside the network.

**Docker Volumes vs Bind Mounts:**
- Bind mounts map a specific host directory into a container. They depend on the host filesystem structure and can introduce permission issues.
- Docker named volumes are managed by Docker, appear in `docker volume ls`, and can be backed up, migrated, and managed independently. They provide better portability and lifecycle management.
- This project uses named volumes with `driver_opts` to store data at `/home/vszpiech/data/`, satisfying both the named volume requirement and the specific host path requirement.

## Instructions

### Prerequisites

- VirtualBox 7.x installed
- The VM must be running Debian 12 (Bookworm) with Docker and Docker Compose installed

### Installation and Execution

1. Clone the repository into the VM
2. Run the bootstrap script to generate secrets, the SSL certificate, and the `.env` file:
   ```bash
   make bootstrap
   ```
3. Build and start:
   ```bash
   make
   ```
4. Access the site at `https://vszpiech.42.fr`

## Resources

- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [NGINX Documentation](https://nginx.org/en/docs/)
- [WordPress CLI Documentation](https://developer.wordpress.org/cli/commands/)
- [MariaDB Documentation](https://mariadb.com/kb/en/documentation/)
- [Dockerfile Best Practices](https://docs.docker.com/develop/develop-images/dockerfile_best-practices/)

**AI Usage:** AI was used to assist with generating configuration file templates, debugging Docker networking issues, and explaining TLS/SSL concepts. All generated content was reviewed, tested, and adapted to meet the specific project requirements.
