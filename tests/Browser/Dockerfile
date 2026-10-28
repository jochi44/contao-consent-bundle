ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-apache

RUN apt-get update \
	&& apt-get install -y --no-install-recommends git unzip libicu-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libzip-dev \
	&& docker-php-ext-configure gd --with-jpeg --with-webp \
	&& docker-php-ext-install intl pdo_mysql gd zip \
	&& a2enmod rewrite headers \
	&& rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
	&& sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
	&& printf '<Directory /var/www/html/public>\n\tAllowOverride All\n\tRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/contao-public.conf \
	&& a2enconf contao-public

WORKDIR /var/www/html
