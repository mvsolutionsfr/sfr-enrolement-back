# Un Dockerfile valide doit commencer par un FROM
# Le FROM définit l'image qui servira de base pour la suite de la construction
FROM hub.valentine.sfr.com/enrolement-sbd/enrolement-back:enrolement-php85-symfony8

COPY config/php/app.ini /usr/local/etc/php/conf.d/app.ini
COPY config/apache/apache2.conf /etc/apache2/apache2.conf
COPY config/apache/000-default.conf /etc/apache2/sites-enabled/000-default.conf
COPY config/cert.pem /usr/lib/ssl/cert.pem

COPY config /var/www/config
COPY public /var/www/public
COPY mig /var/www/mig
COPY src /var/www/src
COPY bin /var/www/bin
COPY templates /var/www/templates
COPY composer.json /var/www/composer.json
COPY .env /var/www/

RUN  a2enmod rewrite

WORKDIR /var/www

RUN ln -s /app/conf/.env /var/www/.env.local
RUN mkdir -p /var/www/var/cache /var/www/var/log \
    && chmod 777 /var/www/var/cache /var/www/var/log

#RUN sed -i 's/;   extension=modulename/extension=elastic_apm_loader.so/g' /usr/local/etc/php/php.ini-development
