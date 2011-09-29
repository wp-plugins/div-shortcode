<?php
/*
Plugin Name: Div Shortcode
Plugin URI: http://www.billerickson.net
Description: Allows you to create a div by using the shortcodes [div] and [end-div]. To add an id of "foo" and class of "bar", use [div id="foo" class="bar"]. If plugin is disabled, only the HTML remains (no shortcodes polluting your content).
Author: Bill Erickson, Dean Hall
Version: 2.0
Author URI: http://www.billerickson.net
*/

class be_div_shortcode {

    /**
     * the regex to use for replacing shortcode content with the filtered end-
     * product
     *
     * It's based on WP 3.2.1's shortcode regex from shortcodes.php, but it's
     * been modified to support nested shortcodes (with help from
     * http://bit.ly/pKBMxq).
     *
     * See be_div_shortcode::filter_match for details on the regex captures.
     *
     * Regular expression match options:
     * - s: This makes a dot also match a newline.
     * - S: Apparently this helps PHP to optimize the expression for multiple
     *      uses.
     *
     * @since 2.0
     *
     * @name   $re_replace
     * @var    string
     * @access private
     */
    private static $re_replace =
        '#(.?)\[div\b(.*?)(/?)\](?:((?:(?R)|.)*?)\[end-div\])?(.?)#sS';
        //'#(.?)\[div\b(.*)(/?)\]((?:(?R)|.)*)\[end-div\](.?)#sUS';

    /**
     * the regex to use for testing for the presence of the shortcode
     *
     * It's faster than be_div_shortcode::$re_replace, and nothing is captured.
     *
     * @since 2.0
     *
     * @name $re_test
     * @var  string
     */
    private static $re_test =
        '#\[div\b(?:.*?)(?:/?)\](?:(?:.*?)\[end-div\])?#sS';

    /**
     * the original post, unfiltered (by us)
     *
     * @since 2.0
     *
     * @name $unfiltered_post
     * @var  string
     */
    private static $unfiltered_post;

    /**
     * the name of the custom field used to store the unfiltered post
     *
     * @since  2.0
     *
     * @name   CF_NAME
     * @var    string
     * @access private
     */
    const CF_NAME = '_be_div_shortcode';

    /**
     * Initialize the plugin; add its filters.
     *
     * @since 2.0
     *
     * @uses add_filter
     * @uses be_div_shortcode::edit_post_content
     * @uses be_div_shortcode::wp_insert_post_data
     * @uses be_div_shortcode::wp_insert_post
     * @uses be_div_shortcode::is_protected_meta
     * @uses be_div_shortcode::div_shortcode
     * @uses be_div_shortcode::end_div_shortcode
     */
    public static function init() {

        /* Register admin filters. */
        if (is_admin()) {

            /* Register our wp_insert_post_data filter. */
            add_filter( 'wp_insert_post_data', 'be_div_shortcode::wp_insert_post_data',  1,  2 );

            /* Register our wp_insert_post filter. */
            add_filter( 'wp_insert_post', 'be_div_shortcode::wp_insert_post',  1,  2 );

            /* Register our edit_post_content filter. */
            add_filter( 'edit_post_content', 'be_div_shortcode::edit_post_content',  1, 2 );

            /* Register our is_protected_meta filter. */
            add_filter( 'is_protected_meta', 'be_div_shortcode::is_protected_meta',  1,  2 );
        }

        /* Register real shortcodes for backward compatibility. */
        add_shortcode( 'div', 'be_div_shortcode::div_shortcode' );
        add_shortcode( 'end-div', 'be_div_shortcode::end_div_shortcode' );
    }

