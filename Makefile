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
	@echo ""
	@echo "=== Database: wp_comments ==="
	@echo '> SELECT * FROM wp_comments'
	@$(DOCKER) exec mariadb mysql -t -u wp_user -p"$(DB_PW)" wordpress_db -e "SELECT comment_ID AS ID, comment_parent AS Re, comment_author AS Author, comment_author_email AS Email, comment_author_IP AS IP, LEFT(comment_content, 50) AS Content, comment_date_gmt AS Date FROM wp_comments ORDER BY comment_ID;"

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

comments:
	@echo "=== Database: wp_comments ==="
	@$(DOCKER) exec mariadb mysql -t -u wp_user -p"$(DB_PW)" wordpress_db -e "SELECT comment_ID AS ID, comment_parent AS Re, comment_author AS Author, comment_author_email AS Email, comment_author_IP AS IP, LEFT(comment_content, 50) AS Content, comment_date_gmt AS Date FROM wp_comments ORDER BY comment_ID;"

usergen:
	@echo "=== Generating comment threads ==="
	@POST=2; WP="$(DOCKER) exec wordpress wp --allow-root"; \
	\
	C=$$($$WP comment create --comment_post_ID=$$POST --comment_author="dev_noodle_42" \
		--comment_content="Just mass-renamed a variable across 47 files. Felt like a god for about 3 seconds until the tests failed." \
		--comment_approved=1 --porcelain); \
	R=$$($$WP comment create --comment_post_ID=$$POST --comment_parent=$$C --comment_author="segfault_steve" \
		--comment_content="The real fun is when find-and-replace turns your 'count' variable into 'acCOUNTing' in every comment." \
		--comment_approved=1 --porcelain); \
	$$WP comment create --comment_post_ID=$$POST --comment_parent=$$R --comment_author="printf_princess" \
		--comment_content="Happened to me with a variable called 'id'. My CSS had 'gridentity' everywhere." \
		--comment_approved=1 --porcelain > /dev/null; \
	$$WP comment create --comment_post_ID=$$POST --comment_parent=$$C --comment_author="404girlfriend" \
		--comment_content="Pro tip: use word-boundary matching. Learned that the hard way when I renamed 'do' to 'execute' and broke every single do-while loop." \
		--comment_approved=1 --porcelain > /dev/null; \
	\
	C=$$($$WP comment create --comment_post_ID=$$POST --comment_author="mass_git_push" \
		--comment_content="Spent 6 hours debugging a Docker container. The issue was a trailing space in my .env file." \
		--comment_approved=1 --porcelain); \
	R=$$($$WP comment create --comment_post_ID=$$POST --comment_parent=$$C --comment_author="chmod_777" \
		--comment_content="Invisible characters are the final boss of programming." \
		--comment_approved=1 --porcelain); \
	$$WP comment create --comment_post_ID=$$POST --comment_parent=$$R --comment_author="kernel_panic_at_the_disco" \
		--comment_content="Once spent an entire day because of a Unicode non-breaking space that looked exactly like a regular space. My terminal lied to me." \
		--comment_approved=1 --porcelain > /dev/null; \
	$$WP comment create --comment_post_ID=$$POST --comment_parent=$$C --comment_author="dev_noodle_42" \
		--comment_content="The .env file is just a txt file with trust issues." \
		--comment_approved=1 --porcelain > /dev/null; \
	\
	C=$$($$WP comment create --comment_post_ID=$$POST --comment_author="printf_princess" \
		--comment_content="TIL git blame is not for assigning blame, it is for finding out who to buy coffee for because their code saved the project 3 years ago." \
		--comment_approved=1 --porcelain); \
	$$WP comment create --comment_post_ID=$$POST --comment_parent=$$C --comment_author="mass_git_push" \
		--comment_content="git blame -> git appreciate -> git buy-a-drink" \
		--comment_approved=1 --porcelain > /dev/null; \
	R=$$($$WP comment create --comment_post_ID=$$POST --comment_parent=$$C --comment_author="sudo_make_sandwich" \
		--comment_content="In my experience git blame is for finding out that I am the person who wrote the terrible code 6 months ago." \
		--comment_approved=1 --porcelain); \
	$$WP comment create --comment_post_ID=$$POST --comment_parent=$$R --comment_author="segfault_steve" \
		--comment_content="Past me is my worst enemy. Present me is writing code for future me to hate. The circle of life." \
		--comment_approved=1 --porcelain > /dev/null; \
	\
	C=$$($$WP comment create --comment_post_ID=$$POST --comment_author="sudo_make_sandwich" \
		--comment_content="Hot take: writing Dockerfiles from scratch is actually fun once you stop copy-pasting from Stack Overflow." \
		--comment_approved=1 --porcelain); \
	R=$$($$WP comment create --comment_post_ID=$$POST --comment_parent=$$C --comment_author="404girlfriend" \
		--comment_content="Bold of you to assume I have ever stopped." \
		--comment_approved=1 --porcelain); \
	$$WP comment create --comment_post_ID=$$POST --comment_parent=$$R --comment_author="chmod_777" \
		--comment_content="Stack Overflow is just my rubber duck that actually talks back." \
		--comment_approved=1 --porcelain > /dev/null; \
	$$WP comment create --comment_post_ID=$$POST --comment_parent=$$C --comment_author="kernel_panic_at_the_disco" \
		--comment_content="Once you understand what each layer does it clicks. The problem is the 47 failed builds before the click." \
		--comment_approved=1 --porcelain > /dev/null; \
	\
	C=$$($$WP comment create --comment_post_ID=$$POST --comment_author="segfault_steve" \
		--comment_content="Normalize talking to your code out loud. I just told my nginx config it was doing great and it finally worked." \
		--comment_approved=1 --porcelain); \
	$$WP comment create --comment_post_ID=$$POST --comment_parent=$$C --comment_author="printf_princess" \
		--comment_content="Rubber duck debugging but you ARE the duck." \
		--comment_approved=1 --porcelain > /dev/null; \
	R=$$($$WP comment create --comment_post_ID=$$POST --comment_parent=$$C --comment_author="dev_noodle_42" \
		--comment_content="I threatened my code with a full rewrite and suddenly the bug fixed itself. Coincidence? I think not." \
		--comment_approved=1 --porcelain); \
	$$WP comment create --comment_post_ID=$$POST --comment_parent=$$R --comment_author="sudo_make_sandwich" \
		--comment_content="The code knows. It can smell fear and rewrites." \
		--comment_approved=1 --porcelain > /dev/null; \
	\
	C=$$($$WP comment create --comment_post_ID=$$POST --comment_author="kernel_panic_at_the_disco" \
		--comment_content="The 5 stages of debugging: 1) That is impossible. 2) That should not happen. 3) How does that even work? 4) Oh. 5) OH NO." \
		--comment_approved=1 --porcelain); \
	$$WP comment create --comment_post_ID=$$POST --comment_parent=$$C --comment_author="chmod_777" \
		--comment_content="You forgot stage 6: works on my machine." \
		--comment_approved=1 --porcelain > /dev/null; \
	$$WP comment create --comment_post_ID=$$POST --comment_parent=$$C --comment_author="mass_git_push" \
		--comment_content="Stage 7: ship it inside a container so it is always your machine." \
		--comment_approved=1 --porcelain > /dev/null; \
	R=$$($$WP comment create --comment_post_ID=$$POST --comment_parent=$$C --comment_author="404girlfriend" \
		--comment_content="Stage 4 is always a missing semicolon or a typo in a config path. Always." \
		--comment_approved=1 --porcelain); \
	$$WP comment create --comment_post_ID=$$POST --comment_parent=$$R --comment_author="segfault_steve" \
		--comment_content="Or a port number. I once spent 4 hours because I had 9000 in one file and 9001 in another." \
		--comment_approved=1 --porcelain > /dev/null; \
	\
	echo "=== Done: 6 threads, 30 comments ==="

.PHONY: all bootstrap up down stop start clean fclean re dbuser dbroot containers check checkloop checktls comments usergen
