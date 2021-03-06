<?php
/*
Plugin Name: Schema Creator by Raven
Plugin URI: http://schema-creator.org/?utm_source=wp&utm_medium=plugin&utm_campaign=schema
Description: Insert schema.org microdata into posts and pages
Version: 1.041
Author: Raven Internet Marketing Tools
Author URI: http://raventools.com/?utm_source=wp&utm_medium=plugin&utm_campaign=schema
License: GPL v2

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA


	Resources

	http://schema-creator.org/
	http://foolip.org/microdatajs/live/
	http://www.google.com/webmasters/tools/richsnippets

*/

if(!defined('SC_BASE'))
	define('SC_BASE', plugin_basename(__FILE__) );

if(!defined('SC_VER'))
	define('SC_VER', '1.041');


class ravenSchema
{

	/**
	 * This is our constructor
	 *
	 * @return ravenSchema
	 */
	public function __construct() {
		add_action					( 'plugins_loaded', 		array( $this, 'textdomain'			) 			);
		add_action					( 'admin_menu',				array( $this, 'schema_settings'		)			);
		add_action					( 'admin_init', 			array( $this, 'reg_settings'		)			);
		add_action					( 'admin_enqueue_scripts',	array( $this, 'admin_scripts'		)			);
		add_action					( 'admin_footer',			array( $this, 'schema_form'			)			);
		add_action					( 'the_posts', 				array( $this, 'schema_loader'		)			);
		add_action					( 'do_meta_boxes',			array( $this, 'metabox_schema'		),	10,	2	);
		add_action					( 'save_post',				array( $this, 'save_metabox'		)			);
		add_action					( 'admin_bar_menu',			array( $this, 'schema_test'			),	9999	);

		add_filter					( 'plugin_action_links',	array( $this, 'quick_link'			),	10,	2	);
		add_filter					( 'body_class',             array( $this, 'body_class'			)			);
		add_filter					( 'media_buttons',			array( $this, 'media_button'		),	31		);
		add_filter					( 'the_content',			array( $this, 'schema_wrapper'		)			);
		add_filter					( 'admin_footer_text',		array( $this, 'schema_footer'		)			);
		add_shortcode				( 'schema',					array( $this, 'shortcode'			)			);
		register_activation_hook	( __FILE__, 				array( $this, 'store_settings'		)			);
	}

	/**
	 * load textdomain for international goodness
	 *
	 * @return ravenSchema
	 */