    /**
     * Swap the filtered and unfiltered post content before storing it in the
     * DB.
     *
     * This runs right BEFORE the post is added to or updated in the database.
     * If there are shortcodes present, this filters the post and stores the
     * filtered post as the actual post. It stores the unfiltered post in a
     * variable so it can be stored in a custom field later.
     *
     * @since  2.0
     * @access private
     *
     * @uses wp_is_post_revision
     * @uses be_div_shortcode::content_has_shortcode
     * @uses be_div_shortcode::filter_shortcodes
     *
     * @param  array $post     the post
     * @param  array $raw_post the original, raw post data
     * @return array           the filtered $post array
     */
    public static function wp_insert_post_data( $post, $raw_post ) {

        /* Don't handle post revisions. */
        if ( wp_is_post_revision( $post ) )
            return $post;

        /* For some reason, this gets called twice. Don't do everything twice,
           or it'll ruin everything. */
        if ( !empty( self::$unfiltered_post ) )
            return $post;

        /* There's a shortcode to be filtered. */
        if ( self::content_has_shortcode( $post['post_content'] ) ) {

            /* Store away the unfiltered post. */
            self::$unfiltered_post = $post['post_content'];

            /* Filter the post. */
            $post['post_content'] =
                self::filter_shortcodes($post['post_content']);
        }

        /* There are no shortcodes to filter. Make sure not to use/to delete
           the custom field later. */
        else
            self::$unfiltered_post = '';

        return $post;
}
    /**
     * Store the unfiltered post content in a custom field after storing the
     * filtered post in the DB.
     *
     * This runs right AFTER the post is added to or updated in the DB and
     * stores the unfiltered post content in a custom field. We store it here
     * and not in self::wp_insert_post_data because new posts have no ID until
     * they're inserted into the DB.
     *
     * @since 2.0
     *
     * @uses wp_is_post_revision
     * @uses delete_post_meta
     * @uses update_post_meta
     *
     * @param  int   $post_id the post's ID
     * @param  array $post    the post
     * @return bool           the return value of either delete_post_meta or
     *                        update_post_meta (but it's not needed)
     */
    public static function wp_insert_post($post_id, $post) {

        /* Don't handle post revisions. */
        if ( wp_is_post_revision( $post ) )
            return;

        /* No shortcodes; make sure there's no custom field. */
        if ( empty( self::$unfiltered_post ) )
            return delete_post_meta( $post_id, self::CF_NAME );

        /* Store the unfiltered post in a custom field. */
        return
            update_post_meta( $post_id, self::CF_NAME, self::$unfiltered_post );
    }

    /**
     * Populate the edit box with the unfiltered post content.
     *
     * This runs right before the edit form is populated with the post and
     * populates the edit box with the unfiltered post (if it needs to).
     *
     * @since 2.0
     *
     * @uses wp_is_post_revision
     * @uses get_post_meta
     *
     * @param  string $content the post's content
     * @param  int    $id      the post's ID
     * @return string          the unfiltered content (with [div] and [end-div]
     *                         shortcodes)
     */
    public static function edit_post_content( $content, $id ) {
        $post = get_post( $id );

        /* Don't handle post revisions. */
        if ( wp_is_post_revision( $post ) )
            return $content;

        /* Try to get the unfiltered content. */
        $unfiltered = get_post_meta( $id, self::CF_NAME, true );

        /* The unfiltered content was retrieved. */
        if ( !empty( $unfiltered ) )
            $content = $unfiltered;

        return $content;
    }

    /**
     * Filter our custom field from the "Custom Field" admin meta box.
     *
     * @since 2.0
     *
     * @param  ?      $a   ?
     * @param  string $key the postmeta key
     * @return bool        whether the custom field is protected (and should
     *                     not be displayed)
     */
    public static function is_protected_meta( $a, $key ) {
        return ( $key == self::CF_NAME );
    }

    /**
     * Determine whether post content has shortcodes.
     *
     * @since  2.0
     *
     * @param  string $content some unfiltered post content
     * @return bool            whether $content contains [div][end-div]
     *                         shortcodes
     */
    private static function content_has_shortcode( $content ) {
        return ( preg_match( self::$re_test, $content ) == 1);
    }

    /**
     * Filter unfiltered post content. Match $content against self::$re_replace
     * and call self::filter_match for each match.
     *
     * @since  2.0
     *
     * @uses be_div_shortcode::filter_match
     *
     * @param  string $content some unfiltered post content
     * @return string          filtered content, free of [div][end-div]
     *                         shortcodes
     */
    private static function filter_shortcodes( $content ) {
        return
            preg_replace_callback(
                self::$re_replace,
                'be_div_shortcode::filter_match',
                $content
            );
    }

