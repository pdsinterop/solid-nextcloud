<?php
/*
 * This file is part of the Ariadne Component Library.
 *
 * (c) Muze <info@muze.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace arc\http;

/**
 * This class parses a .htpasswd formatted string and extracts the users
 * and encrypted passwords from it.
 * Then you can check a username and password combination against this
 * list.
 */
class Htpasswd {

    public $users = [];

    /**
     * The constructor needs a string with the contents of a htpasswd file
     */
    public function __construct($htpasswd) {
        $lines = preg_split('/\r\n|\r|\n/',$htpasswd);
        foreach ($lines as $line) {
            if (strpos($line, ':')===false) {
                continue;
            }
            list($user,$password) = array_map('trim', explode(':',$line));
            if ($user && $password) {
                $this->users[$user] = $password;
            }
        }
    }

    /**
     * This method checks if a username and password combination matches
     * a user and encrypted password from the htpasswd file.
     * @param string $user
     * @param string $password
     * @return bool
     */
    public function check($user, $password) {
        if ( !isset($this->users[$user]) ) {
            return false;
        }
        $checks  = [ '{SSHA}' => 'saltedSHA1', '{SHA}' => 'SHA1', '$apr1$' => 'MD5', '$2y$' => 'bcrypt' ];
        $crypted = $this->users[$user];
        $check   = 'crypt';
        foreach($checks as $match => $algorithm) {
            if ( strpos($crypted, $match) === 0 ) {
                $check = $algorithm;
                break;
            }
        }
        return $this->$check($crypted, $password);
    }

    private function crypt($crypted, $password) {
        return (crypt( $password, substr($crypted,0,CRYPT_SALT_LENGTH) ) == $crypted);
    }

    private function saltedSHA1($crypted, $password) {
        $hash = base64_decode(substr($crypted, 6));
        return (substr($hash, 0, 20) == pack('H*', sha1($password . substr($hash, 20))));
    }

    private function SHA1($crypted, $password) {
		$non_salted_sha1 = "{SHA}" . base64_encode(pack("H*", sha1($password)));
        return ($non_salted_sha1 == $crypted);
    }

    private function MD5($crypted, $password) {
        // thanks to http://blog.ethlo.com/2013/02/01/using-php-and-existing-htpasswd-file-for-authentication.html
        $passParts = explode('$', $crypted);
        $salt      = $passParts[2];
        $hashed    = self::cryptApr1Md5($password, $salt);
        return $hashed == $crypted;
    }

    private function bcrypt($crypted, $password) {
        return password_verify($password, $crypted);
    }

    private function cryptApr1Md5($plainpasswd, $salt) {
        $len  = strlen($plainpasswd);
        $text = $plainpasswd.'$apr1$'.$salt;
        $bin  = pack("H32", md5($plainpasswd.$salt.$plainpasswd));
        for ($i = $len; $i > 0; $i -= 16) { 
            $text .= substr($bin, 0, min(16, $i));
        }
        for ($i = $len; $i > 0; $i >>= 1) {
            $text .= ($i & 1) ? chr(0) : $plainpasswd[0];
        }
        $bin = pack("H32", md5($text));
        for ($i = 0; $i < 1000; $i++) {
            $new = ($i & 1) ? $plainpasswd : $bin;
            if ($i % 3) {
                $new .= $salt;
            }
            if ($i % 7) {
                $new .= $plainpasswd;
            }
            $new .= ($i & 1) ? $bin : $plainpasswd;
            $bin = pack("H32", md5($new));
        }
        $tmp = '';
        for ($i = 0; $i < 5; $i++) {
            $k = $i + 6;
            $j = $i + 12;
            if ($j == 16) {
                $j = 5;
            }
            $tmp = $bin[$i].$bin[$k].$bin[$j].$tmp;
        }
        $tmp = chr(0).chr(0).$bin[11].$tmp;
        $tmp = strtr(strrev(substr(base64_encode($tmp), 2)),
        "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/",
        "./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz");
        return "$"."apr1"."$".$salt."$".$tmp;
    }
}
