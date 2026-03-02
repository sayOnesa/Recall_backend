# Use official PHP 8.2 with Apache
FROM php:8.2-apache

# Install mysqli extension to connect to MySQL/Aurora
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Optional: enable other PHP extensions if needed
# RUN docker-php-ext-install pdo pdo_mysql

# Copy all backend files into the Apache web root
COPY . /var/www/html/

# Set correct permissions for Apache
RUN chown -R www-data:www-data /var/www/html

# Expose port 80 (Apache default)
EXPOSE 80

# Start Apache in the foreground
CMD ["apache2-foreground"]