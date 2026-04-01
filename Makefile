DATA_DIR = /home/vszpiech/data
DOCKER = ./scripts/docker_cmd.sh

all: up

bootstrap:
	bash ./scripts/bootstrap_vm.sh

up: $(DATA_DIR)/wordpress $(DATA_DIR)/mariadb
	$(DOCKER) compose -f srcs/docker-compose.yml up -d --build

$(DATA_DIR)/%:
	mkdir -p $@

down:
	$(DOCKER) compose -f srcs/docker-compose.yml down

stop:
	$(DOCKER) compose -f srcs/docker-compose.yml stop

start:
	$(DOCKER) compose -f srcs/docker-compose.yml start

clean: down
	$(DOCKER) system prune -af
	sudo rm -rf $(DATA_DIR)/wordpress/* $(DATA_DIR)/mariadb/*

fclean: clean
	VOLUMES="$$($(DOCKER) volume ls -q)"; [ -z "$$VOLUMES" ] || $(DOCKER) volume rm $$VOLUMES
	sudo rm -rf $(DATA_DIR)

re: fclean all

# ── Evaluation helpers ──

SECRETS = ./secrets
DB_PW = $(shell cat $(SECRETS)/db_password.txt)
DB_ROOT_PW = $(shell cat $(SECRETS)/db_root_password.txt)

dbuser:
	@echo "=== MariaDB: wp_user session ==="
	@echo "--- TABLES ---"
	@$(DOCKER) exec mariadb mysql -t -u wp_user -p"$(DB_PW)" wordpress_db -e "SHOW TABLES;"
	@echo ""
	@echo "--- WP USERS ---"
	@$(DOCKER) exec mariadb mysql -t -u wp_user -p"$(DB_PW)" wordpress_db -e "SELECT user_login, user_email, ID FROM wp_users;"

dbroot:
	@echo "=== MariaDB: root session ==="
	@echo "--- DATABASES ---"
	@$(DOCKER) exec mariadb mysql -t -u root -p"$(DB_ROOT_PW)" -e "SHOW DATABASES;"
	@echo ""
	@echo "--- MYSQL USERS ---"
	@$(DOCKER) exec mariadb mysql -t -u root -p"$(DB_ROOT_PW)" -e "SELECT User, Host FROM mysql.user;"
	@echo ""
	@echo "--- GRANTS FOR wp_user ---"
	@$(DOCKER) exec mariadb mysql -t -u root -p"$(DB_ROOT_PW)" -e "SHOW GRANTS FOR 'wp_user'@'%';"

containers:
	@$(DOCKER) ps -a --format "table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}"

check:
	@echo "=== Containers ==="
	@$(DOCKER) compose -f srcs/docker-compose.yml ps
	@echo ""
	@echo "=== WordPress Users ==="
	@$(DOCKER) exec wordpress wp user list --allow-root --fields=user_login,roles
	@echo ""
	@echo "=== Secrets (mounted, not in env) ==="
	@echo "-- wordpress:" && $(DOCKER) exec wordpress ls /run/secrets/
	@echo "-- mariadb:" && $(DOCKER) exec mariadb ls /run/secrets/
	@echo ""
	@echo "=== No passwords in env ==="
	@$(DOCKER) exec wordpress env | grep -i password && echo "FAIL: password in env!" || echo "PASS: no passwords in env"
	@echo ""
	@echo "=== Volumes ==="
	@$(DOCKER) volume ls
	@echo ""
	@echo "=== Network ==="
	@$(DOCKER) network ls | grep inception
	@echo ""
	@echo "=== PID 1 ==="
	@echo "nginx:     $$($(DOCKER) exec nginx cat /proc/1/cmdline | tr '\0' ' ')"
	@echo "wordpress: $$($(DOCKER) exec wordpress cat /proc/1/cmdline | tr '\0' ' ')"
	@echo "mariadb:   $$($(DOCKER) exec mariadb cat /proc/1/cmdline | tr '\0' ' ')"
	@echo ""
	@echo "=== TLS ==="
	@echo | openssl s_client -connect vszpiech.42.fr:443 -tls1_2 2>/dev/null | grep "Protocol  :" || true
	@echo Q | openssl s_client -connect vszpiech.42.fr:443 -tls1_3 2>/dev/null | grep "Protocol  :" || true

.PHONY: all bootstrap up down stop start clean fclean re dbuser dbroot containers check
