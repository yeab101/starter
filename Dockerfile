# Cloud image for the Student Registration System (e.g. AletCloud).
# PHP + Apache; the MySQL database is provided by the host through
# DB_HOST / DB_PORT / DB_NAME / DB_USER / DB_PASSWORD environment variables.
FROM php:8.3-apache

RUN docker-php-ext-install mysqli \
 && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
 && a2enconf servername \
 && { echo "expose_php = Off"; echo "display_errors = Off"; echo "log_errors = On"; \
      echo "session.cookie_httponly = 1"; echo "date.timezone = Africa/Addis_Ababa"; } \
    > /usr/local/etc/php/conf.d/srms.ini \
 && printf '%s\n' \
      '<Directory /var/www/html/includes>' '    Require all denied' '</Directory>' \
      '<FilesMatch "\.(sql|md|ps1|bat)$">' '    Require all denied' '</FilesMatch>' \
    > /etc/apache2/conf-available/srms-protect.conf \
 && a2enconf srms-protect

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
