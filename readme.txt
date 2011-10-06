=== Div Shortcode ===
Contributors: billerickson, deanpence
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=4BJ57JH34CA7A
Tags: shortcode, div, columns
Requires at least: 3.0
Tested up to: 3.1.3
Stable tag: 2.0.1

Allows you to create a div by using the shortcodes [div] and [end-div]. 

== Description ==

This plugin implements shortcode-like behavior for [div][end-div] that won't disappear if the plugin is disabled or deleted.

You can use attributes like id="foo", class="bar", and enabled:
[div id="foo" class="bar" enabled]...[end-div]

You can use self-closing shortcodes:
[div id="foo" /]

You can even use nested shortcodes(!):
[div class="outer"][div class="inner"]...[end-div][end-div]

If you disable this plugin, all your content will appear just like it did before--with no shortcodes. (But you won't see the shortcodes in the edit box either. Just re-enable the plugin to see the shortcodes in the edit box again.)

Portions of this code were adapted from shortcodes.php in WordPress 3.2.1.

Note: [div] is not a real shortcode; you cannot use [/div]. Instead, use [end-div].

== Changelog ==

= 2.0.1 =
* Some people have reported issues with the shortcode no longer working. This (hopefully) fixes it.

= 2.0 =
* Completely rebuilt by Dean Hall using pseudo-shortcodes. Content is actually stored as html, so if you disable the plugin the div's still work.


= 1.0 =
* Release of plugin
