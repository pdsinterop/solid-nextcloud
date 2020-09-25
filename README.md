# solid-nextcloud
A plugin to make Nextcloud compatible with Solid


## Special instructions for running webid-provider-tests against nextcloud-server
First of all, we don't have a good way to programmatically log in to Nextcloud yet
so for now, you'll have to follow the "[running with manually harvested cookie](https://github.com/pdsinterop/test-suites#running-with-manually-harvested-cookie)"
instructions from the PDS Interop test suites repo.

Second, we don't have a way to programmatically generate the IDP key pair yet,
so for now, you'll also have to follow the "configuring IDP key pair" instructions below:

## Configuring IDP keys

If you followed the instructions from "[running with manually harvested cookie](https://github.com/pdsinterop/test-suites#running-with-manually-harvested-cookie)"
then you'll now be logged in as Alice on https://localhost.
Go to https://localhost/settings/admin/security and scroll down to 'Solid OpenID Connect Settings'.
For the Private Key, paste:
```
-----BEGIN RSA PRIVATE KEY-----
MIIEpAIBAAKCAQEAvqb0htUFZaZ+z5rn7cHWg0VzsSoVnusbtJvwWtHfD0T0s6Hb
OqzE5h2fgdGbB49HRtc21SNHx6jeEStGv03yyqYkLUKrJJSg+ksrL+pT3Nd0h25q
sx7YUoPPxnm6sbd3XTg5efCb2yyV2dOoAegUPjK46Ra6PqUvmICQWDsjnv0VJIx+
TdDWmKY2xElk0T6CVNMD08OZVTHPwJgpGdRZyCK/SSmrvmAZ6K3ocKySJdKgYriR
bVMdx9NsczRkYU9b7tUpPmLu3IvsLboTbfRN23Y70Gx3Z8fuI1FRn23sEuQSIRW+
NsAi7l+AEdJ7MdYn0xSY6YMNJ0/aGXi55gagQwIDAQABAoIBAQCz8CNNtnPXkqKR
EmTfk1kAoGYmyc+KI+AMQDlDnlzmrnA9sf+Vi0Zy4XaQMeId6m6dP7Yyx4+Rs6GT
lsK4/7qs5M20If4hEl40nQlvubvY7UjAIch2sh/9EQbjDjTUUpJH2y70FdEjtRrh
cdBZrE6evYSkCZ1STtlzF7QkcfyWqilTHEntrHRaM3N+B6F74Yi5g6VyGE9uqKEM
EuGDHVSXizdUjauTTVEa4o7pxTh+eTIdQsfRewer7iuxFPo2vBNOTU2O/obNUsVK
mgmGM4QDjurgXLL2XPr0dVVo3eiFvIdmtZgGVyLfL/vUXH7bwUIfkV6qWyRmdBiY
Dfsm8BJBAoGBAOGebDUVnP3NgFacWVYrtvBXcH2Q6X1W6JEAxctDDsnjchTdyG9E
zcsMVM/gFKXIDF5VeNoSt2pwCTBL6K0oPC31c01clActbHStaJWOOCuifzrvmu4n
X51TNGoKggbbSVx1UTifKte2t6SPRaZ26EqVrmO44fGkA3ip6TRYnSFzAoGBANhT
J47EieRWiNflq9XqDAZ1fZzo3AHB+b+pO4r8GZr3Dw0ShCAnQXv7Gb2JAJvE3UrC
Aq5r3yZMM7nI+n/OT06+UcJ3/vDGAPx9trNrpWkwmcWBmoBfp86vDRhT0kEIiKbO
wLYMmSNLHNkmQQdBX2ytnsRxRyCWtQmm09bzOJHxAoGBAKEB/nSPnP5elfS5FOPy
xFWWANgK/yWMTOGV7JFWpIocvz/22d/V+QqrHSdP4UxBi9oSIvF1I+FYXKZTtZNE
wFWH8SXHKHhKyTgmvBjmal1xVFyJu0WzYX+TbjcykoI0IZFSw4ilxdw1L67G88yM
1M7NLKtLuCpKgpOspZjOmCvTAoGAGji6KswYCt2SaNkmIx/jpUTInSR8xpnEtD7H
QOmeEPKxmFwON/eKMIUXcaoRsNAEIvOxb4MT4YiLHJIIC0XuxxS6xF/XP0hBBloW
s1jxC/cgLJixKa5uoNcHN1OxwMBQECgvo+GTDnwkWw4QA9kgwAOroxQ4EvMxrqHS
O9Pvn4ECgYA7xr/3Sz8n+BhgOdABW0m91P144rK9QDYiaClSxAha1KiFunmAy3pB
Uxdl4yTCTA9yKIH7X3bShDXnj+RmEZ+SkwzpPuKvAE8ZkZQuXv41anFrZYkR2PZy
oYiERqXgH5yS/mkDeXRFx1nWsVxjoLWfd/Vi7Lr43cuYFy4UjqXZdg==
-----END RSA PRIVATE KEY-----
```
For the Encryption Key, paste:
```
P76gcBVeXsVzrHiYp4IIwore5rQz4cotdZ2j9GV5V04=
```
Obviously, don't use these keys in production since they are now published here. :)