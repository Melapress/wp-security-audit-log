<?php
/**
 * Class: Assets
 *
 * @since   4.2.0
 * @package wsal
 */

declare(strict_types=1);

namespace WSAL\Helpers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Helpers\Assets' ) ) {
	/**
	 * Helper class unifying the loading process of some UI assets such as JS and/or CSS.
	 *
	 * @since 4.2.0
	 */
	class Assets {

		/**
		 * Date format to use in JS datepickers.
		 *
		 * @var string
		 */
		public const DATEPICKER_DATE_FORMAT = 'yyyy-mm-dd';

		/**
		 * Enqueues datepicker styles and scripts.
		 */
		public static function load_datepicker() {

			// There is a time picker that still depends on this. It only displays along with datepickers, so we load it here.
			$plugin_base_url = WSAL_BASE_URL;
			wp_enqueue_script( 'wsal-datepick-plugin-js', $plugin_base_url . '/js/jquery.plugin.min.js', array( 'jquery' ), WSAL_VERSION, true );

			wp_enqueue_script( 'jquery-ui-datepicker' );
			// @see https://jqueryui.com/themeroller/#!zThemeParams=5d000080002306000000000000003d8888d844329a8dfe02723de3e570162214ca12b8cb8413b6f9589fa4e2b1a2db6d1bf51a21a6d3eb8745ba8a45845887cdd2c81bb11e5b1e218a622ba1e2b65ef484aa737e7f97cb9436fbfff675c854a94e12dbd1d177e2915524c2a1c915f642815e681f8bfde06ebbed23c6d840e980961719941a0d63371ad9525b7fd72ab56307a2ce7f67ae7cbc813e94f156471a9a0f12377de053405ce36cac0439cfe24ee1ff80538f81895720dd612ee0879cfa835a7361f471bb7d3eecb592ce3216e96caf4171281380ac0dc27753c39ca82ab53c570e775afa6127b627c4a7b1ffd67fb416911db041e84d5dc7d0dcb84876aeb192892096c9839e39d4dd96b53582789551d6c9a4dfeaff8557b86e884cd485a878c9e66375b58e483b92a8618cd1cfdfd06e52017d260bb4b456c8c91a2c5912a0ed2e89c61863b8e484927bb00c2be9cd644fe4313ad8475a5ea87c6214d45f3304c0119e9cedf96af83e54a30649d6abcfdea4ca05bedd307220f0f32b5784bd69419ddc9c5a0438f0a0d88d4dca9b926796ee6af98593c50f9ebf11f273171d2a4ecdcf5f1681b8f5cd4bfb13a612cf578a508616bde7470eb5fe9f7466377ece42f9b53f3e97744f85a1ec1be3e7e5e921f4be863728b7fd428552
			wp_enqueue_style( 'jquery-style', $plugin_base_url . '/css/jquery-ui/jquery-ui.min.css', array(), WSAL_VERSION );
			?>
			<script type="text/javascript">
				function wsal_CreateDatePicker($, $input, date) {
					$input.datepicker({
						dateFormat: 'yy-mm-dd'
					});
					$input.attr('autocomplete', 'off')
				}

				function wsal_CheckDate(value) {
					// regular expression to match date format yyyy-mm-dd
					var re = /^(\d{4})-(\d{1,2})-(\d{1,2})$/;
					return value.match(re);
				}
			</script>
			<?php
		}
	}
}
