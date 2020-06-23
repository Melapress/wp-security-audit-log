<?php
/**
 * Browser Information Class
 *
 * Browser Information Class of the plugin.
 *
 * @since   3.2.2
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * File: Browser.php
 * Author: Chris Schuld (http://chrisschuld.com/)
 * Last Modified: August 20th, 2010
 *
 * Copyright (C) 2008-2010 Chris Schuld  (chris@chrisschuld.com)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details at:
 * http://www.gnu.org/copyleft/gpl.html
 *
 *
 * Typical Usage:
 *
 *   $browser = new Browser();
 *   if( $browser->getBrowser() == Browser::BROWSER_FIREFOX && $browser->getVersion() >= 2 ) {
 *    echo 'You have FireFox version 2 or greater';
 *   }
 *
 * User Agents Sampled from: http://www.useragentstring.com/
 *
 * This implementation is based on the original work from Gary White
 * http://apptools.com/phptools/browser/
 *
 * UPDATES:
 *
 * 2010-08-20 (v1.9):
 *  + Added MSN Explorer Browser (legacy)
 *  + Added Bing/MSN Robot (Thanks Rob MacDonald)
 *  + Added the Android Platform (PLATFORM_ANDROID)
 *  + Fixed issue with Android 1.6/2.2 (Thanks Tom Hirashima)
 *
 * 2010-04-27 (v1.8):
 *  + Added iPad Support
 *
 * 2010-03-07 (v1.7):
 *  + *MAJOR* Rebuild (preg_match and other "slow" routine removal(s))
 *  + Almost allof Gary's original code has been replaced
 *  + Large PHPUNIT testing environment created to validate new releases and additions
 *  + Added FreeBSD Platform
 *  + Added OpenBSD Platform
 *  + Added NetBSD Platform
 *  + Added SunOS Platform
 *  + Added OpenSolaris Platform
 *  + Added support of the Iceweazel Browser
 *  + Added isChromeFrame() call to check if chromeframe is in use
 *  + Moved the Opera check in front of the Firefox check due to legacy Opera User Agents
 *  + Added the __toString() method (Thanks Deano)
 *
 * 2009-11-15:
 *  + Updated the checkes for Firefox
 *  + Added the NOKIA platform
 *  + Added Checks for the NOKIA brower(s)
 *
 * 2009-11-08:
 *  + PHP 5.3 Support
 *  + Added support for BlackBerry OS and BlackBerry browser
 *  + Added support for the Opera Mini browser
 *  + Added additional documenation
 *  + Added support for isRobot() and isMobile()
 *  + Added support for Opera version 10
 *  + Added support for deprecated Netscape Navigator version 9
 *  + Added support for IceCat
 *  + Added support for Shiretoko
 *
 * 2010-04-27 (v1.8):
 *  + Added iPad Support
 *
 * 2009-08-18:
 *  + Updated to support PHP 5.3 - removed all deprecated function calls
 *  + Updated to remove all double quotes (") -- converted to single quotes (')
 *
 * 2009-04-27:
 *  + Updated the IE check to remove a typo and bug (thanks John)
 *
 * 2009-04-22:
 *  + Added detection for GoogleBot
 *  + Added detection for the W3C Validator.
 *  + Added detection for Yahoo! Slurp
 *
 * 2009-03-14:
 *  + Added detection for iPods.
 *  + Added Platform detection for iPhones
 *  + Added Platform detection for iPods
 *
 * 2009-02-16: (Rick Hale)
 *  + Added version detection for Android phones.
 *
 * 2008-12-09:
 *  + Removed unused constant
 *
 * 2008-11-07:
 *  + Added Google's Chrome to the detection list
 *  + Added isBrowser(string) to the list of functions special thanks to
 *    Daniel 'mavrick' Lang for the function concept (http://mavrick.id.au)
 *
 *
 * Gary White noted: "Since browser detection is so unreliable, I am
 * no longer maintaining this script. You are free to use and or
 * modify/update it as you want, however the author assumes no
 * responsibility for the accuracy of the detected values."
 *
 * Anyone experienced with Gary's script might be interested in these notes:
 *
 *   Added class constants
 *   Added detection and version detection for Google's Chrome
 *   Updated the version detection for Amaya
 *   Updated the version detection for Firefox
 *   Updated the version detection for Lynx
 *   Updated the version detection for WebTV
 *   Updated the version detection for NetPositive
 *   Updated the version detection for IE
 *   Updated the version detection for OmniWeb
 *   Updated the version detection for iCab
 *   Updated the version detection for Safari
 *   Updated Safari to remove mobile devices (iPhone)
 *   Added detection for iPhone
 *   Added detection for robots
 *   Added detection for mobile devices
 *   Added detection for BlackBerry
 *   Removed Netscape checks (matches heavily with firefox & mozilla)
 *
 * @version 1.9
 * @package PegasusPHP
 */
class WSAL_Browser {

	/**
	 * Agent
	 *
	 * @var string
	 */
	public $_agent = '';

	/**
	 * Browser name
	 *
	 * @var string
	 */
	public $_browser_name = '';

	/**
	 * Version
	 *
	 * @var string
	 */
	public $_version = '';

	/**
	 * Platform
	 *
	 * @var string
	 */
	public $_platform = '';

	/**
	 * OS
	 *
	 * @var string
	 */
	public $_os = '';

	/**
	 * _is_aol
	 *
	 * @var bool
	 */
	public $_is_aol = false;

	/**
	 * _is_mobile
	 *
	 * @var bool
	 */
	public $_is_mobile = false;

	/**
	 * _is_robot
	 *
	 * @var bool
	 */
	public $_is_robot = false;

	/**
	 * _aol_version
	 *
	 * @var string
	 */
	public $_aol_version = '';


	/**
	 * BROWSER_UNKNOWN
	 *
	 * @var string
	 */
	public $BROWSER_UNKNOWN = 'unknown';

	/**
	 * VERSION_UNKNOWN
	 *
	 * @var string
	 */
	public $VERSION_UNKNOWN = 'unknown';


