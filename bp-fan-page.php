<?php
/*
Plugin Name: BP Fan Page
Plugin URI: https://wordpress.org/plugins/bp-fan-page/
Author: Venutius
Author URI: https://www.buddyuser.com
Description: This plugin give grou administratrs the option to turn a group into a Fan Page, meaning for members it is an announce only group.
Version: 1.2.0
* @package    bp-fan-page
* @copyright  Copyright (c) 2021, George Chaplin, however the license allows you to copy and modify at will. If you are able to make money with solutions that include this plugin a few beers would be appreciated ;)
* @link       https://buddyuser.com
* @license    https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*
*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.
 
This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/


// Localization
function bpfp_localization() {
	if ( file_exists( dirname( __FILE__ ).'/langs/bp-fan-page-' . get_locale() . 'mo' ) ) {
		load_plugin_textdomain('bp-fan-page', dirname( __FILE__ ).'/langs/bp-fan-page-' . get_locale() . 'mo' );
	}
}
 
add_action('init', 'bpfp_localization');


// filter member value. Return false if fan page group and regular member, otherwise return true
// this mainly controls post fields for group activity and group forum
function bpfp_filter_group_member( $is_member ) {
	global $bp;
	
	if ( bpfp_get_fan_page_group( groups_get_current_group() ) == 'fan-page' ) {
		if ( $bp->is_item_admin || $bp->is_item_mod ) {
			//echo "<p id='fan-page' style='color:#888;font-size:11px;'>". esc_attr__('Note: This is a fan page group where you have access to add content. Regular members can view but not add content.', 'bp-fan-page' ) . "</i></p>";
			return true;
		} else {
			//echo "<p id='fan-page' style='color:#888;font-size:11px;'>" . esc_attr__('This is a fan page group, regular members cannot add content.', 'bp-fan-page' ) . "</i></p>";
			return false;
		}
	} else {
		return $is_member;
	}
}
add_filter( 'bp_group_is_member', 'bpfp_filter_group_member' );


// filter out group status and don't return 'public' for fan page groups
// this mainly controls post fields for group activity and group forum
function bpfp_filter_group_status( $status ) {
	global $bp;

	//echo '<p>filter_group_status - item_admin:'.$bp->is_item_admin . ', item_mod:'.$bp->is_item_mod.'</pre>';
	
	if ( bpfp_get_fan_page_group( groups_get_current_group() ) == 'fan-page' )
		if ( $bp->is_item_admin || $bp->is_item_mod )
			return $status;
		else
			return 'fan-page';
	else
		return $status;
}
add_filter( 'bp_get_group_status', 'bpfp_filter_group_status' );


// change the name of the group if it's an fan page group
function bpfp_filter_group_type( $type ) {
	if ( bpfp_get_fan_page_group( groups_get_current_group() ) == 'fan-page' ) {
		if ($type == 'Public Group')
			$type = esc_attr__('Fan Page', 'bp-fan-page' );
		elseif ($type == 'Private Group')
			$type = esc_attr__('Private Fan Page', 'bp-fan-page' );
		elseif ($type == 'Hidden Group')
			$type = esc_attr__('Hidden Fan PAge', 'bp-fan-page' );
	}
	return $type;
}
add_filter( 'bp_get_group_type', 'bpfp_filter_group_type', 1 );


// create the fan page group option during group creation and editing
function bpfp_add_fan_page_group_form() {
	?>
	<hr >
	<div class="radio">
		<label><input type="radio" name="bpfp-fan-page-group" value="normal" <?php bpfp_fan_page_group_setting('normal') ?> /> <?php esc_attr_e( 'This is a normal group (all group members can add content).', 'bp-fan-page' ) ?></label>
		<label><input type="radio" name="bpfp-fan-page-group" value="fan-page" <?php bpfp_fan_page_group_setting('fan-page') ?> /> <?php esc_attr_e( 'This is a fan page group (only moderators and admins can add content).', 'bp-fan-page' ) ?>
		<?php if ( function_exists( 'ass_get_group_subscription_status' ) || function_exists( 'ges_get_group_subscription_status' ) ) echo '<br> &nbsp; &nbsp; &nbsp; ' . esc_attr__('Usually, you will set the Email Subscription Defaults to \"Subscribed\" below.', 'bp-fan-page') . '</li></ul>'; ?>
		</label>
	</div>
	<hr />
	<?php
}
add_action ( 'bp_after_group_settings_admin' ,'bpfp_add_fan_page_group_form' );
add_action ( 'bp_after_group_settings_creation_step' ,'bpfp_add_fan_page_group_form' );


// Get the fan page group setting
function bpfp_get_fan_page_group( $group = false ) {
	global $groups_template;
	
	if ( !$group )
		$group =& $groups_template->group;

	$group_id = isset( $group->id ) ? $group->id : null;

	if ( ! $group_id &&  isset( $group->group_id ) )
		$group_id = $group->group_id;

	$fan_page_group = groups_get_groupmeta( $group_id, 'bpfp_fan_page_group' );

	return apply_filters( 'bpfp_fan_page_group', $fan_page_group );
}


// echo fan page group checked setting for the group admin - default to 'normal' in group creation
function bpfp_fan_page_group_setting( $setting ) {
	if ( $setting == bpfp_get_fan_page_group( groups_get_current_group() ) )
		echo ' checked="checked"';
	if ( !bpfp_get_fan_page_group( groups_get_current_group() ) && $setting == 'normal' )
		echo ' checked="checked"';
}


// Save the fan page group setting in the group meta, if normal, delete it
function bpfp_save_fan_page_group( $group ) { 
	global $bp, $_POST;
	if ( $postval = sanitize_text_field( $_POST['bpfp-fan-page-group'] ) ) {
		if ( $postval == 'fan-page' ) {
			groups_update_groupmeta( $group->id, 'bpfp_fan_page_group', $postval );
			//$group_type = bp_groups_set_group_type( $group->id, 'fan-page' );
		} elseif ( $postval=='normal' ) {
			groups_delete_groupmeta( $group->id, 'bpfp_fan_page_group' );
			//$group_type = bp_groups_set_group_type( $group->id, 'group' );
		}
	}
}
add_action( 'groups_group_after_save', 'bpfp_save_fan_page_group' );


// change the name of forum to Announcements for announce groups and hide post links and member list from regular members (cosmetic only)
function bpfp_change_forum_title( $args ) {
	global $bp;
	if ( bpfp_get_fan_page_group( groups_get_current_group() ) == 'fan-page' && bp_group_is_forum_enabled()) {
		$display = true;
		$group_permalink = bp_get_group_url( groups_get_current_group() );

		bp_core_remove_subnav_item( bp_get_current_group_slug(), 'forum', 'groups' );
		bp_core_new_subnav_item( array(
			'name' 				=> 'Announcements',
			'slug' 				=> 'forum',
			'parent_slug' 		=> bp_get_current_group_slug(),
			'parent_url' 		=> $group_permalink,
			'position' 			=> 10,
			'item_css_id'     	=> 'nav-forum',
			'screen_function' 	=> 'bp_template_content_display_hook',
			'user_has_access' 	=> $display,
			'no_access_url'   	=> $group_permalink,
		), 'groups' );

		if ( !$bp->is_item_admin && !$bp->is_item_mod ) {
			echo '<style type="text/css">#subnav a[href="#post-new"], #subnav .new-reply-link, #members-groups-li { display: none; }</style>';
		}
	}
}
add_action( 'bp_before_group_header', 'bpfp_change_forum_title' );

// Close forum new topics for non group admin users
function bpfp_access_topic_form( $retval ) {
	if ( $retval == true ) {
		if ( bpfp_get_fan_page_group( groups_get_current_group() ) == 'fan-page' ) {
			$retval = bbfp_current_user_can_publish_topics();
		}
	}
	return $retval;
}
add_filter( 'bbp_current_user_can_access_create_topic_form', 'bpfp_access_topic_form', 20 );

//Check if user is admin in current forum
function bbfp_current_user_can_publish_topics() {
	global $bp;
	$current_group = $bp->groups->current_group->id;
	$user_id = get_current_user_id();
	
	if ( groups_is_user_admin( $user_id, $current_group ) || groups_is_user_mod( $user_id, $current_group ) || current_user_can( 'manage_options' ) ) {
		return true;
	} else {
		return false;
	}
}

//Register the Fan Page group type
function bpfp_custom_group_types() {
    bp_groups_register_group_type( 'fan-page', array(
        'labels' => array(
            'name' => 'Fan Page',
            'singular_name' => 'Fan Page'
        ),
        'has_directory' => 'fan-pages',
        'show_in_create_screen' => true,
        'show_in_list' => true,
        'description' => 'Announce only Fan Page',
        'create_screen_checked' => true
    ) );
    bp_groups_register_group_type( 'group', array(
        'labels' => array(
            'name' => 'Group',
            'singular_name' => 'Group'
        ),
        'has_directory' => 'groups',
        'show_in_create_screen' => true,
        'show_in_list' => true,
        'description' => 'Normal Group',
        'create_screen_checked' => true
    ) );
}
//add_action( 'bp_groups_register_group_types', 'bpfp_custom_group_types' );

//Add group directory button for Fan Pages

function bpfp_directory_tab() {

	if ( ! apply_filters( 'bp_fan_page_display_directory_tab', true ) ) {
		return;
	}
	
	$args = array( 
		'meta_query' => array(
			'relation' => 'AND',
			array(
			'key' 		=> 'bpfp_fan_page_group',
			'value'		=> 'fan-page'
			) )
	);
	
	$fan_pages = groups_get_groups( $args );
	$count = $fan_pages['total'];
	
	?>
	<li id="groups-fan-pages">
		<a href="<?php echo esc_url(bp_groups_directory_url() . 'fan-pages/'); ?>"><?php printf( esc_attr__( 'Fan Pages %s', 'bp-fan-page' ), '<span>' . esc_attr($count) . '</span>' ); ?></a>
	</li>
	<?php
}

add_action( 'bp_groups_directory_group_filter', 'bpfp_directory_tab' );

/**
 * Filter bp_has_groups() transparently for listing our groups
 *
 * @param array $args args array.
 *
 * @return mixed
 */
function bp_fan_page_filter_groups_list( $args ) {

	// our scope must be fan-pages.
	if ( isset( $args['scope'] ) && $args['scope'] == 'fan-pages' )  {

		$groups = groups_get_groups();
		$groups = $groups['groups'];
		$group_includes = array();
		$group_excludes = array();
		if ( $groups ) {
			foreach ( $groups as $group ) {
				$fan_page = groups_get_groupmeta( $group->id, 'bpfp_fan_page_group', true );
				if ( isset( $fan_page ) && $fan_page == 'fan-page' ) {
					$group_includes[] = $group->id;
				} else {
					$group_excludes[] = $group->id;
				}
			}
		}
		
		$args['include'] = $group_includes;
		$args['exclude'] = $group_excludes;
			
	}

	return $args;
}

add_filter( 'bp_after_has_groups_parse_args', 'bp_fan_page_filter_groups_list' );