    /**
     * Filter a single match capture in $pmatch.
     *
     * Example of the contents of $match:
     *
     *     Content:
     *         a[div attr="attrval" attr2]content[end-div]z
     *
     *     Match positional values:
     *         [0]: the entire match
     *              'a[div attr="attrval" attr2]content[end-div]z'
     *
     *         [1]: the last character before the [div]
     *              'a'
     *
     *         [2]: everything in the opening tag except its name
     *              ' attr="attrval" attr2'
     *
     *         [3]: the '/' in a self-closing shortcode
     *              ''
     *
     *         [4]: everything between the opening and closing tags
     *              'content'
     *
     *         [5]: the first character after the [end-div] or [div/]
     *              'z'
     *
     * [1] and [5] are captured in case the entire pattern is surrounded by
     * more square brackets. This escapes the pattern, and no filtering or
     * replacement is done:
     *
     *   This:
     * [[div /]] [[div]content[end-div]] [[div][div]...[end-div][end-div]]
     *   becomes this:
     * [div /] [div]content[end-div] [div][div]...[end-div][end-div]
     *
     * This was adapted from code in WordPress 3.2.1's shortcodes.php.
     *
     * @since 2.0
     *
     * @uses shortcode_parse_atts
     * @uses be_div_shortcode::reduce_attrs
     *
     * @param  array  $pmatch a preg_* match array of self::$re_replace
     * @return string the shortcode content in $match magically transformed
     */
    public static function filter_match( $pmatch ) {

        /* Allow escaping like this: [[div]] or this:
           [[div]content[end-div]] */
        if ($pmatch[1] == '[' && $pmatch[5] == ']') {
            return substr( $pmatch[0], 1, -1 );
        }

        /* Parse and reduce attributes to a string. */
        $attrs = shortcode_parse_atts( $pmatch[2] );
        if ( is_array( $attrs ) )
            $attrs =
                array_reduce( $attrs, 'be_div_shortcode::reduce_attrs', '' );

        /* Handle a self-closing tag. */
        if ( !empty( $pmatch[3] ) ) {
            return
                $pmatch[1]
                . '<div'
                . $attrs
                . ' />'
                . $pmatch[5];
        }

        /* There may or may not be any content. */
        if ( isset( $pmatch[4] ) ) {
            if ( self::content_has_shortcode( $pmatch[4] ) ) {
                $pmatch[4] = self::filter_shortcodes( $pmatch[4] );
            }
        }
        else
            $pmatch[4] = '';

        /* Glue together the final string. */
        return
            $pmatch[1]
            . '<div'
            . $attrs
            . '>'
            . $pmatch[4]
            . '</div>'
            . $pmatch[5];
    }

    /**
     * Reduce all shortcode attributes to one by simply concatenating them with
     * spaces.
     *
     * @since 2.0
     *
     * @param  string $attr1 the existing reduced value of all attributes so
     *                       far
     * @param  string $attr2 the next attribute (if any)
     * @return string        the two attributes concatenated with a space
     */
    public static function reduce_attrs( $attr1, $attr2 ) {
        if ( isset( $attr2 ) && !empty( $attr2 ) )
            return $attr1 . ' ' . $attr2;
        else
            return $attr1;
    }

    /**
     * Filter the [div] shortcode.
     *
     * This is only for backwards-compatibility. When you create or save a post
     * it won't use this any more.
     *
     * @since  1.0
     * @param  array  $attrs the shortcode attributes
     * @return string the resulting <div>
     */
    public function div_shortcode( $atts ) {
		extract( shortcode_atts( array( 'class' => '', 'id' => '' ), $atts ) );
		$return = '<div';
		if ( !empty( $class ) ) $return .= ' class="' . $class . '"';
		if ( !empty( $id ) ) $return .= ' id="' . $id . '"';
		$return .= '>';
		return $return;    
	}

    /**
     * Filter the [end-div] shortcode.
     *
     * This is only for backwards-compatibility. When you create or save a post
     * it won't use this any more.
     *
     * @since  1.0
     * @param  array  $attrs the shortcode attributes (ignored)
     * @return string the resulting </div>
     */
    public function end_div_shortcode($attrs) {
        return '</div>';
    }
}

/* Make it so! */
be_div_shortcode::init();