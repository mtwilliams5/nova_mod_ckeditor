# CKEditor Plugin for Nova 2
This mod has been tested to work on Nova 2.4.x. It may work on other versions of Nova, but has not been tested for such.

# IMPORTANT!
This mod alters the default behaviour of the following functions in Nova's controllers. If you have already altered these functions in any way, you will need to resolve any conflicts between the two states of the controller files manually. If these functions are altered in any way in a Nova upgrade then sites running this mod will not see those changes until this mod is updated to also include said changes.
Below is a list of the modified controllers and the functions within them:
* `application/controllers/manage.php`
  * logs
  * news
  * posts
* `application/controllers/messages.php`
  * write
* `application/controllers/write.php`
  * missionpost
  * newsitem
  * personallog

## Installation
Download the files included in the mod to your computer (minus this README.md). The following files will need altering before the plugin can function:
* `application/config/ckeditor/posts.js`
* `application/config/ckeditor/news_messages.js`

In both files, you will need to make the following edits:
* Change the `config.baseHref` value to your site's root URL
* Change the `config.contentsCss` value to the path of the content.css file of your site (the path from `/application/` onwards is already present)

If your site is installed to a subdirectory (e.g. `example.com/nova/`), you must also edit the following files, adding said directory to the `customConfig` value:
* `application/config/ckeditor/manage_logs_js.php`
* `application/config/ckeditor/manage_news_js.php`
* `application/config/ckeditor/manage_posts_js.php`
* `application/config/ckeditor/messages_write_js.php`
* `application/config/ckeditor/write_missionpost_js.php`
* `application/config/ckeditor/write_newsitem_js.php`
* `application/config/ckeditor/write_personallog_js.php`

If you use a skin other than default or titan, then you will need to find the following line in the skin's `template_admin.php` file:  
`<?php include_once($this->config->item('include_head_admin'));?>`  
And after it add the following:
`<script src="//cdn.ckeditor.com/4.6.2/standard/ckeditor.js"></script>`

You may need to alter the default styles for the editor content area to match your skin. These styles are stored in `application/views/_base_override/admin/css/content.css`. Changes to this file will affect all skins which pull in CKEditor. If you wish to allow changes to this file on a per-skin basis, follow the instructions [here](https://github.com/mtwilliams5/nova_mod_ckeditor/wiki/Advanced-Configuration)