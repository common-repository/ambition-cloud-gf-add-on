= Ambition Cloud Extension =
Contributors: ambitioncloud
Tags: Fintelligence, Fintel, AmbitionCloud, Ambition Cloud, Gravity Forms Addon
Author URI: https://fintelligence.com.au
Author: support@fintelligence.com.au
Requires at least: 5.0
Tested up to: 6.1.1
Stable tag: 2.0.6
Requires PHP: 7.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integrates Gravity Forms with Ambition Cloud, allowing form submissions to be automatically sent to your Ambition Cloud account.

== Description ==

Ambition is a cloud based multi-faceted platform by [Fintelligence](https://fintelligence.com.au) that creates an ecosystem that your brokers will live in. Designed by experienced finance asset brokers, [Ambition Cloud](https://fintelligence.com.au/crm-system/) is easy to use and seamlessly integrates into your business. The system can be adapted to suit your work flows and internal processes. It has built in API capabilities to increase your teams productivity and reduce the time it takes to settle a deal. As a business owner, Ambition Cloud  provides insights from your data to better understand and streamline your business.
This plugin extends [Gravity Forms](https://gravityforms.com/) to setup the fields & forms and allow you to push data straight into the Ambition Cloud system (including your campaign tracking). It also allows you to (optionally) forward the user to a more complete application including signing of the privacy consent form.

== Installation ==

<ol>
  <li>Upload the plugin folder ‘gravity-forms-custom-post-types’ to your <code>/wp-content/plugins/</code> folder</li>
  <li>Activate the plugin through the ‘Plugins’ menu in WordPress</li>
  <li>Make sure you also have Gravity Forms activated.</li>
  <li>Once activated go to the Ambition Cloud section under your Gravity Forms settings to add your system URL, tenant key and create a new form.</li>
</ol>

For each form you can create a new feed that links the fields from your form to the corresponding field in Ambition Cloud.  There is also an option to return a link to one of the hosted forms to complete a full application.
To automatically redirect the user on submission of your Gravity Form, go to the confirmations section and add the follwing in the redirect URL.
fast-track={ambition:fast_track_link}

== Screenshots ==

== Changelog ==
= 2.0.5 =
- Fixed error preventing custom fields from being created

= 1.2 =
- Fixed an issue that causes the plugin settings to always show the TENANT Url and Key as invalid even when they are correct.

= 1.1 =
- Added support for Gravity Forms 2.5.

= 1.0 =
- It's all new!
