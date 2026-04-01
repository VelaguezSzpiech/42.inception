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
	@echo '> docker exec mariadb mysql -t -u wp_user -p"***" wordpress_db -e "SHOW TABLES;"'
	@$(DOCKER) exec mariadb mysql -t -u wp_user -p"$(DB_PW)" wordpress_db -e "SHOW TABLES;"
	@echo ""
	@echo "--- WP USERS ---"
	@echo '> docker exec mariadb mysql -t -u wp_user -p"***" wordpress_db -e "SELECT user_login, user_email, ID FROM wp_users;"'
	@$(DOCKER) exec mariadb mysql -t -u wp_user -p"$(DB_PW)" wordpress_db -e "SELECT user_login, user_email, ID FROM wp_users;"

dbroot:
	@echo "=== MariaDB: root session ==="
	@echo "--- DATABASES ---"
	@echo '> docker exec mariadb mysql -t -u root -p"***" -e "SHOW DATABASES;"'
	@$(DOCKER) exec mariadb mysql -t -u root -p"$(DB_ROOT_PW)" -e "SHOW DATABASES;"
	@echo ""
	@echo "--- MYSQL USERS ---"
	@echo '> docker exec mariadb mysql -t -u root -p"***" -e "SELECT User, Host FROM mysql.user;"'
	@$(DOCKER) exec mariadb mysql -t -u root -p"$(DB_ROOT_PW)" -e "SELECT User, Host FROM mysql.user;"
	@echo ""
	@echo "--- GRANTS FOR wp_user ---"
	@echo '> docker exec mariadb mysql -t -u root -p"***" -e "SHOW GRANTS FOR wp_user@%;"'
	@$(DOCKER) exec mariadb mysql -t -u root -p"$(DB_ROOT_PW)" -e "SHOW GRANTS FOR 'wp_user'@'%';"

containers:
	@echo '> docker ps -a'
	@$(DOCKER) ps -a --format "table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}"

check:
	@echo "=== Containers ==="
	@echo '> docker compose -f srcs/docker-compose.yml ps'
	@$(DOCKER) compose -f srcs/docker-compose.yml ps
	@echo ""
	@echo "=== WordPress Users ==="
	@echo '> docker exec wordpress wp user list --allow-root --fields=user_login,roles'
	@$(DOCKER) exec wordpress wp user list --allow-root --fields=user_login,roles
	@echo ""
	@echo "=== Secrets (mounted, not in env) ==="
	@echo '> docker exec wordpress ls /run/secrets/'
	@echo "-- wordpress:" && $(DOCKER) exec wordpress ls /run/secrets/
	@echo '> docker exec mariadb ls /run/secrets/'
	@echo "-- mariadb:" && $(DOCKER) exec mariadb ls /run/secrets/
	@echo ""
	@echo "=== No passwords in env ==="
	@echo '> docker exec wordpress env | grep -i password'
	@$(DOCKER) exec wordpress env | grep -i password && echo "FAIL: password in env!" || echo "PASS: no passwords in env"
	@echo ""
	@echo "=== Volumes ==="
	@echo '> docker volume ls'
	@$(DOCKER) volume ls
	@echo ""
	@echo "=== Network ==="
	@echo '> docker network ls | grep inception'
	@$(DOCKER) network ls | grep inception
	@echo ""
	@echo "=== PID 1 ==="
	@echo '> docker exec <container> cat /proc/1/cmdline'
	@echo "nginx:     $$($(DOCKER) exec nginx cat /proc/1/cmdline | tr '\0' ' ')"
	@echo "wordpress: $$($(DOCKER) exec wordpress cat /proc/1/cmdline | tr '\0' ' ')"
	@echo "mariadb:   $$($(DOCKER) exec mariadb cat /proc/1/cmdline | tr '\0' ' ')"
	@echo ""
	@echo "=== TLS ==="
	@echo '> openssl s_client -connect vszpiech.42.fr:443 -tls1_2'
	@echo | openssl s_client -connect vszpiech.42.fr:443 -tls1_2 2>/dev/null | grep "Protocol  :" || true
	@echo '> openssl s_client -connect vszpiech.42.fr:443 -tls1_3'
	@echo Q | openssl s_client -connect vszpiech.42.fr:443 -tls1_3 2>/dev/null | grep "Protocol  :" || true

