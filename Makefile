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

.PHONY: all bootstrap up down stop start clean fclean re
