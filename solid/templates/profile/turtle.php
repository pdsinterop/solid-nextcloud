<?php
	header("Content-type: text/turtle");
?>@prefix : <#>.
@prefix solid: <http://www.w3.org/ns/solid/terms#>.
@prefix pro: <./>.
@prefix foaf: <http://xmlns.com/foaf/0.1/>.
@prefix schem: <http://schema.org/>.
@prefix acl: <http://www.w3.org/ns/auth/acl#>.
@prefix ldp: <http://www.w3.org/ns/ldp#>.
@prefix inbox: </inbox/>.
@prefix sp: <http://www.w3.org/ns/pim/space#>.
@prefix ser: </>.

pro:turtle a foaf:PersonalProfileDocument; foaf:maker :me; foaf:primaryTopic :me.

:me
    a schem:Person, foaf:Person;
    acl:trustedApp
            [
                acl:mode acl:Append, acl:Control, acl:Read, acl:Write;
                acl:origin <http://localhost:3002>
            ];
    ldp:inbox inbox:;
    sp:preferencesFile </settings/prefs.ttl>;
    sp:storage ser:;
    solid:account ser:;
    solid:privateTypeIndex </settings/privateTypeIndex.ttl>;
    solid:publicTypeIndex </settings/publicTypeIndex.ttl>;
    foaf:name "<?php p($_['displayName']); ?>";
    foaf:knows <<?php p($_['friends'][0]); ?>>.
