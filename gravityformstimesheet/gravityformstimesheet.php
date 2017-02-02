<?php

	/*
		Plugin Name: Gravity Forms Timesheet Addon
		Description: Adds a new field type to Gravity Forms called 'Timesheet', which allows users to provide time and date ranges.
		Author: Gennady Kovshenin
		Author URI: http://codeseekah.com
		Version: 0.1
	*/

	if ( !class_exists( 'GFForms' ) || !defined( 'WPINC' ) )
		return;

	class GFTimesheet {

		private static $_translation_domain = 'gravityformstimesheet';
		
		/**
		 * Dashboard-only initialization.
		 */
		public static function admin_init() {
			add_filter( 'gform_add_field_buttons', array( __CLASS__, 'add_timesheet_field_button' ) );
			add_action( 'gform_editor_js', array( __CLASS__, 'editor_js' ) );
			add_filter( 'gform_field_type_title', array( __CLASS__, 'field_type_title' ) );
			add_action( 'gform_editor_js_set_default_values', array( __CLASS__, 'timesheet_label' ) );
			add_filter( 'gform_entry_field_value', array( __CLASS__, 'lead_timesheet_value' ), null, 4 );
			add_filter( 'gform_entries_field_value', array( __CLASS__, 'lead_timesheet_title' ), null, 4 );
			add_filter( 'gform_field_content', array( __CLASS__, 'hide_timesheet_edit' ), null, 5 );
		}

		/**
		 * Initialization.
		 */
		public static function init() {
			add_action( 'gform_field_input', array( __CLASS__, 'timesheet_input' ), null, 5 );
			add_filter( 'gform_field_validation', array( __CLASS__, 'timesheet_validation' ), null, 4 );
			add_filter( 'gform_save_field_value', array( __CLASS__, 'save_timesheet_input' ), null, 4 );
			add_filter( 'gform_merge_tag_filter', array( __CLASS__, 'render_timesheet_mergetag' ), null, 5 );
		}

		/**
		 * Adds the timesheet field type into the Advanced fields metabox.
		 */
		public static function add_timesheet_field_button( $fieldgroups ) {
			foreach ( $fieldgroups as &$group ) {
				if ( $group['name'] === 'advanced_fields' ) {
					$group['fields'][] = array(
						'class' => 'button',
						'value' => __( 'Timesheet', self::$_translation_domain ),
						'data-type' => 'timesheet',
						'onclick' => "StartAddField( 'timesheet' );"
					);
					break;
				}
			}
			return $fieldgroups;
		}

		/**
		 * Injects the timesheet field logic in Form Edit.
		 */
		public static function editor_js() {
			?>
				<script type='text/javascript'>
					fieldSettings['timesheet'] = '.label_setting, .admin_label_setting, .rules_setting, .date_format_setting, .time_format_setting, .maxrows_setting, .visibility_setting, .description_setting, .css_class_setting, .add_icon_url_setting, .delete_icon_url_setting';
				</script>
			<?php
		}

		/**
		 * The title of the field in Form Edit.
		 */
		public static function field_type_title( $type ) {
			if ( $type == 'timesheet' )
				return __( 'Timesheet', self::$_translation_domain );
			return $type;
		}

		/**
		 * The label of the field in Form Edit.
		 */
		public static function timesheet_label() {
			?>
				case 'timesheet':
					field.label = '<?php _e( 'Timesheet', self::$_translation_domain ) ?>';
					break;
			<?php
		}

		/**
		 * Rendering the UI of the timesheet field.
		 */
		public static function timesheet_input( $input, $field, $value, $lead_id, $form_id ) {
			if ( $field['type'] != 'timesheet' )
				return;

			wp_enqueue_style( 'gforms_datepicker_css', GFCommon::get_base_url() . '/css/datepicker.css', null, GFCommon::$version );
			wp_enqueue_script( 'gform_datepicker_init' );

			if ( is_admin() ) return;

			if ( !empty( $value ) )
				$value = maybe_unserialize( $value );

			if ( !is_array( $value ) )
				$value = array( array(
					'date' => '',
					'checkin_hour' => '', 'checkin_minute' => '', 'checkin_24' => '',
					'checkout_hour' => '', 'checkout_minute' => '', 'checkout_24' => '',
					'comment' => ''
				) );
			else {
				$value = self::unserialize( $value, $field );
			}

			ob_start();
			?>
				<div class="ginput_container ginput_timesheet" id="ginput_timesheet-<?php echo esc_attr( $field['id'] ); ?>">
					<table class="gfield_timesheet">
						<colgroup>
							<col class="gfield_timesheet_column gfield_timesheet_column_date">
							<col class="gfield_timesheet_column gfield_timesheet_column_checkin">
							<col class="gfield_timesheet_column gfield_timesheet_column_checkout">
							<col class="gfield_timesheet_column gfield_timesheet_column_comment">
						</colgroup>

						<thead>
							<tr>
								<th><?php esc_html_e( 'Date', self::$_translation_domain ); ?></th>
								<th><?php esc_html_e( 'Check in', self::$_translation_domain ); ?></th>
								<th><?php esc_html_e( 'Check out', self::$_translation_domain ); ?></th>
								<th><?php esc_html_e( 'Comment', self::$_translation_domain ); ?></th>
								<th></th>
							</tr>
						</thead>

						<tbody>
							<?php
								$rownum = 1;
								$format = empty( $field['dateFormat'] ) ? 'mdy' : esc_attr( $field['dateFormat'] );
								$add_icon = !rgempty( 'addIconUrl', $field ) ? $field['addIconUrl'] : GFCommon::get_base_url() . '/images/add.png';
								$delete_icon = !rgempty( 'deleteIconUrl', $field ) ? $field['deleteIconUrl'] : GFCommon::get_base_url() . '/images/remove.png';
								$maxrows = !empty( $field['maxRows'] ) && is_numeric( $field['maxRows'] ) && $field['maxRows'] > -1 ? $field['maxRows'] : 0;

								foreach ( $value as $item ):
									?>
										<tr class="gfield_timesheet_row">
											<td class="gfield_timesheet_cell gfield_timesheet_cell_date">
												<input type="text" class="datepicker <?php echo $format; ?>" name="input_<?php echo esc_attr( $field['id'] ); ?>[]" value="<?php echo esc_attr( $item['date'] ); ?>">
											</td>
											<td class="gfield_timesheet_cell gfield_timesheet_cell_checkin">
												<?php self::display_time_field( $field, array(
														'hour' => $item['checkin_hour'],
														'minute' => $item['checkin_minute'],
														'24' => $item['checkin_24']
													) ); ?>
											</td>
											<td class="gfield_timesheet_cell gfield_timesheet_cell_checkout">
												<?php self::display_time_field( $field, array(
														'hour' => $item['checkout_hour'],
														'minute' => $item['checkout_minute'],
														'24' => $item['checkout_24']
													) ); ?>
											</td>
											<td class="gfield_timesheet_cell gfield_timesheet_cell_comment">
												<input type="text" name="input_<?php echo esc_attr( $field['id'] ); ?>[]" value="<?php echo esc_attr( $item['comment'] ); ?>">
											</td>
											<td class="gfield_timesheet_cell gfield_timesheet_icons">
												<img src="<?php echo esc_attr( $add_icon ); ?>" class="add_list_item" style="cursor: pointer; margin: 0 3px;">
												<img src="<?php echo esc_attr( $delete_icon ); ?>" class="delete_list_item" style="cursor: pointer;">
											</td>
										</tr>
									<?php
									$rownum++;
								endforeach;
							?>
						</tbody>
					</table>

					<script>
						jQuery( document ).ready( function() {
							var timesheet = jQuery( '#ginput_timesheet-<?php echo esc_attr( $field['id'] ); ?>' );
							var maxrows = <?php echo json_encode( $maxrows ); ?>;

							var hide_buttons = function() {
								if ( timesheet.find( 'tr.gfield_timesheet_row' ).length < 2 ) timesheet.find( '.delete_list_item' ).css( 'visibility', 'hidden' );
								else timesheet.find( '.delete_list_item' ).css( 'visibility', '' );
								if ( maxrows < 1 ) return;
								if ( timesheet.find( 'tr.gfield_timesheet_row' ).length >= maxrows ) timesheet.find( '.add_list_item' ).css( 'visibility', 'hidden' );
								else timesheet.find( '.add_list_item' ).css( 'visibility', '' );
							};

							hide_buttons();

							timesheet.on( 'click', '.add_list_item', function( e ) {
								e.preventDefault();
								if ( maxrows > 0 && timesheet.find( 'tr.gfield_timesheet_row' ).length >= maxrows ) return false;
								var tr = jQuery( e.target ).parents( 'tr.gfield_timesheet_row' ).clone();
								tr.find( '.gfield_timesheet_cell_date input' ).removeClass( 'hasDatepicker' ).attr( 'id', '' );
								tr.find( 'input' ).val( '' );
								timesheet.find( 'tbody'	).append( tr );
								hide_buttons();
								gformInitDatepicker();
								return false;
							} );

							timesheet.on( 'click', '.delete_list_item', function( e ) {
								e.preventDefault();
								if ( timesheet.find( 'tr.gfield_timesheet_row' ).length < 2 ) return false;
								jQuery( e.target ).parents( 'tr.gfield_timesheet_row' ).remove();
								hide_buttons();
								return false;
							} );
						} );
					</script>
				</div>
			<?php
			
			return ob_get_clean();
		}

		/**
		 * Timesheet field validation on submit.
		 */
		public static function timesheet_validation( $result, $value, $form, $field ) {
			if ( $field['type'] != 'timesheet' )
				return $result;

			$chunksize = rgar( $field, 'timeFormat') == '24' ? 6 : 8;
			$values = array_chunk( $value, $chunksize );

			$value = array();
			foreach ( $values as $chunk ) {
				while ( count( $chunk ) < $chunksize ) {
					$chunk []= '';
				}
				$value = array(
					'date' => array_shift( $chunk ),
					'checkin_hour' => array_shift( $chunk ), 'checkin_minute' => array_shift( $chunk ), 'checkin_24' => rgar( $field, 'timeFormat') == '24' ? '' : array_shift( $chunk ),
					'checkout_hour' => array_shift( $chunk ), 'checkout_minute' => array_shift( $chunk ), 'checkout_24' => rgar( $field, 'timeFormat') == '24' ? '' : array_shift( $chunk ),
					'comment' => array_shift( $chunk )
				);

				if ( !$field['isRequired'] ) {
					if ( empty( $value['date'] ) && empty( $value['checkin_hour'] ) && empty( $value['checkin_minute'] ) && empty( $value['checkout_hour'] ) && empty( $value['checkout_minute'] ) && empty( $value['comment'] ) ) {
						continue;
					}
				}

				$date_info = GFCommon::parse_date( $value['date'], rgar( $field, 'dateFormat' ) );
				if ( GFCommon::is_empty_array( $date_info ) || !checkdate( $date_info['month'], $date_info['day'], $date_info['year'] ) ) {
					$result['is_valid'] = false;
					$result['message'] = __( 'Invalid date or date format', self::$_translation_domain );
					return $result;
				}

				if ( !is_numeric( $value['checkin_hour'] ) || !is_numeric( $value['checkin_minute'] )
					|| !is_numeric( $value['checkout_hour'] ) || !is_numeric( $value['checkout_minute'] )
				) {

					$result['is_valid'] = false;
					$result['message'] = __( 'Invalid time or time format', self::$_translation_domain );
					return $result;
				}

				if ( rgar( $field, 'timeFormat') != '24' ) {
					if ( $value['checkin_24'] == 'am' && intval( $value['checkin_hour'] ) == 12 ) $value['checkin_hour'] = 0;
					if ( $value['checkin_24'] == 'pm' && intval( $value['checkin_hour'] ) != 12 ) $value['checkin_hour'] += 12;
					if ( $value['checkout_24'] == 'pm' && intval( $value['checkout_hour'] ) != 12 ) $value['checkout_hour'] += 12;
				}

				if (
					( intval( $value['checkin_hour'] ) < 0 || intval( $value['checkin_hour'] ) > 23 )
					|| ( intval( $value['checkout_hour'] ) < 0 || intval( $value['checkout_hour'] ) > 23 )
					|| ( intval( $value['checkin_minute'] ) < 0 || intval( $value['checkin_minute'] ) > 59 )
					|| ( intval( $value['checkout_minute'] ) < 0 || intval( $value['checkout_minute'] ) > 59 )
				)  {
					$result['is_valid'] = false;
					$result['message'] = __( 'Invalid time or time format', self::$_translation_domain );
					return $result;
				}

				if ( intval( $value['checkin_hour'] ) >= intval( $value['checkout_hour'] ) ) {
					if ( intval( $value['checkin_minute'] ) >= intval( $value['checkout_minute'] ) ) {
						$result['is_valid'] = false;
						$result['message'] = __( 'Invalid time or time format', self::$_translation_domain );
						return $result;
					}
				}
			}

			/* We're expected to be valid at this point... */

			if ( !$result['is_valid'] ) {
				/* Why are we not valid? Is it the comments? */
				if ( empty( $value['comment'] ) ) $result['is_valid'] = true;
			}

			return $result;
		}

		/**
		 * Serialize the timesheet data when saving.
		 *
		 * If we're editing the lead, ignore and override. See `hide_timesheet_edit`
		 * for more information.
		 */
		public static function save_timesheet_input( $value, $lead, $field, $form ) {
			if ( $field['type'] != 'timesheet' )
				return $value;
			if ( is_admin() ) {
				$mode = empty( $_POST['screen_mode'] ) ? 'view' : $_POST['screen_mode'];
				if ( RG_CURRENT_VIEW == 'entry' || $mode == 'edit' ) {
					return $lead[$field['id']]; // Ignore and reset
				}
			}
			return serialize( $value );
		}

		/**
		 * Render the lead view of the timesheet field.
		 */
		public static function lead_timesheet_value( $display_value, $field, $lead, $form ) {
			if ( $field['type'] != 'timesheet' )
				return $display_value;

			$value = maybe_unserialize( $display_value );
			$chunksize = rgar( $field, 'timeFormat') == '24' ? 6 : 8;
			$values = array_chunk( $value, $chunksize );
			$value = array();
			foreach ( $values as $chunk ) {
				while ( count( $chunk ) < $chunksize ) {
					$chunk []= '';
				}
				$entry = array(
					'date' => array_shift( $chunk ),
					'checkin_hour' => array_shift( $chunk ), 'checkin_minute' => array_shift( $chunk ), 'checkin_24' => rgar( $field, 'timeFormat') == '24' ? '' : array_shift( $chunk ),
					'checkout_hour' => array_shift( $chunk ), 'checkout_minute' => array_shift( $chunk ), 'checkout_24' => rgar( $field, 'timeFormat') == '24' ? '' : array_shift( $chunk ),
					'comment' => array_shift( $chunk )
				);
				if ( !empty( $entry['date'] ) )
					$value []= $entry;
			}

			ob_start();
			?>
				<table>
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', self::$_translation_domain ); ?></th>
							<th><?php esc_html_e( 'Check in', self::$_translation_domain ); ?></th>
							<th><?php esc_html_e( 'Check out', self::$_translation_domain ); ?></th>
							<th><?php esc_html_e( 'Comment', self::$_translation_domain ); ?></th>
						</tr>
					<thead>
					<tbody>
						<?php
							$minutes = 0;
							foreach ( $value as $entry ):
								?>
									<tr>
										<td><?php echo $entry['date']; ?></td>
										<td><?php printf( '%02d:%02d%s', $entry['checkin_hour'], $entry['checkin_minute'], rgar( $field, 'timeFormat') == '24' ? '' : ' ' .$entry['checkin_24'] ); ?></td>
										<td><?php printf( '%02d:%02d%s', $entry['checkout_hour'], $entry['checkout_minute'], rgar( $field, 'timeFormat') == '24' ? '' : ' ' .$entry['checkout_24'] ); ?></td>
										<td><?php echo esc_html( $entry['comment'] ); ?></td>
									</tr>
								<?php
								if ( rgar( $field, 'timeFormat') != '24' ) {
									if ( $entry['checkin_24'] == 'am' && intval( $entry['checkin_hour'] ) == 12 ) $entry['checkin_hour'] = 0;
									if ( $entry['checkin_24'] == 'pm' && $entry['checkin_hour'] != 12 ) $entry['checkin_hour'] += 12;
									if ( $entry['checkout_24'] == 'pm' && $entry['checkout_hour'] != 12 ) $entry['checkout_hour'] += 12;
								}
								
								$minutes += ( ( $entry['checkout_hour'] * 60 ) + $entry['checkout_minute'] ) - ( ( $entry['checkin_hour'] * 60 ) + $entry['checkin_minute'] );
							endforeach;
						?>
					</tbody>
					<tfoot>
						<tr>
							<td colspan="2"><?php printf( esc_html__( 'Total entries: %d', self::$_translation_domain ), count( $value ) ); ?></td>
							<td colspan="2"><?php printf( esc_html__( 'Total time: %02d:%02d', self::$_translation_domain ), $minutes / 60, $minutes % 60);?></td>
						</tr>
					</tfoot>
				</table>
			<?php

			return ob_get_clean();
		}

		/**
		 * The title in the list of leads.
		 */
		public static function lead_timesheet_title( $value, $form_id, $field_id, $lead ) {
			$field = RGFormsModel::get_field( RGFormsModel::get_form_meta( $form_id ), $field_id );
			if ( $field['type'] != 'timesheet' )
				return $value;
			$value = self::unserialize( html_entity_decode( $value ), $field );
			$minutes = 0;
			foreach ( $value as $entry ):
				if ( rgar( $field, 'timeFormat') == '12' ) {
					if ( $entry['checkin_24'] == 'am' && intval( $entry['checkin_hour'] ) == 12 ) $entry['checkin_hour'] = 0;
					if ( $entry['checkin_24'] == 'pm' && $entry['checkin_hour'] != 12 ) $entry['checkin_hour'] += 12;
					if ( $entry['checkout_24'] == 'pm' && $entry['checkout_hour'] != 12 ) $entry['checkout_hour'] += 12;
				}
				$minutes += ( ( $entry['checkout_hour'] * 60 ) + $entry['checkout_minute'] ) - ( ( $entry['checkin_hour'] * 60 ) + $entry['checkin_minute'] );
			endforeach;

			return sprintf( '%d entries, %02d:%02d total', count( $value ), $minutes/60, $minutes % 60 );
		}

		/**
		 * Hides the edit timesheet field in the Edit Lead view.
		 *
		 * Thanks to Gravity Form's "thoughtfulness" we are having
		 * to resort to crazy hacks in order for the lead detail not to be deleted.
		 * We inject a hidden field with some values that we'll simply override.
		 * See `update_lead_field_value` in forms_model, it's crazy out there...
		 */
		public static function hide_timesheet_edit( $content, $field, $value, $lead_id, $form_id ) {
			$mode = empty( $_POST['screen_mode'] ) ? 'view' : $_POST['screen_mode'];
			if ( RG_CURRENT_VIEW != 'entry' || $mode != 'edit' || $field['type'] != 'timesheet' )
				return $content;
			return '<input type="hidden" name="input_' . $field['id'] . '" value="#">';
		}

		/**
		 * Renders the timesheet table wherever used; mostly email
		 * notifications and confirmations. But who knows where else...
		 */
		public static function render_timesheet_mergetag( $field_value, $merge_tag, $options, $field, $raw_field_value ) {
			if ( $field['type'] != 'timesheet' )
				return $field_value;
			$value = self::unserialize( $raw_field_value, $field );
			ob_start();
			$minutes = 0;
			?>
				<table>
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', self::$_translation_domain ); ?></th>
							<th><?php esc_html_e( 'Check in', self::$_translation_domain ); ?></th>
							<th><?php esc_html_e( 'Check out', self::$_translation_domain ); ?></th>
							<th><?php esc_html_e( 'Comment', self::$_translation_domain ); ?></th>
						</tr>
					<thead>
					<tbody>
						<?php
							$minutes = 0;
							foreach ( $value as $entry ):
								?>
									<tr>
										<td><?php echo $entry['date']; ?></td>
										<td><?php printf( '%02d:%02d%s', $entry['checkin_hour'], $entry['checkin_minute'], rgar( $field, 'timeFormat') == '24' ? '' : ' ' .$entry['checkin_24'] ); ?></td>
										<td><?php printf( '%02d:%02d%s', $entry['checkout_hour'], $entry['checkout_minute'], rgar( $field, 'timeFormat') == '24' ? '' : ' ' .$entry['checkout_24'] ); ?></td>
										<td><?php echo esc_html( $entry['comment'] ); ?></td>
									</tr>
								<?php
								if ( rgar( $field, 'timeFormat') != '24' ) {
									if ( $entry['checkin_24'] == 'am' && intval( $entry['checkin_hour'] ) == 12 ) $entry['checkin_hour'] = 0;
									if ( $entry['checkin_24'] == 'pm' && $entry['checkin_hour'] != 12 ) $entry['checkin_hour'] += 12;
									if ( $entry['checkout_24'] == 'pm' && $entry['checkout_hour'] != 12 ) $entry['checkout_hour'] += 12;
								}
								
								$minutes += ( ( $entry['checkout_hour'] * 60 ) + $entry['checkout_minute'] ) - ( ( $entry['checkin_hour'] * 60 ) + $entry['checkin_minute'] );
							endforeach;
						?>
					</tbody>
					<tfoot>
						<tr>
							<td colspan="2"><?php printf( esc_html__( 'Total entries: %d', self::$_translation_domain ), count( $value ) ); ?></td>
							<td colspan="2"><?php printf( esc_html__( 'Total time: %02d:%02d', self::$_translation_domain ), $minutes / 60, $minutes % 60);?></td>
						</tr>
					</tfoot>
				</table>
			<?php

			return ob_get_clean();
		}

		/**
		 * The HH:MM (am/pm) set markup.
		 */
		private static function display_time_field( $field, $value ) {
			?>
			<div class="clear-multi">
				<div class="gfield_time_hour ginput_container">
					<input type="text" maxlength="2" name="input_<?php echo esc_attr( $field['id'] ); ?>[]" value="<?php echo esc_attr( $value['hour'] ); ?>">
					:
					<label><?php echo esc_html( __( 'HH', 'gravityforms' ) ); ?></label>
				</div>
				<div class="gfield_time_minute ginput_container">
					<input type="text" maxlength="2" name="input_<?php echo esc_attr( $field['id'] ); ?>[]" value="<?php echo esc_attr( $value['minute'] ); ?>">
					<label><?php echo esc_html( __( 'MM', 'gravityforms' ) ); ?></label>
				</div>
				<?php
					if ( rgar( $field, 'timeFormat') != '24' ) {
						?>
							<div class="gfield_time_ampm ginput_container">
								<select name="input_<?php echo esc_attr( $field['id'] ); ?>[]">
									<option value="am" <?php selected( 'am', $value['24'] ); ?>><?php echo esc_html( __( 'AM', 'gravityforms' ) ); ?></option>
									<option value="pm" <?php selected( 'pm', $value['24'] ); ?>><?php echo esc_html( __( 'PM', 'gravityforms' ) ); ?></option>
								</select>
							</div>
						<?php
					}
				?>
			</div>
			<?php
		}

		/**
		 * Unserialize the value into a workable array or arrays.
		 */
		private static function unserialize( $value, $field ) {
			$chunksize = rgar( $field, 'timeFormat') == '24' ? 6 : 8;
			$values = array_chunk( maybe_unserialize( $value ), $chunksize );
			$value = array();
			foreach ( $values as $chunk ) {
				while ( count( $chunk ) < $chunksize ) {
					$chunk []= '';
				}
				$entry = array(
					'date' => array_shift( $chunk ),
					'checkin_hour' => array_shift( $chunk ), 'checkin_minute' => array_shift( $chunk ), 'checkin_24' => rgar( $field, 'timeFormat') == '24' ? '' : array_shift( $chunk ),
					'checkout_hour' => array_shift( $chunk ), 'checkout_minute' => array_shift( $chunk ), 'checkout_24' => rgar( $field, 'timeFormat') == '24' ? '' : array_shift( $chunk ),
					'comment' => array_shift( $chunk )
				);
				if ( !empty( $entry['date'] ) )
					$value []= $entry;
			}
			return $value;
		}

	}

	add_action( 'admin_init', array( 'GFTimesheet', 'admin_init' ) );
	add_action( 'init', array( 'GFTimesheet', 'init' ) );
