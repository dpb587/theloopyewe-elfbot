A bot for the elves' workstations. You probably won't find this very useful. If we can improve here, let us know!

Learn more about the background of this project from [this post](http://dpb587.me/blog/2015/02/21/sending-work-from-a-web-application-to-desktop-applications.html).


## Requirements

A recent version of PHP. If you're on an outdated OS X system, you can try installing [Liip's PHP package](http://php-osx.liip.ch/):

    $ curl -s http://php-osx.liip.ch/install.sh | bash -s 5.5

If you are working from source, you will need [composer](https://getcomposer.org/):

    $ curl -sS https://getcomposer.org/installer | php

If you are creating releases, you will need [jq](http://stedolan.github.io/jq/) and [bumpversion](https://pypi.python.org/pypi/bumpversion).


## Configuration

Configure the endpoints, queue, and tasks in a JSON file. Get started from the [`etc/example.json`](./etc/example.json)
file.


## Installation


### Deploy


    # download the PHAR from the latest release
    $ open https://github.com/theloopyewe/elfbot/releases/latest
    $ chmod +x ~/Applications/elfbot.phar

    # run it
    $ ~/Applications/elfbot.phar -vvv \
      --config-file="${HOME}/Library/Preferences/com.theloopyewe.elfbot.default.json" \
      run

    # or install and start it as a launchd agent
    $ ~/Applications/elfbot.phar -vvv \
      --config-file="${HOME}/Library/Preferences/com.theloopyewe.elfbot.default.json" \
      install-launchd \
      --start \
      --executable=/usr/local/php5-5.5.5-20131020-222726/bin/php \
      com.theloopyewe.elfbot.default.n0


### Development

    $ git clone git@github.com:theloopyewe/elfbot.git
    $ cd elfbot/
    $ composer.phar install
    $ ./bin/console -vvv --config-file=etc/local.json run


#### Release

    # write some release notes
    $ vim release.md

    # publish
    $ ./bin/build-publish patch release.md

    # cleanup
    $ rm release.md


## License

[MIT License](./LICENSE)