	/**
	 * BROWSER_OPERA
	 *
	 * @var string
	 */
	public $BROWSER_OPERA = 'Opera';                            // URL: http://www.opera.com/.

	/**
	 * BROWSER_OPERA_MINI
	 *
	 * @var string
	 */
	public $BROWSER_OPERA_MINI = 'Opera Mini';                  // URL: http://www.opera.com/mini/.

	/**
	 * BROWSER_WEBTV
	 *
	 * @var string
	 */
	public $BROWSER_WEBTV = 'WebTV';                            // URL: http://www.webtv.net/pc/.

	/**
	 * BROWSER_IE
	 *
	 * @var string
	 */
	public $BROWSER_IE = 'Internet Explorer';                   // URL: http://www.microsoft.com/ie/.

	/**
	 * BROWSER_POCKET_IE
	 *
	 * @var string
	 */
	public $BROWSER_POCKET_IE = 'Pocket Internet Explorer';     // URL: http://en.wikipedia.org/wiki/Internet_Explorer_Mobile.

	/**
	 * BROWSER_KONQUEROR
	 *
	 * @var string
	 */
	public $BROWSER_KONQUEROR = 'Konqueror';                    // URL: http://www.konqueror.org/.

	/**
	 * BROWSER_ICAB
	 *
	 * @var string
	 */
	public $BROWSER_ICAB = 'iCab';                              // URL: http://www.icab.de/.

	/**
	 * BROWSER_OMNIWEB
	 *
	 * @var string
	 */
	public $BROWSER_OMNIWEB = 'OmniWeb';                        // URL: http://www.omnigroup.com/applications/omniweb/.

	/**
	 * BROWSER_FIREBIRD
	 *
	 * @var string
	 */
	public $BROWSER_FIREBIRD = 'Firebird';                      // URL: http://www.ibphoenix.com/.

	/**
	 * BROWSER_FIREFOX
	 *
	 * @var string
	 */
	public $BROWSER_FIREFOX = 'Firefox';                        // URL: http://www.mozilla.com/en-US/firefox/firefox.html.

	/**
	 * BROWSER_ICEWEASEL
	 *
	 * @var string
	 */
	public $BROWSER_ICEWEASEL = 'Iceweasel';                    // URL: http://www.geticeweasel.org/.

	/**
	 * BROWSER_SHIRETOKO
	 *
	 * @var string
	 */
	public $BROWSER_SHIRETOKO = 'Shiretoko';                    // URL: http://wiki.mozilla.org/Projects/shiretoko.

	/**
	 * BROWSER_MOZILLA
	 *
	 * @var string
	 */
	public $BROWSER_MOZILLA = 'Mozilla';                        // URL: http://www.mozilla.com/en-US/.

	/**
	 * BROWSER_AMAYA
	 *
	 * @var string
	 */
	public $BROWSER_AMAYA = 'Amaya';                            // URL: http://www.w3.org/Amaya/.

	/**
	 * BROWSER_LYNX
	 *
	 * @var string
	 */
	public $BROWSER_LYNX = 'Lynx';                              // URL: http://en.wikipedia.org/wiki/Lynx.

	/**
	 * BROWSER_SAFARI
	 *
	 * @var string
	 */
	public $BROWSER_SAFARI = 'Safari';                          // URL: http://apple.com.

	/**
	 * BROWSER_IPHONE
	 *
	 * @var string
	 */
	public $BROWSER_IPHONE = 'iPhone';                          // URL: http://apple.com.

	/**
	 * BROWSER_IPOD
	 *
	 * @var string
	 */
	public $BROWSER_IPOD = 'iPod';                              // URL: http://apple.com.

	/**
	 * BROWSER_IPAD
	 *
	 * @var string
	 */
	public $BROWSER_IPAD = 'iPad';                              // URL: http://apple.com.

	/**
	 * BROWSER_CHROME
	 *
	 * @var string
	 */
	public $BROWSER_CHROME = 'Chrome';                          // URL: http://www.google.com/chrome.

	/**
	 * BROWSER_ANDROID
	 *
	 * @var string
	 */
	public $BROWSER_ANDROID = 'Android';                        // URL: http://www.android.com/.

	/**
	 * BROWSER_GOOGLEBOT
	 *
	 * @var string
	 */
	public $BROWSER_GOOGLEBOT = 'GoogleBot';                    // URL: http://en.wikipedia.org/wiki/Googlebot.

	/**
	 * BROWSER_SLURP
	 *
	 * @var string
	 */
	public $BROWSER_SLURP = 'Yahoo! Slurp';                     // URL: http://en.wikipedia.org/wiki/Yahoo!_Slurp.

	/**
	 * BROWSER_W3CVALIDATOR
	 *
	 * @var string
	 */
	public $BROWSER_W3CVALIDATOR = 'W3C Validator';             // URL: http://validator.w3.org/.

	/**
	 * BROWSER_BLACKBERRY
	 *
	 * @var string
	 */
	public $BROWSER_BLACKBERRY = 'BlackBerry';                  // URL: http://www.blackberry.com/.

	/**
	 * BROWSER_ICECAT
	 *
	 * @var string
	 */
	public $BROWSER_ICECAT = 'IceCat';                          // URL: http://en.wikipedia.org/wiki/GNU_IceCat.

	/**
	 * BROWSER_NOKIA_S60
	 *
	 * @var string
	 */
	public $BROWSER_NOKIA_S60 = 'Nokia S60 OSS Browser';        // URL: http://en.wikipedia.org/wiki/Web_Browser_for_S60.

	/**
	 * BROWSER_NOKIA
	 *
	 * @var string
	 */
	public $BROWSER_NOKIA = 'Nokia Browser';                    // * All other WAP-based browsers on the Nokia Platform.

	/**
	 * BROWSER_MSN
	 *
	 * @var string
	 */
	public $BROWSER_MSN = 'MSN Browser';                        // URL: http://explorer.msn.com/.

