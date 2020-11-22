install:
	composer install

run:
	php -S localhost:8000 -t src/public

run_rtz_file_read:
	php src/lib/run_rtz_file_read.php $(outdir) $(rtzinfile)

test:
	./vendor/bin/phpunit --coverage-html coverage --whitelist src --bootstrap tests/tests_init.php  tests/

lint:
	vendor/bin/phpcs --standard=PSR2 src tests

fix:
	vendor/bin/phpcbf --standard=PSR2 src tests

ci-install-dependencies:
	apt update
	apt install -y wget git zip unzip
	wget https://getcomposer.org/installer
	php installer
	php composer.phar install

ci: ci-install-dependencies lint test
