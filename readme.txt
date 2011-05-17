=== Cleeng Content Monetization ===
Contributors: mtymek, Donald Res
Tags: cleeng, content monetization, micropayment, protect, monetize, money, earn money, social payment
Requires at least: 2.9.2
Tested up to: 3.1.2
Stable tag: 1.1.2
License: New BSD License

Cleeng for WordPress helps you to make money with your digital content. It allows you to easily 
charge for your text, images and videos.

== Description ==

Cleeng makes one-click content monetization incredibly easy. The solution allows bloggers and 
publishers to sell as much or as little of their digital content as they want. Visitors of a blog or website using Cleeng get a frictionless experience. 
They pay with one click and get access to the protected content instantly.

See http://cleeng.com/features for 5 good reasons why to use Cleeng.

Cleeng enables website publishers, bloggers and other content creators to generate
micro payments and make (incremental) money from their creations. For visitors it simplifies
the access to unique quality content by offering a one-click, pay as you go
solution. It works on web-enabled devices and for any kind
of online content and avoids the hassle of multiple subscriptions.

With the Cleeng for WordPress plugin you can start monetizing your content
within minutes. After installation of Cleeng and signing up as a publisher,
within the editor you can simply select the content you want to protect and click
the green button "Create Cleeng Content from Selection". You can define
the description and the price and after clicking save, two tags appear within
your editing field that mark your protected content. When you publish the
content the price and other references are saved on the Cleeng server (via
the Cleeng API). After publication visitors coming to your web-page will be
able to read and view the page normally, however part of the content is
protected. Now your visitors can with the one-click, pay as you go solution
instantly access this content.

Have a try: it really works this easy!


== Installation ==

1. Upload Cleeng For WordPress to your wp-content/plugins directory.
2. Activate it in "Plugins" menu in WordPress.
3. Within the right hand site of your edit pages (blog and pages)
   the Cleeng widget should appear.
4. To be able to protect and charge for your content, you need
   to sign-up with Cleeng as a publisher. Check http://cleeng.com for more information


== Frequently Asked Questions ==

You can find the FAQ on http://cleeng.com/support or read and contribute on the Publisher 
Community on http://cleeng.com/forum/publishers/

== Screenshots ==

1. This is how Cleeng layer looks when the visitor sees Cleeng for the first time
2. This is how Cleeng layer looks when the visitor is registered
3. Purchase confirmation screen - all new users get 2 free credits
4. Creating new content (Admin)
5. Cleeng Widget (Admin)

== Changelog ==

= 1.1.2 =
* fixed admin login

= 1.1.1 =
* improvements in CSS file
* removed call to error_reporting from ajax.php (should help for external plugins generating E_NOTICE)
* "what is Cleeng" link opens in new window
* use window.postMessage to communicate between popup and main window if possible

= 1.1.0 =
* 1.1 embedded full PayPal Digital Goods support

= 1.0.3 =
* fixed wordpress claiming that Cleeng is not in latest version

= 1.0.2 =
* support for translations 
* minor visual improvements

= 1.0.1 =
* compatibility with WordPress 3.1
* try to load content again with ajax if it fails on the backend
* fixed behaviour when revealing content after user is logged in
* compatibility check on startup
* made plugin more "tolerant" to special situations like connection problems  
  or server errors
* now api.cleeng.com is used as JSON-RPC endpoint

= 1.0 =
* official release

= 0.9.7 =
* improved the **layout of the layer**: cleaner, simpler, focused on ONE given transaction.
* improved the **color scheme**, to better integrate within most common sites
* publisher/bloggers **co-branding** to increase user confidence
* improved support for **multiple content types**: article, chart, file, image, spreadsheet, video (embed)
* different behavior for users who encounter Cleeng for the first time
* improved usability of the bottom bar (visible after content is revealed)
* improved regular expressions
* other fixes

= 0.9.6 =
* made plugin compatible with jQuery 1.3.2
* made plugin compatible with WordPress 2.9.2
* fixed shadow in IE browsers
* added screenshots and updated readme.txt

= 0.9.5 =
* added readme.txt and licensing information

= 0.9.4 =
* disable WP SuperCache plugin for pages with Cleeng content

= 0.9.3 =
* content information is fetched from Cleeng on server side - no more
  waiting until Javascript loads, purchased content appears immediately
  after user refreshes page;
* fixed compatibility issues related to Internet Explorer family browsers;
* Fixed compatibility with different WP templates
* fixed lots of small issues/bugs