	/**
	 * BROWSER_MSNBOT
	 *
	 * @var string
	 */
	public $BROWSER_MSNBOT = 'MSN Bot';                         // URL: http://search.msn.com/msnbot.htm. URL: http://en.wikipedia.org/wiki/Msnbot  (used for Bing as well).

	/**
	 * BROWSER_NETSCAPE_NAVIGATOR
	 *
	 * @var string
	 */
	public $BROWSER_NETSCAPE_NAVIGATOR = 'Netscape Navigator';  // URL: http://browser.netscape.com/ (DEPRECATED).

	/**
	 * BROWSER_GALEON
	 *
	 * @var string
	 */
	public $BROWSER_GALEON = 'Galeon';                          // URL: http://galeon.sourceforge.net/ (DEPRECATED).

	/**
	 * BROWSER_NETPOSITIVE
	 *
	 * @var string
	 */
	public $BROWSER_NETPOSITIVE = 'NetPositive';                // URL: http://en.wikipedia.org/wiki/NetPositive (DEPRECATED).

	/**
	 * BROWSER_PHOENIX
	 *
	 * @var string
	 */
	public $BROWSER_PHOENIX = 'Phoenix';                        // URL: http://en.wikipedia.org/wiki/History_of_Mozilla_Firefox (DEPRECATED).


	/**
	 * PLATFORM_UNKNOWN
	 *
	 * @var string
	 */
	public $PLATFORM_UNKNOWN = 'unknown';

	/**
	 * PLATFORM_WINDOWS
	 *
	 * @var string
	 */
	public $PLATFORM_WINDOWS = 'Windows';

	/**
	 * PLATFORM_WINDOWS_CE
	 *
	 * @var string
	 */
	public $PLATFORM_WINDOWS_CE = 'Windows CE';

	/**
	 * PLATFORM_APPLE
	 *
	 * @var string
	 */
	public $PLATFORM_APPLE = 'Apple';

	/**
	 * PLATFORM_LINUX
	 *
	 * @var string
	 */
	public $PLATFORM_LINUX = 'Linux';

	/**
	 * PLATFORM_OS2
	 *
	 * @var string
	 */
	public $PLATFORM_OS2 = 'OS/2';

	/**
	 * PLATFORM_BEOS
	 *
	 * @var string
	 */
	public $PLATFORM_BEOS = 'BeOS';

	/**
	 * PLATFORM_IPHONE
	 *
	 * @var string
	 */
	public $PLATFORM_IPHONE = 'iPhone';

	/**
	 * PLATFORM_IPOD
	 *
	 * @var string
	 */
	public $PLATFORM_IPOD = 'iPod';

	/**
	 * PLATFORM_IPAD
	 *
	 * @var string
	 */
	public $PLATFORM_IPAD = 'iPad';

	/**
	 * PLATFORM_BLACKBERRY
	 *
	 * @var string
	 */
	public $PLATFORM_BLACKBERRY = 'BlackBerry';

	/**
	 * PLATFORM_NOKIA
	 *
	 * @var string
	 */
	public $PLATFORM_NOKIA = 'Nokia';

	/**
	 * PLATFORM_FREEBSD
	 *
	 * @var string
	 */
	public $PLATFORM_FREEBSD = 'FreeBSD';

	/**
	 * PLATFORM_OPENBSD
	 *
	 * @var string
	 */
	public $PLATFORM_OPENBSD = 'OpenBSD';

	/**
	 * PLATFORM_NETBSD
	 *
	 * @var string
	 */
	public $PLATFORM_NETBSD = 'NetBSD';

	/**
	 * PLATFORM_SUNOS
	 *
	 * @var string
	 */
	public $PLATFORM_SUNOS = 'SunOS';

	/**
	 * PLATFORM_OPENSOLARIS
	 *
	 * @var string
	 */
	public $PLATFORM_OPENSOLARIS = 'OpenSolaris';

	/**
	 * PLATFORM_ANDROID
	 *
	 * @var string
	 */
	public $PLATFORM_ANDROID = 'Android';

	/**
	 * OPERATING_SYSTEM_UNKNOWN
	 *
	 * @var string
	 */
	public $OPERATING_SYSTEM_UNKNOWN = 'unknown';

	/**
	 * Method: Constructor.
	 *
	 * @param string $useragent - User agent.
	 */
	public function __construct( $useragent = '' ) {
		$this->reset();
		if ( '' != $useragent ) {
			$this->setUserAgent( $useragent );
		} else {
			$this->determine();
		}
	}

	/**
	 * Reset all properties
	 */
	public function reset() {
		$http_user_agent = filter_input( INPUT_SERVER, 'HTTP_USER_AGENT', FILTER_SANITIZE_STRING );
		$this->_agent = ! empty( $http_user_agent ) ? $http_user_agent : '';
		$this->_browser_name = $this->BROWSER_UNKNOWN;
		$this->_version = $this->VERSION_UNKNOWN;
		$this->_platform = $this->PLATFORM_UNKNOWN;
		$this->_os = $this->OPERATING_SYSTEM_UNKNOWN;
		$this->_is_aol = false;
		$this->_is_mobile = false;
		$this->_is_robot = false;
		$this->_aol_version = $this->VERSION_UNKNOWN;
	}

	/**
	 * Check to see if the specific browser is valid
	 *
	 * @param string $browser_name - Browser name.
	 * @return True if the browser is the specified browser
	 */
	public function isBrowser( $browser_name ) {
		return 0 == strcasecmp( $this->_browser_name, trim( $browser_name ) );
	}

	/**
	 * The name of the browser.  All return types are from the class contants
	 *
	 * @return string Name of the browser
	 */
	public function getBrowser() {
		return $this->_browser_name;
	}

	/**
	 * Set the name of the browser
	 *
	 * @param string $browser - The name of the Browser.
	 */
	public function setBrowser( $browser ) {
		$this->_browser_name = $browser;
	}

	/**
	 * The name of the platform.  All return types are from the class contants
	 *
	 * @return string Name of the browser
	 */
	public function getPlatform() {
		return $this->_platform;
	}

