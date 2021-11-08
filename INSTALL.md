# Installing this app to your Nextcloud

## If your Nextcloud was installed using Snap

Steps you probably already took:
* Point a DNS A record to the server that will run Nextcloud, for instance "A test-nextcloud-snap 188.166.99.179"
* Install Nextcloud using Snap:
> root@ubuntu-s-4vcpu-8gb-amd-ams3-01:~# snap install nextcloud
> nextcloud 22.2.0snap2 from Nextcloud✓ installed

* Browse to it over http and complete the setup -> Screenshot 1

It's important that you have a public DNS A record pointing to the server, since you'll need it to enable https, which is a requirement for Solid:

* Run this on your server to add a LetsEncrypt cert to your Nextcloud:

> root@ubuntu-s-4vcpu-8gb-amd-ams3-01:~# nextcloud.enable-https lets-encryptIn order for Let's Encrypt to verify that you actually own the
> domain(s) for which you're requesting a certificate, there are a
> number of requirements of which you need to be aware:
> 
> 1. In order to register with the Let's Encrypt ACME server, you must
>    agree to the currently-in-effect Subscriber Agreement located
>    here:
> 
>        https://letsencrypt.org/repository/
> 
>    By continuing to use this tool you agree to these terms. Please
>    cancel now if otherwise.
> 
> 2. You must have the domain name(s) for which you want certificates
>    pointing at the external IP address of this machine.
> 
> 3. Both ports 80 and 443 on the external IP address of this machine
>    must point to this machine (e.g. port forwarding might need to be
>    setup on your router).
> 
> Have you met these requirements? (y/n) 
> Please answer yes or no.
> Have you met these requirements? (y/n) yes
> Please enter an email address (for urgent notices or key recovery): michiel-testing@pondersource.com                
> Please enter your domain name(s) (space-separated): test-nextcloud-snap.michielbdejong.com
> Attempting to obtain certificates... done
> Restarting apache... done
> root@ubuntu-s-4vcpu-8gb-amd-ams3-01:~# 

* Now you can visit your Nextcloud over https -> Screenshot 2
* Go to the 'Apps' menu -> Screenshot 3
* Search for 'solid' -> Screenshot 4
* Download and install -> Screenshot 5
* If you can't find v0.0.3 in through the search function, you can also download it explicitly:
> `root@ubuntu-s-4vcpu-8gb-amd-ams3-01:/var/snap/nextcloud/current/nextcloud/extra-apps# wget https://github.com/pdsinterop/solid-nextcloud/releases/download/v0.0.3/solid.tar.gz`
* In all cases, make sure you click 'Enable' for the Solid app on https://test-nextcloud-snap.michielbdejong.com/index.php/settings/apps
* Now test with your browser: `https://test-nextcloud-snap.michielbdejong.com/index.php/apps/solid/openid`
* It should be a JSON document, something like `{"id_token_signing_alg_values_supported":["RS256"],"subject_types_supported":["public"],"response_types_supported":[...`
* The following [is a bit tricky](https://github.com/nextcloud-snap/nextcloud-snap/issues/412#issuecomment-930878692) but it seems to work:
> `sudo cp -r /snap/nextcloud/current/htdocs /var/snap/nextcloud/current/nextcloud/config/`
> `cd /var/snap/nextcloud/current/nextcloud/config/htdocs`
> `sudo mount /var/snap/nextcloud/current/nextcloud/config/htdocs /snap/nextcloud/current/htdocs/ -o bind`
> `cp .htaccess bak.htaccess`
> `sed -i '95 i\  RewriteRule ^\\.well-known/openid-configuration /apps/solid/openid [R=302,L]' htdocs/.htaccess`
> `sudo snap restart nextcloud.apache`
* Now test that `https://test-nextcloud-snap.michielbdejong.com/.well-known/openid-configuration` redirects to `https://test-nextcloud-snap.michielbdejong.com/index.php/apps/solid/openid`
* Add this to your /etc/fstabs and restart the server:
```
/var/snap/nextcloud/current/nextcloud/config/htdocs /snap/nextcloud/current/htdocs none auto,bind,x-systemd.before=snap.nextcloud.apache.service,x-systemd.requires-mounts-for=/snap/nextcloud/current/,x-systemd.required-by=snap.nextcloud.apache.service 0 0
```
