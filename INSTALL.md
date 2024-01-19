# Installing this app to your Nextcloud

If you have installed Nextcloud [using snap](https://www.digitalocean.com/community/tutorials/how-to-install-and-configure-nextcloud-on-ubuntu-22-04)
you should be able to run the latest version of this app. You can find it in the Nextcloud app store as `Solid`, or by running `sudo nextcloud.occ app:install solid`.

## Building from source
To switch the version of your Solid app from the "store-bought" version to the latest unreleased version, you will need to build from source:
```
sudo /bin/bash
cd /var/snap/nextcloud/current/nextcloud/extra-apps/
rm -r solid
git clone https://github.com/pdsinterop/solid-nextcloud
ln -s solid-nextcloud/solid
cd solid
apt update
apt install -y php git php-curl php-gd php-opcache php-xml php-gd \
  php-curl php-zip php-json libxml2 libxml2-dev php-xml php-mbstring \
  build-essential curl php-sqlite3 php-xdebug php-mbstring php-zip \
  php-imagick imagemagick php-intl
make
```

## Troubleshooting
* if installing the app from source, make sure you run `apt install -y composer`, `composer update`, and `composer install --no-dev --prefer-dist`
* when editing /var/www/html/.htaccess make sure that you run `sudo a2enmod rewrite`, edit your `/etc/apache2/sites-enabled/000-default.conf` to something like:
```
<VirtualHost *:443>
    DocumentRoot /var/www/html
    ErrorLog ${APACHE_LOG_DIR}/error.log 
    CustomLog ${APACHE_LOG_DIR}/access.log combined

    SSLEngine on
    SSLCertificateFile "/etc/letsencrypt/live/cloud.pondersource.org/fullchain.pem"
    SSLCertificateKeyFile "/etc/letsencrypt/live/cloud.pondersource.org/privkey.pem"
</VirtualHost>
<VirtualHost *:80>
    DocumentRoot /var/www/html
    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
<Directory /var/www/>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
</Directory>
```
(notice, importantly, the `AllowOverride All` that makes apache read your .htaccess in the first place) and then run `systemctl restart apache2`.
* if installed through snap, the logs you want to check server-side are in `/var/snap/nextcloud/current/logs/`
* if installed the old way with Apache, check `/var/log/apache2/error.log` and `/var/www/html/data/nextcloud.log`
