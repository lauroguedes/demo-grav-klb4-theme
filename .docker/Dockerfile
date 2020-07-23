FROM ambientum/php:7.3-nginx
ENV XDEBUG_ENABLED=true
USER root
RUN apk add --update nodejs nodejs-npm

COPY ./xdebug.ini /etc/php7/conf.d/xdebug.ini
COPY ./laravel.conf /etc/nginx/sites/laravel.conf