	public function textdomain() {

		load_plugin_textdomain( 'schema', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * show settings link on plugins page
	 *
	 * @return ravenSchema
	 */

    public function quick_link( $links, $file ) {

		static $this_plugin;

		if (!$this_plugin) {
			$this_plugin = plugin_basename(__FILE__);
		}

    	// check to make sure we are on the correct plugin
    	if ($file == $this_plugin) {

			$settings_link	= '<a href="'.menu_page_url( 'schema-creator', 0 ).'">'.__('Settings', 'schema').'</a>';

        	array_unshift($links, $settings_link);
    	}

		return $links;

	}

	/**
	 * add link to admin toolbar for testing
	 *
	 * @return ravenSchema
	 */

	public function schema_test( $wp_admin_bar ) {

		// no link on admin panel
		if ( is_admin() )
			return;

		// only load on singles
		if ( !is_singular() )
			return;

		//get some variables
		global $post;
		$link = get_permalink($post->ID);

		// set args for tab
		global $wp_admin_bar;

			$args = array(
				'parent'	=> 'top-secondary',
				'id'		=> 'schema-test',
				'title' 	=> 'Test Schema',
				'href'		=> 'http://www.google.com/webmasters/tools/richsnippets?url='.urlencode($link).'&html=',
				'meta'		=> array(
					'class'		=> 'schema-test',
					'target'	=> '_blank'
					)
			);

		$wp_admin_bar->add_node($args);
	}

	/**
	 * display metabox
	 *
	 * @return ravenSchema
	 */

	public function metabox_schema( $page, $context ) {

		// check to see if they have options first
		$schema_options	= get_option('schema_options');

		// they haven't enabled this? THEN YOU LEAVE NOW
		if(empty($schema_options['body']) && empty($schema_options['post']) )
			return;

		// get custom post types
		$args = array(
			'public'   => true,
			'_builtin' => false
		);
		$output		= 'names';
		$operator	= 'and';

		$customs	= get_post_types($args,$output,$operator);
		$builtin	= array('post' => 'post', 'page' => 'page');

		$types		= $customs !== false ? array_merge($customs, $builtin) : $builtin;

		if ( in_array( $page,  $types ) && 'side' == $context )
			add_meta_box('schema-post-box', __('Schema Display Options', 'schema'), array(&$this, 'schema_post_box'), $page, $context, 'high');


	}

	/**
	 * Display checkboxes for disabling the itemprop and itemscope
	 *
	 * @return ravenSchema
	 */

	public function schema_post_box() {

		global $post;
		$disable_body	= get_post_meta($post->ID, '_schema_disable_body', true);
		$disable_post	= get_post_meta($post->ID, '_schema_disable_post', true);

		// use nonce for security
		wp_nonce_field( SC_BASE, 'schema_nonce' );

		echo '<p class="schema-post-option">';
		echo '<input type="checkbox" name="schema_disable_body" id="schema_disable_body" value="true" '.checked($disable_body, 'true', false).'>';
		echo '<label for="schema_disable_body">'.__('Disable body itemscopes on this post.', 'schema').'</label>';
		echo '</p>';

		echo '<p class="schema-post-option">';
		echo '<input type="checkbox" name="schema_disable_post" id="schema_disable_post" value="true" '.checked($disable_post, 'true', false).'>';
		echo '<label for="schema_disable_post">'.__('Disable content itemscopes on this post.', 'schema').'</label>';
		echo '</p>';

	}

	/**
	 * save the data
	 *
	 * @return ravenSchema
	 */


	public function save_metabox($post_id) {

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return;

		if ( isset($_POST['schema_nonce']) && !wp_verify_nonce( $_POST['schema_nonce'], SC_BASE ) )
			return;

		if ( !current_user_can( 'edit_post', $post_id ) )
			return;

		// OK, we're authenticated: we need to find and save the data

		$db_check	= isset($_POST['schema_disable_body']) ? 'true' : 'false';
		$dp_check	= isset($_POST['schema_disable_post']) ? 'true' : 'false';

		update_post_meta($post_id, '_schema_disable_body', $db_check);
		update_post_meta($post_id, '_schema_disable_post', $dp_check);

	}

	/**
	 * build out settings page
	 *
	 * @return ravenSchema
	 */


	public function schema_settings() {
	    add_submenu_page('options-general.php', __('Schema Creator', 'schema'), __('Schema Creator', 'schema'), 'manage_options', 'schema-creator', array( $this, 'schema_creator_display' ));
	}

	/**
	 * Register settings
	 *
	 * @return ravenSchema
	 */


	public function reg_settings() {
		register_setting( 'schema_options', 'schema_options');

	}

	/**
	 * Store settings
	 *
	 *
	 * @return ravenSchema
	 */


	public function store_settings() {

		// check to see if they have options first
		$options_check	= get_option('schema_options');

		// already have options? LEAVE THEM ALONE SIR
		if(!empty($options_check))
			return;

		// got nothin? well then, shall we?
		$schema_options['css']	= 'false';
		$schema_options['body']	= 'true';
		$schema_options['post']	= 'true';

		update_option('schema_options', $schema_options);

	}

	/**
	 * Content for pop-up tooltips
	 *
	 * @return ravenSchema
	 */

	public function tooltip() {

		$tooltip = array(

			'default_css'	=> __('Check to remove Schema Creator CSS from the microdata HTML output.', 'schema'),

			'body_class'	=> __('Check to add the <code>http://schema.org/Blog</code> schema itemtype to the BODY element on your pages and posts. Your theme must have the <code>body_class</code> template tag for this to work.', 'schema'),

			'post_class'	=> __('Check to add the <code>http://schema.org/BlogPosting</code> schema itemtype to the content wrapper on your pages and posts.', 'schema'),

			// end tooltip content
		);

		return $tooltip;
	}

	/**
	 * Display main options page structure
	 *
	 * @return ravenSchema
	 */

	public function schema_creator_display() {

		if (!current_user_can('manage_options') )
			return;
		?>

		<div class="wrap">
    	<div class="icon32" id="icon-schema"><br></div>
		<h2><?php _e('Schema Creator Settings', 'schema'); ?></h2>

	        <div class="schema_options">
            	<div class="schema_form_text">
				<p><?php _e('By default, the', 'schema'); ?> <a target="_blank" href="<?php echo esc_url( __( 'http://schema-creator.org/?utm_source=wp&utm_medium=plugin&utm_campaign=schema', 'schema' ) ); ?>" title="<?php esc_attr_e( 'Schema Creator', 'schema' ); ?>"> <?php _e('Schema Creator', 'schema'); ?></a> plugin by  <a target="_blank" href="<?php echo esc_url( __( 'http://raventools.com/?utm_source=wp&utm_medium=plugin&utm_campaign=schema', 'schema' ) ); ?>" title="<?php esc_attr_e( 'Raven Internet Marketing Tools', 'schema' ); ?>"> <?php _e('Raven Internet Marketing Tools', 'schema'); ?></a> <?php _e('includes unique CSS IDs and classes. You can reference the CSS to control the style of the HTML that the Schema Creator plugin outputs.', 'schema'); ?></p>

            	<p><?php _e('The plugin can also automatically include <code>http://schema.org/Blog</code> and <code>http://schema.org/BlogPosting</code> schemas to your pages and posts.', 'schema'); ?></p>

				<p><?php _e('Google also offers a', 'schema'); ?> <a target="_blank" href="<?php echo esc_url( __( 'http://www.google.com/webmasters/tools/richsnippets/', 'schema' ) ); ?>" title="<?php esc_attr_e( 'Rich Snippet Testing tool', 'schema' ); ?>"> <?php _e('Rich Snippet Testing tool', 'schema'); ?></a> <?php _e('to review and test the schemas in your pages and posts.', 'schema'); ?></p>

                </div>

                <div class="schema_form_options">
	            <form method="post" action="options.php">
			    <?php
                settings_fields( 'schema_options' );
				$schema_options	= get_option('schema_options');

				$css_hide	= (isset($schema_options['css']) && $schema_options['css'] == 'true' ? 'checked="checked"' : '');
				$body_tag	= (isset($schema_options['body']) && $schema_options['body'] == 'true' ? 'checked="checked"' : '');
				$post_tag	= (isset($schema_options['post']) && $schema_options['post'] == 'true' ? 'checked="checked"' : '');

				$tooltips	= $this->tooltip();

				?>

				<p>
                <label for="schema_options[css]"><input type="checkbox" id="schema_css" name="schema_options[css]" class="schema_checkbox" value="true" <?php echo $css_hide; ?>/> <?php _e('Exclude default CSS for schema output', 'schema'); ?></label>
                <span class="ap_tooltip" tooltip="<?php echo $tooltips['default_css']; ?>">(?)</span>
                </p>

				<p>
                <label for="schema_options[body]"><input type="checkbox" id="schema_body" name="schema_options[body]" class="schema_checkbox" value="true" <?php echo $body_tag; ?> /> <?php _e('Apply itemprop &amp; itemtype to main body tag', 'schema'); ?></label>
                <span class="ap_tooltip" tooltip="<?php echo $tooltips['body_class']; ?>">(?)</span>
                </p>

				<p>
                <label for="schema_options[post]"><input type="checkbox" id="schema_post" name="schema_options[post]" class="schema_checkbox" value="true" <?php echo $post_tag; ?> /> <?php _e('Apply itemscope &amp; itemtype to content wrapper', 'schema'); ?></label>
                <span class="ap_tooltip" tooltip="<?php echo $tooltips['post_class']; ?>">(?)</span>
                </p>

	    		<p class="submit"><input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" /></p>
				</form>
                </div>

            </div>

        </div>


	<?php }


	/**
	 * load scripts and style for admin settings page
	 *
	 * @return ravenSchema
	 */


	public function admin_scripts($hook) {
		// for post editor
		if ( $hook == 'post-new.php' || $hook == 'post.php' ) :
			wp_enqueue_style( 'schema-admin', plugins_url('/lib/css/schema-admin.css', __FILE__), array(), SC_VER, 'all' );

			wp_enqueue_script( 'jquery-ui-core');
			wp_enqueue_script( 'jquery-ui-datepicker');
			wp_enqueue_script( 'jquery-ui-slider');
			wp_enqueue_script( 'jquery-timepicker', plugins_url('/lib/js/jquery.timepicker.js', __FILE__) , array('jquery'), SC_VER, true );
			wp_enqueue_script( 'format-currency', plugins_url('/lib/js/jquery.currency.min.js', __FILE__) , array('jquery'), SC_VER, true );
			wp_enqueue_script( 'schema-form', plugins_url('/lib/js/schema.form.init.js', __FILE__) , array('jquery'), SC_VER, true );
		endif;

		// for admin settings screen
		$current_screen = get_current_screen();
		if ( 'settings_page_schema-creator' == $current_screen->base ) :
			wp_enqueue_style( 'schema-admin', plugins_url('/lib/css/schema-admin.css', __FILE__), array(), SC_VER, 'all' );

			wp_enqueue_script( 'jquery-qtip', plugins_url('/lib/js/jquery.qtip.min.js', __FILE__) , array('jquery'), SC_VER, true );
			wp_enqueue_script( 'schema-admin', plugins_url('/lib/js/schema.admin.init.js', __FILE__) , array('jquery'), SC_VER, true );
		endif;
	}


	/**
	 * add attribution link to settings page
	 *
	 * @return ravenSchema
	 */

	public function schema_footer($text) {
		$current_screen = get_current_screen();

		if ( 'settings_page_schema-creator' !== $current_screen->base )
			return $text;

		$text = '<span id="footer-thankyou">'.__('This plugin brought to you by the fine folks at', 'schema').' <a target="_blank" href="'.esc_url( __( 'http://raventools.com/?utm_source=wp&utm_medium=plugin&utm_campaign=schema', 'schema' ) ).'" title="'.esc_attr( 'Internet Marketing Tools for SEO and Social Media', 'schema' ).'"> '. __('Raven Internet Marketing Tools', 'schema').'</a>.</span>';

		return $text;
	}

	/**
	 * load body classes
	 *
	 * @return ravenSchema
	 */


	public function body_class( $classes ) {

		if (is_search() || is_404() )
			return $classes;

		$schema_options = get_option('schema_options');

		$bodytag = isset($schema_options['body']) && $schema_options['body'] == 'true' ? true : false;

		// user disabled the tag. so bail.
		if($bodytag === false )
			return $classes;

		// check for single post disable
		global $post;

		if (empty($post))
			return $classes;

		$disable_body	= get_post_meta($post->ID, '_schema_disable_body', true);

		if($disable_body == 'true' )
			return $classes;

		$backtrace = debug_backtrace();
		if ( $backtrace[4]['function'] === 'body_class' )
			echo 'itemtype="http://schema.org/Blog" ';
			echo 'itemscope="" ';

		return $classes;
	}

	/**
	 * load front-end CSS if shortcode is present
	 *
	 * @return ravenSchema
	 */


	public function schema_loader($posts) {

		// no posts present. nothing more to do here
		if ( empty($posts) )
			return $posts;

		// they said they didn't want the CSS. their loss.
		$schema_options = get_option('schema_options');

		if(isset($schema_options['css']) && $schema_options['css'] == 'true' )
			return $posts;


		// false because we have to search through the posts first
		$found = false;

		// search through each post
		foreach ($posts as $post) {
			$meta_check	= get_post_meta($post->ID, '_raven_schema_load', true);
			// check the post content for the short code
			$content	= $post->post_content;
			if ( preg_match('/schema(.*)/', $content) ) {
				// we have found a post with the short code
				$found = true;
				// stop the search
				break;
			}
		}

		if ($found == true )
			wp_enqueue_style( 'schema-style', plugins_url('/lib/css/schema-style.css', __FILE__), array(), SC_VER, 'all' );

		if (empty($meta_check) && $found == true )
			update_post_meta($post->ID, '_raven_schema_load', 'true');

		if ($found == false )
			delete_post_meta($post->ID, '_raven_schema_load');

		return $posts;
	}

	/**
	 * wrap content in markup
	 *
	 * @return ravenSchema
	 */

	public function schema_wrapper($content) {

		$schema_options = get_option('schema_options');

		$wrapper = isset($schema_options['post']) && $schema_options['post'] == 'true' ? true : false;

		// user disabled content wrapper. just return the content as usual
		if ($wrapper === false)
			return $content;

		// check for single post disable
		global $post;
		$disable_post	= get_post_meta($post->ID, '_schema_disable_post', true);

		if($disable_post == 'true' )
			return $content;

		// updated content filter to wrap the itemscope
        $content = '<div itemscope itemtype="http://schema.org/BlogPosting">'.$content.'</div>';

    // Returns the content.
    return $content;

	}

	/**
	 * Build out shortcode with variable array of options
	 *
	 * @return ravenSchema
	 */

	public function shortcode( $atts, $content = null ) {
		extract( shortcode_atts( array(
			'type'				=> '',
			'evtype'			=> '',
			'orgtype'			=> '',
			'name'				=> '',
			'orgname'			=> '',
			'jobtitle'			=> '',
			'url'				=> '',
			'description'		=> '',
			'bday'				=> '',
			'street'			=> '',
			'pobox'				=> '',
			'city'				=> '',
			'state'				=> '',
			'postalcode'		=> '',
			'country'			=> '',
			'email'				=> '',
			'phone'				=> '',
			'fax'				=> '',
			'brand'				=> '',
			'manfu'				=> '',
			'model'				=> '',
			'single_rating'		=> '',
			'agg_rating'		=> '',
			'prod_id'			=> '',
			'price'				=> '',
			'condition'			=> '',
			'sdate'				=> '',
			'stime'				=> '',
			'edate'				=> '',
			'duration'			=> '',
			'director'			=> '',
			'producer'			=> '',
			'actor_1'			=> '',
			'author'			=> '',
			'publisher'			=> '',
			'pubdate'			=> '',
			'edition'			=> '',
			'isbn'				=> '',
			'ebook'				=> '',
			'paperback'			=> '',
			'hardcover'			=> '',
			'rev_name'			=> '',
			'rev_body'			=> '',
			'user_review'		=> '',
			'min_review'		=> '',
			'max_review'		=> '',
			'ingrt_1'			=> '',
			'image'				=> '',
			'instructions'		=> '',
			'prephours'			=> '',
			'prepmins'			=> '',
			'cookhours'			=> '',
			'cookmins'			=> '',
			'yield'				=> '',
			'calories'			=> '',
			'fatcount'			=> '',
			'sugarcount'		=> '',
			'saltcount'			=> '',

		), $atts ) );

		// create array of actor fields
		$actors = array();
		foreach ( $atts as $key => $value ) {
			if ( strpos( $key , 'actor' ) === 0 )
				$actors[] = $value;
		}

		// create array of actor fields
		$ingrts = array();
		foreach ( $atts as $key => $value ) {
			if ( strpos( $key , 'ingrt' ) === 0 )
				$ingrts[] = $value;
		}

		// wrap schema build out
		$sc_build = '<div id="schema_block" class="schema_'.$type.'">';

		// person
		if(isset($type) && $type == 'person') {

		$sc_build .= '<div itemscope itemtype="http://schema.org/Person">';

			if(!empty($name) && !empty($url) ) {
				$sc_build .= '<a class="schema_url" target="_blank" itemprop="url" href="'.esc_url($url).'">';
				$sc_build .= '<div class="schema_name" itemprop="name">'.$name.'</div>';
				$sc_build .= '</a>';
			}

			if(!empty($name) && empty($url) )
				$sc_build .= '<div class="schema_name" itemprop="name">'.$name.'</div>';

			if(!empty($orgname)) {
				$sc_build .= '<div itemscope itemtype="http://schema.org/Organization">';
				$sc_build .= '<span class="schema_orgname" itemprop="name">'.$orgname.'</span>';
				$sc_build .= '</div>';
			}

			if(!empty($jobtitle))
				$sc_build .= '<div class="schema_jobtitle" itemprop="jobtitle">'.$jobtitle.'</div>';

			if(!empty($description))
				$sc_build .= '<div class="schema_description" itemprop="description">'.esc_attr($description).'</div>';

			if(	!empty($street) ||
				!empty($pobox) ||
				!empty($city) ||
				!empty($state) ||
				!empty($postalcode) ||
				!empty($country)
				)
				$sc_build .= '<div itemprop="address" itemscope itemtype="http://schema.org/PostalAddress">';

			if(!empty($street))
				$sc_build .= '<div class="street" itemprop="streetAddress">'.$street.'</div>';

			if(!empty($pobox))
				$sc_build .= '<div class="pobox">'.__('P.O. Box:', 'schema' ).' <span itemprop="postOfficeBoxNumber">'.$pobox.'</span></div>';

			if(!empty($city) && !empty($state)) {
				$sc_build .= '<div class="city_state">';
				$sc_build .= '<span class="locale" itemprop="addressLocality">'.$city.'</span>,';
				$sc_build .= '<span class="region" itemprop="addressRegion">'.$state.'</span>';
				$sc_build .= '</div>';
			}

				// secondary check if one part of city / state is missing to keep markup consistent
				if(empty($state) && !empty($city) )
					$sc_build .= '<div class="city_state"><span class="locale" itemprop="addressLocality">'.$city.'</span></div>';

				if(empty($city) && !empty($state) )
					$sc_build .= '<div class="city_state"><span class="region" itemprop="addressRegion">'.$state.'</span></div>';

			if(!empty($postalcode))
				$sc_build .= '<div class="postalcode" itemprop="postalCode">'.$postalcode.'</div>';

			if(!empty($country))
				$sc_build .= '<div class="country" itemprop="addressCountry">'.$country.'</div>';

			if(	!empty($street) ||
				!empty($pobox) ||
				!empty($city) ||
				!empty($state) ||
				!empty($postalcode) ||
				!empty($country)
				)
				$sc_build .= '</div>';

			if(!empty($email))
				$sc_build .= '<div class="email" itemprop="email">'.antispambot($email).'</div>';

			if(!empty($phone))
				$sc_build .= '<div class="phone" itemprop="telephone">'.__('Phone:', 'schema' ).' '.$phone.'</div>';

			if(!empty($bday))
				$sc_build .= '<div class="bday"><meta itemprop="birthDate" content="'.$bday.'">'.__('DOB:', 'schema' ).' '.date('m/d/Y', strtotime($bday)).'</div>';

			// close it up
			$sc_build .= '</div>';

		}

		// product
		if(isset($type) && $type == 'product') {

		$sc_build .= '<div itemscope itemtype="http://schema.org/Product">';

			if(!empty($name) && !empty($url) ) {
				$sc_build .= '<a class="schema_url" target="_blank" itemprop="url" href="'.esc_url($url).'">';
				$sc_build .= '<div class="schema_name" itemprop="name">'.$name.'</div>';
				$sc_build .= '</a>';
			}

			if(!empty($name) && empty($url) )
				$sc_build .= '<div class="schema_name" itemprop="name">'.$name.'</div>';

			if(!empty($description))
				$sc_build .= '<div class="schema_description" itemprop="description">'.esc_attr($description).'</div>';

			if(!empty($brand))
				$sc_build .= '<div class="brand" itemprop="brand" itemscope itemtype="http://schema.org/Organization"><span class="desc_type">'.__('Brand:', 'schema' ).'</span> <span itemprop="name">'.$brand.'</span></div>';

			if(!empty($manfu))
				$sc_build .= '<div class="manufacturer" itemprop="manufacturer" itemscope itemtype="http://schema.org/Organization"><span class="desc_type">'.__('Manufacturer:', 'schema' ).'</span> <span itemprop="name">'.$manfu.'</span></div>';

			if(!empty($model))
				$sc_build .= '<div class="model"><span class="desc_type">'.__('Model:', 'schema' ).'</span> <span itemprop="model">'.$model.'</span></div>';

			if(!empty($prod_id))
				$sc_build .= '<div class="prod_id"><span class="desc_type">'.__('Product ID:', 'schema' ).'</span> <span itemprop="productID">'.$prod_id.'</span></div>';

			if(!empty($single_rating) && !empty($agg_rating)) {
				$sc_build .= '<div itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">';
				$sc_build .= '<span itemprop="ratingValue">'.$single_rating.'</span> '.__('based on', 'schema' ).' ';
				$sc_build .= '<span itemprop="reviewCount">'.$agg_rating.'</span> '.__('reviews', 'schema' ).'';
				$sc_build .= '</div>';
			}

				// secondary check if one part of review is missing to keep markup consistent
				if(empty($agg_rating) && !empty($single_rating) )
					$sc_build .= '<div itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating"><span itemprop="ratingValue"><span class="desc_type">'.__('Review:', 'schema' ).'</span> '.$single_rating.'</span></div>';

				if(empty($single_rating) && !empty($agg_rating) )
					$sc_build .= '<div itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating"><span itemprop="reviewCount">'.$agg_rating.'</span> '.__('total reviews', 'schema' ).'</div>';

			if(!empty($price) && !empty($condition)) {
				$sc_build .= '<div class="offers" itemprop="offers" itemscope itemtype="http://schema.org/Offer">';
				$sc_build .= '<span class="price" itemprop="price">'.$price.'</span>';
				$sc_build .= '<link itemprop="itemCondition" href="http://schema.org/'.$condition.'Condition" /> '.$condition.'';
				$sc_build .= '</div>';
			}

			if(empty($condition) && !empty ($price))
				$sc_build .= '<div class="offers" itemprop="offers" itemscope itemtype="http://schema.org/Offer"><span class="price" itemprop="price">'.$price.'</span></div>';


			// close it up
			$sc_build .= '</div>';

		}

		// event
		if(isset($type) && $type == 'event') {

		$default   = (!empty($evtype) ? $evtype : 'Event');
		$sc_build .= '<div itemscope itemtype="http://schema.org/'.$default.'">';

			if(!empty($name) && !empty($url) ) {
				$sc_build .= '<a class="schema_url" target="_blank" itemprop="url" href="'.esc_url($url).'">';
				$sc_build .= '<div class="schema_name" itemprop="name">'.$name.'</div>';
				$sc_build .= '</a>';
			}

			if(!empty($name) && empty($url) )
				$sc_build .= '<div class="schema_name" itemprop="name">'.$name.'</div>';

			if(!empty($description))
				$sc_build .= '<div class="schema_description" itemprop="description">'.esc_attr($description).'</div>';

			if(!empty($sdate) && !empty($stime) ) {
				$metatime = $sdate.'T'.date('G:i', strtotime($sdate.$stime));
				$sc_build .= '<div><meta itemprop="startDate" content="'.$metatime.'">'.__('Starts:', 'schema' ).' '.date('m/d/Y', strtotime($sdate)).' '.$stime.'</div>';
			}
				// secondary check for missing start time
				if(empty($stime) && !empty($sdate) )
					$sc_build .= '<div><meta itemprop="startDate" content="'.$sdate.'">'.__('Starts:', 'schema' ).' '.date('m/d/Y', strtotime($sdate)).'</div>';

			if(!empty($edate))
				$sc_build .= '<div><meta itemprop="endDate" content="'.$edate.':00.000">'.__('Ends:', 'schema' ).' '.date('m/d/Y', strtotime($edate)).'</div>';

			if(!empty($duration)) {

				$hour_cnv	= date('G', strtotime($duration));
				$mins_cnv	= date('i', strtotime($duration));

				$hours		= (!empty($hour_cnv) && $hour_cnv > 0 ? $hour_cnv.' '.__('hours:', 'schema' ) : '');
				$minutes	= (!empty($mins_cnv) && $mins_cnv > 0 ? ' '.__('and', 'schema' ).' '.$mins_cnv.' '.__('minutes', 'schema' ) : '');

				$sc_build .= '<div><meta itemprop="duration" content="0000-00-00T'.$duration.'">'.__('Duration:', 'schema' ).' '.$hours.$minutes.'</div>';
			}

			// close actual event portion
			$sc_build .= '</div>';

			if(	!empty($street) ||
				!empty($pobox) ||
				!empty($city) ||
				!empty($state) ||
				!empty($postalcode) ||
				!empty($country)
				)
				$sc_build .= '<div itemprop="address" itemscope itemtype="http://schema.org/PostalAddress">';

			if(!empty($street))
				$sc_build .= '<div class="street" itemprop="streetAddress">'.$street.'</div>';

			if(!empty($pobox))
				$sc_build .= '<div class="pobox">'.__('P.O. Box:', 'schema' ).' <span itemprop="postOfficeBoxNumber">'.$pobox.'</span></div>';

			if(!empty($city) && !empty($state)) {
				$sc_build .= '<div class="city_state">';
				$sc_build .= '<span class="locale" itemprop="addressLocality">'.$city.'</span>,';
				$sc_build .= '<span class="region" itemprop="addressRegion"> '.$state.'</span>';
				$sc_build .= '</div>';
			}

				// secondary check if one part of city / state is missing to keep markup consistent
				if(empty($state) && !empty($city) )
					$sc_build .= '<div class="city_state"><span class="locale" itemprop="addressLocality">'.$city.'</span></div>';

				if(empty($city) && !empty($state) )
					$sc_build .= '<div class="city_state"><span class="region" itemprop="addressRegion">'.$state.'</span></div>';

			if(!empty($postalcode))
				$sc_build .= '<div class="postalcode" itemprop="postalCode">'.$postalcode.'</div>';

			if(!empty($country))
				$sc_build .= '<div class="country" itemprop="addressCountry">'.$country.'</div>';

			if(	!empty($street) ||
				!empty($pobox) ||
				!empty($city) ||
				!empty($state) ||
				!empty($postalcode) ||
				!empty($country)
				)
				$sc_build .= '</div>';

		}

		// organization
		if(isset($type) && $type == 'organization') {

		$default   = (!empty($orgtype) ? $orgtype : 'Organization');
		$sc_build .= '<div itemscope itemtype="http://schema.org/'.$default.'">';

			if(!empty($name) && !empty($url) ) {
				$sc_build .= '<a class="schema_url" target="_blank" itemprop="url" href="'.esc_url($url).'">';
				$sc_build .= '<div class="schema_name" itemprop="name">'.$name.'</div>';
				$sc_build .= '</a>';
			}

			if(!empty($name) && empty($url) )
				$sc_build .= '<div class="schema_name" itemprop="name">'.$name.'</div>';

			if(!empty($description))
				$sc_build .= '<div class="schema_description" itemprop="description">'.esc_attr($description).'</div>';

			if(	!empty($street) ||
				!empty($pobox) ||
				!empty($city) ||
				!empty($state) ||
				!empty($postalcode) ||
				!empty($country)
				)
				$sc_build .= '<div itemprop="address" itemscope itemtype="http://schema.org/PostalAddress">';

			if(!empty($street))
				$sc_build .= '<div class="street" itemprop="streetAddress">'.$street.'</div>';

			if(!empty($pobox))
				$sc_build .= '<div class="pobox">'.__('P.O. Box:', 'schema' ).' <span itemprop="postOfficeBoxNumber">'.$pobox.'</span></div>';

			if(!empty($city) && !empty($state)) {
				$sc_build .= '<div class="city_state">';
				$sc_build .= '<span class="locale" itemprop="addressLocality">'.$city.'</span>,';
				$sc_build .= '<span class="region" itemprop="addressRegion"> '.$state.'</span>';
				$sc_build .= '</div>';
			}

				// secondary check if one part of city / state is missing to keep markup consistent
				if(empty($state) && !empty($city) )
					$sc_build .= '<div class="city_state"><span class="locale" itemprop="addressLocality">'.$city.'</span></div>';

				if(empty($city) && !empty($state) )
					$sc_build .= '<div class="city_state"><span class="region" itemprop="addressRegion">'.$state.'</span></div>';

			if(!empty($postalcode))
				$sc_build .= '<div class="postalcode" itemprop="postalCode">'.$postalcode.'</div>';

			if(!empty($country))
				$sc_build .= '<div class="country" itemprop="addressCountry">'.$country.'</div>';

			if(	!empty($street) ||
				!empty($pobox) ||
				!empty($city) ||
				!empty($state) ||
				!empty($postalcode) ||
				!empty($country)
				)
				$sc_build .= '</div>';

			if(!empty($email))
				$sc_build .= '<div class="email" itemprop="email">'.antispambot($email).'</div>';

			if(!empty($phone))
				$sc_build .= '<div class="phone" itemprop="telephone">'.__('Phone:', 'schema' ).' '.$phone.'</div>';

			if(!empty($fax))
				$sc_build .= '<div class="fax" itemprop="faxNumber">'.__('Fax:', 'schema' ).' '.$fax.'</div>';

			// close it up
			$sc_build .= '</div>';

		}

		// movie
		if(isset($type) && $type == 'movie') {

		$sc_build .= '<div itemscope itemtype="http://schema.org/Movie">';

			if(!empty($name) && !empty($url) ) {
				$sc_build .= '<a class="schema_url" target="_blank" itemprop="url" href="'.esc_url($url).'">';
				$sc_build .= '<div class="schema_name" itemprop="name">'.$name.'</div>';
				$sc_build .= '</a>';
			}

			if(!empty($name) && empty($url) )
				$sc_build .= '<div class="schema_name" itemprop="name">'.$name.'</div>';

			if(!empty($description))
				$sc_build .= '<div class="schema_description" itemprop="description">'.esc_attr($description).'</div>';


			if(!empty($director))
				$sc_build .= '<div itemprop="director" itemscope itemtype="http://schema.org/Person">'.__('Directed by:', 'schema' ).' <span itemprop="name">'.$director.'</span></div>';

			if(!empty($producer))
				$sc_build .= '<div itemprop="producer" itemscope itemtype="http://schema.org/Person">'.__('Produced by:', 'schema' ).' <span itemprop="name">'.$producer.'</span></div>';

			if(!empty($actor_1)) {
				$sc_build .= '<div>'.__('Starring:', 'schema' ).'';
					foreach ($actors as $actor) {
						$sc_build .= '<div itemprop="actors" itemscope itemtype="http://schema.org/Person">';
						$sc_build .= '<span itemprop="name">'.$actor.'</span>';
						$sc_build .= '</div>';
					}
				$sc_build .= '</div>';
			}


			// close it up
			$sc_build .= '</div>';

		}

		// book
		if(isset($type) && $type == 'book') {

		$sc_build .= '<div itemscope itemtype="http://schema.org/Book">';

			if(!empty($name) && !empty($url) ) {
				$sc_build .= '<a class="schema_url" target="_blank" itemprop="url" href="'.esc_url($url).'">';
				$sc_build .= '<div class="schema_name" itemprop="name">'.$name.'</div>';
				$sc_build .= '</a>';
			}

			if(!empty($name) && empty($url) )
				$sc_build .= '<div class="schema_name" itemprop="name">'.$name.'</div>';

			if(!empty($description))
				$sc_build .= '<div class="schema_description" itemprop="description">'.esc_attr($description).'</div>';

			if(!empty($author))
				$sc_build .= '<div itemprop="author" itemscope itemtype="http://schema.org/Person">'.__('Written by:', 'schema' ).' <span itemprop="name">'.$author.'</span></div>';

			if(!empty($publisher))
				$sc_build .= '<div itemprop="publisher" itemscope itemtype="http://schema.org/Organization">'.__('Published by:', 'schema' ).' <span itemprop="name">'.$publisher.'</span></div>';

			if(!empty($pubdate))
				$sc_build .= '<div class="bday"><meta itemprop="datePublished" content="'.$pubdate.'">'.__('Date Published:', 'schema' ).' '.date('m/d/Y', strtotime($pubdate)).'</div>';

			if(!empty($edition))
				$sc_build .= '<div>'.__('Edition:', 'schema' ).' <span itemprop="bookEdition">'.$edition.'</span></div>';

			if(!empty($isbn))
				$sc_build .= '<div>'.__('ISBN:', 'schema' ).' <span itemprop="isbn">'.$isbn.'</span></div>';

			if( !empty($ebook) || !empty($paperback) || !empty($hardcover) ) {
				$sc_build .= '<div>'.__('Available in:', 'schema' ).' ';

					if(!empty($ebook))
						$sc_build .= '<link itemprop="bookFormat" href="http://schema.org/Ebook">'.__('Ebook', 'schema' ).' ';

					if(!empty($paperback))
						$sc_build .= '<link itemprop="bookFormat" href="http://schema.org/Paperback">'.__('Paperback', 'schema' ).' ';

					if(!empty($hardcover))
						$sc_build .= '<link itemprop="bookFormat" href="http://schema.org/Hardcover">'.__('Hardcover', 'schema' ).' ';

				$sc_build .= '</div>';
			}


			// close it up
			$sc_build .= '</div>';

		}

		// review
		if(isset($type) && $type == 'review') {

		$sc_build .= '<div itemscope itemtype="http://schema.org/Review">';

			if(!empty($name) && !empty($url) ) {
				$sc_build .= '<a class="schema_url" target="_blank" itemprop="url" href="'.esc_url($url).'">';
				$sc_build .= '<div class="schema_name" itemprop="name">'.$name.'</div>';
				$sc_build .= '</a>';
			}

			if(!empty($name) && empty($url) )
				$sc_build .= '<div class="schema_name" itemprop="name">'.$name.'</div>';

			if(!empty($description))
				$sc_build .= '<div class="schema_description" itemprop="description">'.esc_attr($description).'</div>';

			if(!empty($rev_name))
				$sc_build .= '<div class="schema_review_name" itemprop="itemReviewed" itemscope itemtype="http://schema.org/Thing"><span itemprop="name">'.$rev_name.'</span></div>';

			if(!empty($author))
				$sc_build .= '<div itemprop="author" itemscope itemtype="http://schema.org/Person">'.__('Written by:', 'schema').' <span itemprop="name">'.$author.'</span></div>';

			if(!empty($pubdate))
				$sc_build .= '<div class="pubdate"><meta itemprop="datePublished" content="'.$pubdate.'">'.__('Date Published:', 'schema').' '.date('m/d/Y', strtotime($pubdate)).'</div>';

			if(!empty($rev_body))
				$sc_build .= '<div class="schema_review_body" itemprop="reviewBody">'.esc_textarea($rev_body).'</div>';

			if(!empty($user_review) ) {
				$sc_build .= '<div itemprop="reviewRating" itemscope itemtype="http://schema.org/Rating">';

				// minimum review scale
				if(!empty($min_review))
					$sc_build .= '<meta itemprop="worstRating" content="'.$min_review.'">';

				$sc_build .= '<span itemprop="ratingValue">'.$user_review.'</span>';

				// max review scale
				if(!empty($max_review))
					$sc_build .= ' / <span itemprop="bestRating">'.$max_review.'</span> '.__('stars', 'schema' ).'';


				$sc_build .= '</div>';
			}

			// close it up
			$sc_build .= '</div>';

		}

		// recipe
		if(isset($type) && $type == 'recipe') {

		$sc_build .= '<div itemscope itemtype="http://schema.org/Recipe">';

			$imgalt = isset($name) ? $name : __('Recipe Image', 'schema' );

			if(!empty($image)) // put image first so it can lay out better
				$sc_build .= '<img class="schema_image" itemprop="image" src="'.esc_url($image).'" alt="'.$imgalt.'" />';

			if(!empty($name) )
				$sc_build .= '<div class="schema_name header_type" itemprop="name">'.$name.'</div>';

			if(!empty($author) && !empty($pubdate) ) {
				$sc_build .= '<div class="schema_byline">';
				$sc_build .= ''.__('By', 'schema' ).' <span class="schema_strong" itemprop="author">'.$author.'</span>';
				$sc_build .= ' '.__('on', 'schema' ).' <span class="schema_pubdate"><meta itemprop="datePublished" content="'.$pubdate.'">'.date('m/d/Y', strtotime($pubdate)).'</span>';
				$sc_build .= '</div>';
			}

			if(!empty($author) && empty($pubdate) )
				$sc_build .= '<div class="schema_author"> '.__('by', 'schema' ).' <span class="schema_strong" itemprop="author">'.$author.'<span></div>';

			if(!empty($description))
				$sc_build .= '<div class="schema_description" itemprop="description">'.esc_attr($description).'</div>';

			if(!empty($yield) || !empty($prephours) || !empty($prepmins) || !empty($cookhours) || !empty($cookmins) ) {
				$sc_build .= '<div>';

				// PREP: both variables present
				if( !empty($prephours) && !empty($prepmins) ) {

					$hrsuffix = $prephours	> 1 ? __('hours', 'schema' )	: __('hour', 'schema' );
					$mnsuffix = $prepmins	> 1 ? __('minutes', 'schema' )	: __('minute', 'schema' );

					$sc_build .= '<p class="stacked">';
					$sc_build .= '<span class="schema_strong">'.__('Prep Time:', 'schema' ).'</span> ';
					$sc_build .= '<meta itemprop="prepTime" content="PT'.$prephours.'H'.$prepmins.'M">';
					$sc_build .= $prephours.' '.$hrsuffix.', '.$prepmins.' '.$mnsuffix.'';
					$sc_build .= '</p>';
				}

				// PREP: no minutes
				if( !empty($prephours) && empty($prepmins) ) {

					$hrsuffix = $prephours	> 1 ? __('hours', 'schema' )	: __('hour', 'schema' );

					$sc_build .= '<p class="stacked">';
					$sc_build .= '<span class="schema_strong">'.__('Prep Time:', 'schema' ).'</span> ';
					$sc_build .= '<meta itemprop="prepTime" content="PT'.$prephours.'H">';
					$sc_build .= $prephours.' '.$hrsuffix.'';
					$sc_build .= '</p>';
				}

				// PREP: no hours
				if( !empty($prepmins) && empty($prephours) ) {

					$mnsuffix = $prepmins	> 1 ? __('minutes', 'schema' )	: __('minute', 'schema' );

					$sc_build .= '<p class="stacked">';
					$sc_build .= '<span class="schema_strong">'.__('Prep Time:', 'schema' ).'</span> ';
					$sc_build .= '<meta itemprop="prepTime" content="PT'.$prepmins.'M">';
					$sc_build .= $prepmins.' '.$mnsuffix.'';
					$sc_build .= '</p>';
				}

				// COOK: both variables present
				if( !empty($cookhours) && !empty($cookmins) ) {

					$hrsuffix = $prephours	> 1 ? __('hours', 'schema' )	: __('hour', 'schema' );
					$mnsuffix = $prepmins	> 1 ? __('minutes', 'schema' )	: __('minute', 'schema' );

					$sc_build .= '<p class="stacked">';
					$sc_build .= '<span class="schema_strong">'.__('Cook Time:', 'schema' ).'</span> ';
					$sc_build .= '<meta itemprop="cookTime" content="PT'.$cookhours.'H'.$cookmins.'M">';
					$sc_build .= $cookhours.' '.$hrsuffix.', '.$cookmins.' '.$mnsuffix.'';
					$sc_build .= '</p>';
				}

				// COOK: no minutes
				if( !empty($cookhours) && empty($cookmins) ) {

					$hrsuffix = $prephours	> 1 ? __('hours', 'schema' )	: __('hour', 'schema' );

					$sc_build .= '<p class="stacked">';
					$sc_build .= '<span class="schema_strong">'.__('Cook Time:', 'schema' ).'</span> ';
					$sc_build .= '<meta itemprop="cookTime" content="PT'.$cookhours.'H">';
					$sc_build .= $cookhours.' '.$hrsuffix.'';
					$sc_build .= '</p>';
				}

				// COOK: no hours
				if( !empty($cookmins) && empty($cookhours) ) {

					$mnsuffix = $prepmins	> 1 ? __('minutes', 'schema' )	: __('minute', 'schema' );

					$sc_build .= '<p class="stacked">';
					$sc_build .= '<span class="schema_strong">'.__('Cook Time:', 'schema' ).'</span> ';
					$sc_build .= '<meta itemprop="cookTime" content="PT'.$cookmins.'M">';
					$sc_build .= $cookmins.' '.$mnsuffix.'';
					$sc_build .= '</p>';
				}

				// YIELD
				if( !empty($yield) ) {

					$sc_build .= '<p class="stacked">';
					$sc_build .= '<span class="schema_strong">'.__('Yield:', 'schema' ).'</span> ';
					$sc_build .= '<meta itemprop="recipeYield">';
					$sc_build .= $yield;
					$sc_build .= '</p>';
				}

				$sc_build .= '</div>';
			}

			if( !empty($calories) || !empty($fatcount) || !empty($sugarcount) || !empty($saltcount) ) {
				$sc_build .= '<div itemprop="nutrition" itemscope itemtype="http://schema.org/NutritionInformation">';
				$sc_build .= '<span class="schema_strong">'.__('Nutrition Information:', 'schema' ).'</span><ul>';

					if(!empty($calories))
						$sc_build .= '<li><span itemprop="calories">'.$calories.' '.__('calories', 'schema' ).'</span></li>';

					if(!empty($fatcount))
						$sc_build .= '<li><span itemprop="fatContent">'.$fatcount.' '.__('grams of fat', 'schema' ).'</span></li>';

					if(!empty($sugarcount))
						$sc_build .= '<li><span itemprop="sugarContent">'.$sugarcount.' '.__('grams of sugar', 'schema' ).'</span></li>';

					if(!empty($saltcount))
						$sc_build .= '<li><span itemprop="sodiumContent">'.$saltcount.' '.__('milligrams of sodium', 'schema' ).'</span></li>';

				$sc_build .= '</ul></div>';
			}

			if(!empty($ingrt_1)) {
				$sc_build .= '<div><span class="schema_strong">'.__('Ingredients:', 'schema' ).'</span>';
				$sc_build .= '<ul>';
					foreach ($ingrts as $ingrt) {
						$sc_build .= '<li><span itemprop="ingredients">'.$ingrt.'</span></li>';
					}
				$sc_build .= '</ul>';
				$sc_build .= '</div>';
			}

			if(!empty($instructions))
				$sc_build .= '<div class="schema_instructions" itemprop="recipeInstructions"><span class="schema_strong">'.__('Instructions:', 'schema' ).'</span><br />'.esc_attr($instructions).'</div>';



			// close it up
			$sc_build .= '</div>';

		}


		// close schema wrap
		$sc_build .= '</div>';

	// return entire build array
	return $sc_build;

	}

	/**
	 * Add button to top level media row
	 *
	 * @return ravenSchema
	 */

	public function media_button() {

		// don't show on dashboard (QuickPress)
		$current_screen = get_current_screen();
		if ( 'dashboard' == $current_screen->base )
			return;

		// don't display button for users who don't have access
		if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') )
			return;

		// do a version check for the new 3.5 UI
		$version	= get_bloginfo('version');

		if ($version < 3.5) {
			// show button for v 3.4 and below
			echo '<a href="#TB_inline?width=650&inlineId=schema_build_form" class="thickbox schema_clear schema_one" id="add_schema" title="' . __('Schema Creator Form') . '">' . __('Schema Creator Form', 'schema' ) . '</a>';
		} else {
			// display button matching new UI
			$img = '<span class="schema-media-icon"></span> ';
			echo '<a href="#TB_inline?width=650&inlineId=schema_build_form" class="thickbox schema_clear schema_two button" id="add_schema" title="' . esc_attr__( 'Add Schema' ) . '">' . $img . __( 'Add Schema', 'schema' ) . '</a>';
		}

	}

	/**
	 * Build form and add into footer
	 *
	 * @return ravenSchema
	 */

	public function schema_form() {

		// don't load form on non-editing pages
		$current_screen = get_current_screen();
		if ( 'post' !== $current_screen->base )
			return;

		// don't display form for users who don't have access
		if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') )
		return;

	?>

		<script type="text/javascript">
			function InsertSchema() {
				//select field options
					var type			= jQuery('#schema_builder select#schema_type').val();
					var evtype			= jQuery('#schema_builder select#schema_evtype').val();
					var orgtype			= jQuery('#schema_builder select#schema_orgtype').val();
					var country			= jQuery('#schema_builder select#schema_country').val();
					var condition		= jQuery('#schema_builder select#schema_condition').val();
				//text field options
					var name			= jQuery('#schema_builder input#schema_name').val();
					var orgname			= jQuery('#schema_builder input#schema_orgname').val();
					var jobtitle		= jQuery('#schema_builder input#schema_jobtitle').val();
					var url				= jQuery('#schema_builder input#schema_url').val();
					var bday			= jQuery('#schema_builder input#schema_bday-format').val();
					var street			= jQuery('#schema_builder input#schema_street').val();
					var pobox			= jQuery('#schema_builder input#schema_pobox').val();
					var city			= jQuery('#schema_builder input#schema_city').val();
					var state			= jQuery('#schema_builder input#schema_state').val();
					var postalcode		= jQuery('#schema_builder input#schema_postalcode').val();
					var email			= jQuery('#schema_builder input#schema_email').val();
					var phone			= jQuery('#schema_builder input#schema_phone').val();
					var fax				= jQuery('#schema_builder input#schema_fax').val();
					var brand			= jQuery('#schema_builder input#schema_brand').val();
					var manfu			= jQuery('#schema_builder input#schema_manfu').val();
					var model			= jQuery('#schema_builder input#schema_model').val();
					var prod_id			= jQuery('#schema_builder input#schema_prod_id').val();
					var single_rating	= jQuery('#schema_builder input#schema_single_rating').val();
					var agg_rating		= jQuery('#schema_builder input#schema_agg_rating').val();
					var price			= jQuery('#schema_builder input#schema_price').val();
					var sdate			= jQuery('#schema_builder input#schema_sdate-format').val();
					var stime			= jQuery('#schema_builder input#schema_stime').val();
					var edate			= jQuery('#schema_builder input#schema_edate-format').val();
					var duration		= jQuery('#schema_builder input#schema_duration').val();
					var actor_group		= jQuery('#schema_builder input#schema_actor_1').val();
					var director		= jQuery('#schema_builder input#schema_director').val();
					var producer		= jQuery('#schema_builder input#schema_producer').val();
					var author			= jQuery('#schema_builder input#schema_author').val();
					var publisher		= jQuery('#schema_builder input#schema_publisher').val();
					var edition			= jQuery('#schema_builder input#schema_edition').val();
					var isbn			= jQuery('#schema_builder input#schema_isbn').val();
					var pubdate			= jQuery('#schema_builder input#schema_pubdate-format').val();
					var ebook			= jQuery('#schema_builder input#schema_ebook').is(':checked');
					var paperback		= jQuery('#schema_builder input#schema_paperback').is(':checked');
					var hardcover		= jQuery('#schema_builder input#schema_hardcover').is(':checked');
					var rev_name		= jQuery('#schema_builder input#schema_rev_name').val();
					var user_review		= jQuery('#schema_builder input#schema_user_review').val();
					var min_review		= jQuery('#schema_builder input#schema_min_review').val();
					var max_review		= jQuery('#schema_builder input#schema_max_review').val();
					var ingrt_group		= jQuery('#schema_builder input#schema_ingrt_1').val();
					var image			= jQuery('#schema_builder input#schema_image').val();
					var prephours		= jQuery('#schema_builder input#schema_prep_hours').val();
					var prepmins		= jQuery('#schema_builder input#schema_prep_mins').val();
					var cookhours		= jQuery('#schema_builder input#schema_cook_hours').val();
					var cookmins		= jQuery('#schema_builder input#schema_cook_mins').val();
					var yield			= jQuery('#schema_builder input#schema_yield').val();
					var calories		= jQuery('#schema_builder input#schema_calories').val();
					var fatcount		= jQuery('#schema_builder input#schema_fatcount').val();
					var sugarcount		= jQuery('#schema_builder input#schema_sugarcount').val();
					var saltcount		= jQuery('#schema_builder input#schema_saltcount').val();
				// textfield options
					var description		= jQuery('#schema_builder textarea#schema_description').val();
					var rev_body		= jQuery('#schema_builder textarea#schema_rev_body').val();
					var instructions	= jQuery('#schema_builder textarea#schema_instructions').val();


			// output setups
			output = '[schema ';
				output += 'type="' + type + '" ';

				// person
				if(type == 'person' ) {
					if(name)
						output += 'name="' + name + '" ';
					if(orgname)
						output += 'orgname="' + orgname + '" ';
					if(jobtitle)
						output += 'jobtitle="' + jobtitle + '" ';
					if(url)
						output += 'url="' + url + '" ';
					if(description)
						output += 'description="' + description + '" ';
					if(bday)
						output += 'bday="' + bday + '" ';
					if(street)
						output += 'street="' + street + '" ';
					if(pobox)
						output += 'pobox="' + pobox + '" ';
					if(city)
						output += 'city="' + city + '" ';
					if(state)
						output += 'state="' + state + '" ';
					if(postalcode)
						output += 'postalcode="' + postalcode + '" ';
					if(country && country !== 'none')
						output += 'country="' + country + '" ';
					if(email)
						output += 'email="' + email + '" ';
					if(phone)
						output += 'phone="' + phone + '" ';
				}

				// product
				if(type == 'product' ) {
					if(url)
						output += 'url="' + url + '" ';
					if(name)
						output += 'name="' + name + '" ';
					if(description)
						output += 'description="' + description + '" ';
					if(brand)
						output += 'brand="' + brand + '" ';
					if(manfu)
						output += 'manfu="' + manfu + '" ';
					if(model)
						output += 'model="' + model + '" ';
					if(prod_id)
						output += 'prod_id="' + prod_id + '" ';
					if(single_rating)
						output += 'single_rating="' + single_rating + '" ';
					if(agg_rating)
						output += 'agg_rating="' + agg_rating + '" ';
					if(price)
						output += 'price="' + price + '" ';
					if(condition && condition !=='none')
						output += 'condition="' + condition + '" ';
				}

				// event
				if(type == 'event' ) {
					if(evtype && evtype !== 'none')
						output += 'evtype="' + evtype + '" ';
					if(url)
						output += 'url="' + url + '" ';
					if(name)
						output += 'name="' + name + '" ';
					if(description)
						output += 'description="' + description + '" ';
					if(sdate)
						output += 'sdate="' + sdate + '" ';
					if(stime)
						output += 'stime="' + stime + '" ';
					if(edate)
						output += 'edate="' + edate + '" ';
					if(duration)
						output += 'duration="' + duration + '" ';
					if(street)
						output += 'street="' + street + '" ';
					if(pobox)
						output += 'pobox="' + pobox + '" ';
					if(city)
						output += 'city="' + city + '" ';
					if(state)
						output += 'state="' + state + '" ';
					if(postalcode)
						output += 'postalcode="' + postalcode + '" ';
					if(country && country !== 'none')
						output += 'country="' + country + '" ';
				}

				// organization
				if(type == 'organization' ) {
					if(orgtype)
						output += 'orgtype="' + orgtype + '" ';
					if(url)
						output += 'url="' + url + '" ';
					if(name)
						output += 'name="' + name + '" ';
					if(description)
						output += 'description="' + description + '" ';
					if(street)
						output += 'street="' + street + '" ';
					if(pobox)
						output += 'pobox="' + pobox + '" ';
					if(city)
						output += 'city="' + city + '" ';
					if(state)
						output += 'state="' + state + '" ';
					if(postalcode)
						output += 'postalcode="' + postalcode + '" ';
					if(country && country !== 'none')
						output += 'country="' + country + '" ';
					if(email)
						output += 'email="' + email + '" ';
					if(phone)
						output += 'phone="' + phone + '" ';
					if(fax)
						output += 'fax="' + fax + '" ';
				}

				// movie
				if(type == 'movie' ) {
					if(url)
						output += 'url="' + url + '" ';
					if(name)
						output += 'name="' + name + '" ';
					if(description)
						output += 'description="' + description + '" ';
					if(director)
						output += 'director="' + director + '" ';
					if(producer)
						output += 'producer="' + producer + '" ';
					if(actor_group) {
						var count = 0;
						jQuery('div.sc_actor').each(function(){
							count++;
							var actor = jQuery(this).find('input').val();
							output += 'actor_' + count + '="' + actor + '" ';
						});
					}
				}

				// book
				if(type == 'book' ) {
					if(url)
						output += 'url="' + url + '" ';
					if(name)
						output += 'name="' + name + '" ';
					if(description)
						output += 'description="' + description + '" ';
					if(author)
						output += 'author="' + author + '" ';
					if(publisher)
						output += 'publisher="' + publisher + '" ';
					if(pubdate)
						output += 'pubdate="' + pubdate + '" ';
					if(edition)
						output += 'edition="' + edition + '" ';
					if(isbn)
						output += 'isbn="' + isbn + '" ';
					if(ebook === true )
						output += 'ebook="yes" ';
					if(paperback === true )
						output += 'paperback="yes" ';
					if(hardcover === true )
						output += 'hardcover="yes" ';
				}

				// review
				if(type == 'review' ) {
					if(url)
						output += 'url="' + url + '" ';
					if(name)
						output += 'name="' + name + '" ';
					if(description)
						output += 'description="' + description + '" ';
					if(rev_name)
						output += 'rev_name="' + rev_name + '" ';
					if(rev_body)
						output += 'rev_body="' + rev_body + '" ';
					if(author)
						output += 'author="' + author + '" ';
					if(pubdate)
						output += 'pubdate="' + pubdate + '" ';
					if(user_review)
						output += 'user_review="' + user_review + '" ';
					if(min_review)
						output += 'min_review="' + min_review + '" ';
					if(max_review)
						output += 'max_review="' + max_review + '" ';
				}

				// recipe
				if(type == 'recipe' ) {
					if(name)
						output += 'name="' + name + '" ';
					if(author)
						output += 'author="' + author + '" ';
					if(pubdate)
						output += 'pubdate="' + pubdate + '" ';
					if(image)
						output += 'image="' + image + '" ';
					if(description)
						output += 'description="' + description + '" ';
					if(prephours)
						output += 'prephours="' + prephours + '" ';
					if(prepmins)
						output += 'prepmins="' + prepmins + '" ';
					if(cookhours)
						output += 'cookhours="' + cookhours + '" ';
					if(cookmins)
						output += 'cookmins="' + cookmins + '" ';
					if(yield)
						output += 'yield="' + yield + '" ';
					if(calories)
						output += 'calories="' + calories + '" ';
					if(fatcount)
						output += 'fatcount="' + fatcount + '" ';
					if(sugarcount)
						output += 'sugarcount="' + sugarcount + '" ';
					if(saltcount)
						output += 'saltcount="' + saltcount + '" ';
					if(ingrt_group) {
						var count = 0;
						jQuery('div.sc_ingrt').each(function(){
							count++;
							var ingrt = jQuery(this).find('input').val();
							output += 'ingrt_' + count + '="' + ingrt + '" ';
						});
					}
					if(instructions)
						output += 'instructions="' + instructions + '" ';
				}

			output += ']';

			window.send_to_editor(output);
			}
		</script>

			<div id="schema_build_form" style="display:none;">
			<div id="schema_builder" class="schema_wrap">
			<!-- schema type dropdown -->
				<div id="sc_type">
					<label for="schema_type"><?php _e('Schema Type', 'schema'); ?></label>
					<select name="schema_type" id="schema_type" class="schema_drop schema_thindrop">
						<option class="holder" value="none">(<?php _e('Select A Type', 'schema'); ?>)</option>
						<option value="person"><?php _e('Person', 'schema'); ?></option>
						<option value="product"><?php _e('Product', 'schema'); ?></option>
						<option value="event"><?php _e('Event', 'schema'); ?></option>
						<option value="organization"><?php _e('Organization', 'schema'); ?></option>
						<option value="movie"><?php _e('Movie', 'schema'); ?></option>
						<option value="book"><?php _e('Book', 'schema'); ?></option>
						<option value="review"><?php _e('Review', 'schema'); ?></option>
						<option value="recipe"><?php _e('Recipe', 'schema'); ?></option>
					</select>
				</div>
			<!-- end schema type dropdown -->

				<div id="sc_evtype" class="sc_option" style="display:none">
					<label for="schema_evtype"><?php _e('Event Type', 'schema'); ?></label>
					<select name="schema_evtype" id="schema_evtype" class="schema_drop schema_thindrop">
						<option value="Event"><?php _e('General', 'schema'); ?></option>
						<option value="BusinessEvent"><?php _e('Business', 'schema'); ?></option>
						<option value="ChildrensEvent"><?php _e('Childrens', 'schema'); ?></option>
						<option value="ComedyEvent"><?php _e('Comedy', 'schema'); ?></option>
						<option value="DanceEvent"><?php _e('Dance', 'schema'); ?></option>
						<option value="EducationEvent"><?php _e('Education', 'schema'); ?></option>
						<option value="Festival"><?php _e('Festival', 'schema'); ?></option>
						<option value="FoodEvent"><?php _e('Food', 'schema'); ?></option>
						<option value="LiteraryEvent"><?php _e('Literary', 'schema'); ?></option>
						<option value="MusicEvent"><?php _e('Music', 'schema'); ?></option>
						<option value="SaleEvent"><?php _e('Sale', 'schema'); ?></option>
						<option value="SocialEvent"><?php _e('Social', 'schema'); ?></option>
						<option value="SportsEvent"><?php _e('Sports', 'schema'); ?></option>
						<option value="TheaterEvent"><?php _e('Theater', 'schema'); ?></option>
						<option value="UserInteraction"><?php _e('User Interaction', 'schema'); ?></option>
						<option value="VisualArtsEvent"><?php _e('Visual Arts', 'schema'); ?></option>
					</select>
				</div>

				<div id="sc_orgtype" class="sc_option" style="display:none">
					<label for="schema_orgtype"><?php _e('Organziation Type', 'schema'); ?></label>
					<select name="schema_orgtype" id="schema_orgtype" class="schema_drop schema_thindrop">
						<option value="Organization"><?php _e('General', 'schema'); ?></option>
						<option value="Corporation"><?php _e('Corporation', 'schema'); ?></option>
						<option value="EducationalOrganization"><?php _e('School', 'schema'); ?></option>
						<option value="GovernmentOrganization"><?php _e('Government', 'schema'); ?></option>
						<option value="LocalBusiness"><?php _e('Local Business', 'schema'); ?></option>
						<option value="NGO"><?php _e('NGO', 'schema'); ?></option>
						<option value="PerformingGroup"><?php _e('Performing Group', 'schema'); ?></option>
						<option value="SportsTeam"><?php _e('Sports Team', 'schema'); ?></option>
					</select>
				</div>

				<div id="sc_name" class="sc_option" style="display:none">
					<label for="schema_name"><?php _e('Name', 'schema'); ?></label>
					<input type="text" name="schema_name" class="form_full" value="" id="schema_name" />
				</div>

				<div id="sc_image" class="sc_option" style="display:none">
					<label for="schema_image">Image URL</label>
					<input type="text" name="schema_image" class="form_full" value="" id="schema_image" />
				</div>

				<div id="sc_orgname" class="sc_option" style="display:none">
					<label for="schema_orgname"><?php _e('Organization', 'schema'); ?></label>
					<input type="text" name="schema_orgname" class="form_full" value="" id="schema_orgname" />
				</div>

				<div id="sc_jobtitle" class="sc_option" style="display:none">
					<label for="schema_jobtitle"><?php _e('Job Title', 'schema'); ?></label>
					<input type="text" name="schema_jobtitle" class="form_full" value="" id="schema_jobtitle" />
				</div>

				<div id="sc_url" class="sc_option" style="display:none">
					<label for="schema_url"><?php _e('Website', 'schema'); ?></label>
					<input type="text" name="schema_url" class="form_full" value="" id="schema_url" />
				</div>

				<div id="sc_description" class="sc_option" style="display:none">
					<label for="schema_description"><?php _e('Description', 'schema'); ?></label>
					<textarea name="schema_description" id="schema_description"></textarea>
				</div>

				<div id="sc_rev_name" class="sc_option" style="display:none">
					<label for="schema_rev_name"><?php _e('Item Name', 'schema'); ?></label>
					<input type="text" name="schema_rev_name" class="form_full" value="" id="schema_rev_name" />
				</div>

				<div id="sc_rev_body" class="sc_option" style="display:none">
					<label for="schema_rev_body"><?php _e('Item Review', 'schema'); ?></label>
					<textarea name="schema_rev_body" id="schema_rev_body"></textarea>
				</div>

				<div id="sc_director" class="sc_option" style="display:none">
					<label for="schema_director"><?php _e('Director', 'schema'); ?></label>
					<input type="text" name="schema_director" class="form_full" value="" id="schema_director" />
				</div>

				<div id="sc_producer" class="sc_option" style="display:none">
					<label for="schema_producer"><?php _e('Productor', 'schema'); ?></label>
					<input type="text" name="schema_producer" class="form_full" value="" id="schema_producer" />
				</div>

				<div id="sc_actor_1" class="sc_option sc_actor sc_repeater" style="display:none">
					<label for="schema_actor_1"><?php _e('Actor', 'schema'); ?></label>
					<input type="text" name="schema_actor_1" class="form_full actor_input" value="" id="schema_actor_1" />
				</div>

				<input type="button" id="clone_actor" value="<?php _e('Add Another Actor', 'schema'); ?>" style="display:none;" />

				<div id="sc_sdate" class="sc_option" style="display:none">
					<label for="schema_sdate"><?php _e('Start Date', 'schema'); ?></label>
					<input type="text" id="schema_sdate" name="schema_sdate" class="schema_datepicker timepicker form_third" value="" />
					<input type="hidden" id="schema_sdate-format" class="schema_datepicker-format" value="" />
				</div>

				<div id="sc_stime" class="sc_option" style="display:none">
					<label for="schema_stime"><?php _e('Start Time', 'schema'); ?></label>
					<input type="text" id="schema_stime" name="schema_stime" class="schema_timepicker form_third" value="" />
				</div>

				<div id="sc_edate" class="sc_option" style="display:none">
					<label for="schema_edate"><?php _e('End Date', 'schema'); ?></label>
					<input type="text" id="schema_edate" name="schema_edate" class="schema_datepicker form_third" value="" />
					<input type="hidden" id="schema_edate-format" class="schema_datepicker-format" value="" />
				</div>

				<div id="sc_duration" class="sc_option" style="display:none">
					<label for="schema_duration"><?php _e('Duration', 'schema'); ?></label>
					<input type="text" id="schema_duration" name="schema_duration" class="schema_timepicker form_third" value="" />
				</div>

				<div id="sc_bday" class="sc_option" style="display:none">
					<label for="schema_bday"><?php _e('Birthday', 'schema'); ?></label>
					<input type="text" id="schema_bday" name="schema_bday" class="schema_datepicker form_third" value="" />
					<input type="hidden" id="schema_bday-format" class="schema_datepicker-format" value="" />
				</div>

				<div id="sc_street" class="sc_option" style="display:none">
					<label for="schema_street"><?php _e('Address', 'schema'); ?></label>
					<input type="text" name="schema_street" class="form_full" value="" id="schema_street" />
				</div>

				<div id="sc_pobox" class="sc_option" style="display:none">
					<label for="schema_pobox"><?php _e('PO Box', 'schema'); ?></label>
					<input type="text" name="schema_pobox" class="form_third schema_numeric" value="" id="schema_pobox" />
				</div>

				<div id="sc_city" class="sc_option" style="display:none">
					<label for="schema_city"><?php _e('City', 'schema'); ?></label>
					<input type="text" name="schema_city" class="form_full" value="" id="schema_city" />
				</div>

				<div id="sc_state" class="sc_option" style="display:none">
					<label for="schema_state"><?php _e('State / Region', 'schema'); ?></label>
					<input type="text" name="schema_state" class="form_third" value="" id="schema_state" />
				</div>

				<div id="sc_postalcode" class="sc_option" style="display:none">
					<label for="schema_postalcode"><?php _e('Postal Code', 'schema'); ?></label>
					<input type="text" name="schema_postalcode" class="form_third" value="" id="schema_postalcode" />
				</div>

				<div id="sc_country" class="sc_option" style="display:none">
					<label for="schema_country"><?php _e('Country', 'schema'); ?></label>
					<select name="schema_country" id="schema_country" class="schema_drop schema_thindrop">
						<option class="holder" value="none">(<?php _e('Select A Country', 'schema'); ?>)</option>
						<option value="US"><?php _e('United States', 'schema'); ?></option>
						<option value="CA"><?php _e('Canada', 'schema'); ?></option>
						<option value="MX"><?php _e('Mexico', 'schema'); ?></option>
						<option value="GB"><?php _e('United Kingdom', 'schema'); ?></option>
						<option value="AF"><?php _e('Afghanistan', 'schema'); ?></option>
						<option value="AX"><?php _e('Aland Islands', 'schema'); ?></option>
						<option value="AL"><?php _e('Albania', 'schema'); ?></option>
						<option value="DZ"><?php _e('Algeria', 'schema'); ?></option>
						<option value="AS"><?php _e('American Samoa', 'schema'); ?></option>
						<option value="AD"><?php _e('Andorra', 'schema'); ?></option>
						<option value="AO"><?php _e('Angola', 'schema'); ?></option>
						<option value="AI"><?php _e('Anguilla', 'schema'); ?></option>
						<option value="AQ"><?php _e('Antarctica', 'schema'); ?></option>
						<option value="AG"><?php _e('Antigua And Barbuda', 'schema'); ?></option>
						<option value="AR"><?php _e('Argentina', 'schema'); ?></option>
						<option value="AM"><?php _e('Armenia', 'schema'); ?></option>
						<option value="AW"><?php _e('Aruba', 'schema'); ?></option>
						<option value="AU"><?php _e('Australia', 'schema'); ?></option>
						<option value="AT"><?php _e('Austria', 'schema'); ?></option>
						<option value="AZ"><?php _e('Azerbaijan', 'schema'); ?></option>
						<option value="BS"><?php _e('Bahamas', 'schema'); ?></option>
						<option value="BH"><?php _e('Bahrain', 'schema'); ?></option>
						<option value="BD"><?php _e('Bangladesh', 'schema'); ?></option>
						<option value="BB"><?php _e('Barbados', 'schema'); ?></option>
						<option value="BY"><?php _e('Belarus', 'schema'); ?></option>
						<option value="BE"><?php _e('Belgium', 'schema'); ?></option>
						<option value="BZ"><?php _e('Belize', 'schema'); ?></option>
						<option value="BJ"><?php _e('Benin', 'schema'); ?></option>
						<option value="BM"><?php _e('Bermuda', 'schema'); ?></option>
						<option value="BT"><?php _e('Bhutan', 'schema'); ?></option>
						<option value="BO"><?php _e('Bolivia, Plurinational State Of', 'schema'); ?></option>
						<option value="BQ"><?php _e('Bonaire, Sint Eustatius And Saba', 'schema'); ?></option>
						<option value="BA"><?php _e('Bosnia And Herzegovina', 'schema'); ?></option>
						<option value="BW"><?php _e('Botswana', 'schema'); ?></option>
						<option value="BV"><?php _e('Bouvet Island', 'schema'); ?></option>
						<option value="BR"><?php _e('Brazil', 'schema'); ?></option>
						<option value="IO"><?php _e('British Indian Ocean Territory', 'schema'); ?></option>
						<option value="BN"><?php _e('Brunei Darussalam', 'schema'); ?></option>
						<option value="BG"><?php _e('Bulgaria', 'schema'); ?></option>
						<option value="BF"><?php _e('Burkina Faso', 'schema'); ?></option>
						<option value="BI"><?php _e('Burundi', 'schema'); ?></option>
						<option value="KH"><?php _e('Cambodia', 'schema'); ?></option>
						<option value="CM"><?php _e('Cameroon', 'schema'); ?></option>
						<option value="CV"><?php _e('Cape Verde', 'schema'); ?></option>
						<option value="KY"><?php _e('Cayman Islands', 'schema'); ?></option>
						<option value="CF"><?php _e('Central African Republic', 'schema'); ?></option>
						<option value="TD"><?php _e('Chad', 'schema'); ?></option>
						<option value="CL"><?php _e('Chile', 'schema'); ?></option>
						<option value="CN"><?php _e('China', 'schema'); ?></option>
						<option value="CX"><?php _e('Christmas Island', 'schema'); ?></option>
						<option value="CC"><?php _e('Cocos (Keeling) Islands', 'schema'); ?></option>
						<option value="CO"><?php _e('Colombia', 'schema'); ?></option>
						<option value="KM"><?php _e('Comoros', 'schema'); ?></option>
						<option value="CG"><?php _e('Congo', 'schema'); ?></option>
						<option value="CD"><?php _e('Congo, The Democratic Republic Of The', 'schema'); ?></option>
						<option value="CK"><?php _e('Cook Islands', 'schema'); ?></option>
						<option value="CR"><?php _e('Costa Rica', 'schema'); ?></option>
						<option value="CI"><?php _e('Cote D\'Ivoire', 'schema'); ?></option>
						<option value="HR"><?php _e('Croatia', 'schema'); ?></option>
						<option value="CU"><?php _e('Cuba', 'schema'); ?></option>
						<option value="CW"><?php _e('Curacao', 'schema'); ?></option>
						<option value="CY"><?php _e('Cyprus', 'schema'); ?></option>
						<option value="CZ"><?php _e('Czech Republic', 'schema'); ?></option>
						<option value="DK"><?php _e('Denmark', 'schema'); ?></option>
						<option value="DJ"><?php _e('Djibouti', 'schema'); ?></option>
						<option value="DM"><?php _e('Dominica', 'schema'); ?></option>
						<option value="DO"><?php _e('Dominican Republic', 'schema'); ?></option>
						<option value="EC"><?php _e('Ecuador', 'schema'); ?></option>
						<option value="EG"><?php _e('Egypt', 'schema'); ?></option>
						<option value="SV"><?php _e('El Salvador', 'schema'); ?></option>
						<option value="GQ"><?php _e('Equatorial Guinea', 'schema'); ?></option>
						<option value="ER"><?php _e('Eritrea', 'schema'); ?></option>
						<option value="EE"><?php _e('Estonia', 'schema'); ?></option>
						<option value="ET"><?php _e('Ethiopia', 'schema'); ?></option>
						<option value="FK"><?php _e('Falkland Islands (Malvinas)', 'schema'); ?></option>
						<option value="FO"><?php _e('Faroe Islands', 'schema'); ?></option>
						<option value="FJ"><?php _e('Fiji', 'schema'); ?></option>
						<option value="FI"><?php _e('Finland', 'schema'); ?></option>
						<option value="FR"><?php _e('France', 'schema'); ?></option>
						<option value="GF"><?php _e('French Guiana', 'schema'); ?></option>
						<option value="PF"><?php _e('French Polynesia', 'schema'); ?></option>
						<option value="TF"><?php _e('French Southern Territories', 'schema'); ?></option>
						<option value="GA"><?php _e('Gabon', 'schema'); ?></option>
						<option value="GM"><?php _e('Gambia', 'schema'); ?></option>
						<option value="GE"><?php _e('Georgia', 'schema'); ?></option>
						<option value="DE"><?php _e('Germany', 'schema'); ?></option>
						<option value="GH"><?php _e('Ghana', 'schema'); ?></option>
						<option value="GI"><?php _e('Gibraltar', 'schema'); ?></option>
						<option value="GR"><?php _e('Greece', 'schema'); ?></option>
						<option value="GL"><?php _e('Greenland', 'schema'); ?></option>
						<option value="GD"><?php _e('Grenada', 'schema'); ?></option>
						<option value="GP"><?php _e('Guadeloupe', 'schema'); ?></option>
						<option value="GU"><?php _e('Guam', 'schema'); ?></option>
						<option value="GT"><?php _e('Guatemala', 'schema'); ?></option>
						<option value="GG"><?php _e('Guernsey', 'schema'); ?></option>
						<option value="GN"><?php _e('Guinea', 'schema'); ?></option>
						<option value="GW"><?php _e('Guinea-Bissau', 'schema'); ?></option>
						<option value="GY"><?php _e('Guyana', 'schema'); ?></option>
						<option value="HT"><?php _e('Haiti', 'schema'); ?></option>
						<option value="HM"><?php _e('Heard Island And Mcdonald Islands', 'schema'); ?></option>
						<option value="VA"><?php _e('Vatican City', 'schema'); ?></option>
						<option value="HN"><?php _e('Honduras', 'schema'); ?></option>
						<option value="HK"><?php _e('Hong Kong', 'schema'); ?></option>
						<option value="HU"><?php _e('Hungary', 'schema'); ?></option>
						<option value="IS"><?php _e('Iceland', 'schema'); ?></option>
						<option value="IN"><?php _e('India', 'schema'); ?></option>
						<option value="ID"><?php _e('Indonesia', 'schema'); ?></option>
						<option value="IR"><?php _e('Iran', 'schema'); ?></option>
						<option value="IQ"><?php _e('Iraq', 'schema'); ?></option>
						<option value="IE"><?php _e('Ireland', 'schema'); ?></option>
						<option value="IM"><?php _e('Isle Of Man', 'schema'); ?></option>
						<option value="IL"><?php _e('Israel', 'schema'); ?></option>
						<option value="IT"><?php _e('Italy', 'schema'); ?></option>
						<option value="JM"><?php _e('Jamaica', 'schema'); ?></option>
						<option value="JP"><?php _e('Japan', 'schema'); ?></option>
						<option value="JE"><?php _e('Jersey', 'schema'); ?></option>
						<option value="JO"><?php _e('Jordan', 'schema'); ?></option>
						<option value="KZ"><?php _e('Kazakhstan', 'schema'); ?></option>
						<option value="KE"><?php _e('Kenya', 'schema'); ?></option>
						<option value="KI"><?php _e('Kiribati', 'schema'); ?></option>
						<option value="KP"><?php _e('North Korea', 'schema'); ?></option>
						<option value="KR"><?php _e('South Korea', 'schema'); ?></option>
						<option value="KW"><?php _e('Kuwait', 'schema'); ?></option>
						<option value="KG"><?php _e('Kyrgyzstan', 'schema'); ?></option>
						<option value="LA"><?php _e('Laos', 'schema'); ?></option>
						<option value="LV"><?php _e('Latvia', 'schema'); ?></option>
						<option value="LB"><?php _e('Lebanon', 'schema'); ?></option>
						<option value="LS"><?php _e('Lesotho', 'schema'); ?></option>
						<option value="LR"><?php _e('Liberia', 'schema'); ?></option>
						<option value="LY"><?php _e('Libya', 'schema'); ?></option>
						<option value="LI"><?php _e('Liechtenstein', 'schema'); ?></option>
						<option value="LT"><?php _e('Lithuania', 'schema'); ?></option>
						<option value="LU"><?php _e('Luxembourg', 'schema'); ?></option>
						<option value="MO"><?php _e('Macao', 'schema'); ?></option>
						<option value="MK"><?php _e('Macedonia', 'schema'); ?></option>
						<option value="MG"><?php _e('Madagascar', 'schema'); ?></option>
						<option value="MW"><?php _e('Malawi', 'schema'); ?></option>
						<option value="MY"><?php _e('Malaysia', 'schema'); ?></option>
						<option value="MV"><?php _e('Maldives', 'schema'); ?></option>
						<option value="ML"><?php _e('Mali', 'schema'); ?></option>
						<option value="MT"><?php _e('Malta', 'schema'); ?></option>
						<option value="MH"><?php _e('Marshall Islands', 'schema'); ?></option>
						<option value="MQ"><?php _e('Martinique', 'schema'); ?></option>
						<option value="MR"><?php _e('Mauritania', 'schema'); ?></option>
						<option value="MU"><?php _e('Mauritius', 'schema'); ?></option>
						<option value="YT"><?php _e('Mayotte', 'schema'); ?></option>
						<option value="FM"><?php _e('Micronesia', 'schema'); ?></option>
						<option value="MD"><?php _e('Moldova', 'schema'); ?></option>
						<option value="MC"><?php _e('Monaco', 'schema'); ?></option>
						<option value="MN"><?php _e('Mongolia', 'schema'); ?></option>
						<option value="ME"><?php _e('Montenegro', 'schema'); ?></option>
						<option value="MS"><?php _e('Montserrat', 'schema'); ?></option>
						<option value="MA"><?php _e('Morocco', 'schema'); ?></option>
						<option value="MZ"><?php _e('Mozambique', 'schema'); ?></option>
						<option value="MM"><?php _e('Myanmar', 'schema'); ?></option>
						<option value="NA"><?php _e('Namibia', 'schema'); ?></option>
						<option value="NR"><?php _e('Nauru', 'schema'); ?></option>
						<option value="NP"><?php _e('Nepal', 'schema'); ?></option>
						<option value="NL"><?php _e('Netherlands', 'schema'); ?></option>
						<option value="NC"><?php _e('New Caledonia', 'schema'); ?></option>
						<option value="NZ"><?php _e('New Zealand', 'schema'); ?></option>
						<option value="NI"><?php _e('Nicaragua', 'schema'); ?></option>
						<option value="NE"><?php _e('Niger', 'schema'); ?></option>
						<option value="NG"><?php _e('Nigeria', 'schema'); ?></option>
						<option value="NU"><?php _e('Niue', 'schema'); ?></option>
						<option value="NF"><?php _e('Norfolk Island', 'schema'); ?></option>
						<option value="MP"><?php _e('Northern Mariana Islands', 'schema'); ?></option>
						<option value="NO"><?php _e('Norway', 'schema'); ?></option>
						<option value="OM"><?php _e('Oman', 'schema'); ?></option>
						<option value="PK"><?php _e('Pakistan', 'schema'); ?></option>
						<option value="PW"><?php _e('Palau', 'schema'); ?></option>
						<option value="PS"><?php _e('Palestine', 'schema'); ?></option>
						<option value="PA"><?php _e('Panama', 'schema'); ?></option>
						<option value="PG"><?php _e('Papua New Guinea', 'schema'); ?></option>
						<option value="PY"><?php _e('Paraguay', 'schema'); ?></option>
						<option value="PE"><?php _e('Peru', 'schema'); ?></option>
						<option value="PH"><?php _e('Philippines', 'schema'); ?></option>
						<option value="PN"><?php _e('Pitcairn', 'schema'); ?></option>
						<option value="PL"><?php _e('Poland', 'schema'); ?></option>
						<option value="PT"><?php _e('Portugal', 'schema'); ?></option>
						<option value="PR"><?php _e('Puerto Rico', 'schema'); ?></option>
						<option value="QA"><?php _e('Qatar', 'schema'); ?></option>
						<option value="RE"><?php _e('Reunion', 'schema'); ?></option>
						<option value="RO"><?php _e('Romania', 'schema'); ?></option>
						<option value="RU"><?php _e('Russian Federation', 'schema'); ?></option>
						<option value="RW"><?php _e('Rwanda', 'schema'); ?></option>
						<option value="BL"><?php _e('St. Barthelemy', 'schema'); ?></option>
						<option value="SH"><?php _e('St. Helena', 'schema'); ?></option>
						<option value="KN"><?php _e('St. Kitts And Nevis', 'schema'); ?></option>
						<option value="LC"><?php _e('St. Lucia', 'schema'); ?></option>
						<option value="MF"><?php _e('St. Martin (French Part)', 'schema'); ?></option>
						<option value="PM"><?php _e('St. Pierre And Miquelon', 'schema'); ?></option>
						<option value="VC"><?php _e('St. Vincent And The Grenadines', 'schema'); ?></option>
						<option value="WS"><?php _e('Samoa', 'schema'); ?></option>
						<option value="SM"><?php _e('San Marino', 'schema'); ?></option>
						<option value="ST"><?php _e('Sao Tome And Principe', 'schema'); ?></option>
						<option value="SA"><?php _e('Saudi Arabia', 'schema'); ?></option>
						<option value="SN"><?php _e('Senegal', 'schema'); ?></option>
						<option value="RS"><?php _e('Serbia', 'schema'); ?></option>
						<option value="SC"><?php _e('Seychelles', 'schema'); ?></option>
						<option value="SL"><?php _e('Sierra Leone', 'schema'); ?></option>
						<option value="SG"><?php _e('Singapore', 'schema'); ?></option>
						<option value="SX"><?php _e('Sint Maarten (Dutch Part)', 'schema'); ?></option>
						<option value="SK"><?php _e('Slovakia', 'schema'); ?></option>
						<option value="SI"><?php _e('Slovenia', 'schema'); ?></option>
						<option value="SB"><?php _e('Solomon Islands', 'schema'); ?></option>
						<option value="SO"><?php _e('Somalia', 'schema'); ?></option>
						<option value="ZA"><?php _e('South Africa', 'schema'); ?></option>
						<option value="GS"><?php _e('South Georgia', 'schema'); ?></option>
						<option value="SS"><?php _e('South Sudan', 'schema'); ?></option>
						<option value="ES"><?php _e('Spain', 'schema'); ?></option>
						<option value="LK"><?php _e('Sri Lanka', 'schema'); ?></option>
						<option value="SD"><?php _e('Sudan', 'schema'); ?></option>
						<option value="SR"><?php _e('Suriname', 'schema'); ?></option>
						<option value="SJ"><?php _e('Svalbard', 'schema'); ?></option>
						<option value="SZ"><?php _e('Swaziland', 'schema'); ?></option>
						<option value="SE"><?php _e('Sweden', 'schema'); ?></option>
						<option value="CH"><?php _e('Switzerland', 'schema'); ?></option>
						<option value="SY"><?php _e('Syria', 'schema'); ?></option>
						<option value="TW"><?php _e('Taiwan', 'schema'); ?></option>
						<option value="TJ"><?php _e('Tajikistan', 'schema'); ?></option>
						<option value="TZ"><?php _e('Tanzania', 'schema'); ?></option>
						<option value="TH"><?php _e('Thailand', 'schema'); ?></option>
						<option value="TL"><?php _e('Timor-Leste', 'schema'); ?></option>
						<option value="TG"><?php _e('Togo', 'schema'); ?></option>
						<option value="TK"><?php _e('Tokelau', 'schema'); ?></option>
						<option value="TO"><?php _e('Tonga', 'schema'); ?></option>
						<option value="TT"><?php _e('Trinidad And Tobago', 'schema'); ?></option>
						<option value="TN"><?php _e('Tunisia', 'schema'); ?></option>
						<option value="TR"><?php _e('Turkey', 'schema'); ?></option>
						<option value="TM"><?php _e('Turkmenistan', 'schema'); ?></option>
						<option value="TC"><?php _e('Turks And Caicos Islands', 'schema'); ?></option>
						<option value="TV"><?php _e('Tuvalu', 'schema'); ?></option>
						<option value="UG"><?php _e('Uganda', 'schema'); ?></option>
						<option value="UA"><?php _e('Ukraine', 'schema'); ?></option>
						<option value="AE"><?php _e('United Arab Emirates', 'schema'); ?></option>
						<option value="UM"><?php _e('United States Minor Outlying Islands', 'schema'); ?></option>
						<option value="UY"><?php _e('Uruguay', 'schema'); ?></option>
						<option value="UZ"><?php _e('Uzbekistan', 'schema'); ?></option>
						<option value="VU"><?php _e('Vanuatu', 'schema'); ?></option>
						<option value="VE"><?php _e('Venezuela', 'schema'); ?></option>
						<option value="VN"><?php _e('Vietnam', 'schema'); ?></option>
						<option value="VG"><?php _e('British Virgin Islands ', 'schema'); ?></option>
						<option value="VI"><?php _e('U.S. Virgin Islands ', 'schema'); ?></option>
						<option value="WF"><?php _e('Wallis And Futuna', 'schema'); ?></option>
						<option value="EH"><?php _e('Western Sahara', 'schema'); ?></option>
						<option value="YE"><?php _e('Yemen', 'schema'); ?></option>
						<option value="ZM"><?php _e('Zambia', 'schema'); ?></option>
						<option value="ZW"><?php _e('Zimbabwe', 'schema'); ?></option>
					</select>
				</div>

				<div id="sc_email" class="sc_option" style="display:none">
					<label for="schema_email"><?php _e('Email Address', 'schema'); ?></label>
					<input type="text" name="schema_email" class="form_full" value="" id="schema_email" />
				</div>

				<div id="sc_phone" class="sc_option" style="display:none">
					<label for="schema_phone"><?php _e('Telephone', 'schema'); ?></label>
					<input type="text" name="schema_phone" class="form_half" value="" id="schema_phone" />
				</div>

				<div id="sc_fax" class="sc_option" style="display:none">
					<label for="schema_fax"><?php _e('Fax', 'schema'); ?></label>
					<input type="text" name="schema_fax" class="form_half" value="" id="schema_fax" />
				</div>

   				<div id="sc_brand" class="sc_option" style="display:none">
					<label for="schema_brand"><?php _e('Brand', 'schema'); ?></label>
					<input type="text" name="schema_brand" class="form_full" value="" id="schema_brand" />
				</div>

   				<div id="sc_manfu" class="sc_option" style="display:none">
					<label for="schema_manfu"><?php _e('Manufacturer', 'schema'); ?></label>
					<input type="text" name="schema_manfu" class="form_full" value="" id="schema_manfu" />
				</div>

   				<div id="sc_model" class="sc_option" style="display:none">
					<label for="schema_model"><?php _e('Model', 'schema'); ?></label>
					<input type="text" name="schema_model" class="form_full" value="" id="schema_model" />
				</div>

   				<div id="sc_prod_id" class="sc_option" style="display:none">
					<label for="schema_prod_id"><?php _e('Product ID', 'schema'); ?></label>
					<input type="text" name="schema_prod_id" class="form_full" value="" id="schema_prod_id" />
				</div>

   				<div id="sc_ratings" class="sc_option" style="display:none">
					<label for="sc_ratings"><?php _e('Aggregate Rating', 'schema'); ?></label>
                    <div class="labels_inline">
					<label for="schema_single_rating"><?php _e('Avg Rating', 'schema'); ?></label>
                    <input type="text" name="schema_single_rating" class="form_eighth schema_numeric" value="" id="schema_single_rating" />
                    <label for="schema_agg_rating"><?php _e('based on', 'schema'); ?> </label>
					<input type="text" name="schema_agg_rating" class="form_eighth schema_numeric" value="" id="schema_agg_rating" />
                    <label><?php _e('reviews', 'schema'); ?></label>
                    </div>
				</div>

   				<div id="sc_reviews" class="sc_option" style="display:none">
					<label for="sc_reviews"><?php _e('Rating', 'schema'); ?></label>
                    <div class="labels_inline">
					<label for="schema_user_review"><?php _e('Rating', 'schema'); ?></label>
                    <input type="text" name="schema_user_review" class="form_eighth schema_numeric" value="" id="schema_user_review" />
                    <label for="schema_min_review"><?php _e('Minimum', 'schema'); ?></label>
					<input type="text" name="schema_min_review" class="form_eighth schema_numeric" value="" id="schema_min_review" />
                    <label for="schema_max_review"><?php _e('Minimum', 'schema'); ?></label>
					<input type="text" name="schema_max_review" class="form_eighth schema_numeric" value="" id="schema_max_review" />
                    </div>
				</div>


   				<div id="sc_price" class="sc_option" style="display:none">
					<label for="schema_price"><?php _e('Price', 'schema'); ?></label>
					<input type="text" name="schema_price" class="form_third sc_currency" value="" id="schema_price" />
				</div>

				<div id="sc_condition" class="sc_option" style="display:none">
					<label for="schema_condition"><?php _e('Condition', 'schema'); ?></label>
					<select name="schema_condition" id="schema_condition" class="schema_drop">
						<option class="holder" value="none">(<?php _e('Select', 'schema'); ?>)</option>
						<option value="New"><?php _e('New', 'schema'); ?></option>
						<option value="Used"><?php _e('Used', 'schema'); ?></option>
						<option value="Refurbished"><?php _e('Refurbished', 'schema'); ?></option>
						<option value="Damaged"><?php _e('Damaged', 'schema'); ?></option>
					</select>
				</div>

   				<div id="sc_author" class="sc_option" style="display:none">
					<label for="schema_author"><?php _e('Author', 'schema'); ?></label>
					<input type="text" name="schema_author" class="form_full" value="" id="schema_author" />
				</div>

   				<div id="sc_publisher" class="sc_option" style="display:none">
					<label for="schema_publisher"><?php _e('Publisher', 'schema'); ?></label>
					<input type="text" name="schema_publisher" class="form_full" value="" id="schema_publisher" />
				</div>

				<div id="sc_pubdate" class="sc_option" style="display:none">
					<label for="schema_pubdate"><?php _e('Published Date', 'schema'); ?></label>
					<input type="text" id="schema_pubdate" name="schema_pubdate" class="schema_datepicker form_third" value="" />
					<input type="hidden" id="schema_pubdate-format" class="schema_datepicker-format" value="" />
				</div>

   				<div id="sc_edition" class="sc_option" style="display:none">
					<label for="schema_edition"><?php _e('Edition', 'schema'); ?></label>
					<input type="text" name="schema_edition" class="form_full" value="" id="schema_edition" />
				</div>

   				<div id="sc_isbn" class="sc_option" style="display:none">
					<label for="schema_isbn"><?php _e('ISBN', 'schema'); ?></label>
					<input type="text" name="schema_isbn" class="form_full" value="" id="schema_isbn" />
				</div>

   				<div id="sc_formats" class="sc_option" style="display:none">
				<label class="list_label"><?php _e('Formats', 'schema'); ?></label>
                	<div class="form_list">
                    <span>
						<input type="checkbox" class="schema_check" id="schema_ebook" name="schema_ebook" value="ebook" />
						<label for="schema_ebook" rel="checker"><?php _e('Ebook', 'schema'); ?></label>
					</span>
                    <span>
						<input type="checkbox" class="schema_check" id="schema_paperback" name="schema_paperback" value="paperback" />
						<label for="schema_paperback" rel="checker"><?php _e('Paperback', 'schema'); ?></label>
					</span>
                    <span>
						<input type="checkbox" class="schema_check" id="schema_hardcover" name="schema_hardcover" value="hardcover" />
						<label for="schema_hardcover" rel="checker"><?php _e('Hardcover', 'schema'); ?></label>
                   </span>
                </div>
				</div>

				<div id="sc_revdate" class="sc_option" style="display:none">
					<label for="schema_revdate"><?php _e('Review Date', 'schema'); ?></label>
					<input type="text" id="schema_revdate" name="schema_revdate" class="schema_datepicker form_third" value="" />
					<input type="hidden" id="schema_revdate-format" class="schema_datepicker-format" value="" />
				</div>

   				<div id="sc_preptime" class="sc_option" style="display:none">
					<label for="sc_preptime"><?php _e('Prep Time', 'schema'); ?></label>
                    <div class="labels_inline">
					<label for="schema_prep_hours"><?php _e('Hours', 'schema'); ?></label>
                    <input type="text" name="schema_prep_hours" class="form_eighth schema_numeric" value="" id="schema_prep_hours" />
                    <label for="schema_prep_mins"><?php _e('Minutes', 'schema'); ?></label>
					<input type="text" name="schema_prep_mins" class="form_eighth schema_numeric" value="" id="schema_prep_mins" />
                    </div>
				</div>

   				<div id="sc_cooktime" class="sc_option" style="display:none">
					<label for="sc_cooktime"><?php _e('Cook Time', 'schema'); ?></label>
                    <div class="labels_inline">
					<label for="schema_cook_hours"><?php _e('Hours', 'schema'); ?></label>
                    <input type="text" name="schema_cook_hours" class="form_eighth schema_numeric" value="" id="schema_cook_hours" />
                    <label for="schema_cook_mins"><?php _e('Minutes', 'schema'); ?></label>
					<input type="text" name="schema_cook_mins" class="form_eighth schema_numeric" value="" id="schema_cook_mins" />
                    </div>
				</div>

   				<div id="sc_yield" class="sc_option" style="display:none">
					<label for="schema_yield"><?php _e('Yield', 'schema'); ?></label>
					<input type="text" name="schema_yield" class="form_third" value="" id="schema_yield" />
					<label class="additional">(<?php _e('serving size', 'schema'); ?>)</label>
				</div>

				<div id="sc_calories" class="sc_option" style="display:none">
					<label for="schema_calories"><?php _e('Calories', 'schema'); ?></label>
					<input type="text" name="schema_calories" class="form_third schema_numeric" value="" id="schema_calories" />
				</div>

				<div id="sc_fatcount" class="sc_option" style="display:none">
					<label for="schema_fatcount"><?php _e('Fat', 'schema'); ?></label>
					<input type="text" name="schema_fatcount" class="form_third schema_numeric" value="" id="schema_fatcount" />
					<label class="additional">(<?php _e('in grams', 'schema'); ?>)</label>
				</div>

				<div id="sc_sugarcount" class="sc_option" style="display:none">
					<label for="schema_sugarcount"><?php _e('Sugar', 'schema'); ?></label>
					<input type="text" name="schema_sugarcount" class="form_third schema_numeric" value="" id="schema_sugarcount" />
					<label class="additional">(<?php _e('in grams', 'schema'); ?>)</label>
				</div>

				<div id="sc_saltcount" class="sc_option" style="display:none">
					<label for="schema_saltcount"><?php _e('Sodium', 'schema'); ?></label>
					<input type="text" name="schema_saltcount" class="form_third schema_numeric" value="" id="schema_saltcount" />
					<label class="additional">(<?php _e('in milligrams', 'schema'); ?>)</label>
				</div>

				<div id="sc_ingrt_1" class="sc_option sc_ingrt sc_repeater ig_repeat" style="display:none">
                        <label for="schema_ingrt_1"><?php _e('Ingredient', 'schema'); ?></label>
                        <input type="text" name="schema_ingrt_1" class="form_half ingrt_input" value="" id="schema_ingrt_1" />
                        <label class="additional">(<?php _e('include both type and amount', 'schema'); ?>)</label>
				</div>

				<input type="button" class="clone_button" id="clone_ingrt" value="<?php _e('Add Another Ingredient', 'schema'); ?>" style="display:none;" />

				<div id="sc_instructions" class="sc_option" style="display:none">
					<label for="schema_instructions"><?php _e('Instructions', 'schema'); ?></label>
					<textarea name="schema_instructions" id="schema_instructions"></textarea>
				</div>

			<!-- button for inserting -->
				<div class="insert_button" style="display:none">
					<input class="schema_insert schema_button" type="button" value="<?php _e('Insert'); ?>" onclick="InsertSchema();"/>
					<input class="schema_cancel schema_clear schema_button" type="button" value="<?php _e('Cancel'); ?>" onclick="tb_remove(); return false;"/>
				</div>

			<!-- various messages -->
				<div id="sc_messages">
                <p class="start"><?php _e('Select a schema type above to get started', 'schema'); ?></p>
                <p class="pending" style="display:none;"><?php _e('This schema type is currently being constructed.', 'schema'); ?></p>
                </div>

			</div>
			</div>

	<?php }


/// end class
}


// Instantiate our class
$ravenSchema = new ravenSchema();
