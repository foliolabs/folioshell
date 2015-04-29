WordPress Command Line Tools
=========================

This is a script developed by [Nooku Team](http://nooku.org) to ease the management of WordPress sites.

It is designed to work on Linux and MacOS. Windows users can use it in [Nooku Vagrant box](https://github.com/nooku/nooku-vagrant)

Installation
------------

1. Download or clone this repository.

1. Make the `wordpress` command executable:

    `$ chmod u+x /path/to/wordpress-console/bin/wordpress`

1. Configure your system to recognize where the executable resides. There are 3 options:
    1. Create a symbolic link in a directory that is already in your PATH, e.g.:

        `$ ln -s /path/to/wordpress-console/bin/wordpress /usr/bin/wordpress`

    1. Explicitly add the executable to the PATH variable which is defined in the the shell configuration file called .profile, .bash_profile, .bash_aliases, or .bashrc that is located in your home folder, i.e.:

        `export PATH="$PATH:/path/to/wordpress-console/bin:/usr/local/bin"`

    1. Add an alias for the executable by adding this to you shell configuration file (see list in previous option):

        `$ alias wordpress=/path/to/wordpress-console/bin/wordpress`

    For options 2 and 3 above, you should log out and then back in to apply your changes to your current session.

1. Test that wordpress executable is found by your system:

    `$ which wordpress`

1. From wordpress-console root (/path/to/wordpress-console), run Composer to fetch dependencies.

    `$ composer install`

For available options, try running:

    wordpress --list
    
Usage 
-----

### Create Sites

To create a site with the latest WordPress version, run:

    wordpress site:create testsite

The newly installed site will be available at /var/www/testsite and testsite.dev after that. You can login into your fresh WordPress installation using these credentials: `admin` / `admin`.

By default the web server root is set to _/var/www_. You can pass _--www=/my/server/path_ to commands for custom values.

You can choose the WordPress version or the sample data to be installed:

    wordpress site:create testsite --wordpress=4.1 --sample-data=blog

You can pick any branch from the Git repository (e.g. master, staging) or any version from 2.5.0 and up using this command.

You can also add your projects into the new site by symlinking. See the Symlinking section below for detailed information.

    wordpress site:create testsite --symlink=project1,project2

For more information and available options, try running:

    wordpress site:create --help

### Delete Sites

You can delete the sites you have created by running:

    wordpress site:delete testsite

### Symlink Plugins

Let's say you are working on your own WordPress component called _Awesome_ and want to develop it with the latest WordPress version.

By default your code is assumed to be in _~/Projects_. You can pass _--projects-dir=/my/code/is/here_ to commands for custom values.

Please note that your source code should resemble the WordPress folder structure for symlinking to work well. For example your administrator section should reside in ~/Projects/awesome/administrator/components/pluginone.

Now to create a new site, execute the site:create command and add a symlink option:

	wordpress site:create testsite --symlink=awesome

Or to symlink your code into an existing site:

	wordpress plugin:symlink testsite awesome

This will symlink all the folders from the _awesome_ folder into _testsite.dev_.

Run discover install to make your component available to WordPress and you are good to go!

For more information on the symlinker, run:

	wordpress plugin:symlink  --help

### Install Plugins

You can use discover install on command line to install plugins.

	wordpress plugin:install testsite pluginone

You need to use the _element_ name in your plugin manifest.

For more information, run:

	wordpress plugin:install --help
	  
Alternatively, you can install plugins using their installation packages using the `plugin:installfile` command. Example:

    wordpress plugin:installfile testsite /home/vagrant/pluginone.v1.x.zip /home/vagrant/plugintwo.v2.x.tar.gz
    
This will install both the pluginone.v1.x.zip and plugintwo.v2.x.tar.gz packages.

### Register Plugins

With the `plugin:activate` command you can insert your plugin into the `plugins` table without the need for a complete install package with a manifest file.

Like `plugin:install`, you should also use what would be the _element_ name from your manifest.

    wordpress plugin:activate testsite pluginone

Other options available for all plugin types: `--enabled`, `--client_id`

## Extra commands

There a few other commands available for you to try out as well :

* `wordpress site:token sitename user` : generates an authentication token for the given `user` to automatically login to `sitename` using the ?auth_token query argument. *Note* requires the [Nooku Framework](https://github.com/nooku/nooku-framework-wordpress) to be installed in your `site`.
* `wordpress versions` : list the available WordPress versions. 
 * Use `wordpress versions --refresh` to get the latest tags and branches from the official [WordPress](https://github.com/WordPress/WordPress) repository.
 * To purge the cache of all WordPress packages, add the `--clear-cache` flag to this command.

## Requirements

* Composer
* WordPress version 3.0 and up.

## Contributing

Fork the project, create a feature branch, and send us a pull request.

## Authors

See the list of [contributors](https://github.com/nooku/wordpress-console/contributors).

## License

The `nooku/wordpress-console` plugin is licensed under the MPL v2 license - see the LICENSE file for details.
