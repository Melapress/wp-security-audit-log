=== WP Activity Log ===
Contributors: Melapress
Plugin URI: https://melapress.com/wordpress-activity-log/
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl.html
Tags: activity log, event log, user tracking, logger, history 
Requires at least: 5.5
Tested up to: 7.1.1
Stable tag: 5.6.7
Requires PHP: 7.4

The #1 user-rated activity log plugin for event logging, activity monitoring and change tracking.

== Description ==

### Monitor activity on your WordPress sites and get clear insights into what's happening with detailed user and event logging.

Keep [WordPress logs](https://melapress.com/wordpress-activity-log/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wsal) of everything that happens on your sites and multisite networks with WP Activity Log instantly, without writing a line of code.

- **Easily detect suspicious activity** on your WordPress site before it escalates
- **Record failed login attempts** to detect potential security breaches and strengthen site protection
- **Track user logins and logouts** to ensure SLAs are consistently met
- **Monitor user activity and productivity** to boost accountability
- **Know exactly what all your users are doing** in real time
- **Know what happened before an outage** for faster, easier troubleshooting
- **Ensure compliance with regulations and standards** like GDPR and PCI DSS
- **Better manage & organize your site and users** for smoother operations
- **Simple setup** ensures you start benefiting quickly and easily

WP Activity Log is a complete logging solution, helping hundreds of thousands of administrators and security professionals track changes on their websites thanks to real-time user activity monitoring.

💎 Need more extensive features? Unlock advanced reporting, exports/mirroring, session management, and real-time alerts with [WP Activity Log premium or enterprise](https://melapress.com/wordpress-activity-log/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wsal). 

[youtube https://www.youtube.com/watch?v=pgFEMIvKFTA]
  
### What WordPress changes WP Activity Log tracks
A website activity log is important for improving troubleshooting, compliance, user management, and security.
Get WP Activity Log and keep track of events on your site. The log not only tells you that a post, a user profile, or an object was updated, it also lets you know exactly what changed, when, and includes a user log (by whom), so you always have the information you need.

Below is a summary of the changes that the plugin can keep a record of:

- **Post, page, and custom post type changes**: Status, content changes, title, URL, custom field, and other metadata changes

- **Tags and category changes**: Creating, modifying, deleting, and adding/removing them from posts

- **Widget and menu changes**: Creating, modifying, or deleting them

- **User changes**: User created or registered, deleted, or added to a site on multisite network

- **User profile changes**: Password, email, display name, and role changes

- **Access logging**: User login, logout, failed logins, and terminating other sessions

- **WordPress core and settings changes**: Installed updates, permalinks, default role, URL, and other site-wide changes

- **WordPress multisite network changes**: Adding, deleting, or archiving sites, adding or removing users from sites, etc.

- **Plugin and Theme changes**: Installing, activating, deactivating, uninstalling, and updating

- **WordPress database changes**: When a plugin adds or removes a table

- **Third-party plugin changes**: WooCommerce Stores & products, Yoast SEO, RankMath, Termly, WPForms, Gravity Forms, Advanced Custom Fields (ACF), MainWP, ManageWP, WP Umbrella, and other popular WordPress plugins

- **WordPress site file changes**: New files added, or existing files modified or deleted.

#### Event details recorded
Detailed event logging ensures that for every event that the plugin records, it reports the:

* Date & time (and milliseconds) of when it happened
* User & role of the user who did the change
* Source IP address from where the change happened
* The object on which the change has taken place

Refer to [WordPress activity log event IDs](https://melapress.com/support/kb/wp-activity-log-list-event-ids/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=wsal) for a complete list of all the changes WP Activity Log can keep a record of and a detailed explanation of what change every event ID represents.

### 💎 Upgrade to WP Activity Log Premium and get even more

The premium edition of WP Activity Log takes WordPress user activity tracking to the next level. It comes bundled with even more features, including log mirroring, enterprise-grade support, user session management, and much more! 

#### Premium features list

- **See who is logged in** and monitor their current activities in real-time
- **Log off any user** at the click of a button
- **Generate fully-configurable HTML and CSV reports** for easy data analysis
- **Receive email, SMS, and Slack notifications** for important changes (fully configurable)
- **Use search filters** to fine-tune results and find what you need in seconds
- **Store the activity logs in an external database** to enhance security and scalability
- **Mirror the activity log** to log management systems such as AWS CloudWatch, Loggly, Papertrail, and others in real-time
- **Mirror the logs to business communication systems** like Slack
- **Send a copy of your website's activity log** to a log file on your web server
- **Archive old activity log data** to another database for improved storage and log management
- **Add notes to activity log entries** for better context and internal documentation

Refer to the [WP Activity Log plugin features and benefits page](https://melapress.com/wordpress-activity-log/features/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wsal) to learn more about the benefits of upgrading to WP Activity Log Premium.

### 🔌 WP Activity Log third-party plugin support

All WP Activity Log editions include activity tracking for third-party plugins, including (in alphabetical order):

- **Advanced Custom Fields (ACF)** – Log changes to post types, taxonomies, and taxonomy terms
- **bbPress** – Track changes to forums, topics, and bbPress settings
- **Gravity Forms** – Track changes to Gravity Forms settings, forms, and entries (leads)
- **LearnDash** – Track changes to courses, lessons, and other system changes, as well as student activity such as course, lesson, and quiz enrollments and completions.
- **MemberPress** – Log changes to plugin settings, memberships, payments, subscriptions, and other actions
- **Multisite & management tools** – Track changes across your network for **MainWP, ManageWP, Modular DS, Infinite WP, WP Umbrella, WP Remote**, and other multisite management plugins
- **Paid Membership Pro** - Log changes to membership levels, user assignments, and more. Premium users can also track order and checkout activity, and access a Members Activity panel inside each member’s profile for instant visibility into recent actions.
- **RankMath** – Log changes to RankMath settings, SEO configurations, and on-page SEO edits
- **Redirection** – Keep a log of changes to redirections and redirection groups
- **Termly** – Log changes to Termly settings and configurations
- **WooCommerce** – Keep a log of changes to store settings, orders, products, coupons, and more
- **WPForms** – Log changes to WPForms settings, forms, form files, and entries (leads)
- **Yoast SEO** – Track changes to Yoast SEO settings and on-page SEO in the Yoast SEO meta box


### Extra Features for Enhanced Monitoring and Management
Both free and premium editions of WP Activity Log include a number of non-logging specific features that make the plugin a complete WordPress monitoring solution. Here is what is included:

#### Free
- **Built-in support for reverse proxies and web application firewalls**
- **Integration with WhatIsMyIpAddress.com** – get all information about an IP address with a single click
- **Limit who can view the WordPress activity log** by users or roles
- **Enable or disable individual event IDs** from the activity log
- **Configurable dashboard widget** highlighting the most recent critical activity
- **Configurable WordPress activity log retention policies**
- **Display user avatars** in events for better recognizability
- **And much more!**

#### Premium
Everything that's included in the Free edition, plus:

- **Full WordPress multisite support**
- **Create custom alerts & notifications** to monitor additional functionality
- **Import and export plugin settings**
- **Real-time activity log** visible in the WordPress admin toolbar
- **And much more!**
    
### 🛠️ Free and premium plugin support

 If you encounter any issues with the free edition of WP Activity Log, you can post and get help on the [WordPress.org support forums](https://wordpress.org/support/plugin/wp-security-audit-log/). You can also find more technical information and plugin documentation on the [Melapress knowledge base](https://melapress.com/support/kb/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=wsal).

Premium plugins include a full year of free updates and dedicated one-to-one premium email support. This means you get direct access to our support team who will assist you with any questions or issues related to the plugins.

### As featured on:

- [Kinsta](https://kinsta.com/blog/wordpress-activity-log/)
- [Pagely](https://pagely.com/blog/2015/01/log-wordpress-dashboard-activity-improved-security-auditing/)
- [Shout Me Loud](https://www.shoutmeloud.com/wordpress-security-audit-log.html)
- [The Dev Couple](https://thedevcouple.com/wp-security-audit-log-review/)
- [WPKube](http://www.wpkube.com/improve-wordpress-security-wp-security-audit-log/)
- [Techwibe](https://www.techwibe.com/wp-security-audit-log-wordpress-plugin/)
- [Tidy Repo](https://tidyrepo.com/wp-security-audit-log-wordpress-activity-log/)
- [KitPloit](http://www.kitploit.com/2016/10/wp-security-audit-log-ultimate.html)
- and many others.

### MAINTAINED & SUPPORTED BY MELAPRESS

Melapress develops high-quality WordPress management and security plugins such as Melapress Login Security, WP 2FA, and Melapress Role Editor.

Browse our list of [WordPress security and administration plugins](https://melapress.com/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=wsal) to see how our plugins can help you better manage and improve the security and administration of your WordPress websites and users.

== Installing WP Activity Log ==

=== Install WP Activity Log from within WordPress ===

1. Visit 'Plugins > Add New'
1. Search for 'WP Activity Log'
1. Install and activate the WP Activity Log plugin
1. Allow or skip diagnostic tracking

=== Install WP Activity Log manually ===

1. Extract the plugin ZIP file and upload it to the `/wp-content/plugins/` directory
1. Activate the WP Activity Log plugin from the 'Plugins' menu in WordPress
1. Allow or skip diagnostic tracking

== Frequently Asked Questions ==

= Does the free edition of WP Activity Log limit the data it collects? =
No, the free edition does not limit the data the plugin collects whatsoever. All events logged by the premium edition are available in the free edition. The premium edition adds additional features such as extensive log filtering options, external database/log management service integration, user session management, and much more.

= How long does WP Activity Log keep data for? =
Log data can be kept indefinitely in both free and premium editions. By default, the plugin retains all audit logging data for 3 months however this can be changed from the initial configuration wizards or the plugin's settings ([activity log retention policies](https://melapress.com/support/kb/wp-activity-log-retention-policies/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=wsal)).

= Where does the plugin store the log? =
By default, the log is stored in the WordPress database. WP Activity Log Enterprise edition includes an option to save the activity log to an external database and log mirroring to AWS Cloudwatch, Loggly, Papertrail, Syslog, & other SIEMs

= Can the log be exported? =
Yes, the [log can easily be exported as either CSV or HTML](https://melapress.com/support/kb/wp-activity-log-data-export/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=wsal). The reports module in the Premium plugin is required for this functionality. 

= How does WP Activity Log Improve Security? =
WP Activity Log keeps a record of security-related user and system activities, including logins, logouts, failed login attempts, changes to settings and configurations, and post edits among other things. This security log can not only uncover suspicious or untoward behaviour, but also aid in forensics and post-mortems for quicker recoveries.

= Who can view the activity log? =
By default, all users with administrator role can view the activity log. However, you can restrict this to a specific administrator or access can be given to other users through the plugin's settings. Refer to the [WordPress activity log privileges](https://melapress.com/support/kb/wp-activity-log-managing-plugin-privileges/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=wsal) for more information on how you can do this.

= Does the plugin send any log data to Melapress? =
No, the plugin does not send any log data to us whatsoever. The only data we receive is license data from the premium edition of the plugin.

= Does the plugin receive updates? =
We update the plugin fairly regularly to ensure the plugin continues to run in tip-top shape while adding new features from time to time.

= Will WP Activity Log slow down my website? =
[WP Activity Log will not slow down your website](https://melapress.com/support/kb/wp-activity-log-slow-down-website/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=wsal). Each version is tested before release to ensure it works in the best way possible. Having said that, you need to make sure your WordPress web server has adequate resources to manage the load of your website.

= How do I get support? =
Support for the Free edition of the plugin is provided only via the WordPress.org support forums. You can also refer to our [support pages](https://melapress.com/support/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=wsal) for all the technical and product documentation.

If you are using the Premium edition, you get direct access to our support team via one-to-one [email support](https://melapress.com/support/submit-ticket/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=wsal).

= How can I report security bugs? =
You can report security bugs through the Patchstack Vulnerability Disclosure Program. Please use this [form](https://patchstack.com/database/vdp/wp-security-audit-log). For more details, please refer to our [Melapress plugins security program](https://melapress.com/plugins-security-program/).

= How does WP Activity Log compare to competitors? =

WP Activity Log stands out as one of the most detailed WordPress activity logging solutions on the market. Unlike many competitors, it offers:

- **Unlimited log retention** – keep a complete history of all user and system activity without arbitrary limits  
- **Detailed event information** – see who made each change, when it happened, and from which IP address  
- **Feature-rich monitoring** – including real-time activity tracking, user session management, advanced reporting, alerts, and support for a wide range of third-party plugins  
- **Vulnerability Disclosure Program** – through Patchstack, we allow security researchers to report vulnerabilities responsibly, helping us address potential issues quickly and maintain a secure plugin for all users  

These capabilities make WP Activity Log a **comprehensive solution for site security, troubleshooting, and compliance**, helping administrators and security teams maintain full visibility over their WordPress websites and multisite networks.

== Screenshots ==

1. The WordPress activity logs from where the site administrator can see all the user and site changes.
2. See who is logged in to your WordPress and manage user sessions with User Sessions Management in the Premium edition.
3. The plugin settings from where the site administrator can configure generic plugin settings, such as [reverse proxy support](https://melapress.com/support/kb/wp-activity-log-support-reverse-proxies-web-application-firewalls/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=wsal), who can manage the plugin, etc.
4. Use the event Inspector to see more technical details about each event in the activity log, such as the session ID and UserAgent string.
5. Configuring WordPress email and instant SMS or Slack alerts with the Email, SMS & Slack Notifications module in the Premium edition.
6. Search in the WordPress activity log using filters to fine-tune the search results.
7. The Enable/Disable events section from where Administrators can disable or enable activity log events.
8. The Activity Log Viewer of a Super Admin in a WordPress multisite network installation allows the admin to view the logs from all the network, or filter the logs of a specific site.
9. WP Activity Log is integrated with the built-in revision system of WordPress, thus allowing you to see what content changes users make on your WordPress posts, pages and custom post types. For more information read [Keep Record of All WordPress Content Changes](https://melapress.com/support/kb/wp-activity-log-how-keep-record-of-content-changes/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=wsal)
10. Mirror the WordPress activity log to an external solution such as Syslog or Papertrail to centralize logging in the Premium edition, to ensure logs are always available and cannot be tampered with in the unfortunate case of a hack attack.
11. Use the plugin settings to exclude objects from the logs, configure automatic pruning of events, which timestamp to be shown in the logs, and much more. The plugin is fully configurable.
12. Generate any type of statistics reports from the activity log with the Premium edition. You can see statistics such as number of newly registered users, number of user profile changes, number of logins, different IP addresses per user, and much more.
13. Use the Premium edition to also export any activity log data to an HTML report or CSV file. CSV files are the most widely supported format and can easily be read, parsed, and imported into third-party systems.

== Changelog ==

= 5.6.7 (2026-09-22) =

 * **Security fix**

	 *  Fixed an Administrator SQL Injection responsibly reported by Ananda Dhakal.

 * **Functionality & plugin improvements**

	 *  Updated the External DB mirroring screen to use the plugin’s existing Select2 4.0.13 library instead of the legacy version.
	 *  Improved multisite access checks for event details, the activity log viewer, and the Latest Events widget to respect site-level viewing permissions.
	 *  Improved search input handling in post-title and post-ID searches in plugin settings and Select2 fields.
	 *  Event `2008` (permanent post deletion) now identifies **System** as the actor and displays the server’s IP address for deletions triggered by WordPress cron, scheduled trash cleanup, or WP-CLI.

 * **Bug fixes**

	 *  Fixed user session policies for individual roles requiring the global session policies setting to be enabled.
	 *  Fixed a banner styling conflict with Ultimate WordPress Auction Plugin that affected the activity log layout.
	 *  Fixed missing refund amounts in WooCommerce order refund event `9041`.
	 *  Fixed the custom notifications database table being left behind when uninstalling the plugin with **Delete all data on uninstall** enabled.
	 *  Fixed event `2002` (modified post) being logged when a more specific event, such as `2065` (modified content), already covers the change.
	 *  Fixed a fatal PHP error that prevented application password revocation when BuddyBoss or another plugin updated unrelated user metadata during the request.
	 *  Fixed monthly reports missing activity from the last day of the month.
	 *  Fixed event `9154` (opened a WooCommerce order) being logged again when the order page reloaded after saving changes.
	 *  Fixed the basic search option disappearing after disconnecting a license.
	 *  Fixed a fatal PHP error and missing event `5704` when duplicating a Gravity Forms form without notifications.
	 *  Fixed auto-refresh on the User Sessions page and made the refresh notice available for translation.
	 *  Fixed an issue with WP Mail SMTP’s **Force From Email** setting, which could cause email delivery failures.
	 *  Fixed client IP detection when the selected proxy header contains multiple addresses, rather than recording the full address list or `Unknown`.

Refer to the complete [plugin changelog](https://melapress.com/support/kb/wp-activity-log-plugin-changelog/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=wsal) for more detailed information about what was new, improved and fixed in previous version updates of WP Activity Log.
