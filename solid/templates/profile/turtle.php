<?php
	header("Content-type: text/turtle");
?>@prefix : <#>.
@prefix solid: <http://www.w3.org/ns/solid/terms#>.
@prefix pro: <./>.
@prefix foaf: <http://xmlns.com/foaf/0.1/>.
@prefix schem: <http://schema.org/>.
@prefix acl: <http://www.w3.org/ns/auth/acl#>.
@prefix ldp: <http://www.w3.org/ns/ldp#>.
@prefix inbox: <<?php p($_['inbox']); ?>>.
@prefix sp: <http://www.w3.org/ns/pim/space#>.
@prefix ser: <<?php p($_['storage']); ?>>.

pro:turtle a foaf:PersonalProfileDocument; foaf:maker :me; foaf:primaryTopic :me.

:me
    a schem:Person, foaf:Person;
    ldp:inbox inbox:;
    sp:preferencesFile <<?php p($_['preferences']); ?>>;
    sp:storage ser:;
    solid:account ser:;
    solid:privateTypeIndex <<?php p($_['privateTypeIndex']); ?>>;
    solid:publicTypeIndex <<?php p($_['publicTypeIndex']); ?>>;
<?php
foreach ($_['friends'] as $k => $v) {
?>
    foaf:knows <<?php p($_['friends'][$k]); ?>>;
<?php
}
?>
    foaf:name "<?php p($_['displayName']); ?>".
