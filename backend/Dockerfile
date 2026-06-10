# PHP 8.3 (CLI) als Basis – wir nutzen den eingebauten Webserver (php -S).
# Kein Apache/Nginx nötig: ideal für eine kleine REST-API in der Entwicklung.
FROM php:8.3-cli

# pdo_mysql: PDO-Treiber für MySQL. Im Basis-Image NICHT enthalten, daher
# nachinstallieren. PDO + Prepared Statements sind unser Datenbankzugriff.
RUN docker-php-ext-install pdo_mysql

WORKDIR /var/www/html

# Der Startbefehl (php -S ...) steht in docker-compose.yml, damit er an einer
# Stelle sichtbar ist. Der Quellcode wird per Volume eingehängt.
