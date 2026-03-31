DATA_DIR = /home/vszpiech/data

all: up

up: $(DATA_DIR)/wordpress $(DATA_DIR)/mariadb
	docker compose -f srcs/docker-compose.yml up -d --build

$(DATA_DIR)/%:
	mkdir -p $@

down:
	docker compose -f srcs/docker-compose.yml down

stop:
	docker compose -f srcs/docker-compose.yml stop

start:
	docker compose -f srcs/docker-compose.yml start

clean: down
	docker system prune -af
	sudo rm -rf $(DATA_DIR)/wordpress/* $(DATA_DIR)/mariadb/*

fclean: clean
	docker volume rm $$(docker volume ls -q) 2>/dev/null || true
	sudo rm -rf $(DATA_DIR)

re: fclean all

.PHONY: all up down stop start clean fclean re
