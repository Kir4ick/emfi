FROM php:8.1-fpm
WORKDIR /root
# Обновляем пакеты
RUN apt-get update -y && apt-get upgrade -y && apt-get install locales -y \
    && localedef -i ru_RU -c -f UTF-8 -A /usr/share/locale/locale.alias ru_RU.UTF-8
ENV LANG ru_RU.utf8
# Устанавливаем php
RUN apt-get install curl zip wget git cron openssl ca-certificates ffmpeg \
    libpq-dev \
    libmcrypt-dev \
    libfreetype6-dev \
    libjpeg-dev \
    libpng-dev \
    libz-dev \
    libxml2-dev \
    libssl-dev \
    libmemcached-dev \
    libldap2-dev \
    libxslt-dev -y
# Устанавливаем зависимости
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && install-php-extensions openssl calendar opcache \
    bcmath exif gd gettext pdo_mysql mysqli imagick opcache mbstring iconv intl \
    pdo_pgsql pgsql shmop soap sockets sysvmsg sysvsem sysvshm zip pcntl xsl \
    ldap gd gearman xdebug-3.1.4
# Очищаем кэш
RUN apt-get clean && rm -rf /var/lib/apt/lists/*
# Создаем группу и пользователя www
RUN groupadd -g 1000 www
RUN useradd -u 1000 -ms /bin/bash -g www www
# Установка composer
RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer
COPY php.ini /usr/local/etc/php/
# Устанавливаем рабочую директорию
WORKDIR /var/www/application/
# RUN chown www:www /var/www/application
RUN chown -R www:www /var/www/application
USER www
CMD ["php-fpm"]