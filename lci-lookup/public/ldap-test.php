<?php

$server = 'ldap://SBDC-02.lc.local:389';
$user   = 'LC\\vend-elink';
$pass   = 'B1gwh33LTurn1n';

$connection = ldap_connect($server);

if (!$connection) {
    echo "Unable to connect to LDAP server.";
    return;
}

ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);

$bind = @ldap_bind($connection, $user, $pass);

if ($bind) {
    echo "LDAP bind successful.";
} else {
    echo "LDAP bind FAILED: " . ldap_error($connection);
}

ldap_close($connection);

