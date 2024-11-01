<?php
define('USER_CATEGORY_NAME', 'um_user_category');
define('USER_CATEGORY_META_KEY', '_um_user_category');

add_action('init', 'um_register_user_category_taxonomy');

function um_register_user_category_taxonomy() {

	register_taxonomy(
		USER_CATEGORY_NAME,
		'user',
		array(
			'public' => true,
			'labels' => array(
				'name' => 'User Groups',
				'singular_name' => 'User Group',
				'menu_name' => 'Groups',
				'search_items' => 'Search Groups',
				'popular_items' => 'Popular Groups',
				'all_items' => 'All Groups',
				'edit_item' => 'Edit Group',
				'update_item' => 'Update Group',
				'add_new_item' => 'Add New Group',
				'new_item_name' => 'New Group Name',
			),
			'update_count_callback' => function() {
				return; //important
			}
		)
	);

}

add_action('admin_menu', 'um_add_user_categories_admin_page');

function um_add_user_categories_admin_page() {

	$taxonomy = get_taxonomy(USER_CATEGORY_NAME);

	add_users_page(
		esc_attr($taxonomy->labels->menu_name), //page title
		esc_attr($taxonomy->labels->menu_name), //menu title
		$taxonomy->cap->manage_terms, //capability
		'edit-tags.php?taxonomy=' . $taxonomy->name //menu slug
	);

}

add_filter('submenu_file', 'um_set_user_category_submenu_active');

function um_set_user_category_submenu_active($submenu_file) {

	global $parent_file;

	if ('edit-tags.php?taxonomy=' . USER_CATEGORY_NAME == $submenu_file) {
		$parent_file = 'users.php';
	}

	return $submenu_file;
}

add_action('show_user_profile', 'um_admin_user_profile_category_select');
add_action('edit_user_profile', 'um_admin_user_profile_category_select');

function um_admin_user_profile_category_select( $user ) {
	$taxonomy = get_taxonomy( USER_CATEGORY_NAME );
	?>
	<table class="form-table">
		<tr>
			<th>
				<label for="<?php echo USER_CATEGORY_META_KEY ?>"><?= __('Groups', 'user-magic') ?></label>
			</th>
			<td>
				<?php
					$user_category_terms = get_terms(array(
						'taxonomy' => USER_CATEGORY_NAME,
						'hide_empty' => 0
					));
					
					$select_options = array();
					
					foreach ($user_category_terms as $term) {
						$select_options[$term->term_id] = $term->name;
					}
					
					$meta_values = get_user_meta($user->ID, USER_CATEGORY_META_KEY, true);
					
					echo um_custom_form_select(
						USER_CATEGORY_META_KEY,
						$meta_values,
						$select_options
					);
				?>
			</td>
		</tr>
	</table>
	<?php
}

function um_custom_form_select($name, $value, $options) {

  if (sizeof($options) > 0) {

  	$name .= '[]';
  
  	foreach ($options as $options_value => $options_label) {
  
  		if ((is_array($value) && in_array($options_value, $value))
  			|| $options_value == $value) {
  			$selected = " checked='checked'";
  		} else {
  			$selected = '';
  		}
  		if (empty($value) && !empty($default_var) && $options_value == $default_var) {
  			$selected = " selected='selected'";
  		}
  		echo "<label class='user-magic-group-checkbox-container'><input name='{$name}' type='checkbox' value='{$options_value}' {$selected}>{$options_label}</label>";
  
  	}
  	
  } else {

    echo __('No groups defined yet.', 'user-magic');    

  }
	
}

add_action('personal_options_update', 'um_admin_save_user_categories');
add_action('edit_user_profile_update', 'um_admin_save_user_categories');

function um_admin_save_user_categories($user_id) {

	$tax = get_taxonomy(USER_CATEGORY_NAME);
	$user = get_userdata($user_id);
	
	$new_categories_ids = $_POST[USER_CATEGORY_META_KEY];
	$user_meta = get_user_meta($user_id, USER_CATEGORY_META_KEY, true);
	$previous_categories_ids = array();
	
	if (!empty($user_meta)) {
		$previous_categories_ids = (array) $user_meta;
	}

	update_user_meta($user_id, USER_CATEGORY_META_KEY, $new_categories_ids);
	um_update_users_categories_count($previous_categories_ids, $new_categories_ids);

}

function um_update_users_categories_count($previous_terms_ids, $new_terms_ids) {

	global $wpdb;

	$terms_ids = array_unique(array_merge( (array) $previous_terms_ids, (array) $new_terms_ids ));
	
	if (count($terms_ids) < 1 ) {
  	return;
  }
	
	foreach ($terms_ids as $term_id) {

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $wpdb->usermeta WHERE meta_key = %s AND meta_value LIKE %s",
				USER_CATEGORY_META_KEY,
				'%"' . $term_id . '"%'
			)
		);

		$wpdb->update($wpdb->term_taxonomy, array('count' => $count), array('term_taxonomy_id' => $term_id));

	}

}
