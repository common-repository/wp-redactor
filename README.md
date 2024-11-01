# wp-redactor
A Wordpress plugin to enable redaction of text from published posts.

## The plugin build process
The plugin's build process will create two new directories `dist` and `docs` for the plugin and code documentation respectively. The `dist` directory can be zipped and used to install the plugin. For development purposes I just symlink the directory to the wordpress plugins directory for instant gratification on build.

The build process will uglify all javascript and scss files into corresponding `min` files as well as maintaining their development versions.

Finally, it will generate the internationalization file and place it in the `dist/languages` directory to be used by WordPress. 

### Setup of development environment

#### VCCW - http://vccw.cc/

Follow the [getting started guide](http://vccw.cc/#h2-2) for creating a generic Wordpress development environment. 

Inside the default.yml file there should be something like the following that will keep a directory inside the VM in sync with a local dev folder.

```
sync_folder: 'www/wordpress'
```

#### Git - https://git-scm.com

Follow the [getting started guide](https://git-scm.com/book/en/v2/Getting-Started-Installing-Git) to install the Git client.

Browse to the mapped ```www/wordpress/wp-config/plugins```  directory and clone the repo and create a working branch from develop.

```
git clone git@github.com:DataSyncTech/wp-redactor.git
git checkout -b issue-<issue number>
```

After making edits to the branch push the changes back to the repo.

```
git commit -a -m 'comment about change'
git push
```

After changes are complete, generate a pull request and the changes will get merged into develop and/or master

This is a really simple example. [More information on branching and merging](https://git-scm.com/book/en/v2/Git-Branching-Basic-Branching-and-Merging)

#### Composer - https://getcomposer.org/

Composer is a depencendy management utility, but also implements [PSR-0](http://www.php-fig.org/psr/psr-0/) and [PSR-4](http://www.php-fig.org/psr/psr-4/) specifications for autoloading files and classes. It is also useful for loading specific packages for different environments (dev vs prod).

Follow the [installation instructions](https://getcomposer.org/doc/00-intro.md) for composer.

1. Browse to the ```wwww/wordpress/wp-content/plugins/wp-redactor``` directory
2. Run ```composer update``` to install the composer dependencies.

A vendor directory will be created which will be packaged for release, but not checked back into git so nothing inside vendor should be modified.

#### Grunt

The plugin uses an automated build process that will generate the documentation and internationalization file using grunt modules. The PHP and plugin headers are generated from the attributes in the `package.json` file. 

If you haven't used  [Grunt](http://gruntjs.com/)  before, be sure to check out the Getting Started guide, as it explains how to create a Gruntfile as well as install and use Grunt plugins.

From the plugin directory, use node and the node package manager to install the required `Grunt` modules. Once the modules are installed just use `grunt` to build the plugin and during active development use `grunt-watch` to watch files for modifications and build the plugin automagically.
```
//install grunt globally.
# npm -g install grunt

//install the grunt modules
$ npm install

//build the plugin into a dist directory
$ grunt 

//watch for changing files and rebuild the plugin
$ grunt watch
```

### Testing with PHPUnit

1. Install PHPUnit by running _composer install_

2. Check out the test repository. The WordPress tests live in the core development repository, at https://develop.svn.wordpress.org/trunk/:
   ```
   $ svn co https://develop.svn.wordpress.org/trunk/ wordpress-develop
   $ cd wordpress-develop
   ```
3. Create an empty MySQL database. The test suite will delete all data from all tables for whichever MySQL database it is configured. Use a separate database.

4. Set up a config file. Copy wp-tests-config-sample.php to wp-tests-config.php, and enter your database credentials. Use a separate database.

5. Export a variable to point to the _tests/phpunit_ directory in wordpress-develop
  ```
   export WP_TESTS_DIR="/wordpress-develop/tests/phpunit"
  ```

6. Run phpunit from the plugin directory
  ```
  $ vendor/phpunit/phpunit/phpunit
  ```

For more information see
[Wordpress PHPUnit testing](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
