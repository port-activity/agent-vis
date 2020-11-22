FROM php:7.3-apache

COPY apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY php/php.ini /usr/local/etc/php/php.ini
COPY . /var/www/

RUN test -d /var/www/visout || mkdir /var/www/visout
RUN chown www-data:www-data /var/www/visout
RUN htpasswd -cb /usr/local/etc/users vis vispassword
