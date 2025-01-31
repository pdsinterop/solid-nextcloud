- scopes aangepast in well-known/openidconfiguration, webid er af en openid er in
- client secret teruggeven bij het register endpoint (gaat de 500 server error in podpro van weg)

Added our token endpoint in /var/www/vhosts/solid-nextcloud/site/www/lib/base.php:
	($request->getRawPathInfo() !== '/apps/oauth2/api/v1/token') &&
	($request->getRawPathInfo() !== '/apps/solid/token')
