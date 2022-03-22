<?php
/**
 * Webfonts API: Provider for locally-hosted fonts.
 *
 * @package    WordPress
 * @subpackage WebFonts
 * @since      6.0.0
 */

/**
 * A core bundled provider for generating `@font-face` styles
 * from locally-hosted font files.
 *
 * This provider builds an optimized `src` (for browser support)
 * and then generates the `@font-face` styles.
 *
 * All know-how (business logic) for how to interact with and
 * generate styles from locally-hosted font files is contained
 * in this provider.
 *
 * @since 6.0.0
 */
class WP_Webfonts_Provider_Local extends WP_Webfonts_Provider {

	/**
	 * The provider's unique ID.
	 *
	 * @since 6.0.0
	 *
	 * @var string
	 */
	protected $id = 'local';

	/**
	 * Gets the `@font-face` CSS styles for locally-hosted font files.
	 *
	 * This method does the following processing tasks:
	 *    1. Orchestrates an optimized `src` (with format) for browser support.
	 *    2. Generates the `@font-face` for all its webfonts.
	 *
	 * For example, when given these webfonts:
	 * <code>
	 * array(
	 *      'source-serif-pro.normal.200 900' => array(
	 *          'provider'    => 'local',
	 *          'font_family' => 'Source Serif Pro',
	 *          'font_weight' => '200 900',
	 *          'font_style'  => 'normal',
	 *          'src'         => 'https://example.com/wp-content/themes/twentytwentytwo/assets/fonts/source-serif-pro/SourceSerif4Variable-Roman.ttf.woff2' ),
	 *      ),
	 *      'source-serif-pro.italic.400 900' => array(
	 *          'provider'    => 'local',
	 *          'font_family' => 'Source Serif Pro',
	 *          'font_weight' => '200 900',
	 *          'font_style'  => 'italic',
	 *          'src'         => 'https://example.com/wp-content/themes/twentytwentytwo/assets/fonts/source-serif-pro/SourceSerif4Variable-Italic.ttf.woff2' ),
	 *      ),
	 * )
	 * </code>
	 *
	 * the following `@font-face` styles are generated and returned:
	 * <code>
	 *
	 * @font-face{
	 *      font-family:"Source Serif Pro";
	 *      font-style:normal;
	 *      font-weight:200 900;
	 *      font-stretch:normal;
	 *      src:local("Source Serif Pro"), url('/assets/fonts/source-serif-pro/SourceSerif4Variable-Roman.ttf.woff2') format('woff2');
	 * }
	 * @font-face{
	 *      font-family:"Source Serif Pro";
	 *      font-style:italic;
	 *      font-weight:200 900;
	 *      font-stretch:normal;
	 *      src:local("Source Serif Pro"), url('/assets/fonts/source-serif-pro/SourceSerif4Variable-Italic.ttf.woff2') format('woff2');
	 * }
	 * </code>
	 *
	 * @since 6.0.0
	 *
	 * @return string The `@font-face` CSS.
	 */
	public function get_css() {
		$css = '';

		foreach ( $this->webfonts as $webfont ) {
			// Order the webfont's `src` items to optimize for browser support.
			$webfont = $this->order_src( $webfont );

			// Build the @font-face CSS for this webfont.
			$css .= '@font-face{' . $this->build_font_face_css( $webfont ) . '}';
		}

		return $css;
	}

	/**
	 * Order `src` items to optimize for browser support.
	 *
	 * @since 6.0.0
	 *
	 * @param object $webfont Webfont to process.
	 * @return object
	 */
	private function order_src( $webfont ) {
		$font_face = $webfont->get_font();

		if ( ! is_array( $font_face['src'] ) ) {
			$font_face['src'] = (array) $font_face['src'];
		}

		$src         = array();
		$src_ordered = array();

		foreach ( $font_face['src'] as $url ) {
			// Add data URIs first.
			if ( 0 === strpos( trim( $url ), 'data:' ) ) {
				$src_ordered[] = array(
					'url'    => $url,
					'format' => 'data',
				);
				continue;
			}
			$format         = pathinfo( $url, PATHINFO_EXTENSION );
			$src[ $format ] = $url;
		}

		// Add woff2.
		if ( ! empty( $src['woff2'] ) ) {
			$src_ordered[] = array(
				'url'    => $src['woff2'],
				'format' => 'woff2',
			);
		}

		// Add woff.
		if ( ! empty( $src['woff'] ) ) {
			$src_ordered[] = array(
				'url'    => $src['woff'],
				'format' => 'woff',
			);
		}

		// Add ttf.
		if ( ! empty( $src['ttf'] ) ) {
			$src_ordered[] = array(
				'url'    => $src['ttf'],
				'format' => 'truetype',
			);
		}

		// Add eot.
		if ( ! empty( $src['eot'] ) ) {
			$src_ordered[] = array(
				'url'    => $src['eot'],
				'format' => 'embedded-opentype',
			);
		}

		// Add otf.
		if ( ! empty( $src['otf'] ) ) {
			$src_ordered[] = array(
				'url'    => $src['otf'],
				'format' => 'opentype',
			);
		}
		$webfont->update_font( array( 'src' => $src_ordered ) );

		return $webfont;
	}

	/**
	 * Builds the font-family's CSS.
	 *
	 * @since 6.0.0
	 *
	 * @param object $webfont Webfont to process.
	 * @return string This font-family's CSS.
	 */
	private function build_font_face_css( $webfont ) {
		$font_face = $webfont->get_font();
		$css = '';

		// Wrap font-family in quotes if it contains spaces.
		if (
			false !== strpos( $font_face['font-family'], ' ' ) &&
			false === strpos( $font_face['font-family'], '"' ) &&
			false === strpos( $font_face['font-family'], "'" )
		) {
			$font_face['font-family'] = '"' . $font_face['font-family'] . '"';
		}

		foreach ( $font_face as $key => $value ) {

			// Skip "provider", since it's for internal API use,
			// and not a valid CSS property.
			if ( 'provider' === $key ) {
				continue;
			}

			// Compile the "src" parameter.
			if ( 'src' === $key ) {
				$value = $this->compile_src( $font_face['font-family'], $value );
			}

			// If font-variation-settings is an array, convert it to a string.
			if ( 'font-variation-settings' === $key && is_array( $value ) ) {
				$value = $this->compile_variations( $value );
			}

			if ( ! empty( $value ) ) {
				$css .= "$key:$value;";
			}
		}

		return $css;
	}

	/**
	 * Compiles the `src` into valid CSS.
	 *
	 * @since 6.0.0
	 *
	 * @param string $font_family Font family.
	 * @param array  $value       Value to process.
	 * @return string The CSS.
	 */
	private function compile_src( $font_family, array $value ) {
		$src = "local($font_family)";

		foreach ( $value as $item ) {
			if ( 0 === strpos( $item['url'], 'file:./' ) ) {
				$absolute_path_to_url = get_stylesheet_directory_uri() . '/' . str_replace( 'file:./', '', $item['url'] );
				$item['url']          = wp_make_link_relative( $absolute_path_to_url );
			}

			$src .= ( 'data' === $item['format'] )
				? ", url({$item['url']})"
				: ", url('{$item['url']}') format('{$item['format']}')";
		}
		return $src;
	}

	/**
	 * Compiles the font variation settings.
	 *
	 * @since 6.0.0
	 *
	 * @param array $font_variation_settings Array of font variation settings.
	 * @return string The CSS.
	 */
	private function compile_variations( array $font_variation_settings ) {
		$variations = '';

		foreach ( $font_variation_settings as $key => $value ) {
			$variations .= "$key $value";
		}

		return $variations;
	}
}
