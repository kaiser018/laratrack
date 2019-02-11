FROM php:apache
WORKDIR /var/www/html/
COPY ./sites.conf /etc/apache2/sites-available/sites.conf
RUN a2dissite 000-default.conf && a2ensite sites.conf && a2enmod rewrite
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data
RUN composer install