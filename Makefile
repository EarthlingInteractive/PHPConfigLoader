.PHONY: run-unit-tests

run-unit-tests: vendor/autoload.php
	vendor/bin/phpsimplertest --bootstrap vendor/autoload.php --colorful-output src/test

vendor/autoload.php: composer.json
	composer install


