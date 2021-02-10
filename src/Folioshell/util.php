<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Folioshell;

class Util
{
    /**
     * Determine if we are running from inside the Joomlatools Box environment.
     * Only boxes >= 1.4.0 can be recognized.
     *
     * @return boolean true|false
     */
    public static function isJoomlatoolsBox()
    {
        if (php_uname('n') === 'joomlatools') {
            return true;
        }

        // Support boxes that do not have the correct hostname set
        $user = exec('whoami');
        if (trim($user) == 'vagrant' && file_exists('/home/vagrant/scripts/dashboard/index.php'))
        {
            if (file_exists('/etc/varnish/default.vcl')) {
                return true;
            }
        }

        return false;
    }
}