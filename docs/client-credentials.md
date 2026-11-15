# Client credentials for scripts, services and agents

A Solid app that runs in a browser signs in with the authorization code flow
and the user approves it on the consent page. A client without a browser
(a script, a background service or an AI agent) cannot do that. For those
clients a user can create client credentials that are bound to their own
WebID. The client then uses the OAuth 2.0 `client_credentials` grant with
DPoP, as in the Solid-OIDC client credentials flow that other pod servers
support.

## Create credentials

The user (or the client, acting as the user) signs in to Nextcloud with a
normal session or an app password. These routes require the CSRF check, so an
API client sends the `OCS-APIRequest: true` header.

```sh
curl -u alice:<app-password> -H 'OCS-APIRequest: true' \
  -H 'Content-Type: application/json' -d '{"name": "backup script"}' \
  https://cloud.example/apps/solid/client-credentials
```

The response (`201 Created`) contains the `client_id`, the `client_secret`
(shown only once), the `issuer` and the `webid` the credentials act as.

| Method   | Route                                       | Purpose                              |
|----------|---------------------------------------------|--------------------------------------|
| `GET`    | `/apps/solid/client-credentials`            | List your credentials (no secrets)   |
| `POST`   | `/apps/solid/client-credentials`            | Create credentials, optional `name`  |
| `DELETE` | `/apps/solid/client-credentials/{clientId}` | Revoke credentials                   |

## Use them

With `@inrupt/solid-client-authn-node`:

```js
import { Session } from "@inrupt/solid-client-authn-node";

const session = new Session();
await session.login({
  clientId: process.env.SOLID_CLIENT_ID,
  clientSecret: process.env.SOLID_CLIENT_SECRET,
  oidcIssuer: "https://cloud.example",
  tokenType: "DPoP",
});

const response = await session.fetch("https://cloud.example/apps/solid/~alice/storage/private/notes.ttl");
```

The access token carries the user's WebID, so the client gets exactly the
access that the user's ACLs give that WebID. A dedicated Nextcloud account for
the client (with its own WebID) keeps its access separate from a person's:
other users can then grant that WebID access to specific resources.

## Notes

- Only credentials created through these routes are bound to a WebID. A
  client registered through dynamic client registration gets no WebID from
  the `client_credentials` grant.
- Revoking credentials stops new tokens. A token that was already issued
  stays valid until it expires (one hour).
- Deleting the Nextcloud user also stops new tokens for their credentials.
