# Installing this app to your Nextcloud

## Set up /.well-known/openid-configuration
After installing and enabling the app, take the JSON from e.g.

`https://cloud.pondersource.org/index.php/apps/solid/openid` (depending on your hostname and whether you have the `index.php/` part in there)

and put that into e.g. `/var/www/html/.well-known/openid-configuration` (depending on your webroot being e.g. `/var/www/html`)
Check that it works: `https://cloud.pondersource.org/.well-known/openid-configuration`
Then add this section to your apache site.conf:
```
<Directory /var/www/html/.well-known/>
    Header always set Access-Control-Allow-Origin: *
</Directory>
```
Then restart Apache.

In earlier versions of this app (e.g. the one in the Dockerfile we use for running the Solid test suite) we
used a redirect from /.well-known/openid-configuration to /index.php/apps/solid/openid but it's difficult
to add CORS headers to a redirect, so that's why just copying the file into a folder like that is preferable.

## If your Nextcloud was installed using Snap

Steps you probably already took:
* Point a DNS A record to the server that will run Nextcloud, for instance "A test-nextcloud-snap 188.166.99.179"
* Install Nextcloud using Snap:
> root@ubuntu-s-4vcpu-8gb-amd-ams3-01:~# snap install nextcloud
> nextcloud 22.2.0snap2 from Nextcloud✓ installed

* Browse to it over http and complete the setup:

<img width="960" alt="Screenshot 1" src="https://user-images.githubusercontent.com/408412/140734318-2872d19c-8a2d-40a5-8e89-e0474c705840.png">

It's important that you have a public DNS A record pointing to the server, since you'll need it to enable https, which is a requirement for Solid:

* Run this on your server to add a LetsEncrypt cert to your Nextcloud:

```sh
root@ubuntu-s-4vcpu-8gb-amd-ams3-01:~# nextcloud.enable-https lets-encryptIn order for Let's Encrypt to verify that you actually own the
domain(s) for which you're requesting a certificate, there are a
number of requirements of which you need to be aware:

1. In order to register with the Let's Encrypt ACME server, you must
   agree to the currently-in-effect Subscriber Agreement located
   here:

       https://letsencrypt.org/repository/

   By continuing to use this tool you agree to these terms. Please
   cancel now if otherwise.

2. You must have the domain name(s) for which you want certificates
   pointing at the external IP address of this machine.

3. Both ports 80 and 443 on the external IP address of this machine
   must point to this machine (e.g. port forwarding might need to be
   setup on your router).

Have you met these requirements? (y/n) 
Please answer yes or no.
Have you met these requirements? (y/n) yes
Please enter an email address (for urgent notices or key recovery): michiel-testing@pondersource.com

Please enter your domain name(s) (space-separated): test-nextcloud-snap.michielbdejong.com
Attempting to obtain certificates... done

Restarting apache... done
root@ubuntu-s-4vcpu-8gb-amd-ams3-01:~# 
```

* Now you can visit your Nextcloud over https:

<img width="960" alt="Screenshot 2" src="https://user-images.githubusercontent.com/408412/140734389-b3d30abd-8568-415f-a3b9-38d8d4249018.png">

* Go to the 'Apps' menu:

<img width="960" alt="Screenshot 3" src="https://user-images.githubusercontent.com/408412/140734411-49661501-43ab-4821-b8a2-60dbc442f964.png">

* Search for 'solid':

<img width="1270" alt="Screenshot 4" src="https://user-images.githubusercontent.com/408412/140734420-6bb1ac6f-b4ee-4df5-b88d-544cdb82f174.png">

* Download and install:

<img width="1152" alt="Screenshot 5" src="https://user-images.githubusercontent.com/408412/140734504-5cdc4837-9e35-4b47-b091-69b9ff913081.png">

* If you can't find v0.0.3 in through the search function, you can also download it explicitly:
> `root@ubuntu-s-4vcpu-8gb-amd-ams3-01:/var/snap/nextcloud/current/nextcloud/extra-apps# wget https://github.com/pdsinterop/solid-nextcloud/releases/download/v0.0.3/solid.tar.gz`
* In all cases, make sure you click 'Enable' for the Solid app on https://test-nextcloud-snap.michielbdejong.com/index.php/settings/apps
* Now test with your browser: `https://test-nextcloud-snap.michielbdejong.com/index.php/apps/solid/openid`
* It should be a JSON document, something like `{"id_token_signing_alg_values_supported":["RS256"],"subject_types_supported":["public"],"response_types_supported":[...`
* The following [is a bit tricky](https://github.com/nextcloud-snap/nextcloud-snap/issues/412#issuecomment-930878692) but it seems to work:
```sh
sudo cp -r /snap/nextcloud/current/htdocs /var/snap/nextcloud/current/nextcloud/config/
cd /var/snap/nextcloud/current/nextcloud/config/htdocs
sudo mount /var/snap/nextcloud/current/nextcloud/config/htdocs /snap/nextcloud/current/htdocs/ -o bind
```
Now make the change described in the "Set up /.well-known/openid-configuration" section above, but using
`/var/snap/nextcloud/current/nextcloud/config/htdocs/.well-known/openid-configuration` as the directory.

FIXME: How can you edit the Apache site.conf if installed via Snap? Maybe see if the Header directive
can live in `/var/snap/nextcloud/current/nextcloud/config/htdocs/.well-known/.htaccess` instead?

Restart Apache using:
```sh
sudo snap restart nextcloud.apache
```

* Now test that `https://test-nextcloud-snap.michielbdejong.com/.well-known/openid-configuration` redirects to `https://test-nextcloud-snap.michielbdejong.com/index.php/apps/solid/openid`
* Add this to your /etc/fstabs and restart the server:
```
/var/snap/nextcloud/current/nextcloud/config/htdocs /snap/nextcloud/current/htdocs none auto,bind,x-systemd.before=snap.nextcloud.apache.service,x-systemd.requires-mounts-for=/snap/nextcloud/current/,x-systemd.required-by=snap.nextcloud.apache.service 0 0
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
* find a way to enable CORS headers (not sure exactly how to do this, see https://github.com/pdsinterop/solid-nextcloud/issues/57)