	/**
	 * Set the name of the platform
	 *
	 * @param string $platform - The name of the Platform.
	 */
	public function setPlatform( $platform ) {
		$this->_platform = $platform;
	}

	/**
	 * The version of the browser.
	 *
	 * @return string Version of the browser (will only contain alpha-numeric characters and a period)
	 */
	public function getVersion() {
		return $this->_version;
	}


	/**
	 * Set the version of the browser
	 *
	 * @param string $version - The version of the Browser.
	 */
	public function setVersion( $version ) {
		$this->_version = preg_replace( '/[^0-9,.,a-z,A-Z-]/', '', $version );
	}

	/**
	 * The version of AOL.
	 *
	 * @return string Version of AOL (will only contain alpha-numeric characters and a period)
	 */
	public function getAolVersion() {
		return $this->_aol_version;
	}

	/**
	 * Set the version of AOL
	 *
	 * @param string $version - The version of AOL.
	 */
	public function setAolVersion( $version ) {
		$this->_aol_version = preg_replace( '/[^0-9,.,a-z,A-Z]/', '', $version );
	}

	/**
	 * Is the browser from AOL?
	 *
	 * @return boolean True if the browser is from AOL otherwise false
	 */
	public function isAol() {
		return $this->_is_aol;
	}

	/**
	 * Is the browser from a mobile device?
	 *
	 * @return boolean True if the browser is from a mobile device otherwise false
	 */
	public function isMobile() {
		return $this->_is_mobile;
	}

	/**
	 * Is the browser from a robot (ex Slurp,GoogleBot)?
	 *
	 * @return boolean True if the browser is from a robot otherwise false
	 */
	public function isRobot() {
		return $this->_is_robot;
	}

	/**
	 * Set the browser to be from AOL
	 *
	 * @param bool $isAol - True/False.
	 */
	public function setAol( $isAol ) {
		$this->_is_aol = $isAol;
	}

	/**
	 * Set the Browser to be mobile
	 *
	 * @param boolean $value - is the browser a mobile brower or not.
	 */
	public function setMobile( $value = true ) {
		$this->_is_mobile = $value;
	}

	/**
	 * Set the Browser to be a robot
	 *
	 * @param boolean $value - is the browser a robot or not.
	 */
	public function setRobot( $value = true ) {
		$this->_is_robot = $value;
	}

	/**
	 * Get the user agent value in use to determine the browser
	 *
	 * @return string The user agent from the HTTP header
	 */
	public function getUserAgent() {
		return $this->_agent;
	}

	/**
	 * Set the user agent value (the construction will use the HTTP header value - this will overwrite it)
	 *
	 * @param string $agent_string - The value for the User Agent.
	 */
	public function setUserAgent( $agent_string ) {
		$this->reset();
		$this->_agent = $agent_string;
		$this->determine();
	}

	/**
	 * Used to determine if the browser is actually "chromeframe"
	 *
	 * @since 1.7
	 * @return boolean True if the browser is using chromeframe
	 */
	public function isChromeFrame() {
		return strpos( $this->_agent, 'chromeframe' ) !== false;
	}

	/**
	 * Returns a formatted string with a summary of the details of the browser.
	 *
	 * @return string formatted string with a summary of the browser
	 */
	public function __toString() {
		$text1   = $this->getUserAgent(); // Grabs the UA (user agent) string.
		$ua_line1 = substr( $text1, 0, 32 ); // The first line we print should only be the first 32 characters of the UA string.
		$text2       = $this->getUserAgent(); // Now we grab it again and save it to a string.
		$towrap_ua    = str_replace( $ua_line1, '', $text2 ); // The rest of the printoff (other than first line) is equivolent.

		/**
		 * To the whole string minus the part we printed off. IE
		 * User Agent:      thefirst32charactersfromUAline1
		 *                  the rest of it is now stored in
		 *                  $text2 to be printed off
		 * But we need to add spaces before each line that is split other than line 1
		 */
		$space = '';
		for ( $i = 0; $i < 25; $i++ ) {
			$space .= ' ';
		}

		// Now we split the remaining string of UA ($text2) into lines that are prefixed by spaces for formatting.
		$wordwrapped = chunk_split( $towrap_ua, 32, "\n $space" );
		return "Platform:                 {$this->getPlatform()} \n" .
			"Browser Name:             {$this->getBrowser()}  \n" .
			"Browser Version:          {$this->getVersion()} \n" .
			"User Agent String:        $ua_line1 \n\t\t\t  " .
			"$wordwrapped";
	}

	/**
	 * Protected routine to calculate and determine what the browser is in use (including platform)
	 */
	public function determine() {
		$this->checkPlatform();
		$this->checkBrowsers();
		$this->checkForAol();
	}

	/**
	 * Protected routine to determine the browser type
	 *
	 * @return boolean True if the browser was detected otherwise false
	 */
	public function checkBrowsers() {
		return (
			/**
			 * Well-known, well-used
			 * Special Notes:
			 * (1) Opera must be checked before FireFox due to the odd
			 *     user agents used in some older versions of Opera
			 * (2) WebTV is strapped onto Internet Explorer so we must
			 *     check for WebTV before IE
			 * (3) (deprecated) Galeon is based on Firefox and needs to be
			 *     tested before Firefox is tested
			 * (4) OmniWeb is based on Safari so OmniWeb check must occur
			 *     before Safari
			 * (5) Netscape 9+ is based on Firefox so Netscape checks
			 *     before FireFox are necessary.
			 */
			$this->checkBrowserWebTv() ||
			$this->checkBrowserInternetExplorer() ||
			$this->checkBrowserOpera() ||
			$this->checkBrowserGaleon() ||
			$this->checkBrowserNetscapeNavigator9Plus() ||
			$this->checkBrowserFirefox() ||
			$this->checkBrowserChrome() ||
			$this->checkBrowserOmniWeb() ||

			// Common mobile.
			$this->checkBrowserAndroid() ||
			$this->checkBrowseriPad() ||
			$this->checkBrowseriPod() ||
			$this->checkBrowseriPhone() ||
			$this->checkBrowserBlackBerry() ||
			$this->checkBrowserNokia() ||

			// Common bots.
			$this->checkBrowserGoogleBot() ||
			$this->checkBrowserMSNBot() ||
			$this->checkBrowserSlurp() ||

			// WebKit base check (post mobile and others).
			$this->checkBrowserSafari() ||

			// Everyone else.
			$this->checkBrowserNetPositive() ||
			$this->checkBrowserFirebird() ||
			$this->checkBrowserKonqueror() ||
			$this->checkBrowserIcab() ||
			$this->checkBrowserPhoenix() ||
			$this->checkBrowserAmaya() ||
			$this->checkBrowserLynx() ||

			$this->checkBrowserShiretoko() ||
			$this->checkBrowserIceCat() ||
			$this->checkBrowserW3CValidator() ||
			$this->checkBrowserMozilla() /* Mozilla is such an open standard that you must check it last */
		);
	}

