FolioShell - WordPress Command Line Tools
=========================

This is a script developed by [Foliolabs Team](https://foliolabs.com) to ease the management of WordPress sites.

It is designed to work on Linux and MacOS. Windows users can use it in [Nooku Vagrant box](https://github.com/nooku/nooku-vagrant)

Installation
------------

1. Download or clone this repository.

1. Make the `folioshell` command executable:

    `$ chmod u+x /path/to/folioshell/bin/folioshell`

1. Configure your system to recognize where the executable resides. There are 3 options:
    1. Create a symbolic link in a directory that is already in your PATH, e.g.:

        `$ ln -s /path/to/folioshell/bin/folioshell /usr/bin/folioshell`

    1. Explicitly add the executable to the PATH variable which is defined in the the shell configuration file called .profile, .bash_profile, .bash_aliases, or .bashrc that is located in your home folder, i.e.:

        `export PATH="$PATH:/path/to/folioshell/bin:/usr/local/bin"`

    1. Add an alias for the executable by adding this to you shell configuration file (see list in previous option):

        `$ alias folioshell=/path/to/folioshell/bin/folioshell`

    For options 2 and 3 above, you should log out and then back in to apply your changes to your current session.

1. Test that `folioshell` executable is found by your system:

    `$ which folioshell`

1. From `folioshell` root (`/path/to/folioshell`), run Composer to fetch dependencies.

    `$ composer install`

For available options, try running:

    folioshell --list
    
Usage 
-----

### Create Sites

To create a site with the latest WordPress version, run:

    folioshell site:create testsite

The newly installed site will be available at /var/www/testsite and testsite.dev after that. You can login into your fresh WordPress installation using these credentials: `admin` / `admin`.

By default the web server root is set to _/var/www_. You can pass _--www=/my/server/path_ to commands for custom values.

You can choose the WordPress version to be installed:

    folioshell site:create testsite --wordpress=4.2

You can pick any branch from the Git repository (e.g. master, staging) using this command.

You can also add your projects into the new site by symlinking. See the Symlinking section below for detailed information.

    folioshell site:create testsite --symlink=project1,project2

For more information and available options, try running:

    folioshell site:create --help

### Delete Sites

You can delete the sites you have created by running:

    folioshell site:delete testsite

### Symlink Plugins

Let's say you are working on your own WordPress component called _Awesome_ and want to develop it with the latest WordPress version.

By default your code is assumed to be in _~/Projects_. You can pass _--projects-dir=/my/code/is/here_ to commands for custom values.

Please note that your source code should resemble the WordPress `wp-content` folder structure for symlinking to work properly. For example, plugins folder should reside in ~/Projects/projectname/code/plugins/projectname.

Now to create a new site, execute the site:create command and add a symlink option:

	folioshell site:create testsite --symlink=projectname

Or to symlink your code into an existing site:

	folioshell extension:symlink testsite projectname

This will symlink all the folders from the _projectname_ folder into _testsite.dev_.

Run discover install to make your component available to WordPress and you are good to go!

For more information on the symlinker, run:

	folioshell extension:symlink  --help

### Install Plugins

You can install plugins from WordPress's Official Plugin Repository on command line to install plugins.

	folioshell extension:install testsite pluginslug

You need to use the unique slug of the plugin.

For more information, run:

	folioshell extension:install --help
	  
Alternatively, you can install plugins using packages or url using the `extension:installfile` command. Example:

    folioshell extension:installfile testsite /home/vagrant/pluginone.v1.x.zip /home/vagrant/plugintwo.v2.x.tar.gz

And as URL

    folioshell extension:installfile testsite http://url.com/to/pluginone.v1.x.zip http://url.com/to/plugintwo.v2.x.tar.gz
    
This will install both the pluginone.v1.x.zip and plugintwo.v2.x.tar.gz packages.

### Activate Plugins

`extension:install` and `extension:installfile` automatically activates the plugin.

## Extra commands

There a few other commands available for you to try out as well :

* `folioshell versions` : list the available WordPress versions. 
 * Use `folioshell versions --refresh` to get the latest tags and branches from the official [WordPress](https://github.com/WordPress/WordPress) repository.
 * To purge the cache of all WordPress packages, add the `--clear-cache` flag to this command.

## Requirements

* Composer

## Contributing

Fork the project, create a feature branch, and send us a pull request.

## Authors

See the list of [contributors](https://github.com/foliolabs/folioshell/contributors).

## License

The `foliolabs/folioshell` repository is licensed under the MPL v2 license - see the LICENSE file for details.
