Reclaim Social Media
====================

Reclaim Social Media version .2, based on Reclaim Social Media version .1 by
[Felix Schwenzel](http://reclaim.fm) & Sascha Lobo.

It’s a single WordPress plugin by Remigi Illi at [digitaleheimat.de](http://digitaleheimat.de), instead of a collection of proxy-scripts and 3rd party plugins. The code of the early version can be found at GitHub ([proxy scripts](https://github.com/diplix/reclaim-proxy-scripts)) and the [reclaim.fm site](http://reclaim.fm/tech-specs-details/).

The plugin is still pretty raw but should be a good foundation for further development.

## What it does
Right now reclaim grabs your tweets, Google+ and Facebook posts, Flickr and Instagram images, YouTube and Vine videos and imports them as WordPress posts. It is written in a modular fashion, which enables anyone to easily write further importer modules for other services.

If reclaim grabs a tweet, it constructs an embed code with clickable hashtags, mentions and real links (unshortened t.co links). To do that, you need to get an API key from Twitter. This is a little complicated, but very much on purpose. We don't want to create a central application, that can be shut down at will by Twitter, but instead many decentralized apps with individual API keys, that can not be shut down easily.

The Twitter embed code is being rendered with Twitter's widget.js, which is a little invasive on users privacy, but looks nice. Also, the embed-code is fully searchable within WordPress. In case an image is attached, it is being saved in the WordPress media library and set as the featured image. Note that with some themes this may cause the image to be shown twice.

The idea is to copy all the relevant data from the services you use and to enable you to keep a copy on your own server.

## Demo
http://root.wirres.net/reclaim/

## Installation
Download a [release](https://github.com/espresto/reclaim-social-media/releases) and extract it in your WordPress installation under "wp-content/plugins".

Alternatively you can install the latest version from git. Go to your WordPress installation path and follow these instructions:

    cd wp-content/plugins
    git clone git://github.com/espresto/reclaim-social-media reclaim
    cd reclaim
    curl -sS https://getcomposer.org/installer | php
    php composer.phar install

Always remember, Reclaim Social Media is still under heavy development and the plugin is not considered stable yet.
