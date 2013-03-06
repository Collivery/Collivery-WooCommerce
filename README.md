MDS Collivery Shipping Module
=============================

*MDS Collivery Shipping Module for WooCommerce, an eCommerce plugin for Wordpress.*

Adds the cost of shipping to the clients final price, and allows you to register the shipping from within the plugin.

Warning!
--------

This plugin is still in beta. Use at own risk!

To Do
------

- ~~Check Compatibility with WC 2.0~~
- Save Client/Address ID for next time
 - If a client has a registered username, hash all their details together with the Client/Address ID. When they visit again, if the hash is the same, use the ID again.
 - ~~If a client isn't registered, save the information within the order~~
- Allow Admins to change and re-validate shipping info.
- Allow Admins to choose which services can be used.
- Improve Admin Settings page
 - Display Default Address
 - ~~Get Town Brief and Location Type from Default Address~~
- Clean-up code for registering shipment.
 - Can be coded better, was in a rush to get it finished.
- Catch all Soap Exceptions and display a useful message.

Installation Instructions
-------------------------
Extract the zip into "wp-content/plugins".
Activate the plugin under "Plugins" in the Wordpress Admin side.
While testing, keep the settings on the demo account, as MDS ignores all the requests on that account. But once you are ready to go live and place it on your site, go to "WooCommerce->Settings->Shipping->MDS Collivery" and put in your own details there.
I also strongly recommend the 10% markup (or higher) while the plugin is in beta, as to cover costs if there ever is a change in price...

License
--------

MDS Collivery Shipping Module for WooCommerce is distributed under the terms of the GNU General Public License, version 3 or later.