	/**
	 * Determine if the user is using a BlackBerry (last updated 1.7)
	 *
	 * @return boolean True if the browser is the BlackBerry browser otherwise false
	 */
	public function checkBrowserBlackBerry() {
		if ( stripos( $this->_agent, 'blackberry' ) !== false ) {
			$aresult = explode( '/', stristr( $this->_agent, 'BlackBerry' ) );
			$aversion = explode( ' ', $aresult[1] );
			$this->setVersion( $aversion[0] );
			$this->_browser_name = $this->BROWSER_BLACKBERRY;
			$this->setMobile( true );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the user is using an AOL User Agent (last updated 1.7)
	 *
	 * @return boolean True if the browser is from AOL otherwise false
	 */
	public function checkForAol() {
		$this->setAol( false );
		$this->setAolVersion( $this->VERSION_UNKNOWN );

		if ( stripos( $this->_agent, 'aol' ) !== false ) {
			$aversion = explode( ' ', stristr( $this->_agent, 'AOL' ) );
			$this->setAol( true );
			$this->setAolVersion( preg_replace( '/[^0-9\.a-z]/i', '', $aversion[1] ) );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is the GoogleBot or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is the GoogletBot otherwise false
	 */
	public function checkBrowserGoogleBot() {
		if ( stripos( $this->_agent, 'googlebot' ) !== false ) {
			$aresult = explode( '/', stristr( $this->_agent, 'googlebot' ) );
			$aversion = explode( ' ', $aresult[1] );
			$this->setVersion( str_replace( ';', '', $aversion[0] ) );
			$this->_browser_name = $this->BROWSER_GOOGLEBOT;
			$this->setRobot( true );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is the MSNBot or not (last updated 1.9)
	 *
	 * @return boolean True if the browser is the MSNBot otherwise false
	 */
	public function checkBrowserMSNBot() {
		if ( stripos( $this->_agent, 'msnbot' ) !== false ) {
			$aresult = explode( '/', stristr( $this->_agent, 'msnbot' ) );
			$aversion = explode( ' ', $aresult[1] );
			$this->setVersion( str_replace( ';', '', $aversion[0] ) );
			$this->_browser_name = $this->BROWSER_MSNBOT;
			$this->setRobot( true );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is the W3C Validator or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is the W3C Validator otherwise false
	 */
	public function checkBrowserW3CValidator() {
		if ( stripos( $this->_agent, 'W3C-checklink' ) !== false ) {
			$aresult = explode( '/', stristr( $this->_agent, 'W3C-checklink' ) );
			$aversion = explode( ' ', $aresult[1] );
			$this->setVersion( $aversion[0] );
			$this->_browser_name = $this->BROWSER_W3CVALIDATOR;
			return true;
		} elseif ( stripos( $this->_agent, 'W3C_Validator' ) !== false ) {
			// Some of the Validator versions do not delineate w/ a slash - add it back in.
			$ua = str_replace( 'W3C_Validator ', 'W3C_Validator/', $this->_agent );
			$aresult = explode( '/', stristr( $ua, 'W3C_Validator' ) );
			$aversion = explode( ' ', $aresult[1] );
			$this->setVersion( $aversion[0] );
			$this->_browser_name = $this->BROWSER_W3CVALIDATOR;
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is the Yahoo! Slurp Robot or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is the Yahoo! Slurp Robot otherwise false
	 */
	public function checkBrowserSlurp() {
		if ( stripos( $this->_agent, 'slurp' ) !== false ) {
			$aresult = explode( '/', stristr( $this->_agent, 'Slurp' ) );
			$aversion = explode( ' ', $aresult[1] );
			$this->setVersion( $aversion[0] );
			$this->_browser_name = $this->BROWSER_SLURP;
			$this->setRobot( true );
			$this->setMobile( false );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is Internet Explorer or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Internet Explorer otherwise false
	 */
	public function checkBrowserInternetExplorer() {

		// Test for v1 - v1.5 IE.
		if ( stripos( $this->_agent, 'microsoft internet explorer' ) !== false ) {
			$this->setBrowser( $this->BROWSER_IE );
			$this->setVersion( '1.0' );
			$aresult = stristr( $this->_agent, '/' );
			if ( preg_match( '/308|425|426|474|0b1/i', $aresult ) ) {
				$this->setVersion( '1.5' );
			}
			return true;

			// Test for versions > 1.5.
		} elseif ( stripos( $this->_agent, 'msie' ) !== false && stripos( $this->_agent, 'opera' ) === false ) {
			// See if the browser is the odd MSN Explorer.
			if ( stripos( $this->_agent, 'msnb' ) !== false ) {
				$aresult = explode( ' ', stristr( str_replace( ';', '; ', $this->_agent ), 'MSN' ) );
				$this->setBrowser( $this->BROWSER_MSN );
				$this->setVersion( str_replace( array( '(', ')', ';' ), '', $aresult[1] ) );
				return true;
			}
			$aresult = explode( ' ', stristr( str_replace( ';', '; ', $this->_agent ), 'msie' ) );
			$this->setBrowser( $this->BROWSER_IE );
			$this->setVersion( str_replace( array( '(', ')', ';' ), '', $aresult[1] ) );
			return true;

			// Test for Pocket IE.
		} elseif ( stripos( $this->_agent, 'mspie' ) !== false || stripos( $this->_agent, 'pocket' ) !== false ) {
			$aresult = explode( ' ', stristr( $this->_agent, 'mspie' ) );
			$this->setPlatform( $this->PLATFORM_WINDOWS_CE );
			$this->setBrowser( $this->BROWSER_POCKET_IE );
			$this->setMobile( true );

			if ( stripos( $this->_agent, 'mspie' ) !== false ) {
				$this->setVersion( $aresult[1] );
			} else {
				$aversion = explode( '/', $this->_agent );
				$this->setVersion( $aversion[1] );
			}
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is Opera or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Opera otherwise false
	 */
	public function checkBrowserOpera() {
		if ( stripos( $this->_agent, 'opera mini' ) !== false ) {
			$resultant = stristr( $this->_agent, 'opera mini' );
			if ( preg_match( '/\//', $resultant ) ) {
				$aresult = explode( '/', $resultant );
				$aversion = explode( ' ', $aresult[1] );
				$this->setVersion( $aversion[0] );
			} else {
				$aversion = explode( ' ', stristr( $resultant, 'opera mini' ) );
				$this->setVersion( $aversion[1] );
			}
			$this->_browser_name = $this->BROWSER_OPERA_MINI;
			$this->setMobile( true );
			return true;
		} elseif ( stripos( $this->_agent, 'opera' ) !== false ) {
			$resultant = stristr( $this->_agent, 'opera' );
			if ( preg_match( '/Version\/(10.*)$/', $resultant, $matches ) ) {
				$this->setVersion( $matches[1] );
			} elseif ( preg_match( '/\//', $resultant ) ) {
				$aresult = explode( '/', str_replace( '(', ' ', $resultant ) );
				$aversion = explode( ' ', $aresult[1] );
				$this->setVersion( $aversion[0] );
			} else {
				$aversion = explode( ' ', stristr( $resultant, 'opera' ) );
				$this->setVersion( isset( $aversion[1] ) ? $aversion[1] : '' );
			}
			$this->_browser_name = $this->BROWSER_OPERA;
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is Chrome or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Chrome otherwise false
	 */
	public function checkBrowserChrome() {
		if ( stripos( $this->_agent, 'Chrome' ) !== false ) {
			$aresult = explode( '/', stristr( $this->_agent, 'Chrome' ) );
			$aversion = explode( ' ', $aresult[1] );
			$this->setVersion( $aversion[0] );
			$this->setBrowser( $this->BROWSER_CHROME );
			return true;
		}
		return false;
	}


	/**
	 * Determine if the browser is WebTv or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is WebTv otherwise false
	 */
	public function checkBrowserWebTv() {
		if ( stripos( $this->_agent, 'webtv' ) !== false ) {
			$aresult = explode( '/', stristr( $this->_agent, 'webtv' ) );
			$aversion = explode( ' ', $aresult[1] );
			$this->setVersion( $aversion[0] );
			$this->setBrowser( $this->BROWSER_WEBTV );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is NetPositive or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is NetPositive otherwise false
	 */
	public function checkBrowserNetPositive() {
		if ( stripos( $this->_agent, 'NetPositive' ) !== false ) {
			$aresult = explode( '/', stristr( $this->_agent, 'NetPositive' ) );
			$aversion = explode( ' ', $aresult[1] );
			$this->setVersion( str_replace( array( '(', ')', ';' ), '', $aversion[0] ) );
			$this->setBrowser( $this->BROWSER_NETPOSITIVE );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is Galeon or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Galeon otherwise false
	 */
	public function checkBrowserGaleon() {
		if ( stripos( $this->_agent, 'galeon' ) !== false ) {
			$aresult = explode( ' ', stristr( $this->_agent, 'galeon' ) );
			$aversion = explode( '/', $aresult[0] );
			$this->setVersion( $aversion[1] );
			$this->setBrowser( $this->BROWSER_GALEON );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is Konqueror or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Konqueror otherwise false
	 */
	public function checkBrowserKonqueror() {
		if ( stripos( $this->_agent, 'Konqueror' ) !== false ) {
			$aresult = explode( ' ', stristr( $this->_agent, 'Konqueror' ) );
			$aversion = explode( '/', $aresult[0] );
			$this->setVersion( $aversion[1] );
			$this->setBrowser( $this->BROWSER_KONQUEROR );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is iCab or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is iCab otherwise false
	 */
	public function checkBrowserIcab() {
		if ( stripos( $this->_agent, 'icab' ) !== false ) {
			$aversion = explode( ' ', stristr( str_replace( '/', ' ', $this->_agent ), 'icab' ) );
			$this->setVersion( $aversion[1] );
			$this->setBrowser( $this->BROWSER_ICAB );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is OmniWeb or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is OmniWeb otherwise false
	 */
	public function checkBrowserOmniWeb() {
		if ( stripos( $this->_agent, 'omniweb' ) !== false ) {
			$aresult = explode( '/', stristr( $this->_agent, 'omniweb' ) );
			$aversion = explode( ' ', isset( $aresult[1] ) ? $aresult[1] : '' );
			$this->setVersion( $aversion[0] );
			$this->setBrowser( $this->BROWSER_OMNIWEB );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is Phoenix or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Phoenix otherwise false
	 */
	public function checkBrowserPhoenix() {
		if ( stripos( $this->_agent, 'Phoenix' ) !== false ) {
			$aversion = explode( '/', stristr( $this->_agent, 'Phoenix' ) );
			$this->setVersion( $aversion[1] );
			$this->setBrowser( $this->BROWSER_PHOENIX );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is Firebird or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Firebird otherwise false
	 */
	public function checkBrowserFirebird() {
		if ( stripos( $this->_agent, 'Firebird' ) !== false ) {
			$aversion = explode( '/', stristr( $this->_agent, 'Firebird' ) );
			$this->setVersion( $aversion[1] );
			$this->setBrowser( $this->BROWSER_FIREBIRD );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is Netscape Navigator 9+ or not (last updated 1.7)
	 * NOTE: (http://browser.netscape.com/ - Official support ended on March 1st, 2008)
	 *
	 * @return boolean True if the browser is Netscape Navigator 9+ otherwise false
	 */
	public function checkBrowserNetscapeNavigator9Plus() {
		if ( stripos( $this->_agent, 'Firefox' ) !== false && preg_match( '/Navigator\/([^ ]*)/i', $this->_agent, $matches ) ) {
			$this->setVersion( $matches[1] );
			$this->setBrowser( $this->BROWSER_NETSCAPE_NAVIGATOR );
			return true;
		} elseif ( stripos( $this->_agent, 'Firefox' ) === false && preg_match( '/Netscape6?\/([^ ]*)/i', $this->_agent, $matches ) ) {
			$this->setVersion( $matches[1] );
			$this->setBrowser( $this->BROWSER_NETSCAPE_NAVIGATOR );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is Shiretoko or not (https://wiki.mozilla.org/Projects/shiretoko) (last updated 1.7)
	 *
	 * @return boolean True if the browser is Shiretoko otherwise false
	 */
	public function checkBrowserShiretoko() {
		if ( stripos( $this->_agent, 'Mozilla' ) !== false && preg_match( '/Shiretoko\/([^ ]*)/i', $this->_agent, $matches ) ) {
			$this->setVersion( $matches[1] );
			$this->setBrowser( $this->BROWSER_SHIRETOKO );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is Ice Cat or not (http://en.wikipedia.org/wiki/GNU_IceCat) (last updated 1.7)
	 *
	 * @return boolean True if the browser is Ice Cat otherwise false
	 */
	public function checkBrowserIceCat() {
		if ( stripos( $this->_agent, 'Mozilla' ) !== false && preg_match( '/IceCat\/([^ ]*)/i', $this->_agent, $matches ) ) {
			$this->setVersion( $matches[1] );
			$this->setBrowser( $this->BROWSER_ICECAT );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is Nokia or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Nokia otherwise false
	 */
	public function checkBrowserNokia() {
		if ( preg_match( '/Nokia([^\/]+)\/([^ SP]+)/i', $this->_agent, $matches ) ) {
			$this->setVersion( $matches[2] );
			if ( stripos( $this->_agent, 'Series60' ) !== false || strpos( $this->_agent, 'S60' ) !== false ) {
				$this->setBrowser( $this->BROWSER_NOKIA_S60 );
			} else {
				$this->setBrowser( $this->BROWSER_NOKIA );
			}
			$this->setMobile( true );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is Firefox or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Firefox otherwise false
	 */
	public function checkBrowserFirefox() {
		if ( stripos( $this->_agent, 'safari' ) === false ) {
			if ( preg_match( '/Firefox[\/ \(]([^ ;\)]+)/i', $this->_agent, $matches ) ) {
				$this->setVersion( $matches[1] );
				$this->setBrowser( $this->BROWSER_FIREFOX );
				return true;
			} elseif ( preg_match( '/Firefox$/i', $this->_agent, $matches ) ) {
				$this->setVersion( '' );
				$this->setBrowser( $this->BROWSER_FIREFOX );
				return true;
			}
		}
		return false;
	}

	/**
	 * Determine if the browser is Firefox or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Firefox otherwise false
	 */
	public function checkBrowserIceweasel() {
		if ( stripos( $this->_agent, 'Iceweasel' ) !== false ) {
			$aresult = explode( '/', stristr( $this->_agent, 'Iceweasel' ) );
			$aversion = explode( ' ', $aresult[1] );
			$this->setVersion( $aversion[0] );
			$this->setBrowser( $this->BROWSER_ICEWEASEL );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is Mozilla or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Mozilla otherwise false
	 */
	public function checkBrowserMozilla() {
		if ( stripos( $this->_agent, 'mozilla' ) !== false && preg_match( '/rv:[0-9].[0-9][a-b]?/i', $this->_agent ) && stripos( $this->_agent, 'netscape' ) === false ) {
			$aversion = explode( ' ', stristr( $this->_agent, 'rv:' ) );
			preg_match( '/rv:[0-9].[0-9][a-b]?/i', $this->_agent, $aversion );
			$this->setVersion( str_replace( 'rv:', '', $aversion[0] ) );
			$this->setBrowser( $this->BROWSER_MOZILLA );
			return true;
		} elseif ( stripos( $this->_agent, 'mozilla' ) !== false && preg_match( '/rv:[0-9]\.[0-9]/i', $this->_agent ) && stripos( $this->_agent, 'netscape' ) === false ) {
			$aversion = explode( '', stristr( $this->_agent, 'rv:' ) );
			$this->setVersion( str_replace( 'rv:', '', $aversion[0] ) );
			$this->setBrowser( $this->BROWSER_MOZILLA );
			return true;
		} elseif ( stripos( $this->_agent, 'mozilla' ) !== false && preg_match( '/mozilla\/([^ ]*)/i', $this->_agent, $matches ) && stripos( $this->_agent, 'netscape' ) === false ) {
			$this->setVersion( $matches[1] );
			$this->setBrowser( $this->BROWSER_MOZILLA );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is Lynx or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Lynx otherwise false
	 */
	public function checkBrowserLynx() {
		if ( stripos( $this->_agent, 'lynx' ) !== false ) {
			$aresult = explode( '/', stristr( $this->_agent, 'Lynx' ) );
			$aversion = explode( ' ', ( isset( $aresult[1] ) ? $aresult[1] : '' ) );
			$this->setVersion( $aversion[0] );
			$this->setBrowser( $this->BROWSER_LYNX );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is Amaya or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Amaya otherwise false
	 */
	public function checkBrowserAmaya() {
		if ( stripos( $this->_agent, 'amaya' ) !== false ) {
			$aresult = explode( '/', stristr( $this->_agent, 'Amaya' ) );
			$aversion = explode( ' ', $aresult[1] );
			$this->setVersion( $aversion[0] );
			$this->setBrowser( $this->BROWSER_AMAYA );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is Safari or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Safari otherwise false
	 */
	public function checkBrowserSafari() {
		if ( stripos( $this->_agent, 'Safari' ) !== false && stripos( $this->_agent, 'iPhone' ) === false && stripos( $this->_agent, 'iPod' ) === false ) {
			$aresult = explode( '/', stristr( $this->_agent, 'Version' ) );
			if ( isset( $aresult[1] ) ) {
				$aversion = explode( ' ', $aresult[1] );
				$this->setVersion( $aversion[0] );
			} else {
				$this->setVersion( $this->VERSION_UNKNOWN );
			}
			$this->setBrowser( $this->BROWSER_SAFARI );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is iPhone or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is iPhone otherwise false
	 */
	public function checkBrowseriPhone() {
		if ( stripos( $this->_agent, 'iPhone' ) !== false ) {
			$aresult = explode( '/', stristr( $this->_agent, 'Version' ) );
			if ( isset( $aresult[1] ) ) {
				$aversion = explode( ' ', $aresult[1] );
				$this->setVersion( $aversion[0] );
			} else {
				$this->setVersion( $this->VERSION_UNKNOWN );
			}
			$this->setMobile( true );
			$this->setBrowser( $this->BROWSER_IPHONE );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is iPod or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is iPod otherwise false
	 */
	public function checkBrowseriPad() {
		if ( stripos( $this->_agent, 'iPad' ) !== false ) {
			$aresult = explode( '/', stristr( $this->_agent, 'Version' ) );
			if ( isset( $aresult[1] ) ) {
				$aversion = explode( ' ', $aresult[1] );
				$this->setVersion( $aversion[0] );
			} else {
				$this->setVersion( $this->VERSION_UNKNOWN );
			}
			$this->setMobile( true );
			$this->setBrowser( $this->BROWSER_IPAD );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is iPod or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is iPod otherwise false
	 */
	public function checkBrowseriPod() {
		if ( stripos( $this->_agent, 'iPod' ) !== false ) {
			$aresult = explode( '/', stristr( $this->_agent, 'Version' ) );
			if ( isset( $aresult[1] ) ) {
				$aversion = explode( ' ', $aresult[1] );
				$this->setVersion( $aversion[0] );
			} else {
				$this->setVersion( $this->VERSION_UNKNOWN );
			}
			$this->setMobile( true );
			$this->setBrowser( $this->BROWSER_IPOD );
			return true;
		}
		return false;
	}

	/**
	 * Determine if the browser is Android or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Android otherwise false
	 */
	public function checkBrowserAndroid() {
		if ( stripos( $this->_agent, 'Android' ) !== false ) {
			$aresult = explode( ' ', stristr( $this->_agent, 'Android' ) );
			if ( isset( $aresult[1] ) ) {
				$aversion = explode( ' ', $aresult[1] );
				$this->setVersion( $aversion[0] );
			} else {
				$this->setVersion( $this->VERSION_UNKNOWN );
			}
			$this->setMobile( true );
			$this->setBrowser( $this->BROWSER_ANDROID );
			return true;
		}
		return false;
	}

	/**
	 * Determine the user's platform (last updated 1.7)
	 */
	public function checkPlatform() {
		if ( stripos( $this->_agent, 'windows' ) !== false ) {
			$this->_platform = $this->PLATFORM_WINDOWS;
		} elseif ( stripos( $this->_agent, 'iPad' ) !== false ) {
			$this->_platform = $this->PLATFORM_IPAD;
		} elseif ( stripos( $this->_agent, 'iPod' ) !== false ) {
			$this->_platform = $this->PLATFORM_IPOD;
		} elseif ( stripos( $this->_agent, 'iPhone' ) !== false ) {
			$this->_platform = $this->PLATFORM_IPHONE;
		} elseif ( stripos( $this->_agent, 'mac' ) !== false ) {
			$this->_platform = $this->PLATFORM_APPLE;
		} elseif ( stripos( $this->_agent, 'android' ) !== false ) {
			$this->_platform = $this->PLATFORM_ANDROID;
		} elseif ( stripos( $this->_agent, 'linux' ) !== false ) {
			$this->_platform = $this->PLATFORM_LINUX;
		} elseif ( stripos( $this->_agent, 'Nokia' ) !== false ) {
			$this->_platform = $this->PLATFORM_NOKIA;
		} elseif ( stripos( $this->_agent, 'BlackBerry' ) !== false ) {
			$this->_platform = $this->PLATFORM_BLACKBERRY;
		} elseif ( stripos( $this->_agent, 'FreeBSD' ) !== false ) {
			$this->_platform = $this->PLATFORM_FREEBSD;
		} elseif ( stripos( $this->_agent, 'OpenBSD' ) !== false ) {
			$this->_platform = $this->PLATFORM_OPENBSD;
		} elseif ( stripos( $this->_agent, 'NetBSD' ) !== false ) {
			$this->_platform = $this->PLATFORM_NETBSD;
		} elseif ( stripos( $this->_agent, 'OpenSolaris' ) !== false ) {
			$this->_platform = $this->PLATFORM_OPENSOLARIS;
		} elseif ( stripos( $this->_agent, 'SunOS' ) !== false ) {
			$this->_platform = $this->PLATFORM_SUNOS;
		} elseif ( stripos( $this->_agent, 'OS\/2' ) !== false ) {
			$this->_platform = $this->PLATFORM_OS2;
		} elseif ( stripos( $this->_agent, 'BeOS' ) !== false ) {
			$this->_platform = $this->PLATFORM_BEOS;
		} elseif ( stripos( $this->_agent, 'win' ) !== false ) {
			$this->_platform = $this->PLATFORM_WINDOWS;
		}
	}
}
