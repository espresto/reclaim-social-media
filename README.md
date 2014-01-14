reclaim social media
====================

reclaim social media version .2, based on reclaim social media version .1 by [felix schwenzel](http://reclaim.fm), sascha lobo. 

it’s a single wordpress plugin by remigi illi at [digitaleheimat.de](http://digitaleheimat.de), instead of a collection of proxy-scripts and 3rd party plugins. The code of the early version can be fount at github ([proxy scripts]( https://github.com/diplix/reclaim-proxy-scripts)) and the [reclaim.fm site](http://reclaim.fm/tech-specs-details/).

The plugin is still pretty raw but should be a pretty good basis for further develpment.

what it does
------------

right now reclaim grabs your tweets, google+ posts, flickr images and youtube movies and creates wordpress posts with them. 

if reclaim grabs a tweet it constructs an embed code with clickable hashtags and mentions and real links. not t.co links. to do that, you need to create a developer account at twitter and enter a couple of keys. this is a little complicated, but very much on purpose. we don’t want to create a central application, that can be shut down at will by twitter, but many, decentralized apps with individual developer keys, that can not be shut down easily.

the twitter embed code is being rendered with twitters widget.js javascript, which is a little invasive on users privacy but looks nice. also, the embedcode is fully searchable within wordpress. in case an image is attached, it is being saved in the wordpress media library and set as featured image. this results in some themes, taht show the image twice.

the idea is, to copy all the relevant data from the services you use and enable you, to keep a copy on your own server.

demo
----

http://root.wirres.net/reclaim/

installation
------------

download a [release](https://github.com/espresto/reclaim-social-media/releases) or clone the repository. if you clone, don't forget to run composer. also remember, the plugin is not yet stable.