checkloop:
	@echo "=== Forbidden patterns (should be empty) ==="
	@echo '> grep -rE "tail -f|sleep infinity|while true|network: host|--link|links:" srcs/'
	@grep -rE "tail -f|sleep infinity|while true|network: host|--link|links:" srcs/ && echo "FAIL: forbidden pattern found!" || echo "PASS: no forbidden patterns"

checktls:
	@echo "=== TLS 1.2 (should work) ==="
	@echo '> echo | openssl s_client -connect vszpiech.42.fr:443 -tls1_2'
	@echo | openssl s_client -connect vszpiech.42.fr:443 -tls1_2 2>/dev/null | grep "Protocol  :" || echo "FAIL"
	@echo ""
	@echo "=== TLS 1.3 (should work) ==="
	@echo '> echo Q | openssl s_client -connect vszpiech.42.fr:443 -tls1_3'
	@echo Q | openssl s_client -connect vszpiech.42.fr:443 -tls1_3 2>/dev/null | grep "Protocol  :" || echo "FAIL"
	@echo ""
	@echo "=== TLS 1.1 (should be rejected) ==="
	@echo '> echo | openssl s_client -connect vszpiech.42.fr:443 -tls1_1'
	@echo | openssl s_client -connect vszpiech.42.fr:443 -tls1_1 2>&1 | grep -i "alert protocol version" && echo "PASS: TLS 1.1 rejected" || echo "FAIL: TLS 1.1 not rejected"

usergen:
	@echo "=== Generating sample comments ==="
	@$(DOCKER) exec wordpress wp comment create --comment_post_ID=2 \
		--comment_author="Alice" --comment_content="Great infrastructure setup! The Docker network isolation is really clean." \
		--comment_approved=1 --allow-root
	@$(DOCKER) exec wordpress wp comment create --comment_post_ID=2 \
		--comment_author="Bob" --comment_content="How does the TLS termination work with the reverse proxy?" \
		--comment_approved=1 --allow-root
	@$(DOCKER) exec wordpress wp comment create --comment_post_ID=2 \
		--comment_author="vszpiech_boss" --comment_content="TLS is terminated at NGINX, which forwards plain FastCGI to WordPress on port 9000. Only TLSv1.2 and v1.3 are accepted." \
		--comment_approved=1 --allow-root
	@$(DOCKER) exec wordpress wp comment create --comment_post_ID=2 \
		--comment_author="Charlie" --comment_content="Nice that secrets are mounted at runtime instead of baked into the images." \
		--comment_approved=1 --allow-root
	@$(DOCKER) exec wordpress wp comment create --comment_post_ID=2 \
		--comment_author="vszpiech_boss" --comment_content="Exactly, all credentials live in /run/secrets/ inside the containers. Nothing in the Dockerfiles or environment." \
		--comment_approved=1 --allow-root
	@$(DOCKER) exec wordpress wp comment create --comment_post_ID=2 \
		--comment_author="Diana" --comment_content="Does data persist after a reboot?" \
		--comment_approved=1 --allow-root
	@$(DOCKER) exec wordpress wp comment create --comment_post_ID=2 \
		--comment_author="Bob" --comment_content="Yes, named volumes map to /home/vszpiech/data/ on the host. Everything survives reboots and container restarts." \
		--comment_approved=1 --allow-root
	@$(DOCKER) exec wordpress wp comment create --comment_post_ID=2 \
		--comment_author="Eve" --comment_content="Love the custom theme. The file tree with animated connections is a nice touch." \
		--comment_approved=1 --allow-root
	@$(DOCKER) exec wordpress wp comment create --comment_post_ID=2 \
		--comment_author="Charlie" --comment_content="What happens if a container crashes?" \
		--comment_approved=1 --allow-root
	@$(DOCKER) exec wordpress wp comment create --comment_post_ID=2 \
		--comment_author="vszpiech_boss" --comment_content="All services have restart: always and run their daemon as PID 1, so Docker restarts them automatically." \
		--comment_approved=1 --allow-root
	@echo "=== Done: 10 comments generated ==="

.PHONY: all bootstrap up down stop start clean fclean re dbuser dbroot containers check checkloop checktls usergen
